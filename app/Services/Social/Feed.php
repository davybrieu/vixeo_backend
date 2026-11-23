<?php

namespace App\Services\Social;

use App\Models\DiaryEntry;
use App\Models\Profile;
use App\Models\ProfileItem;
use App\Models\SocialActivity;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * The feed (« Fil d'actualité », docs/social.md), in two tabs, and the « Activité » of a profile:
 *
 * - « Abonnements » ([page]): what the active profile and the profiles it follows did, the most
 *   recent first: reviews, lists, watched titles and titles liked (« J'aime »). Watched and liked
 *   titles are grouped by title in a page (« Léa, Max et 1 autre ont vu Severance », « Léa et Max
 *   aiment Dune »), and left out when the same profile reviewed the same title in the page;
 * - « Pour toi » ([forYou], the default tab of the apps): reviews and lists of the whole network
 *   that the profile can see, and what the profiles it follows watched and liked, ranked by what
 *   they drew (J'aime, comments), the tastes in common with their author and their age;
 * - « Activité » ([activity]): everything one profile did, the most recent first, each with its
 *   date (episodes of the same show watched the same day together).
 *
 * Muted, blocked and suspended profiles are left out, and what the moderation hid.
 */
class Feed
{
    public const PAGE = 40;

    /** « Pour toi »: a page, the days looked at and the most recent publications ranked. */
    public const FOR_YOU_PAGE = 20;

    public const FOR_YOU_DAYS = 30;

    public const FOR_YOU_CANDIDATES = 300;

    /** « Pour toi »: the most recent titles watched or liked by the followed profiles looked at. */
    public const FOR_YOU_FOLLOWED = 200;

    private const RELATIONS = ['profile', 'review', 'diaryEntry', 'list', 'item'];

    public function __construct(private Audience $audience, private SocialCards $cards, private Taste $taste) {}

    /**
     * A page of « Pour toi » (1 first); `next`: the following page, null at the end.
     *
     * Score of a publication: a list counts a little more than a review, a written review more
     * than stars alone, a title watched or liked less (more when several followed profiles did);
     * each J'aime and each comment adds to it, as do the tastes in common with the author and
     * following them; then the score halves about every 2 days. The same author's later
     * publications in the ranking count less, so a page shows several voices.
     *
     * @return array{data: list<array<string, mixed>>, next: int|null}
     */
    public function forYou(Profile $viewer, int $page = 1): array
    {
        $page = max(1, $page);
        $hidden = [...$this->audience->muted($viewer), (int) $viewer->id];
        $since = now()->subDays(self::FOR_YOU_DAYS);
        $following = $this->following($viewer);

        $published = SocialActivity::query()
            ->whereIn('type', [SocialActivity::REVIEW, SocialActivity::LIST])
            ->where('created_at', '>=', $since)
            ->whereNotIn('profile_id', $hidden);
        $published = $this->audience->constrain($published, $viewer)
            ->orderByDesc('id')->limit(self::FOR_YOU_CANDIDATES)->with(self::RELATIONS)->get();
        $followed = SocialActivity::query()
            ->whereIn('type', [SocialActivity::WATCHED, SocialActivity::LIKED])
            ->where('created_at', '>=', $since)
            ->whereIn('profile_id', $following->keys()->diff($hidden)->values()->all());
        $followed = $this->audience->constrain($followed, $viewer)
            ->orderByDesc('id')->limit(self::FOR_YOU_FOLLOWED)->with(self::RELATIONS)->get();
        $activities = $published->concat($followed)->sortByDesc('id')->values();

        $items = $this->items($activities, $viewer);
        $authorOf = fn (array $item): int => (int) ($item['review']['author']['id'] ?? $item['list']['author']['id'] ?? $item['profiles'][0]['id'] ?? 0);
        $compatibility = $this->taste->compatibilities($viewer, collect($items)->map($authorOf)->unique()->values()->all());

        $now = now();
        $ranked = collect($items)->map(function (array $item) use ($compatibility, $following, $now, $authorOf) {
            $author = $authorOf($item);
            $card = $item['review'] ?? $item['list'] ?? null;
            $base = match ($item['kind']) {
                'list' => 1.5 + ($card['items_count'] >= 5 ? 0.5 : 0),
                'review' => 1 + (trim((string) ($card['comment'] ?? '')) === '' ? 0 : 1),
                default => 0.5 + 0.25 * ((int) $item['profiles_count'] - 1),
            };
            $score = $base
                + (int) ($card['likes_count'] ?? 0)
                + 1.5 * (int) ($card['comments_count'] ?? 0)
                + ($compatibility[$author] ?? 0) / 40
                + ($following->has($author) ? 1 : 0);
            $hours = abs(Carbon::parse($item['created_at'])->diffInMinutes($now)) / 60;

            return ['item' => $item, 'author' => $author, 'score' => $score / ((1 + $hours / 48) ** 1.5)];
        })->sortByDesc('score')->values();

        // Several voices: each earlier publication of the same author lowers the next ones.
        $seen = [];
        $ranked = $ranked->map(function (array $row) use (&$seen) {
            $count = $seen[$row['author']] = ($seen[$row['author']] ?? 0) + 1;

            return [...$row, 'score' => $row['score'] * (0.6 ** ($count - 1))];
        })->sortByDesc('score')->values();

        $slice = $ranked->slice(($page - 1) * self::FOR_YOU_PAGE, self::FOR_YOU_PAGE);

        return [
            'data' => $slice->pluck('item')->values()->all(),
            'next' => $ranked->count() > $page * self::FOR_YOU_PAGE ? $page + 1 : null,
        ];
    }

    /**
     * A page of the feed; `before`: the `next` of the previous page.
     *
     * @return array{data: list<array<string, mixed>>, next: int|null}
     */
    public function page(Profile $viewer, ?int $before): array
    {
        $authors = $this->following($viewer)->keys()->push((int) $viewer->id)
            ->diff($this->audience->muted($viewer))->diff($this->audience->blocked($viewer))->values()->all();

        $activities = SocialActivity::query()
            ->whereIn('profile_id', $authors)
            ->whereIn('profile_id', Profile::query()->select('id')->whereNull('suspended_at'))
            ->when($before !== null, fn ($q) => $q->where('id', '<', $before))
            ->orderByDesc('id')->limit(self::PAGE)
            ->with(self::RELATIONS)
            ->get();

        return [
            'data' => $this->items($activities, $viewer),
            'next' => $activities->count() === self::PAGE ? (int) $activities->last()->id : null,
        ];
    }

    /**
     * A page of the « Activité » of [member] (who may be the viewer): each review, list, watched
     * title and title liked with its date, the most recent first; `before`: the `next` of the
     * previous page.
     *
     * @return array{data: list<array<string, mixed>>, next: int|null}
     */
    public function activity(Profile $member, ?Profile $viewer, ?int $before): array
    {
        $activities = SocialActivity::query()
            ->where('profile_id', $member->id)
            ->when($before !== null, fn ($q) => $q->where('id', '<', $before))
            ->orderByDesc('id')->limit(self::PAGE)
            ->with(self::RELATIONS)
            ->get();

        return [
            'data' => $this->items($activities, $viewer, timeline: true),
            'next' => $activities->count() === self::PAGE ? (int) $activities->last()->id : null,
        ];
    }

    /**
     * Profiles [viewer] follows (accepted), as keys.
     *
     * @return Collection<int, int>
     */
    private function following(Profile $viewer): Collection
    {
        return DB::table('profile_follows')->where('follower_profile_id', $viewer->id)
            ->whereNotNull('accepted_at')->pluck('followed_profile_id')->map(intval(...))->flip();
    }

    /**
     * The items of [activities]. Watched and liked titles are grouped by title (by title and day
     * for a [timeline]: one profile); in the feed, those of a profile who reviewed the same title
     * in the page are left out (the review says it).
     *
     * @param  Collection<int, SocialActivity>  $activities
     * @return list<array<string, mixed>>
     */
    private function items(Collection $activities, ?Profile $viewer, bool $timeline = false): array
    {
        $reviews = $activities->where('type', SocialActivity::REVIEW)->pluck('review')->filter(fn ($r) => $r !== null && $r->hidden_at === null);
        $lists = $activities->where('type', SocialActivity::LIST)->pluck('list')->filter(fn ($l) => $l !== null && $l->hidden_at === null && $l->items_count > 0);
        $reviewCards = collect($this->cards->reviews($reviews->values(), $viewer))->keyBy('review_id');
        $listCards = collect($this->cards->lists($lists->values(), $viewer))->keyBy('id');
        $reviewed = $timeline ? collect() : $reviews->map(fn ($r) => "{$r->profile_id}:{$r->titleId()}")->flip();

        $items = [];
        $groups = [];
        foreach ($activities as $activity) {
            $at = (string) $activity->created_at?->toIso8601String();
            if ($activity->type === SocialActivity::REVIEW && $reviewCards->has((int) $activity->tmdb_rating_id)) {
                $items[] = ['kind' => 'review', 'activity_id' => (int) $activity->id, 'created_at' => $at, 'review' => $reviewCards[(int) $activity->tmdb_rating_id]];

                continue;
            }
            if ($activity->type === SocialActivity::LIST && $listCards->has((int) $activity->profile_list_id)) {
                $items[] = ['kind' => 'list', 'activity_id' => (int) $activity->id, 'created_at' => $at, 'list' => $listCards[(int) $activity->profile_list_id]];

                continue;
            }
            $title = match (true) {
                $activity->type === SocialActivity::WATCHED && $activity->diaryEntry !== null => self::watchedTitle($activity->diaryEntry),
                $activity->type === SocialActivity::LIKED && $activity->item?->is_liked === true => self::likedTitle($activity->item),
                default => null,
            };
            if ($title === null || $reviewed->has("{$activity->profile_id}:{$title['item_id']}")) {
                continue;
            }
            $key = $activity->type.':'.$title['item_id'].($timeline ? ':'.$activity->created_at?->toDateString() : '');
            if (! isset($groups[$key])) {
                // The group takes the place of its most recent activity.
                $groups[$key] = count($items);
                $items[] = ['kind' => $activity->type, 'activity_id' => (int) $activity->id, 'created_at' => $at, 'title' => $title, 'entries' => []];
            }
            $items[$groups[$key]]['entries'][] = [$activity->profile, $activity->diaryEntry];
        }

        return array_map(fn (array $item) => isset($item['entries']) ? $this->group($item) : $item, $items);
    }

    /** @return array{type: string, id: int, item_id: string, title: string, year: int|null, poster_url: string|null} */
    private static function watchedTitle(DiaryEntry $entry): array
    {
        return [
            'type' => (string) $entry->media_type,
            'id' => (int) $entry->tmdb_id,
            'item_id' => "{$entry->media_type}:{$entry->tmdb_id}",
            'title' => (string) $entry->title,
            'year' => $entry->year === null ? null : (int) $entry->year,
            'poster_url' => $entry->poster_url === null ? null : (string) $entry->poster_url,
        ];
    }

    /** @return array{type: string, id: int, item_id: string, title: string, year: int|null, poster_url: string|null}|null */
    private static function likedTitle(ProfileItem $item): ?array
    {
        $parsed = TitleSnapshots::parse((string) $item->item_id);
        if ($parsed === null) {
            return null;
        }

        return [
            'type' => $parsed[0]->value,
            'id' => $parsed[1],
            'item_id' => (string) $item->item_id,
            'title' => (string) $item->title,
            'year' => $item->year === null ? null : (int) $item->year,
            'poster_url' => $item->poster_url === null ? null : (string) $item->poster_url,
        ];
    }

    /**
     * A title watched or liked, with who did it.
     *
     * @param  array{kind: string, activity_id: int, created_at: string, title: array<string, mixed>, entries: list<array{Profile, DiaryEntry|null}>}  $item
     * @return array<string, mixed>
     */
    private function group(array $item): array
    {
        $profiles = collect($item['entries'])->map(fn (array $pair) => $pair[0])->unique('id')->values();
        $entries = collect($item['entries'])->map(fn (array $pair) => $pair[1])->filter();

        return [
            'kind' => $item['kind'],
            'activity_id' => $item['activity_id'],
            'created_at' => $item['created_at'],
            'title' => $item['title'],
            /**
             * The first 3 profiles who watched or liked it.
             *
             * @var list<array<string, mixed>>
             */
            'profiles' => $profiles->take(3)->map(fn (Profile $p) => SocialCards::identity($p))->all(),
            'profiles_count' => $profiles->count(),
            /** Episodes among the entries (`watched`: a TV show followed episode by episode). */
            'episodes_count' => $entries->whereNotNull('episode_number')->count(),
            /** Watched again (`watched`). */
            'rewatch' => $entries->isNotEmpty() && $entries->every(fn (DiaryEntry $e) => $e->rewatch),
        ];
    }
}
