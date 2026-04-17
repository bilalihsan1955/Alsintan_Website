<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TelemetryLog extends Model
{
    /** @use HasFactory<\Database\Factories\TelemetryLogFactory> */
    use HasFactory;

    protected $fillable = [
        'tractor_id',
        'ts',
        'lat',
        'lng',
        'speed_kmh',
        'engine_hours',
        'vibration_g',
        'engine_on',
        'gps_on',
        'fuel_lph',
        'rpm',
        'raw_payload',
    ];

    protected function casts(): array
    {
        return [
            'ts' => 'datetime',
            'lat' => 'float',
            'lng' => 'float',
            'speed_kmh' => 'float',
            'engine_hours' => 'float',
            'vibration_g' => 'float',
            'engine_on' => 'boolean',
            'gps_on' => 'boolean',
            'fuel_lph' => 'float',
            'rpm' => 'integer',
        ];
    }

    public function tractor(): BelongsTo
    {
        return $this->belongsTo(Tractor::class);
    }
}
