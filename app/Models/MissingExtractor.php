<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MissingExtractor extends Model
{
    protected $fillable = [
        'url',
        'host',
        'provider',
        'name',
        'type',
        'tmdb_id',
        'title',
        'season',
        'episode',
        'user_id',
        'occurrences',
        'first_seen_at',
        'last_seen_at',
    ];

    protected function casts(): array
    {
        return [
            'first_seen_at' => 'datetime',
            'last_seen_at' => 'datetime',
            'occurrences' => 'integer',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
