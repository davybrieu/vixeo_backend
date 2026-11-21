<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Something another profile did for this one (the app's « Notifications » page, docs/social.md):
 * followed it, asked to follow it, accepted its request, liked one of its reviews. Written only
 * through App\Services\Social\SocialNotifications.
 */
class SocialNotification extends Model
{
    public const UPDATED_AT = null;

    public const FOLLOW = 'follow';

    public const FOLLOW_REQUEST = 'follow_request';

    public const FOLLOW_ACCEPTED = 'follow_accepted';

    public const REVIEW_LIKE = 'review_like';

    protected $guarded = [];

    protected function casts(): array
    {
        return ['read_at' => 'datetime'];
    }

    /** Who receives it. @return BelongsTo<Profile, $this> */
    public function profile(): BelongsTo
    {
        return $this->belongsTo(Profile::class);
    }

    /** Who did it. @return BelongsTo<Profile, $this> */
    public function actor(): BelongsTo
    {
        return $this->belongsTo(Profile::class, 'actor_profile_id');
    }

    /** The review liked. @return BelongsTo<TmdbRating, $this> */
    public function rating(): BelongsTo
    {
        return $this->belongsTo(TmdbRating::class, 'tmdb_rating_id');
    }
}
