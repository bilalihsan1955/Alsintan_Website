<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Tractor extends Model
{
    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = [
        'id',
        'name',
        'plate_number',
        'device_uid',
        'status',
        'api_token_hash',
        'api_token_last_used_at',
    ];

    protected $hidden = [
        'api_token_hash',
    ];

    protected function casts(): array
    {
        return [
            'api_token_last_used_at' => 'datetime',
        ];
    }

    protected $appends = ['device_id'];

    public function getDeviceIdAttribute(): string
    {
        return (string) ($this->attributes['id'] ?? '');
    }

    public function telemetryLogs(): HasMany
    {
        return $this->hasMany(TelemetryLog::class);
    }

    public function latestPosition(): HasOne
    {
        return $this->hasOne(TractorPositionLatest::class);
    }

    public function geofenceEvents(): HasMany
    {
        return $this->hasMany(GeofenceEvent::class);
    }

    public function anomalyEvents(): HasMany
    {
        return $this->hasMany(AnomalyEvent::class);
    }

    public function maintenancePlans(): HasMany
    {
        return $this->hasMany(MaintenancePlan::class);
    }

    public function maintenanceRecords(): HasMany
    {
        return $this->hasMany(MaintenanceRecord::class);
    }

    public function utilizationDaily(): HasMany
    {
        return $this->hasMany(UtilizationDaily::class);
    }

    public function geofenceZones(): BelongsToMany
    {
        return $this->belongsToMany(GeofenceZone::class, 'geofence_zone_tractor', 'tractor_id', 'zone_id')
            ->withTimestamps();
    }
}
