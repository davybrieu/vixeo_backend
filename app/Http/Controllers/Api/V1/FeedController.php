<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Api\V1\Concerns\ResolvesSocialProfiles;
use App\Http\Controllers\Controller;
use App\Services\Social\Feed;
use App\Services\Social\Taste;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * The feed (« Fil », docs/social.md) and the accounts to follow.
 *
 * @tags Social
 */
class FeedController extends Controller
{
    use ResolvesSocialProfiles;

    /**
     * The feed.
     *
     * What the active profile and those it follows did, the most recent first: `kind` = `review`
     * (with `review`), `list` (with `list`) or `watched` (`title`, the first `profiles` who watched
     * it, `profiles_count`, `episodes_count`). `before`: the `next` of the previous page (null: the
     * end).
     */
    public function index(Request $request, Feed $feed): JsonResponse
    {
        return response()->json($feed->page($this->author($request), $request->integer('before') ?: null));
    }

    /**
     * Accounts to follow.
     *
     * Followed by those the active profile follows, close tastes (`compatibility`, 0 to 100), then
     * the most followed; with the `reason` to show.
     */
    public function suggestions(Request $request, Taste $taste): JsonResponse
    {
        return response()->json(['data' => $taste->suggestions($this->author($request))]);
    }
}
