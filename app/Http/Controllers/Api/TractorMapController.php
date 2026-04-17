<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\TelemetryLog;
use App\Models\Tractor;
use App\Models\TractorPositionLatest;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class TractorMapController extends Controller
{
    public function latestPositions()
    {
        $latest = TractorPositionLatest::query()->orderBy('tractor_id')->get();
        $routes = $this->shortRoutesForMany($latest->pluck('tractor_id')->all(), 2);

        $data = $latest->map(fn (TractorPositionLatest $p) => [
            'id' => (string) $p->tractor_id,
            'tractor_id' => (string) $p->tractor_id,
            'lat' => (float) $p->lat,
            'lng' => (float) $p->lng,
            'status' => (string) ($p->status ?? 'offline'),
            'engine_on' => (bool) $p->engine_on,
            'gps_on' => (bool) $p->gps_on,
            'ts' => $p->ts?->toIso8601String(),
            'route_history' => $routes[(string) $p->tractor_id] ?? [],
        ]);

        return response()->json(['data' => $data, 'meta' => ['count' => $data->count()]]);
    }

    public function routeHistory(string $id, Request $request)
    {
        $limit = max(10, min(1200, (int) $request->query('limit', 800)));
        if (! Tractor::query()->whereKey((string) $id)->exists()) {
            return response()->json(['message' => 'Traktor tidak ditemukan'], 404);
        }

        $track = TelemetryLog::query()
            ->where('tractor_id', (string) $id)
            ->whereNotNull('lat')
            ->whereNotNull('lng')
            ->orderBy('ts')
            ->limit($limit)
            ->get(['lat', 'lng', 'ts'])
            ->map(fn (TelemetryLog $l) => [
                'lat' => (float) $l->lat,
                'lng' => (float) $l->lng,
                'time' => $l->ts?->toIso8601String(),
            ])
            ->values();

        return response()->json(['data' => ['tractor_id' => (string) $id, 'track' => $track], 'meta' => ['count' => $track->count()]]);
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
            $id = (string) $row->tractor_id;
            $out[$id] ??= [];
            $out[$id][] = ['lat' => (float) $row->lat, 'lng' => (float) $row->lng];
        }
        foreach ($out as $id => $points) {
            $out[$id] = array_reverse($points);
        }
        return $out;
    }
}
