<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\AppPlatform;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\AppUpdateRequest;
use App\Services\AppUpdates;
use Illuminate\Http\JsonResponse;

class AppUpdateController extends Controller
{
    /** @unauthenticated */
    public function check(AppUpdateRequest $request, AppUpdates $updates): JsonResponse
    {
        $build = (int) $request->validated('build');
        $version = (string) $request->validated('version');
        $result = $updates->check(AppPlatform::from($request->validated('platform')), $build);
        $latest = $result['latest'];

        return response()->json([
            'status' => $result['status'],
            'current_version' => $version,
            'current_build' => $build,
            'latest' => $latest === null ? null : [
                'version' => (string) $latest->version,
                'build' => (int) $latest->build,
                'notes' => $latest->notes === null ? null : (string) $latest->notes,
                'download_url' => $latest->download_url === null ? null : (string) $latest->download_url,
                'sha256' => $latest->sha256 === null ? null : (string) $latest->sha256,
                'published_at' => $latest->published_at->toIso8601String(),
            ],
            'minimum_build' => $result['minimum_build'],
            'update_url' => $result['update_url'],
        ]);
    }
}
