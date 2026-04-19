<?php

namespace App\Http\Controllers;

use App\Models\AnomalyEvent;
use App\Models\GeofenceEvent;
use App\Models\GeofenceZone;
use App\Models\Group;
use App\Models\GroupPerformanceScore;
use App\Models\MaintenancePlan;
use App\Models\MaintenanceRecord;
use App\Models\TelemetryLog;
use App\Models\Tractor;
use App\Models\TractorPositionLatest;
use App\Models\UtilizationDaily;
use App\Services\StrategicKpiService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\Rule;

class StrategicWebController extends Controller
{
    public function __construct(private readonly StrategicKpiService $kpiService) {}

    private function resolvePerPage(Request $request): int
    {
        $perPage = (int) $request->query('per_page', 10);
        return in_array($perPage, [10, 50], true) ? $perPage : 10;
    }

    /** Per-tabel: pakai {prefix}_per_page, fallback ke per_page lama. */
    private function resolveTablePerPage(Request $request, string $key): int
    {
        $raw = $request->query($key);
        if ($raw === null || $raw === '') {
            $raw = $request->query('per_page', 10);
        }
        $perPage = (int) $raw;
        return in_array($perPage, [10, 50], true) ? $perPage : 10;
    }

    public function index(Request $request)
    {
        $from = $request->query('from') ? Carbon::parse((string) $request->query('from'))->startOfDay() : now()->subDays(30)->startOfDay();
        $to = $request->query('to') ? Carbon::parse((string) $request->query('to'))->endOfDay() : now()->endOfDay();

        $kpi = $this->kpiService->overview($from, $to);
        $coverage = $this->kpiService->coverageSeries($from, $to);
        $fuel = $this->kpiService->fuelEfficiencySeries($from, $to);
        $geofenceAlerts = $this->geofenceAlerts($request, $from, $to);
        $groupScores = $this->groupScores($request);
        $anomalies = $this->anomalies($request, $from, $to);
        $utilization = $this->utilization($request, $from, $to);
        $maintenancePlans = $this->maintenancePlans($request);
        $maintenanceRecords = $this->maintenanceRecords($request, $from, $to);

        $hasZoneTractorPivot = Schema::hasTable('geofence_zone_tractor');
        $zonesQuery = GeofenceZone::query()->orderByDesc('is_active')->orderBy('name');
        if ($hasZoneTractorPivot) {
            $zonesQuery->with(['tractors:id,name']);
        }
        $zones = $zonesQuery->get();
        if (! $hasZoneTractorPivot) {
            $zones->each(fn (GeofenceZone $z) => $z->setRelation('tractors', collect()));
        }
        $zonesAll = $zones;
        $tractors = Tractor::query()->orderBy('id')->get(['id', 'name']);
        $latestPositions = $this->fleetMapPositions();
        /** Sama dengan dashboard: urutan kronologis, maks. 800 titik GPS per alat (lihat DashboardController::loadTractorDashboard). */
        $routeHistoryByTractor = $this->latestRouteHistoryByTractor($latestPositions->pluck('tractor_id')->unique()->values()->all());
        $fuelFlowChart = $this->fuelFlowChartDatasets($from, $to);

        return view('strategic.index', [
            'from' => $from,
            'to' => $to,
            'kpi' => $kpi,
            'coverageSeries' => $coverage,
            'fuelSeries' => $fuel,
            'fuelFlowChart' => $fuelFlowChart,
            'geofenceAlerts' => $geofenceAlerts,
            'groupScores' => $groupScores,
            'anomalies' => $anomalies,
            'utilizationRows' => $utilization,
            'maintenancePlans' => $maintenancePlans,
            'maintenanceRecords' => $maintenanceRecords,
            'geofenceZones' => $zones,
            'geofenceZonesAll' => $zonesAll,
            'latestPositions' => $latestPositions,
            'routeHistoryByTractor' => $routeHistoryByTractor,
            'tractors' => $tractors,
            'groups' => Group::query()->orderBy('name')->get(['id', 'name', 'village']),
            'zoneTractorFeatureReady' => $hasZoneTractorPivot,
            'periodLabel' => $request->query('gp_period')
                ?: now()->format('Y').'-Q'.(int) ceil(((int) now()->format('n')) / 3),
        ]);
    }

    private function redirectStrategic(Request $request): \Illuminate\Http\RedirectResponse
    {
        return redirect()->route('strategic', $request->query());
    }

    /**
     * Hitung turunan rapor kinerja: total_score = rata-rata (activity + maintenance),
     * grade berdasarkan ambang ketat (A≥90, B 80–89, C 70–79, D 60–69, E<60).
     * Sumber nilai tunggal di server agar konsisten dengan tampilan UI (read-only).
     *
     * @return array{total_score: float, grade: string}
     */
    private function computeGroupScoreDerived(float $activity, float $maintenance): array
    {
        $activity = max(0.0, min(100.0, $activity));
        $maintenance = max(0.0, min(100.0, $maintenance));
        $total = round(($activity + $maintenance) / 2, 2);
        $grade = match (true) {
            $total >= 90 => 'A',
            $total >= 80 => 'B',
            $total >= 70 => 'C',
            $total >= 60 => 'D',
            default => 'E',
        };

        return ['total_score' => $total, 'grade' => $grade];
    }

    public function storeGroupScore(Request $request)
    {
        $data = $request->validate([
            'group_id' => [
                'required',
                'exists:groups,id',
                Rule::unique('group_performance_scores', 'group_id')->where(fn ($q) => $q->where('period', $request->input('period'))),
            ],
            'period' => ['required', 'string', 'max:32'],
            'activity_score' => ['required', 'numeric', 'min:0', 'max:100'],
            'maintenance_score' => ['required', 'numeric', 'min:0', 'max:100'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ]);
        $data += $this->computeGroupScoreDerived((float) $data['activity_score'], (float) $data['maintenance_score']);
        GroupPerformanceScore::query()->create($data);

        return $this->redirectStrategic($request)->with('ok', 'Rapor kinerja kelompok tani berhasil ditambahkan.');
    }

    public function updateGroupScore(GroupPerformanceScore $groupPerformanceScore, Request $request)
    {
        $data = $request->validate([
            'group_id' => [
                'required',
                'exists:groups,id',
                Rule::unique('group_performance_scores', 'group_id')
                    ->where(fn ($q) => $q->where('period', $request->input('period')))
                    ->ignore($groupPerformanceScore->id),
            ],
            'period' => ['required', 'string', 'max:32'],
            'activity_score' => ['required', 'numeric', 'min:0', 'max:100'],
            'maintenance_score' => ['required', 'numeric', 'min:0', 'max:100'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ]);
        $data += $this->computeGroupScoreDerived((float) $data['activity_score'], (float) $data['maintenance_score']);
        $groupPerformanceScore->update($data);

        return $this->redirectStrategic($request)->with('ok', 'Rapor kinerja kelompok tani diperbarui.');
    }

    public function deleteGroupScore(GroupPerformanceScore $groupPerformanceScore, Request $request)
    {
        $groupPerformanceScore->delete();

        return $this->redirectStrategic($request)->with('ok', 'Rapor kinerja kelompok tani dihapus.');
    }

    public function storeAnomaly(Request $request)
    {
        $data = $request->validate([
            'detected_at' => ['required', 'date'],
            'tractor_id' => ['required', 'string', 'exists:tractors,id'],
            'anomaly_type' => ['required', 'string', 'max:120'],
            'severity' => ['required', 'string', Rule::in(['HIGH', 'MEDIUM', 'LOW', 'high', 'medium', 'low'])],
            'description' => ['nullable', 'string', 'max:5000'],
            'status' => ['required', 'string', Rule::in(['OPEN', 'RESOLVED', 'open', 'resolved'])],
            'resolved_at' => ['nullable', 'date'],
            'resolved_note' => ['nullable', 'string', 'max:2000'],
        ]);
        $data['severity'] = strtoupper($data['severity']);
        $data['status'] = strtoupper($data['status']);
        AnomalyEvent::query()->create($data);

        return $this->redirectStrategic($request)->with('ok', 'Data anomali berhasil ditambahkan.');
    }

    public function updateAnomaly(AnomalyEvent $anomalyEvent, Request $request)
    {
        $data = $request->validate([
            'detected_at' => ['required', 'date'],
            'tractor_id' => ['required', 'string', 'exists:tractors,id'],
            'anomaly_type' => ['required', 'string', 'max:120'],
            'severity' => ['required', 'string', Rule::in(['HIGH', 'MEDIUM', 'LOW', 'high', 'medium', 'low'])],
            'description' => ['nullable', 'string', 'max:5000'],
            'status' => ['required', 'string', Rule::in(['OPEN', 'RESOLVED', 'open', 'resolved'])],
            'resolved_at' => ['nullable', 'date'],
            'resolved_note' => ['nullable', 'string', 'max:2000'],
        ]);
        $data['severity'] = strtoupper($data['severity']);
        $data['status'] = strtoupper($data['status']);
        $anomalyEvent->update($data);

        return $this->redirectStrategic($request)->with('ok', 'Data anomali diperbarui.');
    }

    public function deleteAnomaly(AnomalyEvent $anomalyEvent, Request $request)
    {
        $anomalyEvent->delete();

        return $this->redirectStrategic($request)->with('ok', 'Data anomali dihapus.');
    }

    public function storeUtilizationDaily(Request $request)
    {
        $data = $request->validate([
            'tractor_id' => [
                'required',
                'string',
                'exists:tractors,id',
                Rule::unique('utilization_daily', 'tractor_id')->where(fn ($q) => $q->where('date', $request->input('date'))),
            ],
            'date' => ['required', 'date'],
            'active_days_rolling' => ['required', 'integer', 'min:0'],
            'estimated_hours' => ['required', 'numeric', 'min:0'],
            'utilization_pct' => ['required', 'numeric', 'min:0', 'max:100'],
            'utilization_status' => ['nullable', 'string', 'max:32'],
        ]);
        if (! empty($data['utilization_status'])) {
            $data['utilization_status'] = strtoupper($data['utilization_status']);
        }
        UtilizationDaily::query()->create($data);

        return $this->redirectStrategic($request)->with('ok', 'Data utilisasi berhasil ditambahkan.');
    }

    public function updateUtilizationDaily(UtilizationDaily $utilizationDaily, Request $request)
    {
        $data = $request->validate([
            'tractor_id' => [
                'required',
                'string',
                'exists:tractors,id',
                Rule::unique('utilization_daily', 'tractor_id')
                    ->where(fn ($q) => $q->where('date', $request->input('date')))
                    ->ignore($utilizationDaily->id),
            ],
            'date' => ['required', 'date'],
            'active_days_rolling' => ['required', 'integer', 'min:0'],
            'estimated_hours' => ['required', 'numeric', 'min:0'],
            'utilization_pct' => ['required', 'numeric', 'min:0', 'max:100'],
            'utilization_status' => ['nullable', 'string', 'max:32'],
        ]);
        if (! empty($data['utilization_status'])) {
            $data['utilization_status'] = strtoupper($data['utilization_status']);
        }
        $utilizationDaily->update($data);

        return $this->redirectStrategic($request)->with('ok', 'Data utilisasi diperbarui.');
    }

    public function deleteUtilizationDaily(UtilizationDaily $utilizationDaily, Request $request)
    {
        $utilizationDaily->delete();

        return $this->redirectStrategic($request)->with('ok', 'Data utilisasi dihapus.');
    }

    public function storeMaintenancePlan(Request $request)
    {
        $data = $request->validate([
            'tractor_id' => ['required', 'string', 'exists:tractors,id'],
            'task_type' => ['required', 'string', 'max:120'],
            'interval_hours' => ['nullable', 'numeric', 'min:0'],
            'current_hours' => ['nullable', 'numeric', 'min:0'],
            'due_hours' => ['nullable', 'numeric', 'min:0'],
            'status' => ['required', 'string', Rule::in(['DONE', 'PENDING', 'OVERDUE', 'done', 'pending', 'overdue'])],
        ]);
        $data['status'] = strtoupper($data['status']);
        MaintenancePlan::query()->create($data);

        return $this->redirectStrategic($request)->with('ok', 'Rencana maintenance berhasil ditambahkan.');
    }

    public function updateMaintenancePlan(MaintenancePlan $maintenancePlan, Request $request)
    {
        $data = $request->validate([
            'tractor_id' => ['required', 'string', 'exists:tractors,id'],
            'task_type' => ['required', 'string', 'max:120'],
            'interval_hours' => ['nullable', 'numeric', 'min:0'],
            'current_hours' => ['nullable', 'numeric', 'min:0'],
            'due_hours' => ['nullable', 'numeric', 'min:0'],
            'status' => ['required', 'string', Rule::in(['DONE', 'PENDING', 'OVERDUE', 'done', 'pending', 'overdue'])],
        ]);
        $data['status'] = strtoupper($data['status']);
        $maintenancePlan->update($data);

        return $this->redirectStrategic($request)->with('ok', 'Rencana maintenance diperbarui.');
    }

    public function deleteMaintenancePlan(MaintenancePlan $maintenancePlan, Request $request)
    {
        $maintenancePlan->delete();

        return $this->redirectStrategic($request)->with('ok', 'Rencana maintenance dihapus.');
    }

    public function storeMaintenanceRecord(Request $request)
    {
        $data = $request->validate([
            'tractor_id' => ['required', 'string', 'exists:tractors,id'],
            'record_date' => ['required', 'date'],
            'record_type' => ['required', 'string', 'max:64'],
            'description' => ['nullable', 'string', 'max:5000'],
            'cost' => ['required', 'numeric', 'min:0'],
            'technician' => ['nullable', 'string', 'max:120'],
            'workshop' => ['nullable', 'string', 'max:120'],
        ]);
        MaintenanceRecord::query()->create($data);

        return $this->redirectStrategic($request)->with('ok', 'Riwayat kesehatan alat berhasil ditambahkan.');
    }

    public function updateMaintenanceRecord(MaintenanceRecord $maintenanceRecord, Request $request)
    {
        $data = $request->validate([
            'tractor_id' => ['required', 'string', 'exists:tractors,id'],
            'record_date' => ['required', 'date'],
            'record_type' => ['required', 'string', 'max:64'],
            'description' => ['nullable', 'string', 'max:5000'],
            'cost' => ['required', 'numeric', 'min:0'],
            'technician' => ['nullable', 'string', 'max:120'],
            'workshop' => ['nullable', 'string', 'max:120'],
        ]);
        $maintenanceRecord->update($data);

        return $this->redirectStrategic($request)->with('ok', 'Riwayat kesehatan alat diperbarui.');
    }

    public function deleteMaintenanceRecord(MaintenanceRecord $maintenanceRecord, Request $request)
    {
        $maintenanceRecord->delete();

        return $this->redirectStrategic($request)->with('ok', 'Riwayat kesehatan alat dihapus.');
    }

    public function storeZone(Request $request)
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'polygon_json' => ['required', 'string', 'max:50000'],
            'is_active' => ['nullable', 'boolean'],
            'tractor_ids' => ['nullable', 'array'],
            'tractor_ids.*' => ['string', 'exists:tractors,id'],
        ]);

        $polygon = json_decode((string) $data['polygon_json'], true);
        if (! is_array($polygon) || count($polygon) < 3) {
            return back()->withErrors(['polygon_json' => 'Polygon harus berupa JSON array minimal 3 titik {lat,lng}.'])->withInput();
        }

        $zone = GeofenceZone::query()->create([
            'name' => $data['name'],
            'zone_type' => 'work',
            'polygon_json' => $data['polygon_json'],
            'is_active' => (bool) ($data['is_active'] ?? true),
        ]);
        if (Schema::hasTable('geofence_zone_tractor')) {
            $zone->tractors()->sync($data['tractor_ids'] ?? []);
        }

        return redirect()->route('strategic', $request->query())->with('ok', 'Zona berhasil dibuat.');
    }

    public function updateZone(GeofenceZone $zone, Request $request)
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'polygon_json' => ['required', 'string', 'max:50000'],
            'is_active' => ['nullable', 'boolean'],
            'tractor_ids' => ['nullable', 'array'],
            'tractor_ids.*' => ['string', 'exists:tractors,id'],
        ]);

        $polygon = json_decode((string) $data['polygon_json'], true);
        if (! is_array($polygon) || count($polygon) < 3) {
            return back()->withErrors(['polygon_json' => 'Polygon harus berupa JSON array minimal 3 titik {lat,lng}.'])->withInput();
        }

        $zone->update([
            'name' => $data['name'],
            'zone_type' => 'work',
            'polygon_json' => $data['polygon_json'],
            'is_active' => $request->boolean('is_active'),
        ]);
        if (Schema::hasTable('geofence_zone_tractor')) {
            $zone->tractors()->sync($data['tractor_ids'] ?? []);
        }

        return redirect()->route('strategic', $request->query())->with('ok', 'Zona berhasil diperbarui.');
    }

    public function toggleZone(GeofenceZone $zone, Request $request)
    {
        $zone->is_active = ! (bool) $zone->is_active;
        $zone->save();
        return redirect()->route('strategic', $request->query())->with('ok', 'Status zona diperbarui.');
    }

    public function deleteZone(GeofenceZone $zone, Request $request)
    {
        $zone->delete();
        return redirect()->route('strategic', $request->query())->with('ok', 'Zona berhasil dihapus.');
    }

    public function routeHistory(string $id, Request $request): JsonResponse
    {
        $tractorId = (string) $id;
        $limit = max(10, min(1200, (int) $request->query('limit', 800)));

        if (! Tractor::query()->whereKey($tractorId)->exists()) {
            return response()->json(['ok' => false, 'message' => 'Traktor tidak ditemukan.'], 404);
        }

        $base = TelemetryLog::query()
            ->where('tractor_id', $tractorId)
            ->whereNotNull('lat')
            ->whereNotNull('lng');
        $ids = (clone $base)->orderByDesc('ts')->limit($limit)->pluck('id');
        $query = $ids->isEmpty()
            ? collect()
            : TelemetryLog::query()
                ->whereIn('id', $ids->all())
                ->orderBy('ts')
                ->get(['lat', 'lng', 'ts']);

        $track = $query->map(fn (TelemetryLog $g) => [
            'lat' => (float) $g->lat,
            'lng' => (float) $g->lng,
            'time' => $g->ts?->toIso8601String(),
        ])->values()->all();

        return response()->json([
            'ok' => true,
            'data' => [
                'tractor_id' => $tractorId,
                'track' => $track,
                'point_count' => count($track),
            ],
        ]);
    }

    /**
     * Posisi per alat untuk peta: pakai tractor_positions_latest jika koordinat valid; jika tidak, titik GPS terakhir dari telemetry_logs.
     *
     * @return Collection<int, TractorPositionLatest|\stdClass>
     */
    private function fleetMapPositions(): Collection
    {
        $tractors = Tractor::query()->orderBy('id')->get(['id', 'name', 'status']);
        $rows = collect();

        foreach ($tractors as $t) {
            $tid = (string) $t->id;
            $pos = TractorPositionLatest::query()->find($tid);

            if ($pos !== null) {
                $lat = $pos->lat;
                $lng = $pos->lng;
                if ($lat !== null && $lng !== null && is_finite((float) $lat) && is_finite((float) $lng)) {
                    $rows->push($pos);

                    continue;
                }
            }

            $log = TelemetryLog::query()
                ->where('tractor_id', $tid)
                ->whereNotNull('lat')
                ->whereNotNull('lng')
                ->orderByDesc('ts')
                ->first();

            if ($log) {
                $rows->push((object) [
                    'tractor_id' => $tid,
                    'lat' => (float) $log->lat,
                    'lng' => (float) $log->lng,
                    'status' => $t->status !== null ? (string) $t->status : 'active',
                    'engine_on' => (bool) ($log->engine_on ?? false),
                ]);
            }
        }

        return $rows;
    }

    /**
     * Jalur GPS per alat: sama logika dengan dashboard — **N titik terbaru** per traktor, urut waktu naik (polyline segmen terkini).
     *
     * @return array<string, list<array{lat: float, lng: float}>>
     */
    private function latestRouteHistoryByTractor(array $tractorIds, int $limitPerTractor = 800): array
    {
        if (empty($tractorIds)) {
            return [];
        }

        /* Per traktor: ambil N titik terbaru (rn menurut ts desc), lalu urut ts naik untuk polyline. */
        $inner = TelemetryLog::query()
            ->select([
                'tractor_id',
                'lat',
                'lng',
                'ts',
                DB::raw('row_number() over (partition by tractor_id order by ts desc) as rn'),
            ])
            ->whereIn('tractor_id', $tractorIds)
            ->whereNotNull('lat')
            ->whereNotNull('lng');

        $rows = DB::query()
            ->fromSub($inner, 'gps_rn')
            ->where('rn', '<=', $limitPerTractor)
            ->orderBy('tractor_id')
            ->orderBy('ts')
            ->get(['tractor_id', 'lat', 'lng']);

        $out = [];
        foreach ($rows as $row) {
            $id = (string) $row->tractor_id;
            $out[$id] ??= [];
            $out[$id][] = [
                'lat' => (float) $row->lat,
                'lng' => (float) $row->lng,
            ];
        }

        return $out;
    }

    /**
     * Nilai Flow BBM sama seperti tabel riwayat di Home: sensor.flow dari raw_payload, fallback ke kolom fuel_lph.
     *
     * @see resources/views/dashboard/index.blade.php ($rFlow = $rs['flow'] ?? $row->fuel_lph)
     */
    private function resolveTelemetryFlowLph(TelemetryLog $log): ?float
    {
        $payload = is_array($log->raw_payload)
            ? $log->raw_payload
            : (json_decode((string) ($log->raw_payload ?? ''), true) ?: []);
        $sensor = $payload['sensor'] ?? [];
        if (is_array($sensor) && array_key_exists('flow', $sensor) && $sensor['flow'] !== null && $sensor['flow'] !== '') {
            if (is_numeric($sensor['flow'])) {
                return (float) $sensor['flow'];
            }
        }
        if ($log->fuel_lph !== null) {
            return (float) $log->fuel_lph;
        }

        return null;
    }

    /**
     * Satu grafik gabungan: beberapa garis (satu per alat), sumber nilai diselaraskan dengan kolom Flow BBM di dashboard Home.
     *
     * @return array{datasets: list<array{label: string, borderColor: string, backgroundColor: string, data: list<array{x: string, y: float}>}>}
     */
    private function fuelFlowChartDatasets(Carbon $from, Carbon $to): array
    {
        $maxPointsPerTractor = 450;
        $palette = [
            ['rgb(217, 119, 6)', 'rgba(217, 119, 6, 0)'],
            ['rgb(5, 150, 105)', 'rgba(5, 150, 105, 0)'],
            ['rgb(14, 165, 233)', 'rgba(14, 165, 233, 0)'],
            ['rgb(124, 58, 237)', 'rgba(124, 58, 237, 0)'],
            ['rgb(219, 39, 119)', 'rgba(219, 39, 119, 0)'],
            ['rgb(234, 88, 12)', 'rgba(234, 88, 12, 0)'],
        ];

        $datasets = [];
        $tractors = Tractor::query()->orderBy('id')->get(['id', 'name']);

        foreach ($tractors as $idx => $t) {
            $tid = (string) $t->id;
            $logs = TelemetryLog::query()
                ->where('tractor_id', $tid)
                ->whereBetween('ts', [$from, $to])
                ->orderBy('ts')
                ->limit(8000)
                ->get(['ts', 'fuel_lph', 'raw_payload']);

            $data = [];
            foreach ($logs as $log) {
                $lph = $this->resolveTelemetryFlowLph($log);
                if ($lph === null) {
                    continue;
                }
                $data[] = [
                    'x' => $log->ts->toIso8601String(),
                    'y' => round($lph, 4),
                ];
            }

            $n = count($data);
            if ($n === 0) {
                continue;
            }
            $step = $n > $maxPointsPerTractor ? (int) ceil($n / $maxPointsPerTractor) : 1;
            if ($step > 1) {
                $sampled = [];
                foreach ($data as $i => $pt) {
                    if ($i % $step === 0) {
                        $sampled[] = $pt;
                    }
                }
                $data = $sampled;
            }

            $label = $tid;
            if ($t->name) {
                $label .= ' — '.$t->name;
            }

            $c = $palette[$idx % count($palette)];
            $datasets[] = [
                'label' => $label,
                'borderColor' => $c[0],
                'backgroundColor' => $c[1],
                'data' => $data,
            ];
        }

        return ['datasets' => $datasets];
    }

    private function geofenceAlerts(Request $request, Carbon $from, Carbon $to): LengthAwarePaginator
    {
        $search = trim((string) $request->query('gf_q', ''));
        $type = trim((string) $request->query('gf_type', ''));
        $perPage = $this->resolveTablePerPage($request, 'gf_per_page');

        return GeofenceEvent::query()
            ->with('zone')
            ->whereBetween('event_ts', [$from, $to])
            ->when($type !== '', fn (Builder $q) => $q->where('event_type', strtoupper($type)))
            ->when($search !== '', function (Builder $q) use ($search) {
                $like = '%'.$search.'%';
                $q->where(function (Builder $w) use ($like) {
                    $w->where('tractor_id', 'like', $like)
                        ->orWhere('message', 'like', $like)
                        ->orWhere('event_type', 'like', $like);
                });
            })
            ->orderByDesc('event_ts')
            ->paginate($perPage, ['*'], 'gf_page')
            ->appends($request->query())
            ->fragment('gf-log-section');
    }

    private function groupScores(Request $request): LengthAwarePaginator
    {
        $period = trim((string) $request->query('gp_period', ''));
        $grade = trim((string) $request->query('gp_grade', ''));
        $search = trim((string) $request->query('gp_q', ''));
        $perPage = $this->resolveTablePerPage($request, 'gp_per_page');
        if ($period === '') {
            $period = now()->format('Y').'-Q'.(int) ceil(((int) now()->format('n')) / 3);
        }

        return GroupPerformanceScore::query()
            ->with('group')
            ->where('period', $period)
            ->when($grade !== '', fn (Builder $q) => $q->where('grade', strtoupper($grade)))
            ->when($search !== '', function (Builder $q) use ($search) {
                $like = '%'.$search.'%';
                $q->whereHas('group', fn (Builder $g) => $g->where('name', 'like', $like)->orWhere('village', 'like', $like))
                    ->orWhere('notes', 'like', $like);
            })
            ->orderByDesc('total_score')
            ->paginate($perPage, ['*'], 'gp_page')
            ->appends($request->query())
            ->fragment('strategic-gp-section');
    }

    private function anomalies(Request $request, Carbon $from, Carbon $to): LengthAwarePaginator
    {
        $status = trim((string) $request->query('an_status', ''));
        $severity = trim((string) $request->query('an_severity', ''));
        $search = trim((string) $request->query('an_q', ''));
        $perPage = $this->resolveTablePerPage($request, 'an_per_page');

        return AnomalyEvent::query()
            ->whereBetween('detected_at', [$from, $to])
            ->when($status !== '', fn (Builder $q) => $q->where('status', strtoupper($status)))
            ->when($severity !== '', fn (Builder $q) => $q->where('severity', strtoupper($severity)))
            ->when($search !== '', function (Builder $q) use ($search) {
                $like = '%'.$search.'%';
                $q->where(function (Builder $w) use ($like) {
                    $w->where('tractor_id', 'like', $like)
                        ->orWhere('anomaly_type', 'like', $like)
                        ->orWhere('description', 'like', $like);
                });
            })
            ->orderByDesc('detected_at')
            ->paginate($perPage, ['*'], 'an_page')
            ->appends($request->query())
            ->fragment('strategic-an-section');
    }

    private function utilization(Request $request, Carbon $from, Carbon $to): LengthAwarePaginator
    {
        $status = trim((string) $request->query('ut_status', ''));
        $search = trim((string) $request->query('ut_q', ''));
        $perPage = $this->resolveTablePerPage($request, 'ut_per_page');

        $sub = UtilizationDaily::query()
            ->selectRaw('tractor_id, max(date) as max_date')
            ->whereBetween('date', [$from->toDateString(), $to->toDateString()])
            ->groupBy('tractor_id');

        return UtilizationDaily::query()
            ->joinSub($sub, 'u_last', function ($join) {
                $join->on('utilization_daily.tractor_id', '=', 'u_last.tractor_id')
                    ->on('utilization_daily.date', '=', 'u_last.max_date');
            })
            ->when($status !== '', fn (Builder $q) => $q->where('utilization_daily.utilization_status', strtoupper($status)))
            ->when($search !== '', fn (Builder $q) => $q->where('utilization_daily.tractor_id', 'like', '%'.$search.'%'))
            ->orderBy('utilization_daily.tractor_id')
            ->paginate($perPage, ['utilization_daily.*'], 'ut_page')
            ->appends($request->query())
            ->fragment('strategic-ut-section');
    }

    private function maintenancePlans(Request $request): LengthAwarePaginator
    {
        $status = trim((string) $request->query('mp_status', ''));
        $search = trim((string) $request->query('mp_q', ''));
        $perPage = $this->resolveTablePerPage($request, 'mp_per_page');

        return MaintenancePlan::query()
            ->when($status !== '', fn (Builder $q) => $q->where('status', strtoupper($status)))
            ->when($search !== '', function (Builder $q) use ($search) {
                $like = '%'.$search.'%';
                $q->where(function (Builder $w) use ($like) {
                    $w->where('tractor_id', 'like', $like)->orWhere('task_type', 'like', $like);
                });
            })
            ->orderBy('tractor_id')
            ->paginate($perPage, ['*'], 'mp_page')
            ->appends($request->query())
            ->fragment('strategic-mp-section');
    }

    private function maintenanceRecords(Request $request, Carbon $from, Carbon $to): LengthAwarePaginator
    {
        $type = trim((string) $request->query('mr_type', ''));
        $search = trim((string) $request->query('mr_q', ''));
        $perPage = $this->resolveTablePerPage($request, 'mr_per_page');

        return MaintenanceRecord::query()
            ->whereBetween('record_date', [$from->toDateString(), $to->toDateString()])
            ->when($type !== '', fn (Builder $q) => $q->where('record_type', $type))
            ->when($search !== '', function (Builder $q) use ($search) {
                $like = '%'.$search.'%';
                $q->where(function (Builder $w) use ($like) {
                    $w->where('tractor_id', 'like', $like)
                        ->orWhere('description', 'like', $like)
                        ->orWhere('technician', 'like', $like);
                });
            })
            ->orderByDesc('record_date')
            ->paginate($perPage, ['*'], 'mr_page')
            ->appends($request->query())
            ->fragment('strategic-mr-section');
    }
}
