<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class GeofenceZone extends Model
{
    /** @use HasFactory<\Database\Factories\GeofenceZoneFactory> */
    use HasFactory;

    protected $fillable = [
        'name',
        'zone_type',
        'polygon_json',
        'is_active',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }

    public function events(): HasMany
    {
        return $this->hasMany(GeofenceEvent::class, 'zone_id');
    }

    public function states(): HasMany
    {
        return $this->hasMany(TractorZoneState::class, 'zone_id');
    }

    public function tractors(): BelongsToMany
    {
        return $this->belongsToMany(Tractor::class, 'geofence_zone_tractor', 'zone_id', 'tractor_id')
            ->withTimestamps();
    }
}
