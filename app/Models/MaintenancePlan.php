<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MaintenancePlan extends Model
{
    /** @use HasFactory<\Database\Factories\MaintenancePlanFactory> */
    use HasFactory;

    protected $fillable = [
        'tractor_id',
        'task_type',
        'interval_hours',
        'current_hours',
        'due_hours',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'interval_hours' => 'float',
            'current_hours' => 'float',
            'due_hours' => 'float',
        ];
    }

    public function tractor(): BelongsTo
    {
        return $this->belongsTo(Tractor::class);
    }
}
