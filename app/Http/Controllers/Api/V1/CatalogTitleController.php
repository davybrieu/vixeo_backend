<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\MediaType;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\CatalogPageRequest;
use App\Http\Requests\Api\V1\CreditsRequest;
use App\Http\Requests\Api\V1\PersonCreditsRequest;
use App\Http\Resources\V1\Catalog\CreditsResource;
use App\Http\Resources\V1\Catalog\MediaItemResource;
use App\Http\Resources\V1\Catalog\MovieResource;
use App\Http\Resources\V1\Catalog\PersonResource;
use App\Http\Resources\V1\Catalog\SeasonResource;
use App\Http\Resources\V1\Catalog\ShowResource;
use App\Services\Imdb\ImdbRatings;
use App\Services\Tmdb\TitleCrew;
use App\Services\Tmdb\TitleSummaries;
use App\Services\Tmdb\TmdbCatalog;
use App\Services\Tmdb\TmdbClient;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * @tags Catalogue
 */
class CatalogTitleController extends Controller
{
    public function __construct(
        private TmdbCatalog $catalog,
        private TitleSummaries $summaries,
        private ImdbRatings $imdb,
        private TmdbClient $tmdb,
    ) {}

    /** Movie details, cast, trailer, age rating and recommendations. */
    public function movie(int $id): MovieResource
    {
        return new MovieResource($this->imdb->attach(MediaType::Movie, $id, $this->summarized($this->catalog->movie($id))));
    }

    /** TV show details, seasons, cast, trailer, age rating and recommendations. */
    public function show(int $id): ShowResource
    {
        return new ShowResource($this->imdb->attach(MediaType::Tv, $id, $this->summarized($this->catalog->show($id))));
    }

    /** Streaming, rental and purchase availability supplied by JustWatch through TMDb. */
    public function watchProviders(Request $request, MediaType $type, int $id): JsonResponse
    {
        $validated = $request->validate(['country' => ['sometimes', 'string', 'size:2', 'alpha']]);
        $country = strtoupper($validated['country'] ?? config('services.tmdb.region', 'FR'));
        $providers = $this->tmdb->get("{$type->value}/{$id}/watch/providers", [], 'details');
        $region = $providers['results'][$country] ?? null;

        return response()->json([
            'data' => [
                'country' => $country,
                'link' => $region['link'] ?? null,
                'flatrate' => $region['flatrate'] ?? [],
                'free' => $region['free'] ?? [],
                'ads' => $region['ads'] ?? [],
                'rent' => $region['rent'] ?? [],
                'buy' => $region['buy'] ?? [],
                'source' => 'JustWatch',
            ],
        ]);
    }

    /** A page of recommendations or similar titles, with logo, summary and IMDb rating. */
    public function recommendations(CatalogPageRequest $request, MediaType $type, int $id): JsonResponse
    {
        $page = $this->catalog->recommendations($type, $id, $request->page());
        $page['results'] = $this->summaries->add($page['results']);

        return response()->json([
            'data' => MediaItemResource::collection($page['results']),
            'meta' => [
                'page' => (int) $page['page'],
                'total_pages' => (int) $page['total_pages'],
                'total_results' => (int) $page['total_results'],
            ],
        ]);
    }

    /** Cast and crew, paginated. */
    public function credits(CreditsRequest $request, MediaType $type, int $id): JsonResponse
    {
        $title = $this->title($type, $id);
        $total = count($request->crew() ? TitleCrew::of($title, $type) : CreditsResource::cast($title, $type));

        return (new CreditsResource($title, $type, $request->page(), $request->crew()))->additional(['meta' => [
            'page' => $request->page(),
            'total_pages' => max(1, (int) ceil($total / CreditsResource::PAGE)),
            'total_results' => $total,
        ]])->response();
    }

    /** A TV season with its episodes and IMDb ratings. */
    public function season(int $id, int $season): SeasonResource
    {
        $data = $this->catalog->season($id, $season);
        $data['episodes'] = $this->imdb->attachToEpisodes($id, $season, array_values((array) ($data['episodes'] ?? [])));

        return new SeasonResource($data);
    }

    /** Biography, best-known titles and filmography for a person. */
    public function person(int $id): PersonResource
    {
        $person = $this->catalog->person($id);
        [$person['known_for'], $person['filmography']] = $this->summaries->addAndRate(
            $person['known_for'],
            $person['filmography'],
        );

        return new PersonResource($person);
    }

    /** A page of a person's known-for titles or filmography. */
    public function personCredits(PersonCreditsRequest $request, int $id): JsonResponse
    {
        $all = $this->catalog->personCredits($id, $request->mediaType());
        $size = TmdbCatalog::PERSON_PAGE;
        $page = $request->page();
        $titles = $this->summaries->ratings(array_slice($all, ($page - 1) * $size, $size));

        return response()->json([
            'data' => MediaItemResource::collection($titles),
            'meta' => [
                'page' => $page,
                'total_pages' => max(1, (int) ceil(count($all) / $size)),
                'total_results' => count($all),
            ],
        ]);
    }

    /** @return array<string, mixed> */
    private function title(MediaType $type, int $id): array
    {
        return $type === MediaType::Movie ? $this->catalog->movie($id) : $this->catalog->show($id);
    }

    /** Add logo, poster and summary to recommendation cards. @param array<string, mixed> $title */
    private function summarized(array $title): array
    {
        $title['recommended'] = $this->summaries->add($title['recommended']);

        return $title;
    }
}
