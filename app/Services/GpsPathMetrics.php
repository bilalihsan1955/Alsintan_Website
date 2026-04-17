<?php

namespace App\Services;

/**
 * Perhitungan jarak geodesik (Haversine) dan panjang lintasan dari rangkaian titik GPS.
 */
class GpsPathMetrics
{
    private const EARTH_RADIUS_M = 6371000.0;

    public static function haversineMeters(float $lat1, float $lon1, float $lat2, float $lon2): float
    {
        $phi1 = deg2rad($lat1);
        $phi2 = deg2rad($lat2);
        $dPhi = deg2rad($lat2 - $lat1);
        $dLambda = deg2rad($lon2 - $lon1);

        $a = sin($dPhi / 2) ** 2
            + cos($phi1) * cos($phi2) * sin($dLambda / 2) ** 2;
        $c = 2 * atan2(sqrt($a), sqrt(max(0.0, 1.0 - $a)));

        return self::EARTH_RADIUS_M * $c;
    }

    /**
     * @param  iterable<int, array{lat: float, lng: float}>  $points
     */
    public static function polylineLengthMeters(iterable $points): float
    {
        $total = 0.0;
        $prev = null;

        foreach ($points as $p) {
            $lat = (float) ($p['lat'] ?? 0);
            $lng = (float) ($p['lng'] ?? 0);
            if ($prev !== null) {
                $total += self::haversineMeters($prev['lat'], $prev['lng'], $lat, $lng);
            }
            $prev = ['lat' => $lat, 'lng' => $lng];
        }

        return $total;
    }
}
