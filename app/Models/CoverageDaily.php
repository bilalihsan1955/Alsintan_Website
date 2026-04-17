<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CoverageDaily extends Model
{
    protected $table = 'coverage_daily';

    protected $fillable = [
        'tractor_id',
        'date',
        'hectare_covered',
    ];

    protected function casts(): array
    {
        return [
            'date' => 'date',
            'hectare_covered' => 'float',
        ];
    }
}
