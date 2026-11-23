<?php

namespace App\Models;

use App\Enums\RatedType;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A review of the profile (« Mes avis », App\Services\TmdbRatings, docs/avis.md): its rating and
 * its comment, kept by Vixeo only. The other columns copy what « Mes avis » shows of the title.
 */
#[Fillable([
    'target', 'type', 'tmdb_id', 'season_number', 'episode_number', 'value', 'comment', 'contains_spoilers',
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
            'contains_spoilers' => 'boolean',
            'hidden_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<Profile, $this> */
    public function profile(): BelongsTo
    {
        return $this->belongsTo(Profile::class);
    }

    /** Likes of this review by other profiles (« J'aime », docs/social.md). @return HasMany<ReviewLike, $this> */
    public function likes(): HasMany
    {
        return $this->hasMany(ReviewLike::class);
    }

    /** Comments of other profiles (docs/social.md). @return HasMany<SocialComment, $this> */
    public function comments(): HasMany
    {
        return $this->hasMany(SocialComment::class);
    }

    /** `movie:550` or `tv:1399` (the show of an episode). */
    public function titleId(): string
    {
        return ($this->type->value === 'movie' ? 'movie' : 'tv').':'.$this->tmdb_id;
    }
}
