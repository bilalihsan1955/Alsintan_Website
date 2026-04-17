<?php

use App\Http\Controllers\Api\AlertController;
use App\Http\Controllers\Api\MaintenanceController;
use App\Http\Controllers\Api\TelemetryIngestController;
use App\Http\Controllers\Api\TractorMapController;
use App\Http\Controllers\Api\UtilizationController;
use App\Http\Controllers\Api\StrategicController;
use Illuminate\Support\Facades\Route;

// Backward compatibility untuk ESP32/RPi lama
Route::prefix('v1')->group(function () {
    Route::post('/sync-data', [TelemetryIngestController::class, 'store']);
    Route::post('/device/telemetry', [TelemetryIngestController::class, 'store']);
});

Route::get('/strategic/kpi', [StrategicController::class, 'kpi']);
Route::get('/strategic/coverage', [StrategicController::class, 'coverage']);
Route::get('/strategic/fuel-efficiency', [StrategicController::class, 'fuelEfficiency']);
Route::get('/performance/groups', [StrategicController::class, 'groupPerformance']);

Route::get('/alerts/geofence', [AlertController::class, 'geofence']);
Route::get('/alerts/anomalies', [AlertController::class, 'anomalies']);
Route::patch('/alerts/anomalies/{id}/resolve', [AlertController::class, 'resolveAnomaly']);

Route::get('/utilization', [UtilizationController::class, 'index']);
Route::get('/maintenance/plans', [MaintenanceController::class, 'plans']);
Route::get('/maintenance/records', [MaintenanceController::class, 'records']);

Route::post('/telemetry/ingest', [TelemetryIngestController::class, 'store']);
Route::get('/tractors/latest-positions', [TractorMapController::class, 'latestPositions']);
Route::get('/tractors/{id}/route-history', [TractorMapController::class, 'routeHistory']);
