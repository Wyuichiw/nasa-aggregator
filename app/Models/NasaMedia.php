<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class NasaMedia extends Model
{
    protected $table = 'nasa_media';

    protected $fillable = [
        'media_date', 'title', 'explanation', 'media_type',
        'source_url', 'local_storage_path', 'copyright',
    ];

    protected function casts(): array
    {
        return ['media_date' => 'date'];
    }

    public function tags(): BelongsToMany
    {
        return $this->belongsToMany(Tag::class, 'nasa_media_tag');
    }
}
