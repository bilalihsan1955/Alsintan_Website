<?php

namespace App\Http\Controllers;

use App\Models\TelemetryLog;
use App\Models\Tractor;
use App\Models\TractorPositionLatest;
use App\Services\GpsPathMetrics;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function index(Request $request): View
    {
        $tractors = Tractor::query()->orderBy('name')->orderBy('id')->get();
        $tractor = $this->resolveTractor($request, $tractors);

        $payload = $tractor !== null ? $this->loadTractorDashboard($tractor) : [];
        $history = $tractor !== null ? $this->telemetryHistory($request, $tractor) : null;

        return view('dashboard.index', array_merge(
            [
                'tractors' => $tractors,
                'tractor' => $tractor,
                'historyRows' => $history,
            ],
            $payload
        ));
    }

    public function data(Request $request): JsonResponse
    {
        $tractors = Tractor::query()->orderBy('name')->orderBy('id')->get();
        $tractor = $this->resolveTractor($request, $tractors);

        if ($tractor === null) {
            return response()->json(['ok' => false, 'message' => 'Traktor tidak ditemukan.'], 404);
        }

        $d = $this->loadTractorDashboard($tractor);

        return response()->json($this->formatJsonSnapshot($tractor, $d));
    }

    /**
     * @param  \Illuminate\Support\Collection<int, Tractor>  $tractors
     */
    private function resolveTractor(Request $request, $tractors): ?Tractor
    {
        $tractorId = $request->query('tractor');
        $tractor = null;
        if ($tractorId !== null && $tractorId !== '') {
            $tractor = Tractor::query()->find((string) $tractorId);
        }

        if ($tractor === null) {
            $tractor = $tractors->first();
        }

        return $tractor;
    }

    /**
     * Satu kali query untuk view maupun endpoint JSON.
     *
     * @return array<string, mixed>
     */
    private function loadTractorDashboard(Tractor $tractor): array
    {
        $latestLog = TelemetryLog::query()
            ->where('tractor_id', $tractor->id)
            ->latest('ts')
            ->first();
        $latestPayload = is_array($latestLog?->raw_payload) ? $latestLog->raw_payload : (json_decode((string) ($latestLog?->raw_payload ?? ''), true) ?: []);
        $flowValue = (float) ($latestLog?->fuel_lph ?? 0);

        $gpsLogs = TelemetryLog::query()
            ->where('tractor_id', $tractor->id)
            ->whereNotNull('lat')
            ->whereNotNull('lng')
            ->orderBy('ts')
            ->limit(800)
            ->get(['lat', 'lng', 'ts']);

        $tz = config('app.timezone');
        $trackPoints = $gpsLogs
            ->map(fn (TelemetryLog $g) => [
                'lat' => (float) $g->lat,
                'lng' => (float) $g->lng,
                'ts' => $g->ts?->timezone($tz)->format('d/m/Y H:i:s'),
            ])
            ->values()
            ->all();

        $pathLengthM = GpsPathMetrics::polylineLengthMeters($trackPoints);

        /** Pusat peta = titik GPS pertama (lokasi awal rekaman), bukan koordinat tetap. */
        $mapCenter = null;
        if (count($trackPoints) > 0) {
            $mapCenter = [
                'lat' => $trackPoints[0]['lat'],
                'lng' => $trackPoints[0]['lng'],
            ];
        }

        $workSession = null;
        $liveUpdatedAt = $latestLog?->ts;

        // SW420: status digital dari payload sensor (is_moving / sw420) atau kolom engine_on hasil ingest; BUKAN dari speed.
        // Nilai analog getaran (g) hanya dari kolom vibration_g (isi dari sensor.vibration di ingest), BUKAN dari flow.
        $sw420Digital = null;
        if (isset($latestPayload['sensor']) && is_array($latestPayload['sensor'])) {
            $s = $latestPayload['sensor'];
            if (array_key_exists('is_moving', $s)) {
                $sw420Digital = (bool) $s['is_moving'];
            } elseif (array_key_exists('sw420', $s)) {
                $sw420Digital = (bool) $s['sw420'];
            }
        }
        if ($sw420Digital === null && $latestLog !== null) {
            $sw420Digital = (bool) ($latestLog->engine_on ?? false);
        }

        $latestSensor = (object) [
            'temperature' => isset($latestPayload['sensor']['temperature']) ? (float) $latestPayload['sensor']['temperature'] : null,
            'humidity' => isset($latestPayload['sensor']['humidity']) ? (float) $latestPayload['sensor']['humidity'] : null,
            'vibration' => $latestLog?->vibration_g,
            'is_moving' => $sw420Digital,
            'created_at' => $latestLog?->ts,
        ];
        $latestSensorWithFlow = (object) [
            'flow' => $flowValue,
            'created_at' => $latestLog?->ts,
        ];

        return [
            'latestSensor' => $latestSensor,
            'latestSensorWithFlow' => $latestSensorWithFlow,
            'flowValue' => $flowValue,
            'latestSpeedKmh' => $latestLog?->speed_kmh !== null ? (float) $latestLog->speed_kmh : null,
            'latestStatus' => $latestLog?->status,
            'latestRpm' => $latestLog?->rpm !== null ? (int) $latestLog->rpm : null,
            'latestEngineHours' => $latestLog?->engine_hours !== null ? (float) $latestLog->engine_hours : null,
            'latestEngineOn' => $latestLog?->engine_on !== null ? (bool) $latestLog->engine_on : null,
            'trackPoints' => $trackPoints,
            'pathLengthM' => $pathLengthM,
            'workSession' => $workSession,
            'mapCenter' => $mapCenter,
            'liveUpdatedAt' => $liveUpdatedAt,
        ];
    }

    /**
     * @param  array<string, mixed>  $d
     * @return array<string, mixed>
     */
    private function formatJsonSnapshot(Tractor $tractor, array $d): array
    {
        /** @var object|null $latestSensor */
        $latestSensor = $d['latestSensor'];
        /** @var object|null $workSession */
        $workSession = $d['workSession'];
        /** @var ?Carbon $liveUpdatedAt */
        $liveUpdatedAt = $d['liveUpdatedAt'];

        return [
            'ok' => true,
            'tractor' => [
                'id' => $tractor->id,
                'device_id' => $tractor->id,
                'name' => $tractor->name,
            ],
            'status' => $d['latestStatus'] ?? null,
            'speed_kmh' => $d['latestSpeedKmh'] ?? null,
            'rpm' => $d['latestRpm'] ?? null,
            'engine_hours' => $d['latestEngineHours'] ?? null,
            'engine_on' => $d['latestEngineOn'] ?? null,
            'sensor' => [
                'temperature' => $latestSensor?->temperature !== null ? (float) $latestSensor->temperature : null,
                'humidity' => $latestSensor?->humidity !== null ? (float) $latestSensor->humidity : null,
                'vibration' => $latestSensor?->vibration !== null ? (float) $latestSensor->vibration : null,
                'flow' => (float) $d['flowValue'],
                'is_moving' => $latestSensor !== null && $latestSensor->is_moving !== null
                    ? (bool) $latestSensor->is_moving
                    : null,
            ],
            'gps' => [
                'track' => $d['trackPoints'],
                'point_count' => count($d['trackPoints']),
                'path_length_m' => (float) $d['pathLengthM'],
                'start' => $d['mapCenter'],
            ],
            'work' => [
                'stored_circumference_m' => $workSession?->total_circumference !== null ? (float) $workSession->total_circumference : null,
                'status' => $workSession?->status,
                'updated_human' => $workSession?->updated_at?->timezone(config('app.timezone'))->diffForHumans(),
            ],
            'live_updated_at' => $liveUpdatedAt?->toIso8601String(),
            'live_updated_human' => $liveUpdatedAt?->timezone(config('app.timezone'))->diffForHumans(),
        ];
    }

    private function resolveLiveTimestamp(?object $latest, ?object $withFlow): ?Carbon
    {
        $times = [];
        if ($latest?->created_at) {
            $times[] = $latest->created_at;
        }
        if ($withFlow?->created_at) {
            $times[] = $withFlow->created_at;
        }
        if ($times === []) {
            return null;
        }

        return collect($times)->max();
    }

    private function telemetryHistory(Request $request, Tractor $tractor)
    {
        $q = trim((string) $request->query('hist_q', ''));
        $from = trim((string) $request->query('hist_from', ''));
        $to = trim((string) $request->query('hist_to', ''));
        $order = strtolower(trim((string) $request->query('hist_order', 'desc')));
        $order = $order === 'asc' ? 'asc' : 'desc';
        $perPage = (int) $request->query('hist_per_page', 10);
        if (! in_array($perPage, [10, 50], true)) {
            $perPage = 10;
        }

        return TelemetryLog::query()
            ->where('tractor_id', $tractor->id)
            ->when($from !== '', fn ($x) => $x->whereDate('ts', '>=', $from))
            ->when($to !== '', fn ($x) => $x->whereDate('ts', '<=', $to))
            ->when($q !== '', function ($x) use ($q) {
                $like = '%'.$q.'%';
                $x->where(function ($w) use ($like) {
                    $w->where('status', 'like', $like)
                        ->orWhere('raw_payload', 'like', $like);
                });
            })
            ->orderBy('ts', $order)
            ->paginate($perPage, ['*'], 'hist_page')
            ->appends($request->query())
            ->fragment('riwayat-sensor');
    }
}
