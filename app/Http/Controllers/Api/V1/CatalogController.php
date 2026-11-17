<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\CatalogList;
use App\Enums\MediaType;
use App\Http\Controllers\Controller;
use App\Http\Middleware\ResolveProfile;
use App\Http\Requests\Api\V1\CatalogHomeRequest;
use App\Http\Requests\Api\V1\CatalogPageRequest;
use App\Http\Requests\Api\V1\CatalogTitlesRequest;
use App\Http\Requests\Api\V1\DiscoverRequest;
use App\Http\Resources\V1\Catalog\GenreResource;
use App\Http\Resources\V1\Catalog\MediaItemResource;
use App\Http\Resources\V1\Catalog\SectionResource;
use App\Services\Addons\Addons;
use App\Services\Tmdb\AgeGate;
use App\Services\Tmdb\CatalogRows;
use App\Services\Tmdb\TitleSummaries;
use App\Services\Tmdb\TmdbCatalog;
use Dedoc\Scramble\Attributes\HeaderParameter;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Metadata from TMDb, cached by the API. Images are served directly by the TMDb CDN.
 *
 * @tags Catalogue
 */
class CatalogController extends Controller
{
    public function __construct(
        private TmdbCatalog $catalog,
        private AgeGate $ageGate,
        private TitleSummaries $summaries,
        private Addons $addons,
    ) {}

    /**
     * Rows of the home from the plug-ins of the account (docs/plugins.md): their catalogs, as
     * light rows (no logo nor summary), after the rows of the home. Titles TMDb does not know are
     * left out; the profile's age limit applies. Without an account: the plug-ins installed for
     * everyone only. A plug-in that does not answer has no row.
     */
    public function addonSections(Request $request): JsonResponse
    {
        $profile = ResolveProfile::profile($request);
        $sections = $this->addons->sections($request->user('sanctum'), $profile);

        $filtered = $this->ageGate->filterMany($profile, array_map(fn (array $section) => $section['items'], $sections));
        foreach ($sections as $index => $section) {
            $sections[$index]['items'] = $filtered[$index];
        }

        return response()->json([
            'sections' => SectionResource::collection(array_values(array_filter($sections, fn (array $s) => $s['items'] !== []))),
        ]);
    }

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
     *
     * After TMDb's curated lists come the trends of the day, then rows by streaming platform, TV
     * network, studio and language (`list`: `trending_day`, `platform_netflix`, `network_hbo`,
     * `studio_marvel`, `language_fr`…). Their items carry no logo nor summary. Every row has its
     * "see all" page: `GET /catalog/{type}/lists/{list}`.
     *
     * Empty (no featured title, no row) when the account turned the Vixeo catalog off
     * (`PATCH /addons/vixeo`, docs/plugins.md).
     */
    #[HeaderParameter('X-Profile-Id', 'Active profile id: its age limit is applied.', type: 'integer')]
    public function home(CatalogHomeRequest $request): JsonResponse
    {
        // The Vixeo catalog turned off by the account (« Plug-ins »): none of its rows, the home
        // keeps "Continuer à regarder", the channels and the rows of the other plug-ins.
        if (! $this->addons->vixeoEnabled($request->user('sanctum'))) {
            return response()->json(['featured' => [], 'sections' => []]);
        }

        $home = $this->catalog->home($request->enum('type', MediaType::class));

        $filtered = $this->ageGate->filterMany(ResolveProfile::profile($request), [
            'featured' => $home['featured'],
            ...array_map(fn (array $section) => $section['items'], $home['sections']),
        ]);

        // Logo and summary for the carousel and the curated rows only: the many other rows
        // (platforms, networks, studios, languages) are `light`, the app reads the title page.
        $rich = array_filter(
            $filtered,
            fn (int|string $index) => $index === 'featured' || empty($home['sections'][$index]['light']),
            ARRAY_FILTER_USE_KEY,
        );
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
     * Movies and TV shows by id (Ma liste, Continuer à regarder), with their logo, length, genres
     * and age rating, in the order asked: one request instead of one per title. Respects the
     * active profile's age limit; titles TMDb doesn't know are left out.
     */
    #[HeaderParameter('X-Profile-Id', 'Active profile id: its age limit is applied.', type: 'integer')]
    public function titles(CatalogTitlesRequest $request): JsonResponse
    {
        $titles = $this->ageGate->filter(ResolveProfile::profile($request), $this->summaries->titles($request->titles()));

        return response()->json(['data' => MediaItemResource::collection($titles)]);
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

        if (Addons::isAddonList($list)) {
            // A catalog of a plug-in ("addon:12:top", the `list` of its row).
            $page = $this->addons->catalogPage($request->user('sanctum'), $type, $list, $request->page());
        } elseif ($curated?->supports($type)) {
            $page = $this->catalog->list($type, $curated, $request->page());
        } else {
            $row = CatalogRows::find($type, $list);
            abort_if($row === null, 404);

            $page = $this->catalog->row($row, $request->page());
        }

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
