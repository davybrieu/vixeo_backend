<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A profile likes another profile's review (« J'aime », docs/social.md). Written only through
 * App\Services\Social\ReviewLikes.
 */
class ReviewLike extends Model
{
    public const UPDATED_AT = null;

    protected $guarded = [];

    /** @return BelongsTo<Profile, $this> */
    public function profile(): BelongsTo
    {
        return $this->belongsTo(Profile::class);
    }

    /** @return BelongsTo<TmdbRating, $this> */
    public function rating(): BelongsTo
    {
        return $this->belongsTo(TmdbRating::class, 'tmdb_rating_id');
    }
}
