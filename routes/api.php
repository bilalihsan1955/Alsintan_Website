<?php

use App\Http\Controllers\Api\AlertController;
use App\Http\Controllers\Api\Auth\AuthController;
use App\Http\Controllers\Api\MaintenanceController;
use App\Http\Controllers\Api\MeController;
use App\Http\Controllers\Api\StrategicController;
use App\Http\Controllers\Api\TelemetryIngestController;
use App\Http\Controllers\Api\TractorMapController;
use App\Http\Controllers\Api\WorkZoneController;
use App\Http\Controllers\Api\UtilizationController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API v1 — Alsintan
|--------------------------------------------------------------------------
|
| Kontrak bersama mobile (Flutter) & web.
| - Mobile memakai JWT bearer (Authorization: Bearer {access_token}).
| - Device IoT (ESP32/RPi) memakai header X-Device-Token per-traktor.
| - Format error JSON ditangani di bootstrap/app.php (validation, 401, 403, 404, 500).
|
*/

Route::prefix('v1')->group(function () {

    /* Auth — tidak butuh token. */
    Route::prefix('auth')->group(function () {
        Route::post('/login', [AuthController::class, 'login']);
        Route::post('/refresh', [AuthController::class, 'refresh']);
        Route::post('/forgot-password', [AuthController::class, 'forgotPassword']);
        Route::post('/reset-password', [AuthController::class, 'resetPassword']);

        /* Logout mencabut refresh_token yang dikirim klien; access tetap hidup hingga expired. */
        Route::post('/logout', [AuthController::class, 'logout'])->middleware('auth.jwt');
    });

    /* Terproteksi: butuh access token valid. */
    Route::middleware('auth.jwt')->group(function () {

        /* Profil & preferensi user saat ini. */
        Route::prefix('me')->group(function () {
            Route::get('/', [MeController::class, 'show']);
            Route::patch('/', [MeController::class, 'update']);
            Route::post('/avatar', [MeController::class, 'uploadAvatar']);
            Route::delete('/avatar', [MeController::class, 'deleteAvatar']);
            Route::patch('/password', [MeController::class, 'updatePassword']);
            Route::get('/preferences', [MeController::class, 'getPreferences']);
            Route::put('/preferences', [MeController::class, 'updatePreferences']);
        });

        /* Dashboard strategis (read-only untuk operator; admin boleh lebih). */
        Route::get('/strategic/kpi', [StrategicController::class, 'kpi']);
        Route::get('/strategic/coverage', [StrategicController::class, 'coverage']);
        Route::get('/strategic/fuel-efficiency', [StrategicController::class, 'fuelEfficiency']);
        Route::get('/performance/groups', [StrategicController::class, 'groupPerformance']);

        /* Alerts & anomaly. Resolve dibatasi admin (ditegakkan di controller/policy ke depan). */
        Route::get('/alerts/geofence', [AlertController::class, 'geofence']);
        Route::get('/alerts/anomalies', [AlertController::class, 'anomalies']);
        Route::patch('/alerts/anomalies/{id}/resolve', [AlertController::class, 'resolveAnomaly'])
            ->middleware('admin');

        Route::get('/utilization', [UtilizationController::class, 'index']);
        Route::get('/maintenance/plans', [MaintenanceController::class, 'plans']);
        Route::get('/maintenance/records', [MaintenanceController::class, 'records']);

        Route::get('/tractors/latest-positions', [TractorMapController::class, 'latestPositions']);
        Route::get('/tractors/{id}/location', [TractorMapController::class, 'showLocation']);
        Route::get('/tractors/{id}/route-history', [TractorMapController::class, 'routeHistory']);
        Route::get('/work-zones', [WorkZoneController::class, 'index']);
    });

    /* Ingest telemetri dari device IoT. Pakai X-Device-Token per-traktor (bukan JWT user). */
    Route::middleware('auth.device')->group(function () {
        Route::post('/telemetry/ingest', [TelemetryIngestController::class, 'store']);
    });

    /*
    |--------------------------------------------------------------------------
    | Backward-compat untuk firmware ESP32/RPi lama
    |--------------------------------------------------------------------------
    | TODO(product-owner): setelah semua device migrasi ke /telemetry/ingest
    | dengan X-Device-Token, dua route di bawah ini sebaiknya dihapus.
    */
    Route::post('/sync-data', [TelemetryIngestController::class, 'store']);
    Route::post('/device/telemetry', [TelemetryIngestController::class, 'store']);
});
