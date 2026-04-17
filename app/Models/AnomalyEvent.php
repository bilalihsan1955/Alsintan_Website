<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AnomalyEvent extends Model
{
    /** @use HasFactory<\Database\Factories\AnomalyEventFactory> */
    use HasFactory;

    protected $fillable = [
        'detected_at',
        'tractor_id',
        'anomaly_type',
        'severity',
        'description',
        'status',
        'resolved_at',
        'resolved_note',
    ];

    protected function casts(): array
    {
        return [
            'detected_at' => 'datetime',
            'resolved_at' => 'datetime',
        ];
    }

    public function tractor(): BelongsTo
    {
        return $this->belongsTo(Tractor::class);
    }
}
