<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Watchlist extends Model
{
    protected $table = 'user_watchlist';

    protected $fillable = [
        'user_id',
        'asteroid_id',
        'notes',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function asteroid(): BelongsTo
    {
        return $this->belongsTo(Asteroid::class);
    }
}
