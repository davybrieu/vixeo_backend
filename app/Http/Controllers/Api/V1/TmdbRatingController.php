<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\MediaType;
use App\Enums\RatedType;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\RatedListRequest;
use App\Http\Requests\Api\V1\RateRequest;
use App\Http\Resources\V1\Tmdb\RatedItemResource;
use App\Models\Profile;
use App\Models\TmdbLink;
use App\Models\TmdbRating;
use App\Services\Tmdb\AgeGate;
use App\Services\Tmdb\RatingTarget;
use App\Services\Tmdb\TmdbAccount;
use App\Services\Tmdb\TmdbLinkRevoked;
use App\Services\TmdbRatings;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Gate;

/**
 * Ratings of the profile's TMDb account and the profile's comments (docs/compte-tmdb.md). The
 * ratings live on TMDb, so they are the same on every device, every profile linked to that account
 * and themoviedb.org; the comments are kept by Vixeo (TMDb's API takes no written review). 409 when
 * no TMDb account is linked (or TMDb no longer accepts it: link it again).
 *
 * @tags Compte TMDb
 */
class TmdbRatingController extends Controller
{
    public function __construct(private TmdbAccount $tmdb, private TmdbRatings $ratings, private AgeGate $ageGate) {}

    /**
     * My ratings ("Mes avis").
     *
     * Every type, or one, the most recently rated first, with the comments. Filtered by the
     * profile's age limit. Ratings given on themoviedb.org show up within 10 minutes.
     */
    public function index(RatedListRequest $request, Profile $profile): JsonResponse
    {
        Gate::authorize('view', $profile);
        $type = $request->type();
        $rows = $this->ratings->all($profile, $this->link($profile))
            ->when($type !== null, fn ($rows) => $rows->where('type', $type))
            ->values();

        // The age limit of the TV show applies to its episodes.
        $allowed = collect($this->ageGate->filter($profile, $rows->map(fn (TmdbRating $r) => [
            'media_type' => $r->type === RatedType::Movie ? 'movie' : 'tv',
            'id' => $r->tmdb_id,
            'row' => $r,
        ])->all()))->pluck('row');

        $perPage = $request->perPage();
        $total = $allowed->count();

        return response()->json([
            'data' => RatedItemResource::collection($allowed->forPage($request->page(), $perPage)->values()),
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
        $link = $this->link($profile);
        $target = RatingTarget::title($type, $id);
        $value = $this->tmdb->rating($link, $target);
        $row = $this->ratings->find($profile, $link, $target);
        if ($value === null && $row !== null) {
            // Removed on TMDb: the comment goes with it.
            $this->ratings->removed($profile, $link, $target);
            $row = null;
        }

        return response()->json(['data' => [
            /** 0.5 to 10, null when not rated. */
            'value' => $value,
            'comment' => $row?->comment === null ? null : (string) $row->comment,
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
        $link = $this->link($profile);
        $ratings = $this->tmdb->seasonRatings($link, $id, $season);
        $rows = $this->ratings->season($profile, $link, $id, $season);

        return response()->json([
            'data' => array_map(fn (int $episode, float $value) => [
                'episode_number' => $episode,
                /** 0.5 to 10. */
                'value' => $value,
                'comment' => $rows->get($episode)?->comment === null ? null : (string) $rows->get($episode)->comment,
            ], array_keys($ratings), array_values($ratings)),
        ]);
    }

    /**
     * Rate a movie or TV show.
     *
     * Creates or updates the rating on TMDb; the comment is kept by Vixeo.
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
     * Creates or updates the rating on TMDb; the comment is kept by Vixeo.
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
        $link = $this->link($profile);
        $this->tmdb->rate($link, $target, $request->value());
        $row = $this->ratings->saved($profile, $link, $target, $request->value(), $request->hasComment(), $request->comment());

        return response()->json(['data' => [
            'value' => (float) $row->value,
            'comment' => $row->comment === null ? null : (string) $row->comment,
        ]]);
    }

    private function remove(Profile $profile, RatingTarget $target): Response
    {
        Gate::authorize('update', $profile);
        $link = $this->link($profile);
        $this->tmdb->unrate($link, $target);
        $this->ratings->removed($profile, $link, $target);

        return response()->noContent();
    }

    private function link(Profile $profile): TmdbLink
    {
        return $profile->tmdbLink ?? throw TmdbLinkRevoked::notLinked();
    }
}
