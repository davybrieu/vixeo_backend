<?php

namespace App\Services\Social;

use App\Enums\MediaType;
use App\Models\Profile;
use App\Models\ProfileItem;
use App\Models\ProfileList;
use App\Models\ProfileListItem;
use App\Models\TmdbRating;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

/**
 * The social part of a title page (docs/social.md): the followed profiles who watched or rated it
 * and their average, the most liked reviews with a comment, and the lists that have it.
 */
class TitleSocial
{
    public const FRIENDS = 30;

    public function __construct(private Audience $audience, private SocialCards $cards) {}

    /** @return array<string, mixed> */
    public function show(?Profile $viewer, MediaType $type, int $id): array
    {
        $friends = $viewer === null ? [] : $this->friends($viewer, $type, $id);
        $ratings = array_values(array_filter(array_column($friends, 'rating'), fn ($v) => $v !== null));
        $reviews = $this->reviewsQuery($viewer, $type, $id);
        $lists = $this->audience->constrain(ProfileList::query(), $viewer)
            ->whereNull('hidden_at')
            ->whereIn('id', ProfileListItem::query()->select('profile_list_id')->where('media_type', $type->value)->where('tmdb_id', $id))
            ->withCount('likes')->orderByDesc('likes_count')->latest('updated_at')->limit(5)->get();

        return [
            /**
             * Followed profiles who watched or rated it, rated first (best first).
             *
             * @var list<array<string, mixed>>
             */
            'friends' => array_slice($friends, 0, self::FRIENDS),
            'friends_count' => count($friends),
            /** Average of their ratings, null without one. */
            'friends_average' => $ratings === [] ? null : round(array_sum($ratings) / count($ratings), 1),
            /**
             * The 3 most liked reviews with a comment.
             *
             * @var list<array<string, mixed>>
             */
            'reviews' => $this->cards->reviews((clone $reviews)->withCount('likes')->orderByDesc('likes_count')->latest('rated_at')->limit(3)->get(), $viewer),
            'reviews_count' => (clone $reviews)->count(),
            /**
             * Lists that have it, the most liked first.
             *
             * @var list<array<string, mixed>>
             */
            'lists' => $this->cards->lists($lists, $viewer),
        ];
    }

    /**
     * Every review with a comment, the most liked (`popular`) or the most recent first.
     *
     * @return array{data: list<array<string, mixed>>, meta: array{current_page: int, last_page: int}}
     */
    public function reviews(?Profile $viewer, MediaType $type, int $id, string $sort): array
    {
        /** @var LengthAwarePaginator<int, TmdbRating> $page */
        $page = $this->reviewsQuery($viewer, $type, $id)
            ->when($sort === 'popular', fn ($q) => $q->withCount('likes')->orderByDesc('likes_count'))
            ->latest('rated_at')->latest('id')->paginate(20);

        return [
            'data' => $this->cards->reviews($page->getCollection(), $viewer),
            'meta' => ['current_page' => $page->currentPage(), 'last_page' => $page->lastPage()],
        ];
    }

    /** @return Builder<TmdbRating> */
    private function reviewsQuery(?Profile $viewer, MediaType $type, int $id): Builder
    {
        return $this->audience->constrain(TmdbRating::query(), $viewer)
            ->where('type', $type->value)->where('tmdb_id', $id)
            ->whereNotNull('comment')->whereNull('hidden_at');
    }

    /** @return list<array<string, mixed>> */
    private function friends(Profile $viewer, MediaType $type, int $id): array
    {
        $following = DB::table('profile_follows')->where('follower_profile_id', $viewer->id)->whereNotNull('accepted_at')
            ->pluck('followed_profile_id')->map(intval(...))->diff($this->audience->blocked($viewer))->values()->all();
        if ($following === []) {
            return [];
        }
        $watched = ProfileItem::query()->where('source', 'tmdb')->where('item_id', "{$type->value}:{$id}")
            ->where('is_watched', true)->whereIn('profile_id', $following)->pluck('profile_id')->map(intval(...));
        $ratings = TmdbRating::query()->where('type', $type->value)->where('tmdb_id', $id)
            ->whereNull('hidden_at')->whereIn('profile_id', $following)->get()->keyBy('profile_id');
        $profiles = Profile::query()->whereIn('id', $watched->merge($ratings->keys())->unique()->all())
            ->whereNull('suspended_at')->get();

        return $profiles
            ->map(fn (Profile $p) => [
                ...SocialCards::identity($p),
                /** Its rating, 0.5 to 10, null when only watched. */
                'rating' => $ratings->has($p->id) ? (float) $ratings[$p->id]->value : null,
                'review_id' => $ratings->has($p->id) ? (int) $ratings[$p->id]->id : null,
            ])
            ->sortBy([fn ($a, $b) => ($b['rating'] ?? -1) <=> ($a['rating'] ?? -1), fn ($a, $b) => strcmp($a['name'], $b['name'])])
            ->values()->all();
    }
}
