<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Tractor;
use App\Services\TelemetryIngestService;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

class TelemetryIngestController extends Controller
{
    public function __construct(private readonly TelemetryIngestService $ingestService) {}

    public function store(Request $request)
    {
        $body = $request->all();

        // Kompatibilitas lama: /api/v1/sync-data dengan payload {device_id, tractor_name, records:[...]}
        if (isset($body['records']) && is_array($body['records'])) {
            $deviceId = (string) ($body['device_id'] ?? '');
            if ($deviceId === '') {
                return response()->json(['message' => 'device_id wajib untuk mode sync-data'], 422);
            }
            $tractor = $this->resolveOrCreateTractorFromDevice(
                $deviceId,
                isset($body['tractor_name']) ? (string) $body['tractor_name'] : null
            );

            $accepted = 0;
            $totalGeofence = 0;
            $totalAnomaly = 0;

            foreach ($body['records'] as $rec) {
                if (! is_array($rec)) {
                    continue;
                }
                $gps = (array) ($rec['gps'] ?? []);
                $sensor = (array) ($rec['sensor'] ?? []);
                $lat = $gps['latitude'] ?? null;
                $lng = $gps['longitude'] ?? null;
                if (! is_numeric($lat) || ! is_numeric($lng)) {
                    continue;
                }

                // flow -> fuel_lph (sensor aliran BBM). vibration -> vibration_g (SW420 analog). Jangan tukar.
                $mapped = [
                    'tractor_id' => (string) $tractor->id,
                    'ts' => $rec['recorded_at'] ?? Carbon::now()->toIso8601String(),
                    'lat' => (float) $lat,
                    'lng' => (float) $lng,
                    'speed_kmh' => null,
                    'engine_hours' => null,
                    'vibration_g' => isset($sensor['vibration']) ? (float) $sensor['vibration'] : null,
                    'engine_on' => (bool) ($sensor['is_moving'] ?? $sensor['sw420'] ?? false),
                    'gps_on' => true,
                    'fuel_lph' => isset($sensor['flow']) ? (float) $sensor['flow'] : null,
                    'rpm' => null,
                    'status' => (($sensor['is_moving'] ?? false) ? 'active' : 'idle'),
                    'raw_payload' => $rec,
                ];

                $out = $this->ingestService->ingest($mapped);
                $accepted++;
                $totalGeofence += $out['geofence_events']->count();
                $totalAnomaly += $out['anomaly_events']->count();
            }

            return response()->json([
                'data' => [
                    'accepted' => $accepted,
                    'geofence_events' => $totalGeofence,
                    'anomaly_events' => $totalAnomaly,
                ],
                'meta' => ['ok' => true, 'compat' => 'sync-data-v1'],
            ]);
        }

        // Mode baru: payload tunggal telemetry/ingest
        $payload = $request->validate([
            'tractor_id' => ['required', 'string'],
            'ts' => ['nullable', 'date'],
            'lat' => ['required', 'numeric'],
            'lng' => ['required', 'numeric'],
            'speed_kmh' => ['nullable', 'numeric'],
            'engine_hours' => ['nullable', 'numeric'],
            'vibration_g' => ['nullable', 'numeric'],
            'engine_on' => ['nullable', 'boolean'],
            'gps_on' => ['nullable', 'boolean'],
            'fuel_lph' => ['nullable', 'numeric'],
            'rpm' => ['nullable', 'integer'],
            'status' => ['nullable', 'string'],
            'raw_payload' => ['nullable'],
        ]);
        if (! Tractor::query()->whereKey((string) $payload['tractor_id'])->exists()) {
            return response()->json(['message' => 'tractor_id tidak ditemukan'], 422);
        }

        $out = $this->ingestService->ingest($payload);
        return response()->json([
            'data' => [
                'log_id' => $out['log']->id,
                'geofence_events' => $out['geofence_events']->count(),
                'anomaly_events' => $out['anomaly_events']->count(),
            ],
            'meta' => ['ok' => true],
        ]);
    }

    private function resolveOrCreateTractorFromDevice(string $deviceId, ?string $tractorName): Tractor
    {
        $tractor = Tractor::query()
            ->where('id', $deviceId)
            ->orWhere('device_uid', $deviceId)
            ->first();

        if (! $tractor) {
            $tractor = Tractor::query()->create([
                'id' => $deviceId,
                'name' => $tractorName ?: $deviceId,
                'plate_number' => null,
                'device_uid' => $deviceId,
                'status' => 'idle',
            ]);
        } elseif ($tractorName && $tractor->name !== $tractorName) {
            $tractor->name = $tractorName;
            $tractor->save();
        }

        return $tractor;
    }
}
