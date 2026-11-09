<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\DeadStreamRequest;
use App\Services\Playback\DeadStreams;
use Illuminate\Http\JsonResponse;

/**
 * Streams the players report as dead (link expired, 403, unsupported codec…). Shared by every
 * account and kept 24 h, so the sources panel drops them right away. See docs/lecture.md.
 *
 * @tags Lecture
 */
class DeadStreamController extends Controller
{
    public function __construct(private DeadStreams $dead) {}

    /**
     * Report a stream as dead.
     *
     * No content and no profile: a dead link is dead for everyone. Kept 24 h.
     */
    public function store(DeadStreamRequest $request): JsonResponse
    {
        $this->dead->markDead($request->validated('source'), $request->validated('stream_id'));

        return response()->json(['message' => __('Dead stream recorded.')]);
    }
}
