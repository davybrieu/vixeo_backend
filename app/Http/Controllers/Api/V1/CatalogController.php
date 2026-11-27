<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\CatalogList;
use App\Enums\MediaType;
use App\Http\Controllers\Controller;
use App\Http\Middleware\ResolveProfile;
use App\Http\Requests\Api\V1\CatalogHomeRequest;
use App\Http\Requests\Api\V1\CatalogPageRequest;
use App\Http\Requests\Api\V1\CatalogTitlesRequest;
use App\Http\Resources\V1\Catalog\GenreResource;
use App\Http\Resources\V1\Catalog\MediaItemResource;
use App\Http\Resources\V1\Catalog\SectionResource;
use App\Http\Resources\V1\Catalog\TitleRatingsResource;
use App\Services\Ratings\MdbListRatings;
use App\Services\Tmdb\AgeGate;
use App\Services\Tmdb\CatalogRows;
use App\Services\Tmdb\TitleSummaries;
use App\Services\Tmdb\TmdbCatalog;
use Dedoc\Scramble\Attributes\HeaderParameter;
use Illuminate\Http\JsonResponse;

/**
 * Metadata from TMDb, cached by the API. Images are served directly by the TMDb CDN.
 *
 * @tags Catalogue
 */
class CatalogController extends Controller
{
    public function __construct(
        private TmdbCatalog $catalog,
        private TitleSummaries $summaries,
    ) {}

    /**
     * Home.
     *
     * Featured carousel (titles with a backdrop) and themed rows.
     *
     * Items carry their title logo (`logo_url`) and a textless poster, unlike other lists.
     *
     * After TMDb's curated lists come the trends of the day, then rows by streaming platform, TV
     * network, studio and language (`list`: `trending_day`, `platform_netflix`, `network_hbo`,
     * `studio_marvel`, `language_fr`…). Their items carry no logo nor summary. Every row has its
     * "see all" page: `GET /catalog/{type}/lists/{list}`.
     */
    public function home(CatalogHomeRequest $request): JsonResponse
    {
        $home = $this->catalog->home($request->enum('type', MediaType::class));

        $filtered = [
            'featured' => $home['featured'],
            ...array_map(fn (array $section) => $section['items'], $home['sections']),
        ];

        // Logo and summary for the carousel and the curated rows only (prepared in advance by
        // `catalog:warm`, CatalogWarmer).
        $rich = array_filter($filtered, fn (int|string $index) => TmdbCatalog::summarized($home, $index), ARRAY_FILTER_USE_KEY);
        $filtered = $this->summaries->addMany($rich) + $filtered;

        $home['featured'] = $filtered['featured'];
        foreach ($home['sections'] as $index => $section) {
            $home['sections'][$index]['items'] = $filtered[$index];
        }
        $home['sections'] = array_values(array_filter($home['sections'], fn (array $section) => $section['items'] !== []));

        return response()->json([
            'featured' => MediaItemResource::collection($home['featured']),
            'sections' => SectionResource::collection($home['sections']),
        ]);
    }

    /**
     * Several titles at once.
     *
     * Movies and TV shows by id (« À voir », Continuer à regarder), with their logo, length, genres
     * and age rating, in the order asked: one request instead of one per title. Respects the
     * active profile's age limit; titles TMDb doesn't know are left out.
     */
    #[HeaderParameter('X-Profile-Id', 'Active profile id: its age limit is applied.', type: 'integer')]
    public function titles(CatalogTitlesRequest $request): JsonResponse
    {
        $titles = $this->summaries->titles($request->titles());

        return response()->json(['data' => MediaItemResource::collection($titles)]);
    }

    /**
     * Additional ratings from MDBList, loaded only when the app opens the ratings modal.
     * IMDb and TMDb remain supplied by the normal title endpoint.
     */
    public function ratings(CatalogTitlesRequest $request, MdbListRatings $mdbList): JsonResponse
    {
        $titles = $request->titles();
        $found = $mdbList->for($titles);

        $data = [];
        foreach ($titles as [$type, $id]) {
            $ratings = $found['ratings']["{$type->value}:{$id}"] ?? null;
            if ($ratings !== null) {
                $data[] = ['type' => $type, 'id' => $id, 'ratings' => $ratings];
            }
        }

        return response()->json([
            'data' => TitleRatingsResource::collection($data),
            'available' => (bool) $found['available'],
        ]);
    }

    /**
     * Genres.
     */
    public function genres(): JsonResponse
    {
        $genres = $this->catalog->genres();

        return response()->json([
            'movie' => GenreResource::collection($genres['movie']),
            'tv' => GenreResource::collection($genres['tv']),
        ]);
    }

    /**
     * List ("Voir tout" of a home row).
     *
     * `list` is the `list` of a home row: a curated list (`trending`, `popular`, `top_rated`,
     * `now_playing`, `upcoming`, `on_the_air`, `airing_today`) or another row of the home
     * (`trending_day`, `platform_netflix`, `network_hbo`, `studio_marvel`, `language_fr`…).
     * `now_playing` and `upcoming` exist for movies only, `on_the_air` and `airing_today`
     * for TV shows only; 404 for a list that does not exist for the type.
     */
    #[HeaderParameter('X-Profile-Id', 'Active profile id: its age limit is applied.', type: 'integer')]
    public function list(CatalogPageRequest $request, MediaType $type, string $list): JsonResponse
    {
        $curated = CatalogList::tryFrom($list);

        if ($curated?->supports($type)) {
            $page = $this->catalog->list($type, $curated, $request->page());
        } else {
            $row = CatalogRows::find($type, $list);
            abort_if($row === null, 404);

            $page = $this->catalog->row($row, $request->page());
        }

        // Logo, summary and IMDb rating with each title, like the home rows (TitleSummaries).
        $page['results'] = $this->summaries->add($page['results']);

        return response()->json([
            'data' => MediaItemResource::collection($page['results']),
            'meta' => [
                'page' => $page['page'],
                'total_pages' => $page['total_pages'],
                'total_results' => $page['total_results'],
            ],
        ]);
    }
}
