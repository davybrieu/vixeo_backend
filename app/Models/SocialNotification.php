<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Something another profile did for this one (the app's « Notifications » page, docs/social.md):
 * followed it, asked to follow it, accepted its request, liked or commented one of its reviews or
 * lists; and the decisions of the moderation about its content. Written only
 * through App\Services\Social\SocialNotifications.
 */
class SocialNotification extends Model
{
    public const UPDATED_AT = null;

    public const FOLLOW = 'follow';

    public const FOLLOW_REQUEST = 'follow_request';

    public const FOLLOW_ACCEPTED = 'follow_accepted';

    public const REVIEW_LIKE = 'review_like';

    public const REVIEW_COMMENT = 'review_comment';

    public const LIST_LIKE = 'list_like';

    public const LIST_COMMENT = 'list_comment';

    /** A decision of the moderation about the profile's content (`data`: what and why). */
    public const MODERATION = 'moderation';

    protected $guarded = [];

    protected function casts(): array
    {
        return ['read_at' => 'datetime', 'data' => 'array'];
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

    /** The review liked or commented. @return BelongsTo<TmdbRating, $this> */
    public function rating(): BelongsTo
    {
        return $this->belongsTo(TmdbRating::class, 'tmdb_rating_id');
    }

    /** The list liked or commented. @return BelongsTo<ProfileList, $this> */
    public function list(): BelongsTo
    {
        return $this->belongsTo(ProfileList::class, 'profile_list_id');
    }

    /** The comment written. @return BelongsTo<SocialComment, $this> */
    public function comment(): BelongsTo
    {
        return $this->belongsTo(SocialComment::class, 'social_comment_id');
    }
}
