<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\MediaType;
use App\Enums\RatedSort;
use App\Enums\RatedType;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\RatedListRequest;
use App\Http\Requests\Api\V1\RateRequest;
use App\Http\Resources\V1\Tmdb\RatedItemResource;
use App\Models\Profile;
use App\Models\TmdbRating;
use App\Services\Tmdb\RatingTarget;
use App\Services\TmdbRatings;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Gate;

/**
 * The profile's reviews: a rating and an optional comment per movie, TV show or episode
 * (docs/avis.md). Kept by Vixeo only.
 *
 * @tags Avis
 */
class TmdbRatingController extends Controller
{
    public function __construct(private TmdbRatings $ratings) {}

    /**
     * My reviews ("Mes avis").
     *
     * Every type, or one, with the comments: the most recently rated first, or sorted by `sort`
     * (oldest, highest, lowest rating; equal ratings: the most recent first). Filtered by the
     * The account profile's full set of ratings.
     */
    public function index(RatedListRequest $request, Profile $profile): JsonResponse
    {
        Gate::authorize('view', $profile);
        $type = $request->type();
        $rows = $this->ratings->all($profile)
            ->when($type !== null, fn ($rows) => $rows->where('type', $type))
            ->sort(self::order($request->sort()))
            ->values();

        $perPage = $request->perPage();
        $total = $rows->count();

        return response()->json([
            'data' => RatedItemResource::collection($rows->forPage($request->page(), $perPage)->values()),
            'meta' => [
                'page' => $request->page(),
                'total_pages' => max(1, (int) ceil($total / $perPage)),
                'total_results' => $total,
            ],
        ]);
    }

    /**
     * My rating of a movie or TV show.
     *
     * `value` is null when not rated; `comment` is the profile's comment.
     */
    public function show(Profile $profile, MediaType $type, int $id): JsonResponse
    {
        Gate::authorize('view', $profile);
        $row = $this->ratings->find($profile, RatingTarget::title($type, $id));

        return response()->json(['data' => [
            /** 0.5 to 10, null when not rated. */
            'value' => $row === null ? null : (float) $row->value,
            'comment' => $row?->comment === null ? null : (string) $row->comment,
            'contains_spoilers' => (bool) ($row?->contains_spoilers ?? false),
            /** Null when not rated. */
            'review_id' => $row === null ? null : (int) $row->id,
        ]]);
    }

    /**
     * My ratings of a season's episodes.
     *
     * Only the rated episodes, with their comments.
     */
    public function season(Profile $profile, int $id, int $season): JsonResponse
    {
        Gate::authorize('view', $profile);
        $rows = $this->ratings->season($profile, $id, $season)->sortKeys();

        return response()->json([
            'data' => $rows->values()->map(fn (TmdbRating $row) => [
                'episode_number' => (int) $row->episode_number,
                /** 0.5 to 10. */
                'value' => (float) $row->value,
                'comment' => $row->comment === null ? null : (string) $row->comment,
                'contains_spoilers' => (bool) $row->contains_spoilers,
                'review_id' => (int) $row->id,
            ])->all(),
        ]);
    }

    /**
     * Rate a movie or TV show.
     *
     * Creates or updates the review.
     */
    public function rate(RateRequest $request, Profile $profile, MediaType $type, int $id): JsonResponse
    {
        return $this->save($request, $profile, RatingTarget::title($type, $id));
    }

    /**
     * Remove a movie or TV show rating.
     *
     * Its comment is removed too.
     */
    public function unrate(Profile $profile, MediaType $type, int $id): Response
    {
        return $this->remove($profile, RatingTarget::title($type, $id));
    }

    /**
     * Rate an episode.
     *
     * Creates or updates the review.
     */
    public function rateEpisode(RateRequest $request, Profile $profile, int $id, int $season, int $episode): JsonResponse
    {
        return $this->save($request, $profile, RatingTarget::episode($id, $season, $episode));
    }

    /**
     * Remove an episode rating.
     *
     * Its comment is removed too.
     */
    public function unrateEpisode(Profile $profile, int $id, int $season, int $episode): Response
    {
        return $this->remove($profile, RatingTarget::episode($id, $season, $episode));
    }

    private function save(RateRequest $request, Profile $profile, RatingTarget $target): JsonResponse
    {
        Gate::authorize('update', $profile);
        $row = $this->ratings->save($profile, $target, $request->value(), $request->hasComment(), $request->comment(), $request->spoilers());

        return response()->json(['data' => [
            'value' => (float) $row->value,
            'comment' => $row->comment === null ? null : (string) $row->comment,
            'contains_spoilers' => (bool) $row->contains_spoilers,
            /** The review itself: its page, its likes and comments (`/social/reviews/{review_id}`). */
            'review_id' => (int) $row->id,
        ]]);
    }

    private function remove(Profile $profile, RatingTarget $target): Response
    {
        Gate::authorize('update', $profile);
        $this->ratings->remove($profile, $target);

        return response()->noContent();
    }

    /**
     * Comparison for "Mes avis": by rating date, or by value then the most recent.
     *
     * @return \Closure(TmdbRating, TmdbRating): int
     */
    private static function order(RatedSort $sort): \Closure
    {
        $recent = fn (TmdbRating $a, TmdbRating $b) => [$b->rated_at->getTimestamp(), $b->id] <=> [$a->rated_at->getTimestamp(), $a->id];

        return match ($sort) {
            RatedSort::Recent => $recent,
            RatedSort::Oldest => fn (TmdbRating $a, TmdbRating $b) => $recent($b, $a),
            RatedSort::Highest => fn (TmdbRating $a, TmdbRating $b) => ($b->value <=> $a->value) ?: $recent($a, $b),
            RatedSort::Lowest => fn (TmdbRating $a, TmdbRating $b) => ($a->value <=> $b->value) ?: $recent($a, $b),
        };
    }
}
