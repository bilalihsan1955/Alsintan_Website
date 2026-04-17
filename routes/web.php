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
