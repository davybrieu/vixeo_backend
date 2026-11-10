<?php

namespace App\Services;

use App\Models\Profile;
use App\Models\ProfileItem;
use Carbon\CarbonImmutable;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Per-profile sync of "Ma liste" (favorite), "Favoris" (liked), playback progress and watched state.
 *
 * - Every write gives the item a new revision (per-profile counter). Clients pull
 *   "everything after revision N" and keep the last revision as their cursor.
 * - Conflicts are resolved per group (favorite / liked / progress / watched) with the
 *   client-side timestamp of the change: the most recent change wins, so a favorite
 *   set on the TV and a progress saved on the phone never overwrite each other.
 */
class ProfileSync
{
    public const MAX_PUSH = 500;

    public const MAX_PULL = 1000;

    /** Client clocks ahead by more than this are brought back to the server time. */
    private const MAX_CLOCK_SKEW_SECONDS = 300;

    private const METADATA = ['type', 'title', 'year', 'poster_url', 'backdrop_url', 'parent_item_id', 'season_number', 'episode_number'];

    /**
     * Apply changes (validated by SyncPushRequest) and return the merged state of each item.
     *
     * @param  list<array<string, mixed>>  $changes
     * @return array{items: Collection<int, ProfileItem>, revision: int}
     */
    public function push(Profile $profile, array $changes): array
    {
        return DB::transaction(function () use ($profile, $changes) {
            // Serializes pushes of the same profile: revisions stay strictly increasing.
            $locked = Profile::whereKey($profile->id)->lockForUpdate()->firstOrFail();
            $revision = $locked->sync_revision;

            $items = $locked->items()
                ->whereIn('item_id', array_unique(array_column($changes, 'item_id')))
                ->get()
                ->keyBy(fn (ProfileItem $item) => self::key($item->source, $item->item_id));

            $touched = [];
            foreach ($changes as $change) {
                $key = self::key($change['source'], $change['item_id']);
                $item = $items->get($key) ?? $locked->items()->make(Arr::only($change, ['source', 'item_id']));

                $this->apply($item, $change);

                if (! $item->exists || $item->isDirty()) {
                    $item->revision = ++$revision;
                    $item->save();
                }

                $items->put($key, $item);
                $touched[$key] = $item;
            }

            if ($revision !== $locked->sync_revision) {
                $locked->sync_revision = $revision;
                Profile::withoutTimestamps(fn () => $locked->save());
            }

            return ['items' => collect(array_values($touched)), 'revision' => $revision];
        });
    }

    /**
     * Items changed after $cursor, oldest change first.
     *
     * @return array{items: Collection<int, ProfileItem>, cursor: int, has_more: bool}
     */
    public function pull(Profile $profile, int $cursor, int $limit): array
    {
        // Read the revision first: every item at or below it is already committed.
        $revision = (int) Profile::whereKey($profile->id)->value('sync_revision');

        $query = $profile->items()
            ->where('revision', '>', $cursor)
            ->where('revision', '<=', $revision)
            ->orderBy('revision');

        if ($cursor === 0) {
            // First sync of a device: reset items carry nothing worth downloading.
            $query->notEmpty();
        }

        $items = $query->limit($limit + 1)->get();
        $hasMore = $items->count() > $limit;
        $items = $items->take($limit)->values();

        return [
            'items' => $items,
            'cursor' => (int) ($hasMore ? $items->last()->revision : max($cursor, $revision)),
            'has_more' => $hasMore,
        ];
    }

    /** @param  array<string, mixed>  $change */
    private function apply(ProfileItem $item, array $change): void
    {
        $item->fill(Arr::only($change, self::METADATA));

        if (isset($change['favorite'])) {
            $at = $this->time($change['favorite']['at']);

            if ($this->isNewer($at, $item->favorite_changed_at)) {
                $favorite = (bool) $change['favorite']['value'];
                // Re-favoriting keeps the original date (sort "ajoutés récemment").
                $item->favorited_at = $favorite ? ($item->is_favorite ? ($item->favorited_at ?? $at) : $at) : null;
                $item->is_favorite = $favorite;
                $item->favorite_changed_at = $at;
            }
        }

        if (isset($change['liked'])) {
            $at = $this->time($change['liked']['at']);

            if ($this->isNewer($at, $item->liked_changed_at)) {
                $liked = (bool) $change['liked']['value'];
                // Liking again keeps the original date (most recent first in "Favoris").
                $item->liked_at = $liked ? ($item->is_liked ? ($item->liked_at ?? $at) : $at) : null;
                $item->is_liked = $liked;
                $item->liked_changed_at = $at;
            }
        }

        if (isset($change['progress'])) {
            $progress = $change['progress'];
            $at = $this->time($progress['at']);

            if ($this->isNewer($at, $item->progress_changed_at)) {
                $item->position_ms = (int) $progress['position_ms'];
                if (array_key_exists('duration_ms', $progress)) {
                    $item->duration_ms = $progress['duration_ms'] !== null ? (int) $progress['duration_ms'] : null;
                }
                $item->last_engaged_at = $progress['continue_watching'] ? $at : null;
                $item->progress_changed_at = $at;
            }
        }

        if (isset($change['watched'])) {
            $at = $this->time($change['watched']['at']);

            if ($this->isNewer($at, $item->watched_changed_at)) {
                $watched = (bool) $change['watched']['value'];
                $item->is_watched = $watched;
                $item->watched_at = $watched ? $at : null;
                $item->watched_changed_at = $at;
            }
        }
    }

    /** Ties go to the incoming change: replaying the same push is harmless. */
    private function isNewer(CarbonImmutable $at, mixed $current): bool
    {
        return $current === null || $at->greaterThanOrEqualTo($current);
    }

    private function time(string $value): CarbonImmutable
    {
        // Stored to the second: truncate so replays compare equal and leave the item untouched.
        $at = CarbonImmutable::parse($value)->utc()->startOfSecond();
        $now = CarbonImmutable::now()->utc()->startOfSecond();

        return $at->greaterThan($now->addSeconds(self::MAX_CLOCK_SKEW_SECONDS)) ? $now : $at;
    }

    private static function key(string $source, string $itemId): string
    {
        return $source."\0".$itemId;
    }
}
