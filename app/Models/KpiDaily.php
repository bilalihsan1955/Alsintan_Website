<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class KpiDaily extends Model
{
    protected $table = 'kpi_daily';
    protected $primaryKey = 'date';
    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = [
        'date',
        'total_tractors',
        'total_data_logs',
        'total_fuel_l',
        'avg_score',
        'open_anomalies',
        'repair_cost_total',
    ];

    protected function casts(): array
    {
        return [
            'date' => 'date',
            'total_tractors' => 'integer',
            'total_data_logs' => 'integer',
            'total_fuel_l' => 'float',
            'avg_score' => 'float',
            'open_anomalies' => 'integer',
            'repair_cost_total' => 'float',
        ];
    }
}
