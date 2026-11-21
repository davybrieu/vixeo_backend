<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Controllers\SiteController;
use App\Http\Middleware\ResolveProfile;
use App\Http\Resources\V1\Tmdb\RatedItemResource;
use App\Models\Profile;
use App\Models\ProfileItem;
use App\Models\ReviewLike;
use App\Models\TmdbRating;
use App\Services\Social\Follows;
use App\Services\Social\ReviewLikes;
use App\Support\SocialLinks;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class SocialProfileController extends Controller
{
    public function __construct(private Follows $follows, private ReviewLikes $likes) {}

    public function search(Request $request): JsonResponse
    {
        $this->assertAdultViewer($request);
        $query = ltrim(trim((string) $request->query('query', '')), '@');
        abort_if(mb_strlen($query) < 2, 422, 'Saisissez au moins deux caractères.');

        $viewer = ResolveProfile::profile($request);

        $profiles = Profile::query()
            ->where('is_kid', false)
            ->whereNotNull('username')
            // Never the active profile itself: it is reached from the navigation (« Profil »).
            ->when($viewer instanceof Profile, fn ($q) => $q->whereKeyNot($viewer->id))
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
    public function show(Request $request, string $username): JsonResponse
    {
        $this->assertAdultViewer($request);
        $profile = $this->publicProfile($username, withCounts: true);
        $viewer = ResolveProfile::profile($request);
        $status = $this->follows->status($viewer, $profile);

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
            'can_view' => $this->follows->canView($viewer, $profile),
            'is_self' => $viewer instanceof Profile && $viewer->id === $profile->id,
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
        abort_if($author->is_kid || $author->username === null, 404);
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
        $items = TmdbRating::query()->where('profile_id', $profile->id)->withCount('likes')->latest('rated_at')->paginate(20);
        $viewer = ResolveProfile::profile($request);
        $liked = $viewer === null ? [] : ReviewLike::query()->where('profile_id', $viewer->id)
            ->whereIn('tmdb_rating_id', $items->getCollection()->modelKeys())->pluck('tmdb_rating_id')->all();

        return response()->json(['data' => collect($items->items())->map(fn (TmdbRating $rating) => [
            ...(new RatedItemResource($rating))->toArray($request),
            'poster_path' => $rating->poster_path,
            /** The active profile likes this review. */
            'liked' => in_array($rating->id, $liked, true),
        ]), 'meta' => [
            'current_page' => $items->currentPage(), 'last_page' => $items->lastPage(),
        ]]);
    }

    public function favoritesList(Request $request, string $username): JsonResponse
    {
        $profile = $this->visibleProfile($request, $username);
        $items = ProfileItem::query()->where('profile_id', $profile->id)
            ->where('is_favorite', true)->latest('favorited_at')->paginate(30);

        return response()->json(['data' => collect($items->items())->map(fn (ProfileItem $item) => [
            'type' => $item->type, 'item_id' => $item->item_id, 'title' => $item->title,
            'year' => $item->year, 'poster_url' => $item->poster_url,
        ]), 'meta' => [
            'current_page' => $items->currentPage(), 'last_page' => $items->lastPage(),
        ]]);
    }

    /**
     * A member's « Favoris » ❤ (movies and TV shows), the most recently added first.
     */
    public function liked(Request $request, string $username): JsonResponse
    {
        $profile = $this->visibleProfile($request, $username);
        $items = ProfileItem::query()->where('profile_id', $profile->id)
            ->where('is_liked', true)->whereIn('type', ['movie', 'tv'])
            ->latest('liked_at')->latest('id')->paginate(30);

        return response()->json(['data' => collect($items->items())->map(fn (ProfileItem $item) => [
            'type' => $item->type, 'item_id' => $item->item_id, 'title' => $item->title,
            'year' => $item->year, 'poster_url' => $item->poster_url,
        ]), 'meta' => [
            'current_page' => $items->currentPage(), 'last_page' => $items->lastPage(),
        ]]);
    }

    /**
     * The people (actors, directors…) a member follows, the most recently followed first.
     *
     * `id` is the TMDb person id, `profile_url` their photo (null without one).
     */
    public function people(Request $request, string $username): JsonResponse
    {
        $profile = $this->visibleProfile($request, $username);
        $items = ProfileItem::query()->where('profile_id', $profile->id)
            ->where('is_followed', true)->where('type', 'person')
            ->latest('followed_at')->latest('id')->paginate(30);

        return response()->json(['data' => collect($items->items())->map(fn (ProfileItem $item) => [
            'id' => (int) str_replace('person:', '', $item->item_id),
            'item_id' => $item->item_id,
            'name' => $item->title,
            'profile_url' => $item->poster_url,
        ]), 'meta' => [
            'current_page' => $items->currentPage(), 'last_page' => $items->lastPage(),
        ]]);
    }

    private function connections(Request $request, string $username, string $direction): JsonResponse
    {
        $profile = $this->visibleProfile($request, $username);
        $relation = $direction === 'followers' ? $profile->followers() : $profile->following();
        $items = $relation->where('is_kid', false)->paginate(30);

        return response()->json(['data' => $items->getCollection()->map(fn (Profile $p) => $this->identity($p)),
            'meta' => ['current_page' => $items->currentPage(), 'last_page' => $items->lastPage()]]);
    }

    /** A member whose lists and reviews the active profile may see (403: a private account it doesn't follow). */
    private function visibleProfile(Request $request, string $username): Profile
    {
        $this->assertAdultViewer($request);
        $profile = $this->publicProfile($username);
        abort_unless($this->follows->canView(ResolveProfile::profile($request), $profile), 403, __('This account is private.'));

        return $profile;
    }

    private function assertAdultViewer(Request $request): void
    {
        $viewer = ResolveProfile::profile($request);
        abort_if($viewer?->is_kid, 403, 'Les profils enfants ne peuvent pas utiliser les fonctions sociales.');
    }

    private function activeProfile(Request $request): Profile
    {
        $profile = ResolveProfile::profile($request);
        abort_unless($profile instanceof Profile, 422, 'Sélectionnez un profil.');
        abort_if($profile->is_kid, 403, 'Les profils enfants ne peuvent pas utiliser les fonctions sociales.');

        return $profile;
    }

    /**
     * An adult profile with a username. Its counters (one subquery each) only for its page: the
     * lists of a profile only need its id.
     */
    private function publicProfile(string $username, bool $withCounts = false): Profile
    {
        return Profile::query()->where('username', mb_strtolower(ltrim($username, '@')))
            ->where('is_kid', false)
            ->when($withCounts, fn ($query) => $query->withCount([
                'followers',
                'following',
                'tmdbRatings as reviews_count',
                'items as watched_count' => fn ($query) => $query->where('is_watched', true)->whereIn('type', ['movie', 'tv']),
                'items as list_count' => fn ($query) => $query->where('is_favorite', true)->whereIn('type', ['movie', 'tv']),
                'items as liked_count' => fn ($query) => $query->where('is_liked', true)->whereIn('type', ['movie', 'tv']),
                'items as people_count' => fn ($query) => $query->where('is_followed', true)->where('type', 'person'),
            ]))
            ->firstOrFail();
    }

    /** A profile in a list (search, followers): who it is, without counters. */
    private function identity(Profile $profile): array
    {
        return [
            'id' => $profile->id, 'username' => $profile->username, 'name' => $profile->name,
            'avatar' => $profile->avatar, 'bio' => $profile->bio,
            /** Private account: its lists and reviews are seen by its accepted followers only. */
            'is_private' => (bool) $profile->is_private,
        ];
    }

    /** A profile's page: who it is, its counters (loaded by publicProfile) and its public address. */
    private function summary(Profile $profile): array
    {
        return [
            ...$this->identity($profile),
            /** The page of the profile on the site, shared from the app (« Partager »). */
            'share_url' => SiteController::memberUrl((string) $profile->username),
            'followers_count' => (int) ($profile->followers_count ?? 0),
            'following_count' => (int) ($profile->following_count ?? 0),
            'reviews_count' => (int) ($profile->reviews_count ?? 0),
            'watched_count' => (int) ($profile->watched_count ?? 0),
            'list_count' => (int) ($profile->list_count ?? 0),
            /** « Favoris » ❤ (movies and TV shows). */
            'liked_count' => (int) ($profile->liked_count ?? 0),
            /** Followed people (actors, directors…). */
            'people_count' => (int) ($profile->people_count ?? 0),
        ];
    }
}
