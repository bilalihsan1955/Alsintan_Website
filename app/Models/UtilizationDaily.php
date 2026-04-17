<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UtilizationDaily extends Model
{
    /** @use HasFactory<\Database\Factories\UtilizationDailyFactory> */
    use HasFactory;

    protected $table = 'utilization_daily';

    protected $fillable = [
        'tractor_id',
        'date',
        'active_days_rolling',
        'estimated_hours',
        'utilization_pct',
        'utilization_status',
    ];

    protected function casts(): array
    {
        return [
            'date' => 'date',
            'active_days_rolling' => 'integer',
            'estimated_hours' => 'float',
            'utilization_pct' => 'float',
        ];
    }

    public function tractor(): BelongsTo
    {
        return $this->belongsTo(Tractor::class);
    }
}
