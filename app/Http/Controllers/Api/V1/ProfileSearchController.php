<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\ProfileSearchRequest;
use App\Http\Resources\V1\ProfileSearchResource;
use App\Models\Profile;
use App\Models\ProfileSearch;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

/**
 * Recent searches of a profile, the same on all its devices (docs/lecture.md).
 *
 * @tags Recherches récentes
 */
class ProfileSearchController extends Controller
{
    /** While typing, a search that extends one made this recently replaces it. */
    private const MERGE_SECONDS = 120;

    /**
     * Recent searches.
     *
     * The 20 most recent, newest first.
     */
    public function index(Profile $profile): AnonymousResourceCollection
    {
        Gate::authorize('view', $profile);

        return ProfileSearchResource::collection(
            $profile->searches()->latest('updated_at')->latest('id')->limit(ProfileSearch::MAX_PER_PROFILE)->get(),
        );
    }

    /**
     * Add a search.
     *
     * Moves it to the top if it is already there (whatever the case). A search typed just before
     * that it extends ("orbi" then "orbite", within 2 minutes) is replaced. Keeps the 20 most recent.
     */
    public function store(ProfileSearchRequest $request, Profile $profile): ProfileSearchResource
    {
        Gate::authorize('update', $profile);
        $query = $request->searchQuery();
        $key = ProfileSearch::keyOf($query);

        $search = DB::transaction(function () use ($profile, $query, $key) {
            $profile->searches()
                ->where('updated_at', '>=', now()->subSeconds(self::MERGE_SECONDS))
                ->where('query_key', '!=', $key)
                ->get()
                ->filter(fn (ProfileSearch $s) => str_starts_with($key, $s->query_key))
                ->each->delete();

            $search = $profile->searches()->firstOrNew(['query_key' => $key]);
            $search->query = $query;
            $search->updated_at = now();
            $search->save();

            $keep = $profile->searches()->latest('updated_at')->latest('id')
                ->limit(ProfileSearch::MAX_PER_PROFILE)->pluck('id');
            $profile->searches()->whereNotIn('id', $keep)->delete();

            return $search;
        });

        return new ProfileSearchResource($search);
    }

    /**
     * Remove a search.
     */
    public function destroy(Profile $profile, ProfileSearch $search): Response
    {
        Gate::authorize('update', $profile);
        $search->delete();

        return response()->noContent();
    }

    /**
     * Clear the recent searches.
     */
    public function clear(Profile $profile): Response
    {
        Gate::authorize('update', $profile);
        $profile->searches()->delete();

        return response()->noContent();
    }
}
