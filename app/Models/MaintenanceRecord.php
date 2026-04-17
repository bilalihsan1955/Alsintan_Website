<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MaintenanceRecord extends Model
{
    /** @use HasFactory<\Database\Factories\MaintenanceRecordFactory> */
    use HasFactory;

    protected $fillable = [
        'tractor_id',
        'record_date',
        'record_type',
        'description',
        'cost',
        'technician',
        'workshop',
    ];

    protected function casts(): array
    {
        return [
            'record_date' => 'date',
            'cost' => 'float',
        ];
    }

    public function tractor(): BelongsTo
    {
        return $this->belongsTo(Tractor::class);
    }
}
