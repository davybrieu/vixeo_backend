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
        $version = (string) $request->validated('version', '');
        $result = $updates->check(AppPlatform::from($request->validated('platform')), $build);
        $latest = $result['latest'];

        return response()->json([
            'status' => $result['status'],
            /** Version name sent by the app (null for older builds that don't send it). */
            'current_version' => $version === '' ? null : $version,
            'current_build' => $build,
            /** Newest Android release published on GitHub, or null if none yet (always null on iOS). */
            'latest' => $latest === null ? null : [
                'version' => (string) $latest->version,
                'build' => (int) $latest->build,
                /** What's new, in French (may be null). */
                'notes' => $latest->notes === null ? null : (string) $latest->notes,
                /** Android: the APK to download and install (https), or null. */
                'download_url' => $latest->downloadUrl === null ? null : (string) $latest->downloadUrl,
                /** SHA-256 of the APK, to check before installing (null without APK). */
                'sha256' => $latest->sha256 === null ? null : (string) $latest->sha256,
                /** Publication date, ISO 8601. */
                'published_at' => (string) $latest->publishedAt->toIso8601String(),
            ],
            /** Installs older than this build must update: a GitHub release marked « [obligatoire] » (null: none). */
            'minimum_build' => $result['minimum_build'] === null ? null : (int) $result['minimum_build'],
            /** Page where the update is installed: the site's install page (no Play Store). */
            'update_url' => $result['update_url'],
        ]);
    }
}
