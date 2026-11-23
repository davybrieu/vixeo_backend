<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Api\V1\Concerns\ResolvesSocialProfiles;
use App\Http\Controllers\Controller;
use App\Services\Social\Feed;
use App\Services\Social\Taste;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * The feed (« Fil d'actualité », docs/social.md), the « Activité » of a profile and the accounts
 * to follow.
 *
 * @tags Social
 */
class FeedController extends Controller
{
    use ResolvesSocialProfiles;

    /**
     * The feed, « Abonnements ».
     *
     * What the active profile and those it follows did, the most recent first: `kind` = `review`
     * (with `review`), `list` (with `list`), `watched` (`title`, the first `profiles` who watched
     * it, `profiles_count`, `episodes_count`) or `liked` (a title liked, « J'aime » : `title`,
     * `profiles`, `profiles_count`). `before`: the `next` of the previous page (null: the end).
     */
    public function index(Request $request, Feed $feed): JsonResponse
    {
        return response()->json($feed->page($this->author($request), $request->integer('before') ?: null));
    }

    /**
     * The feed, « Pour toi ».
     *
     * Reviews and lists of the whole network that the active profile can see (not its own, nor
     * those of muted or blocked profiles), and the titles the profiles it follows watched or liked,
     * from the last 30 days, the most interesting first: what they drew (J'aime, comments), the
     * tastes in common with their author, their age. Same items as the feed. `page`: 1 first;
     * `next`: the following page (null: the end).
     */
    public function forYou(Request $request, Feed $feed): JsonResponse
    {
        return response()->json($feed->forYou($this->author($request), max(1, $request->integer('page', 1))));
    }

    /**
     * A member's activity.
     *
     * Its reviews, lists, watched titles and titles liked, each with its date (`created_at`), the
     * most recent first; the same items as the feed (episodes of a show watched the same day
     * together). `before`: the `next` of the previous page (null: the end). 403: a private account
     * the active profile doesn't follow; 404: blocked.
     */
    public function activity(Request $request, Feed $feed, string $username): JsonResponse
    {
        $member = $this->visibleMember($request, $username);

        return response()->json($feed->activity($member, $this->viewer($request), $request->integer('before') ?: null));
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
