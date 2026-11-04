<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\SearchType;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\SearchRequest;
use App\Http\Resources\V1\Catalog\MediaItemResource;
use App\Http\Resources\V1\Catalog\PersonItemResource;
use App\Services\Tmdb\TmdbCatalog;
use Illuminate\Http\JsonResponse;

/**
 * @tags Catalogue
 */
class CatalogSearchController extends Controller
{
    public function __construct(private TmdbCatalog $catalog) {}

    /**
     * Search (all types).
     *
     * First page of movies, TV shows and people, grouped. Use the per-type search for more.
     */
    public function search(SearchRequest $request): JsonResponse
    {
        $results = $this->catalog->search($request->searchQuery());

        return response()->json([
            'movies' => [
                'data' => MediaItemResource::collection($results['movies']['results']),
                'total_results' => $results['movies']['total_results'],
            ],
            'shows' => [
                'data' => MediaItemResource::collection($results['shows']['results']),
                'total_results' => $results['shows']['total_results'],
            ],
            'people' => [
                'data' => PersonItemResource::collection($results['people']['results']),
                'total_results' => $results['people']['total_results'],
            ],
        ]);
    }

    /**
     * Search one type (paginated).
     *
     * Items are movies/TV shows (`MediaItemResource`) or people (`PersonItemResource`).
     */
    public function searchType(SearchRequest $request, SearchType $type): JsonResponse
    {
        $page = $this->catalog->searchType($type, $request->searchQuery(), $request->page());

        return response()->json([
            'data' => $type === SearchType::Person
                ? PersonItemResource::collection($page['results'])
                : MediaItemResource::collection($page['results']),
            'meta' => [
                'page' => $page['page'],
                'total_pages' => $page['total_pages'],
                'total_results' => $page['total_results'],
            ],
        ]);
    }
}
