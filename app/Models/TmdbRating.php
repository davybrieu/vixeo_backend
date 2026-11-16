<?php

namespace App\Models;

use App\Enums\RatedType;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A review of the profile (« Mes avis », App\Services\TmdbRatings): its rating and its comment.
 * Vixeo owns it; when a TMDb account is linked, the rating is sent there (`account_id`: the TMDb
 * account it was last sent to, null when not yet). The comment stays in Vixeo (TMDb's API takes
 * no written review).
 */
#[Fillable([
    'account_id', 'target', 'type', 'tmdb_id', 'season_number', 'episode_number', 'value', 'comment',
    'title', 'episode_name', 'date', 'poster_path', 'backdrop_path', 'still_path', 'vote_average', 'rated_at',
])]
class TmdbRating extends Model
{
    /** Longest comment. */
    public const MAX_COMMENT = 2000;

    protected function casts(): array
    {
        return [
            'account_id' => 'integer',
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
