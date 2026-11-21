<?php

namespace App\Services\Social;

use App\Models\Profile;
use App\Models\ReviewLike;
use App\Models\SocialNotification;
use App\Models\TmdbRating;

/**
 * « J'aime » on the reviews of other profiles (docs/social.md); the author is notified.
 */
class ReviewLikes
{
    public function __construct(private SocialNotifications $notifications) {}

    public function like(Profile $profile, TmdbRating $review): void
    {
        $like = ReviewLike::query()->firstOrCreate(
            ['profile_id' => $profile->id, 'tmdb_rating_id' => $review->id],
            ['created_at' => now()],
        );
        if ($like->wasRecentlyCreated) {
            $this->notifications->notify($review->profile, $profile, SocialNotification::REVIEW_LIKE, $review);
        }
    }

    public function unlike(Profile $profile, TmdbRating $review): void
    {
        ReviewLike::query()->where('profile_id', $profile->id)->where('tmdb_rating_id', $review->id)->delete();
        $this->notifications->forget($review->profile, $profile, [SocialNotification::REVIEW_LIKE], $review);
    }
}
