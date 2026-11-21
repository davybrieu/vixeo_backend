<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\MediaType;
use App\Enums\SearchSort;
use App\Enums\SearchType;
use App\Http\Controllers\Controller;
use App\Http\Middleware\ResolveProfile;
use App\Http\Requests\Api\V1\DiscoverRequest;
use App\Http\Requests\Api\V1\SearchRequest;
use App\Http\Resources\V1\Catalog\MediaItemResource;
use App\Http\Resources\V1\Catalog\PersonItemResource;
use App\Services\Tmdb\AgeGate;
use App\Services\Tmdb\TitleSummaries;
use App\Services\Tmdb\TmdbCatalog;
use Dedoc\Scramble\Attributes\HeaderParameter;
use Illuminate\Http\JsonResponse;

/**
 * @tags Catalogue
 */
class CatalogSearchController extends Controller
{
    public function __construct(private TmdbCatalog $catalog, private AgeGate $ageGate, private TitleSummaries $summaries) {}

    /**
     * Search (all types).
     *
     * First page of movies, TV shows and people, grouped. Use the per-type search for more.
     * Movies and TV shows respect the active profile's age limit.
     *
     * With a filter (genre, years, rating) or another order than relevance, movies and TV shows
     * are the matching titles among the first 60 results of each, sorted; people are only
     * returned without a filter.
     */
    #[HeaderParameter('X-Profile-Id', 'Active profile id: its age limit is applied.', type: 'integer')]
    public function search(SearchRequest $request): JsonResponse
    {
        $results = $this->catalog->search($request->searchQuery(), $request->titleFilters(), $request->sort());

        $filtered = $this->summaries->addMany($this->ageGate->filterMany(ResolveProfile::profile($request), [
            'movies' => $results['movies']['results'],
            'shows' => $results['shows']['results'],
        ]));
        $results['movies']['results'] = $filtered['movies'];
        $results['shows']['results'] = $filtered['shows'];

        return response()->json([
            'movies' => [
                'data' => MediaItemResource::collection($results['movies']['results']),
                'total_results' => (int) $results['movies']['total_results'],
            ],
            'shows' => [
                'data' => MediaItemResource::collection($results['shows']['results']),
                'total_results' => (int) $results['shows']['total_results'],
            ],
            'people' => [
                'data' => PersonItemResource::collection($results['people']['results']),
                'total_results' => (int) $results['people']['total_results'],
            ],
        ]);
    }

    /**
     * Browse without a text (all types).
     *
     * First page of movies and of TV shows matching the filters of the search page (genre, years,
     * minimum rating) in an order (popularity by default), without a text: the same answer as the
     * search, never with people. Released titles only. The rating is IMDb's, else TMDb's
     * (docs/notes.md).
     */
    #[HeaderParameter('X-Profile-Id', 'Active profile id: its age limit is applied.', type: 'integer')]
    public function discover(DiscoverRequest $request): JsonResponse
    {
        $results = $this->catalog->discoverAll($request->titleFilters(), $request->sort());
        $filtered = $this->summaries->addMany($this->ageGate->filterMany(ResolveProfile::profile($request), [
            'movies' => $results['movies']['results'],
            'shows' => $results['shows']['results'],
        ]));

        return response()->json([
            'movies' => [
                'data' => MediaItemResource::collection($filtered['movies']),
                'total_results' => (int) $results['movies']['total_results'],
            ],
            'shows' => [
                'data' => MediaItemResource::collection($filtered['shows']),
                'total_results' => (int) $results['shows']['total_results'],
            ],
            'people' => ['data' => [], 'total_results' => 0],
        ]);
    }

    /**
     * Browse one type without a text (paginated).
     *
     * Movies or TV shows matching the filters, in an order, page by page. With a minimum rating, a
     * page may hold fewer than 20 titles (even none) while later pages still have some: read
     * `meta.total_pages`. `total_results` is then an upper bound.
     */
    #[HeaderParameter('X-Profile-Id', 'Active profile id: its age limit is applied.', type: 'integer')]
    public function discoverType(DiscoverRequest $request, MediaType $type): JsonResponse
    {
        $page = $this->catalog->discover($type, $request->titleFilters(), $request->sort(), $request->page());
        $results = $this->summaries->add($this->ageGate->filter(ResolveProfile::profile($request), $page['results']));

        return response()->json([
            'data' => MediaItemResource::collection($results),
            'meta' => [
                'page' => (int) $page['page'],
                'total_pages' => (int) $page['total_pages'],
                'total_results' => (int) $page['total_results'],
            ],
        ]);
    }

    /**
     * Search one type (paginated).
     *
     * Items are movies/TV shows (`MediaItemResource`) or people (`PersonItemResource`).
     *
     * Movies and TV shows with a filter (genre, years, rating) or another order than relevance:
     * the matching titles among the first 60 results, sorted, in a single page. Filters and order
     * don't apply to people.
     */
    #[HeaderParameter('X-Profile-Id', 'Active profile id: its age limit is applied.', type: 'integer')]
    public function searchType(SearchRequest $request, SearchType $type): JsonResponse
    {
        $filters = $request->titleFilters();
        $sort = $request->sort();
        $page = $type !== SearchType::Person && (! $filters->isEmpty() || $sort !== SearchSort::Relevance)
            ? $this->catalog->searchTitles($type, $request->searchQuery(), $filters, $sort)
            : $this->catalog->searchType($type, $request->searchQuery(), $request->page());
        $page['results'] = $this->ageGate->filter(ResolveProfile::profile($request), $page['results']);
        if ($type !== SearchType::Person) {
            $page['results'] = $this->summaries->add($page['results']);
        }

        return response()->json([
            'data' => $type === SearchType::Person
                ? PersonItemResource::collection($page['results'])
                : MediaItemResource::collection($page['results']),
            'meta' => [
                'page' => (int) $page['page'],
                'total_pages' => (int) $page['total_pages'],
                'total_results' => (int) $page['total_results'],
            ],
        ]);
    }
}
