<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CloseApproachEvent extends Model
{
    protected $fillable = [
        'asteroid_id',
        'close_approach_date',
        'close_approach_datetime',
        'relative_velocity_kmh',
        'miss_distance_km',
        'orbiting_body',
    ];

    protected function casts(): array
    {
        return [
            'close_approach_date' => 'date',
            'close_approach_datetime' => 'datetime',
            'relative_velocity_kmh' => 'decimal:2',
            'miss_distance_km' => 'decimal:2',
        ];
    }

    public function asteroid(): BelongsTo
    {
        return $this->belongsTo(Asteroid::class);
    }
}
