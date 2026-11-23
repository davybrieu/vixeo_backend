<?php

namespace App\Services\Social;

use App\Models\DiaryEntry;
use App\Models\ProfileItem;
use App\Models\ProfileList;
use App\Models\SocialActivity;
use App\Models\TmdbRating;

/**
 * What the followers of a profile see in their feed (docs/social.md): a review (new, or with a new
 * comment: it comes back at the top), a watched title (a diary entry), a list (once it has a
 * title) and a title liked (« J'aime »). Deleting the review, the entry, the list or the synced
 * item deletes its activity (foreign keys); unliking the title too.
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

    /**
     * Titles that just became liked, or no longer are (App\Services\ProfileSync): a title liked
     * again comes back at the top.
     *
     * @param  list<ProfileItem>  $liked
     * @param  list<ProfileItem>  $unliked
     */
    public function liked(array $liked, array $unliked): void
    {
        $ids = array_map(fn (ProfileItem $item) => (int) $item->id, [...$liked, ...$unliked]);
        if ($ids !== []) {
            SocialActivity::query()->where('type', SocialActivity::LIKED)->whereIn('profile_item_id', $ids)->delete();
        }
        foreach ($liked as $item) {
            SocialActivity::query()->create([
                'profile_id' => $item->profile_id,
                'type' => SocialActivity::LIKED,
                'profile_item_id' => $item->id,
                'created_at' => now(),
            ]);
        }
    }
}
