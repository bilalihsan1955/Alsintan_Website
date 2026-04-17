<?php

namespace App\Services;

use App\Events\AnomalyEventCreated;
use App\Events\GeofenceEventCreated;
use App\Events\TractorUpdated;
use App\Models\TelemetryLog;
use App\Models\Tractor;
use App\Models\TractorPositionLatest;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class TelemetryIngestService
{
    private const TRACTOR_UPDATED_THROTTLE_SECONDS = 2;

    public function __construct(
        private readonly GeofenceService $geofenceService,
        private readonly AnomalyDetectionService $anomalyService
    ) {}

    /**
     * @param  array<string, mixed>  $payload
     * @return array{log: TelemetryLog, latest: TractorPositionLatest, geofence_events: Collection<int, \App\Models\GeofenceEvent>, anomaly_events: Collection<int, \App\Models\AnomalyEvent>}
     */
    public function ingest(array $payload): array
    {
        return DB::transaction(function () use ($payload) {
            $tractorId = (string) $payload['tractor_id'];
            $tractor = Tractor::query()->findOrFail($tractorId);
            $ts = isset($payload['ts']) ? Carbon::parse((string) $payload['ts']) : now();

            $log = TelemetryLog::query()->create([
                'tractor_id' => $tractor->id,
                'ts' => $ts,
                'lat' => (float) $payload['lat'],
                'lng' => (float) $payload['lng'],
                'speed_kmh' => $payload['speed_kmh'] ?? null,
                'engine_hours' => $payload['engine_hours'] ?? null,
                'vibration_g' => $payload['vibration_g'] ?? null,
                'engine_on' => (bool) ($payload['engine_on'] ?? false),
                'gps_on' => (bool) ($payload['gps_on'] ?? false),
                'fuel_lph' => $payload['fuel_lph'] ?? null,
                'rpm' => $payload['rpm'] ?? null,
                'raw_payload' => $this->asJsonText($payload['raw_payload'] ?? $payload),
            ]);

            $status = isset($payload['status']) ? (string) $payload['status'] : (string) ($tractor->status ?: 'active');

            /** @var TractorPositionLatest $latest */
            $latest = TractorPositionLatest::query()->updateOrCreate(
                ['tractor_id' => $tractor->id],
                [
                    'ts' => $ts,
                    'lat' => (float) $payload['lat'],
                    'lng' => (float) $payload['lng'],
                    'speed_kmh' => $payload['speed_kmh'] ?? null,
                    'status' => $status,
                    'engine_on' => (bool) ($payload['engine_on'] ?? false),
                    'gps_on' => (bool) ($payload['gps_on'] ?? false),
                    'vibration_g' => $payload['vibration_g'] ?? null,
                    'engine_hours' => $payload['engine_hours'] ?? null,
                ]
            );

            if ($tractor->status !== $status) {
                $tractor->status = $status;
                $tractor->save();
            }

            $geofenceEvents = $this->geofenceService->process(
                (float) $payload['lat'],
                (float) $payload['lng'],
                $tractor->id,
                $ts
            );

            $anomalyEvents = $this->anomalyService->detectFromIngest($log, $tractor, $latest);

            $this->broadcastTractorUpdatedIfAllowed([
                'tractor_id' => $tractor->id,
                'ts' => $ts->toIso8601String(),
                'position' => ['lat' => (float) $payload['lat'], 'lng' => (float) $payload['lng']],
                'speed_kmh' => isset($payload['speed_kmh']) ? (float) $payload['speed_kmh'] : null,
                'engine_hours' => isset($payload['engine_hours']) ? (float) $payload['engine_hours'] : null,
                'vibration_g' => isset($payload['vibration_g']) ? (float) $payload['vibration_g'] : null,
                'engine_on' => (bool) ($payload['engine_on'] ?? false),
                'gps_on' => (bool) ($payload['gps_on'] ?? false),
                'status' => $status,
            ]);

            foreach ($geofenceEvents as $ev) {
                event(new GeofenceEventCreated($ev->toArray()));
            }
            foreach ($anomalyEvents as $ev) {
                event(new AnomalyEventCreated($ev->toArray()));
            }

            return [
                'log' => $log,
                'latest' => $latest,
                'geofence_events' => $geofenceEvents,
                'anomaly_events' => $anomalyEvents,
            ];
        });
    }

    private function broadcastTractorUpdatedIfAllowed(array $payload): void
    {
        $tractorId = (string) ($payload['tractor_id'] ?? '');
        if ($tractorId === '') {
            event(new TractorUpdated($payload));
            return;
        }

        $key = 'tractor.updated:last_ts:'.$tractorId;
        $now = now()->getTimestamp();
        $last = (int) Cache::get($key, 0);
        if ($last > 0 && ($now - $last) < self::TRACTOR_UPDATED_THROTTLE_SECONDS) {
            return;
        }
        Cache::put($key, $now, self::TRACTOR_UPDATED_THROTTLE_SECONDS + 2);
        event(new TractorUpdated($payload));
    }

    private function asJsonText(mixed $raw): ?string
    {
        if ($raw === null) {
            return null;
        }
        if (is_string($raw)) {
            return $raw;
        }

        return json_encode($raw, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: null;
    }
}
