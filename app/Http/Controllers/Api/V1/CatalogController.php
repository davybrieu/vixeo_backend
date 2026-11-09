<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\CatalogList;
use App\Enums\MediaType;
use App\Http\Controllers\Controller;
use App\Http\Middleware\ResolveProfile;
use App\Http\Requests\Api\V1\CatalogHomeRequest;
use App\Http\Requests\Api\V1\CatalogPageRequest;
use App\Http\Requests\Api\V1\DiscoverRequest;
use App\Http\Resources\V1\Catalog\GenreResource;
use App\Http\Resources\V1\Catalog\MediaItemResource;
use App\Http\Resources\V1\Catalog\SectionResource;
use App\Services\Tmdb\AgeGate;
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
    public function __construct(private TmdbCatalog $catalog, private AgeGate $ageGate, private TitleSummaries $summaries) {}

    /**
     * Catalog configuration.
     *
     * Image base URL and sizes (URL = `image_base_url` + size + `*_path`), and the TMDb
     * attribution that the app must display (e.g. in "À propos").
     */
    public function config(): JsonResponse
    {
        return response()->json([
            'image_base_url' => (string) config('services.tmdb.image_base_url'),
            'poster_sizes' => ['w92', 'w154', 'w185', 'w342', 'w500', 'w780', 'original'],
            'backdrop_sizes' => ['w300', 'w780', 'w1280', 'original'],
            'profile_sizes' => ['w45', 'w185', 'h632', 'original'],
            'still_sizes' => ['w92', 'w185', 'w300', 'original'],
            'attribution' => 'This product uses the TMDB API but is not endorsed or certified by TMDB.',
            'attribution_url' => 'https://www.themoviedb.org',
        ]);
    }

    /**
     * Home.
     *
     * Featured carousel (titles with a backdrop) and themed rows.
     *
     * Items carry their title logo (`logo_url`) and a textless poster, unlike other lists.
     */
    #[HeaderParameter('X-Profile-Id', 'Active profile id: its age limit is applied.', type: 'integer')]
    public function home(CatalogHomeRequest $request): JsonResponse
    {
        $home = $this->catalog->home($request->enum('type', MediaType::class));

        $filtered = $this->summaries->addMany($this->ageGate->filterMany(ResolveProfile::profile($request), [
            'featured' => $home['featured'],
            ...array_map(fn (array $section) => $section['items'], $home['sections']),
        ]));

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
     * `now_playing` and `upcoming` exist for movies only, `on_the_air` and `airing_today`
     * for TV shows only.
     */
    #[HeaderParameter('X-Profile-Id', 'Active profile id: its age limit is applied.', type: 'integer')]
    public function list(CatalogPageRequest $request, MediaType $type, CatalogList $list): JsonResponse
    {
        abort_unless($list->supports($type), 404);

        $page = $this->catalog->list($type, $list, $request->page());
        $page['results'] = $this->ageGate->filter(ResolveProfile::profile($request), $page['results']);

        return response()->json([
            'data' => MediaItemResource::collection($page['results']),
            'meta' => [
                'page' => $page['page'],
                'total_pages' => $page['total_pages'],
                'total_results' => $page['total_results'],
            ],
        ]);
    }

    /**
     * Discover (search page without text).
     *
     * Movies or TV shows by genre, years and minimum rating, in the chosen order.
     */
    #[HeaderParameter('X-Profile-Id', 'Active profile id: its age limit is applied.', type: 'integer')]
    public function discover(DiscoverRequest $request, MediaType $type): JsonResponse
    {
        $page = $this->catalog->discover($type, $request->titleFilters(), $request->sort(), $request->page());
        $page['results'] = $this->summaries->add($this->ageGate->filter(ResolveProfile::profile($request), $page['results']));

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
