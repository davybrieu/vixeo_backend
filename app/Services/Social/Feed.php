<?php

namespace App\Services\Social;

use App\Models\DiaryEntry;
use App\Models\Profile;
use App\Models\SocialActivity;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * The feed (« Fil », docs/social.md): what the active profile and the profiles it follows did, the
 * most recent first: reviews, lists and watched titles. Watched titles are grouped by title in a
 * page (« Léa, Max et 1 autre ont vu Severance », « Léa a vu 3 épisodes de Severance »), and left
 * out when the same profile reviewed the same title in the page. Muted, blocked and suspended
 * profiles are left out, and what the moderation hid.
 */
class Feed
{
    public const PAGE = 40;

    public function __construct(private Audience $audience, private SocialCards $cards) {}

    /**
     * A page of the feed; `before`: the `next` of the previous page.
     *
     * @return array{data: list<array<string, mixed>>, next: int|null}
     */
    public function page(Profile $viewer, ?int $before): array
    {
        $authors = DB::table('profile_follows')->where('follower_profile_id', $viewer->id)
            ->whereNotNull('accepted_at')->pluck('followed_profile_id')->map(intval(...))->push((int) $viewer->id)
            ->diff($this->audience->muted($viewer))->diff($this->audience->blocked($viewer))->values()->all();

        $activities = SocialActivity::query()
            ->whereIn('profile_id', $authors)
            ->whereIn('profile_id', Profile::query()->select('id')->whereNull('suspended_at'))
            ->when($before !== null, fn ($q) => $q->where('id', '<', $before))
            ->orderByDesc('id')->limit(self::PAGE)
            ->with(['profile', 'review', 'diaryEntry', 'list'])
            ->get();

        return [
            'data' => $this->items($activities, $viewer),
            'next' => $activities->count() === self::PAGE ? (int) $activities->last()->id : null,
        ];
    }

    /**
     * @param  Collection<int, SocialActivity>  $activities
     * @return list<array<string, mixed>>
     */
    private function items(Collection $activities, Profile $viewer): array
    {
        $reviews = $activities->where('type', SocialActivity::REVIEW)->pluck('review')->filter(fn ($r) => $r !== null && $r->hidden_at === null);
        $lists = $activities->where('type', SocialActivity::LIST)->pluck('list')->filter(fn ($l) => $l !== null && $l->hidden_at === null && $l->items_count > 0);
        $reviewCards = collect($this->cards->reviews($reviews->values(), $viewer))->keyBy('review_id');
        $listCards = collect($this->cards->lists($lists->values(), $viewer))->keyBy('id');
        $reviewed = $reviews->map(fn ($r) => "{$r->profile_id}:{$r->titleId()}")->flip();

        $items = [];
        $watched = [];
        foreach ($activities as $activity) {
            $at = (string) $activity->created_at?->toIso8601String();
            if ($activity->type === SocialActivity::REVIEW && $reviewCards->has((int) $activity->tmdb_rating_id)) {
                $items[] = ['kind' => 'review', 'activity_id' => (int) $activity->id, 'created_at' => $at, 'review' => $reviewCards[(int) $activity->tmdb_rating_id]];
            } elseif ($activity->type === SocialActivity::LIST && $listCards->has((int) $activity->profile_list_id)) {
                $items[] = ['kind' => 'list', 'activity_id' => (int) $activity->id, 'created_at' => $at, 'list' => $listCards[(int) $activity->profile_list_id]];
            } elseif ($activity->type === SocialActivity::WATCHED && $activity->diaryEntry !== null) {
                $entry = $activity->diaryEntry;
                $title = "{$entry->media_type}:{$entry->tmdb_id}";
                if ($reviewed->has("{$activity->profile_id}:{$title}")) {
                    continue;
                }
                if (! isset($watched[$title])) {
                    // The group takes the place of its most recent entry.
                    $watched[$title] = count($items);
                    $items[] = ['kind' => 'watched', 'activity_id' => (int) $activity->id, 'created_at' => $at, 'entries' => []];
                }
                $items[$watched[$title]]['entries'][] = [$activity->profile, $entry];
            }
        }

        return array_map(fn (array $item) => $item['kind'] === 'watched' ? $this->watched($item) : $item, $items);
    }

    /**
     * @param  array{kind: string, activity_id: int, created_at: string, entries: list<array{Profile, DiaryEntry}>}  $item
     * @return array<string, mixed>
     */
    private function watched(array $item): array
    {
        /** @var DiaryEntry $first */
        $first = $item['entries'][0][1];
        $profiles = collect($item['entries'])->map(fn (array $pair) => $pair[0])->unique('id')->values();
        $entries = collect($item['entries'])->map(fn (array $pair) => $pair[1]);

        return [
            'kind' => 'watched',
            'activity_id' => $item['activity_id'],
            'created_at' => $item['created_at'],
            'title' => [
                'type' => (string) $first->media_type,
                'id' => (int) $first->tmdb_id,
                'item_id' => "{$first->media_type}:{$first->tmdb_id}",
                'title' => (string) $first->title,
                'year' => $first->year,
                'poster_url' => $first->poster_url,
            ],
            /**
             * The first 3 profiles who watched it.
             *
             * @var list<array<string, mixed>>
             */
            'profiles' => $profiles->take(3)->map(fn (Profile $p) => SocialCards::identity($p))->all(),
            'profiles_count' => $profiles->count(),
            /** Episodes among the entries (a TV show followed episode by episode). */
            'episodes_count' => $entries->whereNotNull('episode_number')->count(),
            'rewatch' => $entries->every(fn (DiaryEntry $e) => $e->rewatch),
        ];
    }
}
