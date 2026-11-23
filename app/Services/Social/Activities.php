<?php

namespace App\Services\Social;

use App\Models\DiaryEntry;
use App\Models\ProfileList;
use App\Models\SocialActivity;
use App\Models\TmdbRating;

/**
 * What the followers of a profile see in their feed (docs/social.md): a review (new, or with a new
 * comment: it comes back at the top), a watched title (a diary entry) and a list (once it has a
 * title). Deleting the review, the entry or the list deletes its activity (foreign keys).
 */
class Activities
{
    public function review(TmdbRating $review): void
    {
        SocialActivity::query()->where('tmdb_rating_id', $review->id)->delete();
        SocialActivity::query()->create([
            'profile_id' => $review->profile_id,
            'type' => SocialActivity::REVIEW,
            'tmdb_rating_id' => $review->id,
            'created_at' => now(),
        ]);
    }

    public function watched(DiaryEntry $entry): void
    {
        SocialActivity::query()->firstOrCreate(
            ['diary_entry_id' => $entry->id],
            ['profile_id' => $entry->profile_id, 'type' => SocialActivity::WATCHED, 'created_at' => now()],
        );
    }

    public function list(ProfileList $list): void
    {
        SocialActivity::query()->firstOrCreate(
            ['profile_list_id' => $list->id],
            ['profile_id' => $list->profile_id, 'type' => SocialActivity::LIST, 'created_at' => now()],
        );
    }
}
