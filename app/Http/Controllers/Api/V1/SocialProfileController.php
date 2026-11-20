<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Middleware\ResolveProfile;
use App\Http\Resources\V1\Tmdb\RatedItemResource;
use App\Models\Profile;
use App\Models\ProfileItem;
use App\Models\TmdbRating;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SocialProfileController extends Controller
{
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
            ->withCount(['followers', 'following'])
            ->orderByRaw('username = ? desc', [mb_strtolower(ltrim($query, '@'))])
            ->limit(30)
            ->get();

        return response()->json(['data' => $profiles->map(fn (Profile $profile) => $this->summary($profile))]);
    }

    public function show(Request $request, string $username): JsonResponse
    {
        $this->assertAdultViewer($request);
        $profile = $this->publicProfile($username);
        $viewer = ResolveProfile::profile($request);

        return response()->json(['data' => [
            ...$this->summary($profile),
            'bio' => $profile->bio,
            'is_following' => $viewer instanceof Profile && $viewer->id !== $profile->id
                ? $viewer->following()->whereKey($profile->id)->exists() : false,
            'is_self' => $viewer instanceof Profile && $viewer->id === $profile->id,
        ]]);
    }

    public function update(Request $request): JsonResponse
    {
        /** @var Profile|null $profile */
        $profile = ResolveProfile::profile($request);
        abort_unless($profile, 422, 'Sélectionnez un profil.');
        abort_if($profile->is_kid, 403, 'Les profils enfants ne disposent pas de profil public.');

        $data = $request->validate([
            'username' => ['sometimes', 'required', 'string', 'min:3', 'max:30', 'regex:/^[a-zA-Z0-9_]+$/', 'unique:profiles,username,'.$profile->id],
            'bio' => ['sometimes', 'nullable', 'string', 'max:500'],
        ]);
        if (isset($data['username'])) {
            $data['username'] = mb_strtolower($data['username']);
        }
        $profile->update($data);

        return response()->json(['data' => $this->summary($profile->fresh()) + ['bio' => $profile->bio]]);
    }

    public function follow(Request $request, string $username): JsonResponse
    {
        $follower = $this->activeProfile($request);
        $target = $this->publicProfile($username);
        abort_if($follower->id === $target->id, 422, 'Vous ne pouvez pas vous suivre vous-même.');
        $follower->following()->syncWithoutDetaching([$target->id]);

        return response()->json(['following' => true]);
    }

    public function unfollow(Request $request, string $username): JsonResponse
    {
        $follower = $this->activeProfile($request);
        $target = $this->publicProfile($username);
        $follower->following()->detach($target->id);

        return response()->json(['following' => false]);
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
     * (`RatedItemResource` : images, year…), plus `poster_path` for older apps.
     */
    public function reviews(Request $request, string $username): JsonResponse
    {
        $this->assertAdultViewer($request);
        $profile = $this->publicProfile($username);
        $items = TmdbRating::query()->where('profile_id', $profile->id)->latest('rated_at')->paginate(20);

        return response()->json(['data' => collect($items->items())->map(fn (TmdbRating $rating) => [
            ...(new RatedItemResource($rating))->toArray($request),
            'poster_path' => $rating->poster_path,
        ]), 'meta' => [
            'current_page' => $items->currentPage(), 'last_page' => $items->lastPage(),
        ]]);
    }

    public function favoritesList(Request $request, string $username): JsonResponse
    {
        $this->assertAdultViewer($request);
        $profile = $this->publicProfile($username);
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
        $this->assertAdultViewer($request);
        $profile = $this->publicProfile($username);
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
        $this->assertAdultViewer($request);
        $profile = $this->publicProfile($username);
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
        $this->assertAdultViewer($request);
        $profile = $this->publicProfile($username);
        $relation = $direction === 'followers' ? $profile->followers() : $profile->following();
        $items = $relation->where('is_kid', false)->withCount(['followers', 'following'])->paginate(30);

        return response()->json(['data' => $items->getCollection()->map(fn (Profile $p) => $this->summary($p)),
            'meta' => ['current_page' => $items->currentPage(), 'last_page' => $items->lastPage()]]);
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

    private function publicProfile(string $username): Profile
    {
        return Profile::query()->where('username', mb_strtolower(ltrim($username, '@')))
            ->where('is_kid', false)
            ->withCount([
                'followers',
                'following',
                'tmdbRatings as reviews_count',
                'items as watched_count' => fn ($query) => $query->where('is_watched', true)->whereIn('type', ['movie', 'tv']),
                'items as list_count' => fn ($query) => $query->where('is_favorite', true)->whereIn('type', ['movie', 'tv']),
                'items as liked_count' => fn ($query) => $query->where('is_liked', true)->whereIn('type', ['movie', 'tv']),
                'items as people_count' => fn ($query) => $query->where('is_followed', true)->where('type', 'person'),
            ])
            ->firstOrFail();
    }

    private function summary(Profile $profile): array
    {
        return [
            'id' => $profile->id, 'username' => $profile->username, 'name' => $profile->name,
            'avatar' => $profile->avatar, 'bio' => $profile->bio,
            'followers_count' => (int) ($profile->followers_count ?? $profile->followers()->count()),
            'following_count' => (int) ($profile->following_count ?? $profile->following()->count()),
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
