<?php

namespace App\Models;

use Database\Factories\ProfileStreamFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Stream preferences of a profile for one content: which streams are favorites (❤) and which one
 * last worked. Written only through App\Services\Playback\StreamPreferences. See docs/lecture.md.
 */
#[Fillable(['source', 'item_id', 'stream_id', 'stream_url', 'stream_type', 'extractor', 'quality', 'language'])]
class ProfileStream extends Model
{
    /** @use HasFactory<ProfileStreamFactory> */
    use HasFactory;

    protected $attributes = [
        'is_favorite' => false,
    ];

    protected function casts(): array
    {
        return [
            'is_favorite' => 'boolean',
            'favorited_at' => 'datetime',
            'last_worked_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<Profile, $this> */
    public function profile(): BelongsTo
    {
        return $this->belongsTo(Profile::class);
    }
}
