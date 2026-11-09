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
use App\Services\Tmdb\AgeGate;
use App\Services\Tmdb\RatingTarget;
use App\Services\Tmdb\TmdbAccount;
use App\Services\Tmdb\TmdbClient;
use App\Services\Tmdb\TmdbLinkRevoked;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Gate;

/**
 * Ratings of the profile's TMDb account (docs/compte-tmdb.md): they live on TMDb, so they are the
 * same on every device, every profile linked to that account and themoviedb.org. 409 when no
 * TMDb account is linked (or TMDb no longer accepts it: link it again).
 *
 * @tags Compte TMDb
 */
class TmdbRatingController extends Controller
{
    public function __construct(private TmdbAccount $tmdb, private TmdbClient $catalog, private AgeGate $ageGate) {}

    /**
     * My ratings.
     *
     * Movies, TV shows or episodes, the most recently rated first, 20 per page. Filtered by the
     * profile's age limit.
     */
    public function index(RatedListRequest $request, Profile $profile): JsonResponse
    {
        Gate::authorize('view', $profile);
        $type = $request->type();
        $page = $this->tmdb->rated($this->link($profile), $type, $request->page());

        $items = match ($type) {
            RatedType::Episode => $this->episodes($profile, $page['results']),
            default => array_map(
                fn (array $item) => ['type' => $type, 'item' => $item, 'show' => null],
                $this->ageGate->filter($profile, array_map(fn (array $item) => ['media_type' => $type->value] + $item, $page['results'])),
            ),
        };

        return response()->json([
            'data' => RatedItemResource::collection($items),
            'meta' => [
                'page' => $page['page'],
                'total_pages' => $page['total_pages'],
                'total_results' => $page['total_results'],
            ],
        ]);
    }

    /**
     * My rating of a movie or TV show.
     *
     * `value` is null when not rated.
     */
    public function show(Profile $profile, MediaType $type, int $id): JsonResponse
    {
        Gate::authorize('view', $profile);

        return response()->json(['data' => [
            /** 0.5 to 10, null when not rated. */
            'value' => $this->tmdb->rating($this->link($profile), RatingTarget::title($type, $id)),
        ]]);
    }

    /**
     * My ratings of a season's episodes.
     *
     * Only the rated episodes.
     */
    public function season(Profile $profile, int $id, int $season): JsonResponse
    {
        Gate::authorize('view', $profile);
        $ratings = $this->tmdb->seasonRatings($this->link($profile), $id, $season);

        return response()->json([
            'data' => array_map(fn (int $episode, float $value) => [
                'episode_number' => $episode,
                /** 0.5 to 10. */
                'value' => $value,
            ], array_keys($ratings), array_values($ratings)),
        ]);
    }

    /**
     * Rate a movie or TV show.
     *
     * Creates or updates the rating on TMDb.
     */
    public function rate(RateRequest $request, Profile $profile, MediaType $type, int $id): JsonResponse
    {
        Gate::authorize('update', $profile);
        $this->tmdb->rate($this->link($profile), RatingTarget::title($type, $id), $request->value());

        return response()->json(['data' => ['value' => $request->value()]]);
    }

    /**
     * Remove a movie or TV show rating.
     */
    public function unrate(Profile $profile, MediaType $type, int $id): Response
    {
        Gate::authorize('update', $profile);
        $this->tmdb->unrate($this->link($profile), RatingTarget::title($type, $id));

        return response()->noContent();
    }

    /**
     * Rate an episode.
     *
     * Creates or updates the rating on TMDb.
     */
    public function rateEpisode(RateRequest $request, Profile $profile, int $id, int $season, int $episode): JsonResponse
    {
        Gate::authorize('update', $profile);
        $this->tmdb->rate($this->link($profile), RatingTarget::episode($id, $season, $episode), $request->value());

        return response()->json(['data' => ['value' => $request->value()]]);
    }

    /**
     * Remove an episode rating.
     */
    public function unrateEpisode(Profile $profile, int $id, int $season, int $episode): Response
    {
        Gate::authorize('update', $profile);
        $this->tmdb->unrate($this->link($profile), RatingTarget::episode($id, $season, $episode));

        return response()->noContent();
    }

    private function link(Profile $profile): TmdbLink
    {
        return $profile->tmdbLink ?? throw TmdbLinkRevoked::notLinked();
    }

    /**
     * Rated episodes with their TV show (title, poster), without the shows the profile may not see.
     *
     * @param  list<array<string, mixed>>  $episodes
     * @return list<array{type: RatedType, item: array<string, mixed>, show: array<string, mixed>|null}>
     */
    private function episodes(Profile $profile, array $episodes): array
    {
        $showIds = array_values(array_unique(array_map(fn (array $e) => (int) ($e['show_id'] ?? 0), $episodes)));
        $allowed = array_map(
            fn (array $show) => (int) $show['id'],
            $this->ageGate->filter($profile, array_map(fn (int $id) => ['media_type' => 'tv', 'id' => $id], $showIds)),
        );
        $shows = $this->catalog->getMany(array_map(fn (int $id) => "tv/{$id}", $allowed));

        $items = [];
        foreach ($episodes as $episode) {
            $showId = (int) ($episode['show_id'] ?? 0);
            if (in_array($showId, $allowed, true)) {
                $items[] = ['type' => RatedType::Episode, 'item' => $episode, 'show' => $shows["tv/{$showId}"] ?? null];
            }
        }

        return $items;
    }
}
