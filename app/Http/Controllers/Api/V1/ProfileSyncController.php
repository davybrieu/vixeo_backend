<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\SyncPullRequest;
use App\Http\Requests\Api\V1\SyncPushRequest;
use App\Http\Resources\V1\ProfileItemResource;
use App\Models\Profile;
use App\Services\ProfileSync;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Gate;

/**
 * Sync of favorites, progress and watched state between the devices of an account.
 * See docs/synchronisation.md.
 *
 * @tags Synchronisation
 */
class ProfileSyncController extends Controller
{
    public function __construct(private ProfileSync $sync) {}

    /**
     * Pull changes.
     *
     * Items changed since `cursor`, oldest change first. Store the returned `cursor` and
     * call again while `has_more` is true.
     */
    public function pull(SyncPullRequest $request, Profile $profile): JsonResponse
    {
        Gate::authorize('view', $profile);

        $result = $this->sync->pull($profile, $request->integer('cursor'), $request->integer('limit', 500));

        return response()->json([
            'data' => ProfileItemResource::collection($result['items']),
            /** Pass it as `cursor` on the next pull. */
            'cursor' => $result['cursor'],
            'has_more' => $result['has_more'],
        ]);
    }

    /**
     * Push changes.
     *
     * For each group (favorite, progress, watched) the most recent change (`at`) wins.
     * Returns the merged state of the pushed items. Then pull to get the other devices' changes.
     */
    public function push(SyncPushRequest $request, Profile $profile): JsonResponse
    {
        Gate::authorize('update', $profile);

        $result = $this->sync->push($profile, $request->changes());

        return response()->json([
            'data' => ProfileItemResource::collection($result['items']),
            /** Latest revision of the profile. */
            'revision' => $result['revision'],
        ]);
    }
}
