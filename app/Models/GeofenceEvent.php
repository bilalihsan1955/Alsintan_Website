<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class GeofenceEvent extends Model
{
    /** @use HasFactory<\Database\Factories\GeofenceEventFactory> */
    use HasFactory;

    protected $fillable = [
        'tractor_id',
        'zone_id',
        'event_type',
        'event_ts',
        'lat',
        'lng',
        'message',
        'resolved_at',
    ];

    protected function casts(): array
    {
        return [
            'event_ts' => 'datetime',
            'lat' => 'float',
            'lng' => 'float',
            'resolved_at' => 'datetime',
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
