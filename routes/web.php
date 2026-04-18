<?php

use App\Http\Controllers\DashboardController;
use App\Http\Controllers\StrategicWebController;
use App\Http\Controllers\TractorManagementController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return redirect()->route('dashboard');
});

Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');
Route::get('/dashboard/data', [DashboardController::class, 'data'])->name('dashboard.data');

Route::view('/profile', 'profile.index')->name('profile');

Route::get('/perangkat', [TractorManagementController::class, 'index'])->name('tractors.manage');
Route::patch('/perangkat/{tractor}', [TractorManagementController::class, 'update'])->name('tractors.update');

Route::get('/strategic', [StrategicWebController::class, 'index'])->name('strategic');
Route::get('/strategic/tractors/{id}/route-history', [StrategicWebController::class, 'routeHistory'])->name('strategic.route-history');
Route::post('/strategic/zones', [StrategicWebController::class, 'storeZone'])->name('strategic.zones.store');
Route::put('/strategic/zones/{zone}', [StrategicWebController::class, 'updateZone'])->name('strategic.zones.update');
Route::patch('/strategic/zones/{zone}/toggle', [StrategicWebController::class, 'toggleZone'])->name('strategic.zones.toggle');
Route::delete('/strategic/zones/{zone}', [StrategicWebController::class, 'deleteZone'])->name('strategic.zones.delete');

Route::post('/strategic/group-scores', [StrategicWebController::class, 'storeGroupScore'])->name('strategic.group-scores.store');
Route::put('/strategic/group-scores/{groupPerformanceScore}', [StrategicWebController::class, 'updateGroupScore'])->name('strategic.group-scores.update');
Route::delete('/strategic/group-scores/{groupPerformanceScore}', [StrategicWebController::class, 'deleteGroupScore'])->name('strategic.group-scores.delete');

Route::post('/strategic/anomalies', [StrategicWebController::class, 'storeAnomaly'])->name('strategic.anomalies.store');
Route::put('/strategic/anomalies/{anomalyEvent}', [StrategicWebController::class, 'updateAnomaly'])->name('strategic.anomalies.update');
Route::delete('/strategic/anomalies/{anomalyEvent}', [StrategicWebController::class, 'deleteAnomaly'])->name('strategic.anomalies.delete');

Route::post('/strategic/utilization-daily', [StrategicWebController::class, 'storeUtilizationDaily'])->name('strategic.utilization-daily.store');
Route::put('/strategic/utilization-daily/{utilizationDaily}', [StrategicWebController::class, 'updateUtilizationDaily'])->name('strategic.utilization-daily.update');
Route::delete('/strategic/utilization-daily/{utilizationDaily}', [StrategicWebController::class, 'deleteUtilizationDaily'])->name('strategic.utilization-daily.delete');

Route::post('/strategic/maintenance-plans', [StrategicWebController::class, 'storeMaintenancePlan'])->name('strategic.maintenance-plans.store');
Route::put('/strategic/maintenance-plans/{maintenancePlan}', [StrategicWebController::class, 'updateMaintenancePlan'])->name('strategic.maintenance-plans.update');
Route::delete('/strategic/maintenance-plans/{maintenancePlan}', [StrategicWebController::class, 'deleteMaintenancePlan'])->name('strategic.maintenance-plans.delete');

Route::post('/strategic/maintenance-records', [StrategicWebController::class, 'storeMaintenanceRecord'])->name('strategic.maintenance-records.store');
Route::put('/strategic/maintenance-records/{maintenanceRecord}', [StrategicWebController::class, 'updateMaintenanceRecord'])->name('strategic.maintenance-records.update');
Route::delete('/strategic/maintenance-records/{maintenanceRecord}', [StrategicWebController::class, 'deleteMaintenanceRecord'])->name('strategic.maintenance-records.delete');
