<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TractorPositionLatest extends Model
{
    /** @use HasFactory<\Database\Factories\TractorPositionLatestFactory> */
    use HasFactory;

    protected $table = 'tractor_positions_latest';
    protected $primaryKey = 'tractor_id';
    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = [
        'tractor_id',
        'ts',
        'lat',
        'lng',
        'speed_kmh',
        'status',
        'engine_on',
        'gps_on',
        'vibration_g',
        'engine_hours',
    ];

    protected function casts(): array
    {
        return [
            'ts' => 'datetime',
            'lat' => 'float',
            'lng' => 'float',
            'speed_kmh' => 'float',
            'engine_on' => 'boolean',
            'gps_on' => 'boolean',
            'vibration_g' => 'float',
            'engine_hours' => 'float',
        ];
    }

    public function tractor(): BelongsTo
    {
        return $this->belongsTo(Tractor::class);
    }
}
