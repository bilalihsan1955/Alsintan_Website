<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\TelemetryLog;
use App\Models\Tractor;
use App\Models\TractorPositionLatest;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class TractorMapController extends Controller
{
    public function latestPositions(Request $request)
    {
        $validated = $request->validate([
            'search' => ['sometimes', 'nullable', 'string', 'max:200'],
            'status' => ['sometimes', 'nullable', 'string', 'in:active,idle,maintenance,offline'],
            'page' => ['sometimes', 'integer', 'min:1'],
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:100'],
            'include_route_history' => ['sometimes', 'boolean'],
            /* Selaras batas jejak di dashboard web (800 titik terbaru). */
            'route_history_limit' => ['sometimes', 'integer', 'min:2', 'max:800'],
        ]);

        $perPage = (int) ($validated['per_page'] ?? 15);
        $search = $validated['search'] ?? null;
        $statusFilter = $validated['status'] ?? null;
        $includeRoute = (bool) ($validated['include_route_history'] ?? false);
        $routeLimit = (int) ($validated['route_history_limit'] ?? 30);

        $base = Tractor::query()->with('latestPosition');

        if ($search !== null && $search !== '') {
            $term = '%'.str_replace(['%', '_'], ['\\%', '\\_'], $search).'%';
            $base->where(function ($q) use ($term) {
                $q->where('id', 'like', $term)
                    ->orWhere('name', 'like', $term)
                    ->orWhere('plate_number', 'like', $term);
            });
        }

        if ($statusFilter !== null) {
            $base->where(function ($w) use ($statusFilter) {
                $w->whereHas('latestPosition', function ($q) use ($statusFilter) {
                    $q->whereRaw('lower(tractor_positions_latest.status) = ?', [strtolower($statusFilter)]);
                })->orWhere(function ($w2) use ($statusFilter) {
                    $w2->whereDoesntHave('latestPosition')
                        ->whereRaw('lower(tractors.status) = ?', [strtolower($statusFilter)]);
                });
            });
        }

        $chipBase = Tractor::query()->with('latestPosition');
        if ($search !== null && $search !== '') {
            $term = '%'.str_replace(['%', '_'], ['\\%', '\\_'], $search).'%';
            $chipBase->where(function ($q) use ($term) {
                $q->where('id', 'like', $term)
                    ->orWhere('name', 'like', $term)
                    ->orWhere('plate_number', 'like', $term);
            });
        }

        $totals = ['total_count' => 0, 'active_count' => 0, 'idle_count' => 0];
        foreach ($chipBase->cursor() as $tractor) {
            $totals['total_count']++;
            $st = $this->resolveFleetStatus($tractor);
            if ($st === 'active') {
                $totals['active_count']++;
            } elseif ($st === 'idle') {
                $totals['idle_count']++;
            }
        }

        $paginator = $base->orderBy('id')->paginate($perPage)->appends($request->query());

        $tractorIds = collect($paginator->items())->map(fn (Tractor $t) => (string) $t->id)->all();
        $routes = $includeRoute && count($tractorIds) > 0
            ? $this->shortRoutesForMany($tractorIds, $routeLimit)
            : [];

        $telemetry = $this->latestTelemetrySnapshotByTractorIds($tractorIds);

        $data = collect($paginator->items())->map(function (Tractor $t) use ($includeRoute, $routes, $telemetry) {
            $tid = (string) $t->id;
            $tel = $telemetry[$tid] ?? ['temperature_c' => null, 'ts' => null];

            return $this->buildUnitPayload(
                $t,
                $includeRoute ? ($routes[$tid] ?? []) : [],
                $tel['temperature_c'],
                $tel['ts'] ?? null
            );
        })->values()->all();

        return response()->json([
            'data' => $data,
            'meta' => [
                'current_page' => $paginator->currentPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
                'last_page' => $paginator->lastPage(),
                'active_count' => $totals['active_count'],
                'idle_count' => $totals['idle_count'],
                'total_count' => $totals['total_count'],
                'online_stale_minutes' => (int) config('alsintan.fleet_online_stale_minutes', 15),
            ],
        ]);
    }

    /**
     * Lokasi & ringkasan satu traktor (kartu unit + track on map).
     */
    public function showLocation(string $id, Request $request)
    {
        $validated = $request->validate([
            'include_route_history' => ['sometimes', 'boolean'],
            /* Selaras batas jejak di dashboard web (800 titik terbaru). */
            'route_history_limit' => ['sometimes', 'integer', 'min:2', 'max:800'],
        ]);

        $tractor = Tractor::query()
            ->with([
                'latestPosition',
                'geofenceZones' => fn ($q) => $q->select('geofence_zones.id', 'geofence_zones.name', 'geofence_zones.zone_type', 'geofence_zones.is_active'),
            ])
            ->whereKey((string) $id)
            ->first();

        if ($tractor === null) {
            abort(404);
        }

        $includeRoute = (bool) ($validated['include_route_history'] ?? false);
        $routeLimit = (int) ($validated['route_history_limit'] ?? 30);
        $tid = (string) $tractor->id;

        $routes = $includeRoute ? $this->shortRoutesForMany([$tid], $routeLimit) : [];
        $telemetry = $this->latestTelemetrySnapshotByTractorIds([$tid]);
        $tel = $telemetry[$tid] ?? ['temperature_c' => null, 'ts' => null];
        $temp = $tel['temperature_c'];

        $payload = $this->buildUnitPayload(
            $tractor,
            $includeRoute ? ($routes[$tid] ?? []) : [],
            $temp,
            $tel['ts'] ?? null
        );

        $zones = $tractor->relationLoaded('geofenceZones')
            ? $tractor->geofenceZones->map(fn ($z) => [
                'id' => (int) $z->id,
                'name' => (string) ($z->name ?? ''),
                'zone_type' => (string) ($z->zone_type ?? ''),
                'is_active' => (bool) ($z->is_active ?? false),
            ])->values()->all()
            : [];

        $payload['work_zones'] = $zones;

        return response()->json([
            'data' => $payload,
            'meta' => [
                'online_stale_minutes' => (int) config('alsintan.fleet_online_stale_minutes', 15),
            ],
        ]);
    }

    public function routeHistory(string $id, Request $request)
    {
        $validated = $request->validate([
            'from' => ['nullable', 'date_format:Y-m-d', 'required_with:to'],
            'to' => ['nullable', 'date_format:Y-m-d', 'required_with:from', 'after_or_equal:from'],
            /* Sama batas dengan peta web (StrategicWebController::routeHistory): default 800, maks. 1200 */
            'limit' => ['sometimes', 'integer', 'min:10', 'max:1200'],
        ]);

        abort_unless(Tractor::query()->whereKey((string) $id)->exists(), 404);

        $limit = max(10, min(1200, (int) ($validated['limit'] ?? 800)));

        $hasRange = ! empty($validated['from']) && ! empty($validated['to']);
        $from = $hasRange ? Carbon::parse($validated['from'])->startOfDay() : null;
        $to = $hasRange ? Carbon::parse($validated['to'])->endOfDay() : null;

        /*
         * Lintasan = **N titik terbaru** dari `telemetry_logs`, urut waktu naik (sama ide polyline ringkas
         * di `shortRoutesForMany` / peta yang menampilkan segmen terkini), bukan N titik **paling lama**.
         */
        $base = TelemetryLog::query()
            ->where('tractor_id', (string) $id)
            ->whereNotNull('lat')
            ->whereNotNull('lng');

        if ($hasRange && $from !== null && $to !== null) {
            $base->whereBetween('ts', [$from, $to]);
        }

        $ids = (clone $base)->orderByDesc('ts')->limit($limit)->pluck('id');

        $track = $ids->isEmpty()
            ? collect()
            : TelemetryLog::query()
                ->whereIn('id', $ids->all())
                ->orderBy('ts')
                ->get(['lat', 'lng', 'ts'])
                ->map(fn (TelemetryLog $l) => [
                    'lat' => (float) $l->lat,
                    'lng' => (float) $l->lng,
                    'ts' => $l->ts?->clone()->utc()->toIso8601String(),
                ])
                ->values();

        return response()->json([
            'data' => $track,
            'meta' => [
                'tractor_id' => (string) $id,
                'source' => 'telemetry_logs',
                'segment' => 'recent',
                'from' => $hasRange && $from ? $from->toDateString() : null,
                'to' => $hasRange && $to ? $to->toDateString() : null,
                'count' => $track->count(),
                'limit' => $limit,
            ],
        ]);
    }

    /**
     * Payload daftar unit / kartu (bukan riwayat log sensor per menit).
     *
     * @param  array<int, array{lat: float, lng: float, ts?: string|null}>  $routeHistory
     * @param  float|null  $temperatureC  Suhu °C dari telemetri terakhir
     */
    private function buildUnitPayload(
        Tractor $t,
        array $routeHistory,
        ?float $temperatureC,
        mixed $telemetryLastTs
    ): array {
        /** @var TractorPositionLatest|null $p */
        $p = $t->latestPosition;

        $posTs = $p?->ts;
        $telTs = $telemetryLastTs instanceof Carbon ? $telemetryLastTs : null;
        $freshTs = $this->freshestTimestamp($posTs, $telTs);

        return [
            'id' => (string) $t->id,
            'name' => (string) ($t->name ?? ''),
            'display_name' => $this->primaryDisplayLabel($t),
            'plate_number' => (string) ($t->plate_number ?? ''),
            'lat' => $p ? (float) $p->lat : 0.0,
            'lng' => $p ? (float) $p->lng : 0.0,
            'speed_kmh' => $p ? (float) ($p->speed_kmh ?? 0) : 0.0,
            'engine_hours' => $p ? (float) ($p->engine_hours ?? 0) : 0.0,
            'status' => $this->resolveFleetStatus($t),
            'connection_status' => $this->resolveConnectionStatus($freshTs),
            'last_update' => $p?->ts?->clone()->utc()->toIso8601String()
                ?? '1970-01-01T00:00:00.000000Z',
            'temperature_c' => $temperatureC,
            'vibration' => $p ? (float) ($p->vibration_g ?? 0) : 0.0,
            'is_engine_on' => (bool) ($p->engine_on ?? false),
            'is_gps_on' => (bool) ($p->gps_on ?? false),
            'route_history' => $routeHistory,
        ];
    }

    private function primaryDisplayLabel(Tractor $t): string
    {
        $name = trim((string) ($t->name ?? ''));

        return $name !== '' ? $name : (string) $t->id;
    }

    private function freshestTimestamp(?Carbon $a, ?Carbon $b): ?Carbon
    {
        if ($a === null) {
            return $b;
        }
        if ($b === null) {
            return $a;
        }

        return $a->greaterThan($b) ? $a : $b;
    }

    private function resolveConnectionStatus(?Carbon $lastAnyTs): string
    {
        if ($lastAnyTs === null) {
            return 'offline';
        }

        $stale = (int) config('alsintan.fleet_online_stale_minutes', 15);
        $lastUtc = $lastAnyTs->copy()->timezone('UTC');

        return $lastUtc->isAfter(now('UTC')->subMinutes($stale)) ? 'online' : 'offline';
    }

    /**
     * @param  array<int, string>  $tractorIds
     * @return array<string, array{temperature_c: ?float, ts: ?Carbon}>
     */
    private function latestTelemetrySnapshotByTractorIds(array $tractorIds): array
    {
        if ($tractorIds === []) {
            return [];
        }

        $sub = TelemetryLog::query()
            ->select('tractor_id', DB::raw('MAX(ts) as max_ts'))
            ->whereIn('tractor_id', $tractorIds)
            ->groupBy('tractor_id');

        $rows = TelemetryLog::query()
            ->from('telemetry_logs')
            ->joinSub($sub, 'latest_ts', function ($join) {
                $join->on('telemetry_logs.tractor_id', '=', 'latest_ts.tractor_id')
                    ->on('telemetry_logs.ts', '=', 'latest_ts.max_ts');
            })
            ->whereIn('telemetry_logs.tractor_id', $tractorIds)
            ->get(['telemetry_logs.tractor_id', 'telemetry_logs.raw_payload', 'telemetry_logs.ts']);

        $out = [];
        foreach ($rows as $row) {
            $tid = (string) $row->tractor_id;
            $out[$tid] = [
                'temperature_c' => $this->extractTemperatureCelsius($row->raw_payload),
                'ts' => $row->ts instanceof Carbon ? $row->ts : null,
            ];
        }

        return $out;
    }

    private function extractTemperatureCelsius(mixed $rawPayload): ?float
    {
        $data = $rawPayload;
        if (is_string($data)) {
            $data = json_decode($data, true);
        }
        if (! is_array($data)) {
            return null;
        }

        if (isset($data['sensor']) && is_array($data['sensor']) && isset($data['sensor']['temperature']) && is_numeric($data['sensor']['temperature'])) {
            return (float) $data['sensor']['temperature'];
        }

        return null;
    }

    /**
     * Status armada untuk mobile: active | idle | maintenance | offline (lowercase).
     */
    private function resolveFleetStatus(Tractor $t): string
    {
        $p = $t->latestPosition;
        $raw = $p?->status ?? ($t->getAttributes()['status'] ?? '');

        return $this->normalizeFleetStatus((string) $raw, (bool) $p);
    }

    private function normalizeFleetStatus(string $raw, bool $hasLatestPosition): string
    {
        $s = strtolower(trim($raw));
        $aliases = [
            'running' => 'active',
            'on' => 'active',
            'operational' => 'active',
            'stopped' => 'idle',
            'park' => 'idle',
            'off' => 'idle',
            'repair' => 'maintenance',
            'service' => 'maintenance',
            'unknown' => 'offline',
        ];
        if ($s === '' && $hasLatestPosition) {
            return 'idle';
        }
        if ($s === '') {
            return 'offline';
        }
        if (isset($aliases[$s])) {
            return $aliases[$s];
        }
        if (in_array($s, ['active', 'idle', 'maintenance', 'offline'], true)) {
            return $s;
        }

        return 'offline';
    }

    private function shortRoutesForMany(array $tractorIds, int $limitPerTractor = 2): array
    {
        if (empty($tractorIds)) {
            return [];
        }
        $rows = TelemetryLog::query()
            ->select([
                'tractor_id',
                'lat',
                'lng',
                'ts',
                DB::raw('row_number() over (partition by tractor_id order by ts desc) as rn'),
            ])
            ->whereIn('tractor_id', $tractorIds)
            ->whereNotNull('lat')
            ->whereNotNull('lng')
            ->get();

        $out = [];
        foreach ($rows as $row) {
            $rn = (int) ($row->rn ?? 0);
            if ($rn < 1 || $rn > $limitPerTractor) {
                continue;
            }
            $tid = (string) $row->tractor_id;
            $out[$tid] ??= [];
            $ts = $row->ts;
            $out[$tid][] = [
                'lat' => (float) $row->lat,
                'lng' => (float) $row->lng,
                'ts' => $ts instanceof Carbon ? $ts->clone()->utc()->toIso8601String() : null,
            ];
        }
        foreach ($out as $tid => $points) {
            $out[$tid] = array_reverse($points);
        }

        return $out;
    }
}
