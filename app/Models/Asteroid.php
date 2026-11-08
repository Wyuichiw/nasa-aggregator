<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Asteroid extends Model
{
    protected $fillable = [
        'nasa_reference_id',
        'name',
        'estimated_diameter_min_m',
        'estimated_diameter_max_m',
        'is_potentially_hazardous',
        'calculated_hazard_index',
    ];

    protected function casts(): array
    {
        return [
            'is_potentially_hazardous' => 'boolean',
            'estimated_diameter_min_m' => 'decimal:2',
            'estimated_diameter_max_m' => 'decimal:2',
            'calculated_hazard_index' => 'decimal:2',
        ];
    }

    public function closeApproachEvents(): HasMany
    {
        return $this->hasMany(CloseApproachEvent::class);
    }
}
