<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Group extends Model
{
    protected $fillable = [
        'name',
        'village',
    ];

    public function scores(): HasMany
    {
        return $this->hasMany(GroupPerformanceScore::class);
    }
}
