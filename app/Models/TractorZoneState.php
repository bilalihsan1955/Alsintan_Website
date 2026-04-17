<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TractorZoneState extends Model
{
    /** @use HasFactory<\Database\Factories\TractorZoneStateFactory> */
    use HasFactory;

    protected $fillable = [
        'tractor_id',
        'zone_id',
        'is_inside',
        'last_transition_at',
    ];

    protected function casts(): array
    {
        return [
            'is_inside' => 'boolean',
            'last_transition_at' => 'datetime',
        ];
    }

    public function tractor(): BelongsTo
    {
        return $this->belongsTo(Tractor::class);
    }

    public function zone(): BelongsTo
    {
        return $this->belongsTo(GeofenceZone::class, 'zone_id');
    }
}
