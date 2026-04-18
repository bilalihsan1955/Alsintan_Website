<?php

use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\StrategicWebController;
use App\Http\Controllers\TractorManagementController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Auth (publik)
|--------------------------------------------------------------------------
*/
Route::get('/login', [LoginController::class, 'showLogin'])->name('login');
Route::post('/login', [LoginController::class, 'login'])->name('login.submit');
Route::post('/logout', [LoginController::class, 'logout'])->middleware('auth')->name('logout');

/*
|--------------------------------------------------------------------------
| Halaman butuh login (semua role)
|--------------------------------------------------------------------------
*/
Route::middleware('auth')->group(function () {
    Route::get('/', fn () => redirect()->route('dashboard'));

    Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');
    Route::get('/dashboard/data', [DashboardController::class, 'data'])->name('dashboard.data');

    /* Profil user — semua role boleh kelola profil sendiri. */
    Route::get('/profile', [ProfileController::class, 'show'])->name('profile');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::post('/profile/avatar', [ProfileController::class, 'uploadAvatar'])->name('profile.avatar.upload');
    Route::delete('/profile/avatar', [ProfileController::class, 'deleteAvatar'])->name('profile.avatar.delete');
    Route::patch('/profile/password', [ProfileController::class, 'updatePassword'])->name('profile.password');
    Route::put('/profile/preferences', [ProfileController::class, 'updatePreferences'])->name('profile.preferences');

    /* Strategic: halaman & read-only resource boleh diakses semua user login. */
    Route::get('/strategic', [StrategicWebController::class, 'index'])->name('strategic');
    Route::get('/strategic/tractors/{id}/route-history', [StrategicWebController::class, 'routeHistory'])->name('strategic.route-history');

    /* Halaman kelola perangkat boleh dilihat, tapi aksi update dibatasi admin (lihat group admin). */
    Route::get('/perangkat', [TractorManagementController::class, 'index'])->name('tractors.manage');
});

/*
|--------------------------------------------------------------------------
| Admin only — CRUD data strategis & master
|--------------------------------------------------------------------------
*/
Route::middleware(['auth', 'admin'])->group(function () {
    /* Kelola master alat (edit). */
    Route::patch('/perangkat/{tractor}', [TractorManagementController::class, 'update'])->name('tractors.update');

    /* Zona kerja (geofence). */
    Route::post('/strategic/zones', [StrategicWebController::class, 'storeZone'])->name('strategic.zones.store');
    Route::put('/strategic/zones/{zone}', [StrategicWebController::class, 'updateZone'])->name('strategic.zones.update');
    Route::patch('/strategic/zones/{zone}/toggle', [StrategicWebController::class, 'toggleZone'])->name('strategic.zones.toggle');
    Route::delete('/strategic/zones/{zone}', [StrategicWebController::class, 'deleteZone'])->name('strategic.zones.delete');

    /* Rapor kinerja kelompok. */
    Route::post('/strategic/group-scores', [StrategicWebController::class, 'storeGroupScore'])->name('strategic.group-scores.store');
    Route::put('/strategic/group-scores/{groupPerformanceScore}', [StrategicWebController::class, 'updateGroupScore'])->name('strategic.group-scores.update');
    Route::delete('/strategic/group-scores/{groupPerformanceScore}', [StrategicWebController::class, 'deleteGroupScore'])->name('strategic.group-scores.delete');

    /* Anomaly. */
    Route::post('/strategic/anomalies', [StrategicWebController::class, 'storeAnomaly'])->name('strategic.anomalies.store');
    Route::put('/strategic/anomalies/{anomalyEvent}', [StrategicWebController::class, 'updateAnomaly'])->name('strategic.anomalies.update');
    Route::delete('/strategic/anomalies/{anomalyEvent}', [StrategicWebController::class, 'deleteAnomaly'])->name('strategic.anomalies.delete');

    /* Utilisasi harian. */
    Route::post('/strategic/utilization-daily', [StrategicWebController::class, 'storeUtilizationDaily'])->name('strategic.utilization-daily.store');
    Route::put('/strategic/utilization-daily/{utilizationDaily}', [StrategicWebController::class, 'updateUtilizationDaily'])->name('strategic.utilization-daily.update');
    Route::delete('/strategic/utilization-daily/{utilizationDaily}', [StrategicWebController::class, 'deleteUtilizationDaily'])->name('strategic.utilization-daily.delete');

    /* Rencana maintenance. */
    Route::post('/strategic/maintenance-plans', [StrategicWebController::class, 'storeMaintenancePlan'])->name('strategic.maintenance-plans.store');
    Route::put('/strategic/maintenance-plans/{maintenancePlan}', [StrategicWebController::class, 'updateMaintenancePlan'])->name('strategic.maintenance-plans.update');
    Route::delete('/strategic/maintenance-plans/{maintenancePlan}', [StrategicWebController::class, 'deleteMaintenancePlan'])->name('strategic.maintenance-plans.delete');

    /* Riwayat maintenance. */
    Route::post('/strategic/maintenance-records', [StrategicWebController::class, 'storeMaintenanceRecord'])->name('strategic.maintenance-records.store');
    Route::put('/strategic/maintenance-records/{maintenanceRecord}', [StrategicWebController::class, 'updateMaintenanceRecord'])->name('strategic.maintenance-records.update');
    Route::delete('/strategic/maintenance-records/{maintenanceRecord}', [StrategicWebController::class, 'deleteMaintenanceRecord'])->name('strategic.maintenance-records.delete');
});
