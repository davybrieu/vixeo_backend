<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Api\V1\Concerns\ResolvesSocialProfiles;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Social\CommentRequest;
use App\Models\SocialComment;
use App\Models\TmdbRating;
use App\Services\Social\Comments;
use App\Services\Social\SocialCards;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

/**
 * A review as a publication (docs/social.md): its page, its comments.
 *
 * @tags Social
 */
class ReviewSocialController extends Controller
{
    use ResolvesSocialProfiles;

    public function __construct(private Comments $comments, private SocialCards $cards) {}

    /**
     * A review.
     *
     * Its author, rating, comment (maybe with spoilers), likes and number of comments. 403: a
     * private account the active profile doesn't follow; 404: deleted, hidden or blocked.
     */
    public function show(Request $request, int $review): JsonResponse
    {
        $row = TmdbRating::query()->with('profile')->findOrFail($review);
        $viewer = $this->assertVisible($request, $row->profile, $row->hidden_at !== null);

        return response()->json(['data' => $this->cards->reviews(collect([$row]), $viewer)[0]]);
    }

    /**
     * Comments of a review.
     *
     * The latest 50, oldest first; `before` (a comment id) for older ones.
     */
    public function comments(Request $request, int $review): JsonResponse
    {
        $row = TmdbRating::query()->with('profile')->findOrFail($review);
        $viewer = $this->assertVisible($request, $row->profile, $row->hidden_at !== null);

        return response()->json($this->comments->page($row, $viewer, $request->integer('before') ?: null));
    }

    /**
     * Comment a review.
     *
     * Its author is notified.
     */
    public function comment(CommentRequest $request, int $review): JsonResponse
    {
        $profile = $this->author($request);
        $row = TmdbRating::query()->with('profile')->findOrFail($review);
        $this->assertVisible($request, $row->profile, $row->hidden_at !== null);
        $comment = $this->comments->add($profile, $row, $request->body(), $request->boolean('contains_spoilers'));

        return response()->json(['data' => SocialCards::comment($comment, $profile, (int) $row->profile_id === (int) $profile->id)], 201);
    }

    /**
     * Delete a comment.
     *
     * Its author, or the author of the review or list it is on.
     */
    public function deleteComment(Request $request, int $comment): Response
    {
        $profile = $this->author($request);
        $this->comments->delete($profile, SocialComment::query()->with(['review', 'list'])->findOrFail($comment));

        return response()->noContent();
    }
}
