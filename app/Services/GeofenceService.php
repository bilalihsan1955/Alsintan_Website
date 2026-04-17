<?php

namespace App\Services;

use App\Models\GeofenceEvent;
use App\Models\GeofenceZone;
use App\Models\TractorZoneState;
use Carbon\Carbon;
use Illuminate\Support\Collection;

class GeofenceService
{
    /**
     * @return Collection<int, GeofenceEvent>
     */
    public function process(float $lat, float $lng, string $tractorId, Carbon $at): Collection
    {
        $zones = GeofenceZone::query()->where('is_active', true)->get();
        $events = collect();

        foreach ($zones as $zone) {
            $insideNow = $this->isPointInsideZone($lat, $lng, $zone);
            $state = TractorZoneState::query()->firstOrCreate(
                ['tractor_id' => $tractorId, 'zone_id' => $zone->id],
                ['is_inside' => false, 'last_transition_at' => null]
            );
            $wasInside = (bool) $state->is_inside;
            $transitioned = $wasInside !== $insideNow;
            $event = null;

            if ($transitioned) {
                if ($wasInside && ! $insideNow) {
                    $event = $this->createEvent('EXIT', $zone, $tractorId, $lat, $lng, $at);
                } elseif (! $wasInside && $insideNow) {
                    /* ENTER setiap kali dari luar → dalam (termasuk pertama kali), agar pasangan dengan EXIT untuk lacak periode di luar area. */
                    $event = $this->createEvent('ENTER', $zone, $tractorId, $lat, $lng, $at);
                }
            }

            if ($event) {
                $events->push($event);
            }

            if ($transitioned) {
                $state->last_transition_at = $at;
            }
            $state->is_inside = $insideNow;
            $state->save();
        }

        return $events;
    }

    public function isPointInsideZone(float $lat, float $lng, GeofenceZone $zone): bool
    {
        $decoded = json_decode((string) $zone->polygon_json, true);
        $ring = $this->extractRingFromPolygonJson($decoded);
        if (! is_array($ring) || count($ring) < 3) {
            return false;
        }

        return $this->pointInPolygon($lat, $lng, $ring);
    }

    /**
     * @return array<int, mixed>
     */
    private function extractRingFromPolygonJson(mixed $decoded): array
    {
        if (! is_array($decoded)) {
            return [];
        }
        if (isset($decoded['type'], $decoded['coordinates'])) {
            $t = strtolower((string) $decoded['type']);
            if ($t === 'polygon' && is_array($decoded['coordinates'][0] ?? null)) {
                return $decoded['coordinates'][0];
            }
            if ($t === 'multipolygon' && is_array($decoded['coordinates'][0][0] ?? null)) {
                return $decoded['coordinates'][0][0];
            }
        }

        return $decoded;
    }

    /**
     * @param  array<int, mixed>  $polygon
     */
    public function pointInPolygon(float $lat, float $lng, array $polygon): bool
    {
        $normalized = [];
        foreach ($polygon as $p) {
            if (is_array($p) && array_key_exists('lat', $p) && array_key_exists('lng', $p)) {
                $normalized[] = [(float) $p['lat'], (float) $p['lng']];
                continue;
            }
            if (is_array($p) && array_key_exists(0, $p) && array_key_exists(1, $p)) {
                $a = (float) $p[0];
                $b = (float) $p[1];
                // Selaras dengan peta: GeoJSON [lng,lat] vs [lat,lng] untuk wilayah Indonesia
                if ($a >= 90.0 && $a <= 150.0 && $b >= -15.0 && $b <= 14.0) {
                    $normalized[] = [$b, $a];
                } elseif ($b >= 90.0 && $b <= 150.0 && $a >= -15.0 && $a <= 14.0) {
                    $normalized[] = [$a, $b];
                } else {
                    $normalized[] = [$a, $b];
                }
            }
        }
        if (count($normalized) < 3) {
            return false;
        }

        $inside = false;
        $j = count($normalized) - 1;
        for ($i = 0; $i < count($normalized); $j = $i++) {
            $latI = (float) $normalized[$i][0];
            $lngI = (float) $normalized[$i][1];
            $latJ = (float) $normalized[$j][0];
            $lngJ = (float) $normalized[$j][1];

            $intersect = (($lngI > $lng) !== ($lngJ > $lng))
                && ($lat < ($latJ - $latI) * ($lng - $lngI) / (($lngJ - $lngI) ?: 1e-12) + $latI);
            if ($intersect) {
                $inside = ! $inside;
            }
        }

        return $inside;
    }

    private function createEvent(
        string $type,
        GeofenceZone $zone,
        string $tractorId,
        float $lat,
        float $lng,
        Carbon $at
    ): GeofenceEvent {
        return GeofenceEvent::query()->create([
            'tractor_id' => $tractorId,
            'zone_id' => $zone->id,
            'event_type' => $type,
            'event_ts' => $at,
            'lat' => $lat,
            'lng' => $lng,
            'message' => $type === 'EXIT'
                ? "Alat {$tractorId} keluar area kerja «{$zone->name}». Jalur GPS di log telemetri setelah titik ini menunjukkan pergerakan di luar area hingga masuk kembali."
                : "Alat {$tractorId} kembali masuk area kerja «{$zone->name}».",
        ]);
    }
}
