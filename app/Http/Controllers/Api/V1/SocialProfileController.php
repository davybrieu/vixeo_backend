<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Middleware\ResolveProfile;
use App\Http\Resources\V1\Tmdb\RatedItemResource;
use App\Models\DiaryEntry;
use App\Models\MessageBlock;
use App\Models\Profile;
use App\Models\ProfileItem;
use App\Models\ProfileList;
use App\Models\ProfileMute;
use App\Models\ReviewLike;
use App\Models\TmdbRating;
use App\Services\Social\Audience;
use App\Services\Social\Follows;
use App\Services\Social\ReviewLikes;
use App\Services\Social\SocialCards;
use App\Services\Social\Taste;
use App\Support\SocialLinks;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class SocialProfileController extends Controller
{
    /**
     * Items of a page of a member's lists (À voir, J'aime, Vus, people, reviews, followers,
     * following, lists): the apps load the next one as the page scrolls (developer's request,
     * 24/11/2026: 20 at a time).
     */
    public const PER_PAGE = 20;

    public function __construct(
        private Follows $follows,
        private ReviewLikes $likes,
        private Audience $audience,
        private SocialCards $cards,
    ) {}

    public function search(Request $request): JsonResponse
    {
        $query = ltrim(trim((string) $request->query('query', '')), '@');
        abort_if(mb_strlen($query) < 2, 422, 'Saisissez au moins deux caractères.');

        $viewer = ResolveProfile::profile($request);

        $profiles = Profile::query()
            ->whereNotNull('username')
            ->whereNull('suspended_at')
            // Never the active profile itself: it is reached from the navigation (« Profil »).
            ->when($viewer instanceof Profile, fn ($q) => $q->whereKeyNot($viewer->id))
            ->whereNotIn('id', $this->audience->blocked($viewer))
            ->where('username', 'like', '%'.addcslashes($query, '%_\\').'%')
            ->orderByRaw('username = ? desc', [mb_strtolower(ltrim($query, '@'))])
            ->limit(30)
            ->get();

        return response()->json(['data' => $profiles->map(fn (Profile $profile) => $this->identity($profile))]);
    }

    /**
     * A member's page.
     *
     * Name, @username, avatar, biography, social networks and counters, also for a private account
     * (`is_private`). Its lists, reviews, followers and followings need `can_view`: public, the
     * active profile itself or an accepted follower (403 otherwise). `follow_status`: `none`,
     * `requested` (waiting for a private account to accept) or `following`.
     */
    public function show(Request $request, string $username, Taste $taste): JsonResponse
    {
        $this->assertAdultViewer($request);
        $profile = $this->publicProfile($username, withCounts: true);
        $viewer = ResolveProfile::profile($request);
        $self = $viewer instanceof Profile && $viewer->id === $profile->id;
        // Blocked by the member: as if it didn't exist.
        abort_if($viewer !== null && ! $self && MessageBlock::query()
            ->where('blocker_profile_id', $profile->id)->where('blocked_profile_id', $viewer->id)->exists(), 404);
        $blocked = $viewer !== null && ! $self && MessageBlock::query()
            ->where('blocker_profile_id', $viewer->id)->where('blocked_profile_id', $profile->id)->exists();
        $status = $this->follows->status($viewer, $profile);
        $canView = ! $blocked && $this->follows->canView($viewer, $profile);

        return response()->json(['data' => [
            ...$this->summary($profile),
            'bio' => $profile->bio,
            /**
             * Accounts on social networks (Instagram, X, TikTok, YouTube, Letterboxd, Threads, Bluesky, website).
             *
             * @var list<array{network: string, label: string, handle: string, url: string}>
             */
            'social_links' => SocialLinks::present($profile->social_links),
            'is_following' => $status === Follows::FOLLOWING,
            /** `none`, `requested` (a private account has not accepted yet) or `following`. */
            'follow_status' => $status,
            /** The active profile sees the lists and reviews (public account, itself, or an accepted follower). */
            'can_view' => $canView,
            'is_self' => $self,
            /** The active profile blocked it (« Débloquer »). */
            'is_blocked' => $blocked,
            /** Hidden from the active profile's feed (« Masquer »). */
            'is_muted' => $viewer !== null && ! $self && $this->audience->isMuted($viewer, $profile),
            /**
             * Tastes in common with the active profile: `score` 0 to 100 (null below 5 titles
             * rated by both), `common` titles rated by both. Null for one's own page.
             *
             * @var array{score: int|null, common: int}|null
             */
            'compatibility' => $viewer instanceof Profile && ! $self && $canView  ? $taste->compatibility($viewer, $profile) : null,
        ]]);
    }

    /**
     * Follow a member.
     *
     * A public account at once (`follow_status`: `following`); a private one receives a request
     * it accepts or declines (`requested`). The member is notified.
     */
    public function follow(Request $request, string $username): JsonResponse
    {
        $follower = $this->activeProfile($request);
        $target = $this->publicProfile($username);
        abort_if($follower->id === $target->id, 422, 'Vous ne pouvez pas vous suivre vous-même.');
        abort_if($this->audience->isBlocked($follower, $target), 403, __('You cannot follow this account.'));
        $status = $this->follows->follow($follower, $target);

        return response()->json(['following' => $status === Follows::FOLLOWING, 'follow_status' => $status]);
    }

    /**
     * Unfollow a member, or withdraw a follow request.
     */
    public function unfollow(Request $request, string $username): JsonResponse
    {
        $follower = $this->activeProfile($request);
        $target = $this->publicProfile($username);
        $this->follows->unfollow($follower, $target);

        return response()->json(['following' => false, 'follow_status' => Follows::NONE]);
    }

    /**
     * Follow requests waiting for the active profile (a private account), the most recent first.
     */
    public function requests(Request $request): JsonResponse
    {
        $profile = $this->activeProfile($request);

        return response()->json(['data' => array_map(fn (array $row) => [
            ...$this->identity($row['profile']),
            'requested_at' => $row['requested_at'],
        ], $this->follows->requests($profile))]);
    }

    /**
     * Accept a follow request: the member now follows the active profile (and is notified).
     */
    public function acceptRequest(Request $request, string $username): JsonResponse
    {
        $profile = $this->activeProfile($request);
        abort_unless($this->follows->accept($profile, $this->publicProfile($username)), 404);

        return response()->json(['accepted' => true]);
    }

    /**
     * Decline a follow request.
     */
    public function declineRequest(Request $request, string $username): Response
    {
        $this->follows->decline($this->activeProfile($request), $this->publicProfile($username));

        return response()->noContent();
    }

    /**
     * Like a member's review.
     *
     * `review` is its `review_id`. Its author is notified; not one's own review (422), nor a
     * private account's one without following it (403).
     */
    public function likeReview(Request $request, int $review): JsonResponse
    {
        [$profile, $rating] = $this->likable($request, $review);
        $this->likes->like($profile, $rating);

        return response()->json(['liked' => true, 'likes_count' => $rating->likes()->count()]);
    }

    /**
     * Take back a like.
     */
    public function unlikeReview(Request $request, int $review): JsonResponse
    {
        [$profile, $rating] = $this->likable($request, $review);
        $this->likes->unlike($profile, $rating);

        return response()->json(['liked' => false, 'likes_count' => $rating->likes()->count()]);
    }

    /** @return array{Profile, TmdbRating} */
    private function likable(Request $request, int $review): array
    {
        $profile = $this->activeProfile($request);
        $rating = TmdbRating::query()->with('profile')->findOrFail($review);
        $author = $rating->profile;
        abort_if(! $author->isSocial() || $rating->hidden_at !== null || $this->audience->isBlocked($profile, $author), 404);
        abort_if($author->id === $profile->id, 422, __('You cannot like your own review.'));
        abort_unless($this->follows->canView($profile, $author), 403, __('This account is private.'));

        return [$profile, $rating];
    }

    public function followers(Request $request, string $username): JsonResponse
    {
        return $this->connections($request, $username, 'followers');
    }

    public function following(Request $request, string $username): JsonResponse
    {
        return $this->connections($request, $username, 'following');
    }

    /**
     * A member's reviews, the most recently rated first: the fields of « Mes avis »
     * (`RatedItemResource` : images, episode, year…), plus `poster_path` for older apps.
     */
    public function reviews(Request $request, string $username): JsonResponse
    {
        $profile = $this->visibleProfile($request, $username);
        $items = TmdbRating::query()->where('profile_id', $profile->id)->whereNull('hidden_at')
            ->withCount(['likes', 'comments' => fn ($q) => $q->whereNull('hidden_at')])->latest('rated_at')->paginate(self::PER_PAGE);
        $viewer = ResolveProfile::profile($request);
        $liked = $viewer === null ? [] : ReviewLike::query()->where('profile_id', $viewer->id)
            ->whereIn('tmdb_rating_id', $items->getCollection()->modelKeys())->pluck('tmdb_rating_id')->all();

        return response()->json(['data' => collect($items->items())->map(fn (TmdbRating $rating) => [
            ...(new RatedItemResource($rating))->toArray($request),
            'poster_path' => $rating->poster_path,
            /** The active profile likes this review. */
            'liked' => in_array($rating->id, $liked, true),
            'comments_count' => (int) ($rating->comments_count ?? 0),
        ]), 'meta' => [
            'current_page' => $items->currentPage(), 'last_page' => $items->lastPage(),
        ]]);
    }

    /**
     * A member's « À voir » (its `favorite` group), the most recently added first.
     */
    public function favoritesList(Request $request, string $username): JsonResponse
    {
        $profile = $this->visibleProfile($request, $username);
        $items = ProfileItem::query()->where('profile_id', $profile->id)
            ->where('is_favorite', true)->latest('favorited_at')->paginate(self::PER_PAGE);

        return response()->json(['data' => collect($items->items())->map(fn (ProfileItem $item) => [
            'type' => $item->type, 'item_id' => $item->item_id, 'title' => $item->title,
            'year' => $item->year, 'poster_url' => $item->poster_url,
        ]), 'meta' => [
            'current_page' => $items->currentPage(), 'last_page' => $items->lastPage(),
        ]]);
    }

    /**
     * A member's « J'aime » ❤ (movies and TV shows), the most recently added first.
     */
    public function liked(Request $request, string $username): JsonResponse
    {
        $profile = $this->visibleProfile($request, $username);
        $items = ProfileItem::query()->where('profile_id', $profile->id)
            ->where('is_liked', true)->whereIn('type', ['movie', 'tv'])
            ->latest('liked_at')->latest('id')->paginate(self::PER_PAGE);

        return response()->json(['data' => collect($items->items())->map(fn (ProfileItem $item) => [
            'type' => $item->type, 'item_id' => $item->item_id, 'title' => $item->title,
            'year' => $item->year, 'poster_url' => $item->poster_url,
        ]), 'meta' => [
            'current_page' => $items->currentPage(), 'last_page' => $items->lastPage(),
        ]]);
    }

    /**
     * The movies and TV shows a member watched (« Vus »), the most recently watched first, with
     * the date (`watched_at`, ISO 8601).
     */
    public function watched(Request $request, string $username): JsonResponse
    {
        $profile = $this->visibleProfile($request, $username);
        $items = ProfileItem::query()->where('profile_id', $profile->id)
            ->where('is_watched', true)->whereIn('type', ['movie', 'tv'])
            ->latest('watched_at')->latest('id')->paginate(self::PER_PAGE);

        return response()->json(['data' => collect($items->items())->map(fn (ProfileItem $item) => [
            'type' => $item->type, 'item_id' => $item->item_id, 'title' => $item->title,
            'year' => $item->year, 'poster_url' => $item->poster_url,
            'watched_at' => $item->watched_at?->toIso8601String(),
        ]), 'meta' => [
            'current_page' => $items->currentPage(), 'last_page' => $items->lastPage(),
        ]]);
    }

    public function lists(Request $request, string $username): JsonResponse
    {
        $profile = $this->visibleProfile($request, $username);
        $viewer = ResolveProfile::profile($request);
        $page = ProfileList::query()->where('profile_id', $profile->id)->whereNull('hidden_at')
            ->when($viewer?->id !== $profile->id, fn ($q) => $q->where('items_count', '>', 0))
            ->latest('updated_at')->latest('id')->paginate(self::PER_PAGE);

        return response()->json([
            'data' => $this->cards->lists($page->getCollection(), $viewer),
            'meta' => ['current_page' => $page->currentPage(), 'last_page' => $page->lastPage()],
        ]);
    }

    /**
     * Block a member.
     *
     * Everywhere: neither follows the other any more, neither sees, likes, comments nor writes to
     * the other (the messaging too).
     */
    public function block(Request $request, string $username): JsonResponse
    {
        $this->audience->block($this->activeProfile($request), $this->publicProfile($username));

        return response()->json(['blocked' => true]);
    }

    /**
     * Unblock a member.
     */
    public function unblock(Request $request, string $username): JsonResponse
    {
        $this->audience->unblock($this->activeProfile($request), $this->publicProfile($username));

        return response()->json(['blocked' => false]);
    }

    /**
     * Mute a member.
     *
     * Hidden from the active profile's feed, still followed (it is not told).
     */
    public function mute(Request $request, string $username): JsonResponse
    {
        $this->audience->mute($this->activeProfile($request), $this->publicProfile($username));

        return response()->json(['muted' => true]);
    }

    /**
     * Unmute a member.
     */
    public function unmute(Request $request, string $username): JsonResponse
    {
        $this->audience->unmute($this->activeProfile($request), $this->publicProfile($username));

        return response()->json(['muted' => false]);
    }

    /**
     * Blocked and muted members.
     *
     * The settings' « Comptes bloqués et masqués ».
     */
    public function hidden(Request $request): JsonResponse
    {
        $profile = $this->activeProfile($request);
        $blocked = MessageBlock::query()->where('blocker_profile_id', $profile->id)->pluck('blocked_profile_id');
        $muted = ProfileMute::query()->where('profile_id', $profile->id)->pluck('muted_profile_id');
        $profiles = Profile::query()->whereIn('id', $blocked->merge($muted)->unique()->all())->orderBy('name')->get();

        return response()->json([
            'blocked' => $profiles->whereIn('id', $blocked->all())->map(fn (Profile $p) => $this->identity($p))->values(),
            'muted' => $profiles->whereIn('id', $muted->all())->map(fn (Profile $p) => $this->identity($p))->values(),
        ]);
    }

    private function activeProfile(Request $request): Profile
    {
        $profile = ResolveProfile::profile($request);
        abort_unless($profile instanceof Profile, 422, 'Profil de compte introuvable.');

        return $profile;
    }

    /**
     * An adult profile with a username. Its counters (one subquery each) only for its page: the
     * lists of a profile only need its id.
     */
    private function publicProfile(string $username, bool $withCounts = false): Profile
    {
        return Profile::query()->where('username', mb_strtolower(ltrim($username, '@')))
            ->whereNull('suspended_at')
            ->when($withCounts, fn ($query) => $query->withCount([
                'lists as lists_count' => fn ($query) => $query->whereNull('hidden_at')->where('items_count', '>', 0),
                'followers',
                'following',
                'tmdbRatings as reviews_count',
                'items as watched_count' => fn ($query) => $query->where('is_watched', true)->whereIn('type', ['movie', 'tv']),
                'items as list_count' => fn ($query) => $query->where('is_favorite', true)->whereIn('type', ['movie', 'tv']),
                'items as liked_count' => fn ($query) => $query->where('is_liked', true)->whereIn('type', ['movie', 'tv']),
                'items as episodes_count' => fn ($query) => $query->where('is_watched', true)->where('type', 'episode'),
            ])->addSelect([
                // Time watched: the movies and episodes of its diary whose length TMDb knows (a
                // whole TV show marked watched has no length), as in the year in review.
                'watch_minutes' => DiaryEntry::query()->selectRaw('coalesce(sum(runtime), 0)')
                    ->whereColumn('diary_entries.profile_id', 'profiles.id')
                    ->where(fn ($query) => $query->where('media_type', 'movie')->orWhereNotNull('episode_number')),
            ]))
            ->firstOrFail();
    }

    /** A profile in a list (search, followers): who it is, without counters. */
    private function identity(Profile $profile): array
    {
        return SocialCards::identity($profile);
    }

    /** A profile's page: who it is, its counters (loaded by publicProfile) and its public address. */
    private function summary(Profile $profile): array
    {
        return [
            ...$this->identity($profile),
            /** The page of the profile on the site, shared from the app (« Partager »). */
            'share_url' => config('vixeo.app_url').'/membre/'.rawurlencode((string) $profile->username),
            /** Cover id (GET /covers): the banner of its page. */
            'cover' => $profile->cover,
            /** Its own cover, shown instead of `cover`; null = `cover`. */
            'cover_url' => $profile->coverUrl(),
            'followers_count' => (int) ($profile->followers_count ?? 0),
            'following_count' => (int) ($profile->following_count ?? 0),
            'reviews_count' => (int) ($profile->reviews_count ?? 0),
            'watched_count' => (int) ($profile->watched_count ?? 0),
            /** Episodes watched. */
            'episodes_count' => (int) ($profile->episodes_count ?? 0),
            /** Minutes watched: movies and episodes of its diary whose length TMDb knows. */
            'watch_minutes' => (int) ($profile->watch_minutes ?? 0),
            /** Titles « À voir ». */
            'list_count' => (int) ($profile->list_count ?? 0),
            /** « J'aime » ❤ (movies and TV shows). */
            'liked_count' => (int) ($profile->liked_count ?? 0),
            /** Lists with a title (« À voir » apart). */
            'lists_count' => (int) ($profile->lists_count ?? 0),
        ];
    }
}
