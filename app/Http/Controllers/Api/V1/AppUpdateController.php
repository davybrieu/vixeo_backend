<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\AppPlatform;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\AppUpdateRequest;
use App\Services\AppUpdates;
use Illuminate\Http\JsonResponse;

/**
 * Updates of the Android and iOS apps (docs/mise-a-jour.md).
 *
 * @tags Application
 */
class AppUpdateController extends Controller
{
    /**
     * Check for an update.
     *
     * Called by the app at launch and when it comes back to the foreground, before any sign-in.
     * `update_required`: the app blocks until the update is installed; `update_available`: it
     * suggests it once.
     *
     * @unauthenticated
     */
    public function check(AppUpdateRequest $request, AppUpdates $updates): JsonResponse
    {
        $build = (int) $request->validated('build');
        $result = $updates->check(AppPlatform::from($request->validated('platform')), $build);
        $latest = $result['latest'];

        return response()->json([
            'status' => $result['status'],
            'current_build' => $build,
            /** Newest published release, or null if none yet. */
            'latest' => $latest === null ? null : [
                'version' => (string) $latest->version,
                'build' => (int) $latest->build,
                /** What's new, in French (may be null). */
                'notes' => $latest->notes === null ? null : (string) $latest->notes,
                'published_at' => $latest->published_at->toIso8601String(),
            ],
            /** Installs older than this build must update (null: none required). */
            'minimum_build' => $result['minimum_build'],
            /** Page where the update is installed (Play Store, website…). */
            'update_url' => $result['update_url'],
        ]);
    }
}
