<?php

namespace App\Http\Controllers;

use App\Models\AnomalyEvent;
use App\Models\GeofenceEvent;
use App\Models\GeofenceZone;
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

        return view('strategic.index', [
            'from' => $from,
            'to' => $to,
            'kpi' => $kpi,
            'coverageSeries' => $coverage,
            'fuelSeries' => $fuel,
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
            'zoneTractorFeatureReady' => $hasZoneTractorPivot,
            'periodLabel' => $request->query('gp_period')
                ?: now()->format('Y').'-Q'.(int) ceil(((int) now()->format('n')) / 3),
        ]);
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

        $query = TelemetryLog::query()
            ->where('tractor_id', $tractorId)
            ->whereNotNull('lat')
            ->whereNotNull('lng')
            ->orderBy('ts')
            ->limit($limit)
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
     * Jalur GPS per alat: sama logika dengan dashboard — titik berurutan menurut waktu, paling banyak 800 titik pertama.
     *
     * @return array<string, list<array{lat: float, lng: float}>>
     */
    private function latestRouteHistoryByTractor(array $tractorIds, int $limitPerTractor = 800): array
    {
        if (empty($tractorIds)) {
            return [];
        }

        $inner = TelemetryLog::query()
            ->select([
                'tractor_id',
                'lat',
                'lng',
                'ts',
                DB::raw('row_number() over (partition by tractor_id order by ts asc) as rn'),
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
