<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\GeofenceZone;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;

class WorkZoneController extends Controller
{
    /**
     * Zona / area kerja (geofence) untuk overlay peta.
     */
    public function index(Request $request)
    {
        $validated = $request->validate([
            'active_only' => ['sometimes', 'boolean'],
        ]);

        $activeOnly = (bool) ($validated['active_only'] ?? false);

        $q = GeofenceZone::query()->orderByDesc('is_active')->orderBy('name');

        if ($activeOnly) {
            $q->where('is_active', true);
        }

        $withTractors = Schema::hasTable('geofence_zone_tractor');
        if ($withTractors) {
            $q->with(['tractors:id,name']);
        }

        $zones = $q->get();

        $data = $zones->map(function (GeofenceZone $z) use ($withTractors) {
            $ring = json_decode((string) ($z->polygon_json ?? ''), true);
            if (! is_array($ring)) {
                $ring = [];
            }

            $row = [
                'id' => (int) $z->id,
                'name' => (string) ($z->name ?? ''),
                'zone_type' => (string) ($z->zone_type ?? ''),
                'is_active' => (bool) ($z->is_active ?? false),
                'polygon' => $ring,
            ];

            if ($withTractors) {
                $row['tractor_ids'] = $z->relationLoaded('tractors')
                    ? $z->tractors->pluck('id')->map(fn ($id) => (string) $id)->values()->all()
                    : [];
            }

            return $row;
        })->values()->all();

        return response()->json([
            'data' => $data,
            'meta' => [
                'count' => count($data),
                'active_only' => $activeOnly,
            ],
        ]);
    }
}
