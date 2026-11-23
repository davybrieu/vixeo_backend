<?php

namespace App\Services\Social;

use App\Models\ListLike;
use App\Models\Profile;
use App\Models\ProfileList;
use App\Models\ProfileListItem;
use App\Models\SocialNotification;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * The lists of a profile (docs/social.md): a title, a description and up to 100 titles in the
 * chosen order, with likes. A title is kept with what the list shows of it (TitleSnapshots), so
 * showing a list never asks TMDb. A list goes to the feed once it has a title.
 */
class Lists
{
    public function __construct(
        private TitleSnapshots $snapshots,
        private Activities $activities,
        private SocialNotifications $notifications,
    ) {}

    /** @param  list<string>  $itemIds  `movie:550`, in order */
    public function create(Profile $profile, string $title, ?string $description, array $itemIds = []): ProfileList
    {
        $list = DB::transaction(function () use ($profile, $title, $description, $itemIds) {
            $list = ProfileList::query()->create([
                'profile_id' => $profile->id,
                'title' => $title,
                'description' => $description,
            ]);
            $this->replaceItems($list, $itemIds);

            return $list;
        });
        $this->published($list);

        return $list;
    }

    /**
     * Changes the title, the description and, when given, the titles (in their new order).
     *
     * @param  list<string>|null  $itemIds
     */
    public function update(ProfileList $list, string $title, ?string $description, ?array $itemIds): ProfileList
    {
        DB::transaction(function () use ($list, $title, $description, $itemIds) {
            $list->fill(['title' => $title, 'description' => $description]);
            if ($itemIds !== null) {
                $this->replaceItems($list, $itemIds);
            }
            $list->touch();
        });
        $this->published($list);

        return $list;
    }

    public function delete(ProfileList $list): void
    {
        $list->delete();
    }

    /** Adds a title at the end; 422 when the list is full or TMDb doesn't know it. */
    public function add(ProfileList $list, string $itemId): ProfileList
    {
        $current = $list->items()->get()->map(fn (ProfileListItem $item) => $item->itemId())->all();
        if (! in_array($itemId, $current, true)) {
            abort_if(count($current) >= ProfileList::MAX_ITEMS, 422, __('A list has 100 titles at most.'));
            DB::transaction(function () use ($list, $current, $itemId) {
                $this->replaceItems($list, [...$current, $itemId]);
                $list->touch();
            });
            $this->published($list);
        }

        return $list;
    }

    public function remove(ProfileList $list, string $itemId): ProfileList
    {
        $current = $list->items()->get()->map(fn (ProfileListItem $item) => $item->itemId())->all();
        if (in_array($itemId, $current, true)) {
            DB::transaction(function () use ($list, $current, $itemId) {
                $this->replaceItems($list, array_values(array_diff($current, [$itemId])));
                $list->touch();
            });
        }

        return $list;
    }

    public function like(Profile $profile, ProfileList $list): void
    {
        $like = ListLike::query()->firstOrCreate(
            ['profile_id' => $profile->id, 'profile_list_id' => $list->id],
            ['created_at' => now()],
        );
        if ($like->wasRecentlyCreated) {
            $this->notifications->notify($list->profile, $profile, SocialNotification::LIST_LIKE, list: $list);
        }
    }

    public function unlike(Profile $profile, ProfileList $list): void
    {
        ListLike::query()->where('profile_id', $profile->id)->where('profile_list_id', $list->id)->delete();
        $this->notifications->forget($list->profile, $profile, [SocialNotification::LIST_LIKE], list: $list);
    }

    /**
     * The lists of [profile], the most recently changed first; with `contains` when [itemId] is
     * given (« Ajouter à une liste »).
     *
     * @return Collection<int, ProfileList>
     */
    public function of(Profile $profile): Collection
    {
        return ProfileList::query()->where('profile_id', $profile->id)->whereNull('hidden_at')
            ->latest('updated_at')->latest('id')->limit(200)->get();
    }

    /**
     * Ids of the lists of [profile] that have [itemId].
     *
     * @return list<int>
     */
    public function containing(Profile $profile, string $itemId): array
    {
        $title = TitleSnapshots::parse($itemId);
        if ($title === null) {
            return [];
        }

        return ProfileListItem::query()->whereIn('profile_list_id', ProfileList::query()->select('id')->where('profile_id', $profile->id))
            ->where('media_type', $title[0]->value)->where('tmdb_id', $title[1])
            ->pluck('profile_list_id')->map(intval(...))->all();
    }

    /**
     * Sets the titles of [list] in this order, keeping what is known of those already in it.
     *
     * @param  list<string>  $itemIds
     */
    private function replaceItems(ProfileList $list, array $itemIds): void
    {
        $itemIds = array_values(array_unique(array_filter($itemIds, fn (string $id) => TitleSnapshots::parse($id) !== null)));
        abort_if(count($itemIds) > ProfileList::MAX_ITEMS, 422, __('A list has 100 titles at most.'));

        $known = $list->exists
            ? ProfileListItem::query()->where('profile_list_id', $list->id)->get()->keyBy(fn (ProfileListItem $i) => $i->itemId())
            : collect();
        $missing = array_values(array_filter($itemIds, fn (string $id) => ! $known->has($id)));
        $fetched = $this->snapshots->of($missing);
        abort_if($missing !== [] && $fetched === [] && $known->isEmpty() && $itemIds !== [], 503, __('The catalog is not available right now.'));

        $rows = [];
        foreach ($itemIds as $itemId) {
            $snapshot = $known->has($itemId) ? $known[$itemId]->only(['title', 'year', 'poster_path', 'backdrop_path']) : ($fetched[$itemId] ?? null);
            if ($snapshot === null) {
                continue;
            }
            [$type, $id] = TitleSnapshots::parse($itemId);
            $rows[] = [
                'profile_list_id' => $list->id,
                'position' => count($rows),
                'media_type' => $type->value,
                'tmdb_id' => $id,
                'title' => mb_substr((string) $snapshot['title'], 0, 255),
                'year' => $snapshot['year'] ?? null,
                'poster_path' => $snapshot['poster_path'] ?? null,
                'backdrop_path' => $snapshot['backdrop_path'] ?? null,
                'created_at' => $known->has($itemId) ? $known[$itemId]->created_at : now(),
            ];
        }

        ProfileListItem::query()->where('profile_list_id', $list->id)->delete();
        foreach (array_chunk($rows, 100) as $chunk) {
            ProfileListItem::query()->insert($chunk);
        }
        $list->items_count = count($rows);
        $list->save();
        $list->unsetRelation('items');
    }

    /** Its first title: the list goes to the feed. */
    private function published(ProfileList $list): void
    {
        if ($list->items_count > 0 && $list->published_at === null) {
            $list->forceFill(['published_at' => now()])->save();
            if (! $list->profile->is_kid) {
                $this->activities->list($list);
            }
        }
    }
}
