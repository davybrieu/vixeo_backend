<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A comment on a review or on a list (docs/social.md), maybe with spoilers. Written only through
 * App\Services\Social\Comments.
 */
class SocialComment extends Model
{
    public const MAX_BODY = 1000;

    protected $guarded = [];

    protected function casts(): array
    {
        return ['contains_spoilers' => 'boolean', 'hidden_at' => 'datetime'];
    }

    /** @return BelongsTo<Profile, $this> */
    public function profile(): BelongsTo
    {
        return $this->belongsTo(Profile::class);
    }

    /** @return BelongsTo<TmdbRating, $this> */
    public function review(): BelongsTo
    {
        return $this->belongsTo(TmdbRating::class, 'tmdb_rating_id');
    }

    /** @return BelongsTo<ProfileList, $this> */
    public function list(): BelongsTo
    {
        return $this->belongsTo(ProfileList::class, 'profile_list_id');
    }
}
