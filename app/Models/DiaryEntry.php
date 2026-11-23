<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A movie or an episode watched by a profile, on a day (« Journal », docs/social.md): written when
 * the title becomes watched (synchronisation) or a second viewing is logged. Written only through
 * App\Services\Social\Diary.
 */
class DiaryEntry extends Model
{
    public const UPDATED_AT = null;

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'tmdb_id' => 'integer',
            'season_number' => 'integer',
            'episode_number' => 'integer',
            'year' => 'integer',
            'watched_at' => 'datetime',
            'rewatch' => 'boolean',
            'runtime' => 'integer',
            'genres' => 'array',
        ];
    }

    /** @return BelongsTo<Profile, $this> */
    public function profile(): BelongsTo
    {
        return $this->belongsTo(Profile::class);
    }

    public function isEpisode(): bool
    {
        return $this->episode_number !== null;
    }
}
