<?php

namespace App\Models;

use App\Enums\RatedType;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A review of the profile (« Mes avis », App\Services\TmdbRatings, docs/avis.md): its rating and
 * its comment, kept by Vixeo only. The other columns copy what « Mes avis » shows of the title.
 */
#[Fillable([
    'target', 'type', 'tmdb_id', 'season_number', 'episode_number', 'value', 'comment',
    'title', 'episode_name', 'date', 'poster_path', 'backdrop_path', 'still_path', 'vote_average', 'rated_at',
])]
class TmdbRating extends Model
{
    /** Longest comment. */
    public const MAX_COMMENT = 2000;

    protected function casts(): array
    {
        return [
            'type' => RatedType::class,
            'tmdb_id' => 'integer',
            'season_number' => 'integer',
            'episode_number' => 'integer',
            'value' => 'float',
            'vote_average' => 'float',
            'rated_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<Profile, $this> */
    public function profile(): BelongsTo
    {
        return $this->belongsTo(Profile::class);
    }
}
