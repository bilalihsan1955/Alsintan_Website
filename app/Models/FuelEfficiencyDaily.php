<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class FuelEfficiencyDaily extends Model
{
    protected $table = 'fuel_efficiency_daily';

    protected $fillable = [
        'tractor_id',
        'date',
        'fuel_used_l',
        'efficiency_value',
    ];

    protected function casts(): array
    {
        return [
            'date' => 'date',
            'fuel_used_l' => 'float',
            'efficiency_value' => 'float',
        ];
    }
}
