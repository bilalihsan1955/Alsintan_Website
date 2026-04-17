<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class GroupPerformanceScore extends Model
{
    protected $fillable = [
        'group_id',
        'period',
        'activity_score',
        'maintenance_score',
        'total_score',
        'grade',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'activity_score' => 'float',
            'maintenance_score' => 'float',
            'total_score' => 'float',
        ];
    }

    public function group(): BelongsTo
    {
        return $this->belongsTo(Group::class);
    }
}
