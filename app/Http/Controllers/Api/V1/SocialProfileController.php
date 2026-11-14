<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Profile;
use App\Models\ProfileItem;
use App\Models\TmdbRating;
use App\Http\Middleware\ResolveProfile;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class SocialProfileController extends Controller
{
    public function search(Request $request): JsonResponse
    {
        $query = trim((string) $request->query('query', ''));
        abort_if(mb_strlen($query) < 2, 422, 'Saisissez au moins deux caractères.');

        $profiles = Profile::query()
            ->where('is_kid', false)
            ->whereNotNull('username')
            ->where('username', 'like', '%'.addcslashes($query, '%_\\').'%')
            ->withCount(['followers', 'following'])
            ->orderByRaw('username = ? desc', [mb_strtolower(ltrim($query, '@'))])
            ->limit(30)
            ->get();

        return response()->json(['data' => $profiles->map(fn (Profile $profile) => $this->summary($profile))]);
    }

    public function show(Request $request, string $username): JsonResponse
    {
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

    public function reviews(string $username): JsonResponse
    {
        $profile = $this->publicProfile($username);
        $items = TmdbRating::query()->where('profile_id', $profile->id)->latest('rated_at')->paginate(20);
        return response()->json(['data' => $items->items(), 'meta' => [
            'current_page' => $items->currentPage(), 'last_page' => $items->lastPage(),
        ]]);
    }

    public function list(string $username): JsonResponse
    {
        $profile = $this->publicProfile($username);
        $items = ProfileItem::query()->where('profile_id', $profile->id)
            ->where('is_favorite', true)->latest('favorited_at')->paginate(30);
        return response()->json(['data' => $items->items(), 'meta' => [
            'current_page' => $items->currentPage(), 'last_page' => $items->lastPage(),
        ]]);
    }

    private function connections(Request $request, string $username, string $direction): JsonResponse
    {
        $profile = $this->publicProfile($username);
        $relation = $direction === 'followers' ? $profile->followers() : $profile->following();
        $items = $relation->where('is_kid', false)->withCount(['followers', 'following'])->paginate(30);
        return response()->json(['data' => $items->getCollection()->map(fn (Profile $p) => $this->summary($p)),
            'meta' => ['current_page' => $items->currentPage(), 'last_page' => $items->lastPage()]]);
    }

    private function activeProfile(Request $request): Profile
    {
        $profile = $request->attributes->get('active_profile');
        abort_unless($profile instanceof Profile, 422, 'Sélectionnez un profil.');
        abort_if($profile->is_kid, 403, 'Les profils enfants ne peuvent pas utiliser les fonctions sociales.');
        return $profile;
    }

    private function publicProfile(string $username): Profile
    {
        return Profile::query()->where('username', mb_strtolower(ltrim($username, '@')))
            ->where('is_kid', false)->withCount(['followers', 'following'])->firstOrFail();
    }

    private function summary(Profile $profile): array
    {
        return [
            'id' => $profile->id, 'username' => $profile->username, 'name' => $profile->name,
            'avatar' => $profile->avatar, 'bio' => $profile->bio,
            'followers_count' => $profile->followers_count ?? $profile->followers()->count(),
            'following_count' => $profile->following_count ?? $profile->following()->count(),
        ];
    }
}
