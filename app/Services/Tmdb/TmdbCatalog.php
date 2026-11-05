<?php

namespace App\Services\Tmdb;

use App\Enums\CatalogList;
use App\Enums\DiscoverSort;
use App\Enums\MediaType;
use App\Enums\SearchSort;
use App\Enums\SearchType;

/**
 * What Vixeo asks TMDb. Returns TMDb data with `media_type` set on every item,
 * so resources can tell movies and TV shows apart; formatting is done by the
 * resources in App\Http\Resources\V1\Catalog.
 */
class TmdbCatalog
{
    /** Rows of the combined (movies + TV shows) home, in display order. */
    private const COMBINED_HOME = [
        ['movie', 'trending'],
        ['tv', 'trending'],
        ['movie', 'popular'],
        ['tv', 'popular'],
        ['movie', 'now_playing'],
        ['movie', 'top_rated'],
        ['tv', 'top_rated'],
    ];

    private const FEATURED_COUNT = 10;

    /** TMDb refuses pages above 500. */
    public const MAX_PAGE = 500;

    /** Pages of results a filtered or sorted text search reads (20 results each). */
    public const SEARCH_DEPTH = 3;

    public function __construct(private TmdbClient $tmdb) {}

    /**
     * Featured carousel + themed rows. Movies and TV shows mixed when $type is null.
     *
     * @return array{featured: list<array<string, mixed>>, sections: list<array<string, mixed>>}
     */
    public function home(?MediaType $type): array
    {
        $rows = $type
            ? array_map(fn (CatalogList $list) => [$type, $list], CatalogList::for($type))
            : array_map(fn (array $row) => [MediaType::from($row[0]), CatalogList::from($row[1])], self::COMBINED_HOME);

        $sections = [];
        foreach ($rows as [$rowType, $list]) {
            $items = $this->list($rowType, $list)['results'];

            if ($items !== []) {
                $sections[] = [
                    'key' => "{$rowType->value}_{$list->value}",
                    'title' => $list->title($rowType),
                    'type' => $rowType->value,
                    'list' => $list->value,
                    'items' => $items,
                ];
            }
        }

        $trending = $type
            ? ($sections[0]['items'] ?? [])
            : $this->items($this->tmdb->get('trending/all/week', [], 'lists')['results'] ?? []);

        $featured = array_filter($trending, fn (array $item) => ! empty($item['backdrop_path']));

        return [
            'featured' => array_slice(array_values($featured), 0, self::FEATURED_COUNT),
            'sections' => $sections,
        ];
    }

    /** @return array{page: int, total_pages: int, total_results: int, results: list<array<string, mixed>>} */
    public function list(MediaType $type, CatalogList $list, int $page = 1): array
    {
        $query = ['page' => $page, 'region' => config('services.tmdb.region')];

        return $this->page($this->tmdb->get($list->path($type), $query, 'lists'), $type->value);
    }

    /** @return array{page: int, total_pages: int, total_results: int, results: list<array<string, mixed>>} */
    public function discover(MediaType $type, TitleFilters $filters, DiscoverSort $sort, int $page = 1): array
    {
        $query = [
            'page' => $page,
            'include_adult' => 'false',
            'sort_by' => $sort->sortBy($type),
        ] + $filters->discoverQuery($type);

        if ($sort === DiscoverSort::Rating) {
            // Without a floor, the top is made of titles with 1 vote at 10/10.
            $query['vote_count.gte'] = max(200, $query['vote_count.gte'] ?? 0);
        }

        if ($sort === DiscoverSort::Oldest) {
            // Known titles, not the first films ever registered (often without image or summary).
            $query['vote_count.gte'] = max(100, $query['vote_count.gte'] ?? 0);
        }

        if ($sort === DiscoverSort::Recent) {
            // Already released only, not announced titles dated in years.
            $field = $type === MediaType::Movie ? 'primary_release_date.lte' : 'first_air_date.lte';
            $query[$field] = min((string) ($query[$field] ?? '9999-12-31'), now()->toDateString());
        }

        return $this->page($this->tmdb->get("discover/{$type->value}", $query, 'lists'), $type->value);
    }

    /** @return array{movie: list<array<string, mixed>>, tv: list<array<string, mixed>>} */
    public function genres(): array
    {
        return [
            'movie' => $this->tmdb->get('genre/movie/list', [], 'genres')['genres'] ?? [],
            'tv' => $this->tmdb->get('genre/tv/list', [], 'genres')['genres'] ?? [],
        ];
    }

    /** @return array<string, mixed> */
    public function movie(int $id): array
    {
        $movie = $this->tmdb->get("movie/{$id}", [
            'append_to_response' => 'credits,videos,release_dates,recommendations,similar,images',
            'include_video_language' => 'fr,en,null',
            // Title logos and textless backdrops (TmdbFormat::logo, textlessBackdrop).
            'include_image_language' => 'fr,en,null',
        ]);

        return $this->withRecommendations($movie, 'movie');
    }

    /** @return array<string, mixed> */
    public function show(int $id): array
    {
        $show = $this->tmdb->get("tv/{$id}", [
            'append_to_response' => 'aggregate_credits,videos,content_ratings,recommendations,similar,external_ids,images',
            'include_video_language' => 'fr,en,null',
            'include_image_language' => 'fr,en,null',
        ]);

        return $this->withRecommendations($show, 'tv');
    }

    /** @return array<string, mixed> */
    public function season(int $showId, int $seasonNumber): array
    {
        $season = $this->tmdb->get("tv/{$showId}/season/{$seasonNumber}");
        $season['show_id'] = $showId;

        return $season;
    }

    /** @return array<string, mixed> */
    public function person(int $id): array
    {
        $person = $this->tmdb->get("person/{$id}", ['append_to_response' => 'combined_credits']);

        // An actor can appear several times in the same title (several roles).
        $credits = [];
        foreach ($this->items($person['combined_credits']['cast'] ?? []) as $credit) {
            $credits[$credit['media_type'].'-'.$credit['id']] ??= $credit;
        }
        $credits = array_values($credits);

        $byVotes = $credits;
        usort($byVotes, fn (array $a, array $b) => ($b['vote_count'] ?? 0) <=> ($a['vote_count'] ?? 0));

        $byDate = $credits;
        usort($byDate, fn (array $a, array $b) => self::date($b) <=> self::date($a));

        unset($person['combined_credits']);

        return $person + [
            'known_for' => array_slice($byVotes, 0, 10),
            'filmography' => array_slice($byDate, 0, 100),
        ];
    }

    /**
     * First page of each kind of result, for the global search screen. With filters or another
     * order than relevance: the filtered and sorted titles (see searchTitles), and no people when
     * a filter is set.
     *
     * @return array<string, array{page: int, total_pages: int, total_results: int, results: list<array<string, mixed>>}>
     */
    public function search(string $query, ?TitleFilters $filters = null, SearchSort $sort = SearchSort::Relevance): array
    {
        $filters ??= new TitleFilters;

        if ($filters->isEmpty() && $sort === SearchSort::Relevance) {
            return [
                'movies' => $this->searchType(SearchType::Movie, $query),
                'shows' => $this->searchType(SearchType::Tv, $query),
                'people' => $this->searchType(SearchType::Person, $query),
            ];
        }

        return [
            'movies' => $this->searchTitles(SearchType::Movie, $query, $filters, $sort),
            'shows' => $this->searchTitles(SearchType::Tv, $query, $filters, $sort),
            // Genre, years and rating don't apply to people: none match a filtered search.
            'people' => $filters->isEmpty()
                ? $this->searchType(SearchType::Person, $query)
                : ['page' => 1, 'total_pages' => 0, 'total_results' => 0, 'results' => []],
        ];
    }

    /**
     * Movies or TV shows matching a text, filtered and sorted by the API: TMDb's search has
     * neither. Reads the first SEARCH_DEPTH pages (the most relevant results), in one page.
     *
     * @return array{page: int, total_pages: int, total_results: int, results: list<array<string, mixed>>}
     */
    public function searchTitles(SearchType $type, string $query, TitleFilters $filters, SearchSort $sort): array
    {
        $first = $this->searchType($type, $query);
        $results = $first['results'];
        for ($page = 2; $page <= min($first['total_pages'], self::SEARCH_DEPTH); $page++) {
            $results = [...$results, ...$this->searchType($type, $query, $page)['results']];
        }

        $seen = [];
        $results = array_values(array_filter($results, function (array $item) use ($filters, &$seen) {
            $key = (int) $item['id'];
            if (isset($seen[$key])) {
                return false;
            }
            $seen[$key] = true;

            return $filters->matches($item);
        }));

        $results = self::sort($results, $sort);

        return ['page' => 1, 'total_pages' => $results === [] ? 0 : 1, 'total_results' => count($results), 'results' => $results];
    }

    /**
     * @param  list<array<string, mixed>>  $items
     * @return list<array<string, mixed>>
     */
    private static function sort(array $items, SearchSort $sort): array
    {
        $rated = fn (array $item) => (int) ($item['vote_count'] ?? 0) >= TitleFilters::MIN_VOTES;

        // usort is stable: equal items keep TMDb's relevance order.
        match ($sort) {
            SearchSort::Relevance => null,
            SearchSort::Popularity => usort($items, fn ($a, $b) => (float) ($b['popularity'] ?? 0) <=> (float) ($a['popularity'] ?? 0)),
            // Titles with too few votes come after the others, whatever their rating.
            SearchSort::Rating => usort($items, fn ($a, $b) => [! $rated($a), (float) ($b['vote_average'] ?? 0)]
                <=> [! $rated($b), (float) ($a['vote_average'] ?? 0)]),
            // Titles without a date come last.
            SearchSort::Recent => usort($items, fn ($a, $b) => [TitleFilters::date($a) === null, TitleFilters::date($b) ?? '']
                <=> [TitleFilters::date($b) === null, TitleFilters::date($a) ?? '']),
            SearchSort::Oldest => usort($items, fn ($a, $b) => [TitleFilters::date($a) === null, TitleFilters::date($a) ?? '']
                <=> [TitleFilters::date($b) === null, TitleFilters::date($b) ?? '']),
        };

        return $items;
    }

    /** @return array{page: int, total_pages: int, total_results: int, results: list<array<string, mixed>>} */
    public function searchType(SearchType $type, string $query, int $page = 1): array
    {
        $response = $this->tmdb->get("search/{$type->value}", [
            'query' => $query,
            'page' => $page,
            'include_adult' => 'false',
        ], 'search');

        return $this->page($response, $type->value);
    }

    /**
     * @param  array<string, mixed>  $response
     * @return array{page: int, total_pages: int, total_results: int, results: list<array<string, mixed>>}
     */
    private function page(array $response, string $mediaType): array
    {
        $allowed = $mediaType === 'person' ? ['person'] : ['movie', 'tv'];

        return [
            'page' => (int) ($response['page'] ?? 1),
            'total_pages' => (int) min((int) ($response['total_pages'] ?? 0), self::MAX_PAGE),
            'total_results' => (int) ($response['total_results'] ?? 0),
            'results' => $this->items($response['results'] ?? [], $mediaType, $allowed),
        ];
    }

    /**
     * @param  array<int, mixed>  $results
     * @param  list<string>  $allowed
     * @return list<array<string, mixed>>
     */
    private function items(array $results, ?string $mediaType = null, array $allowed = ['movie', 'tv']): array
    {
        $items = [];
        foreach ($results as $item) {
            if (! is_array($item) || ! isset($item['id'])) {
                continue;
            }

            $item['media_type'] = $mediaType ?? ($item['media_type'] ?? null);

            if (in_array($item['media_type'], $allowed, true)) {
                $items[] = $item;
            }
        }

        return $items;
    }

    /**
     * "Vous pourriez aussi aimer": TMDb recommendations, or similar titles when there are none.
     *
     * @param  array<string, mixed>  $title
     * @return array<string, mixed>
     */
    private function withRecommendations(array $title, string $mediaType): array
    {
        $recommended = $this->items($title['recommendations']['results'] ?? [], $mediaType);

        if ($recommended === []) {
            $recommended = $this->items($title['similar']['results'] ?? [], $mediaType);
        }

        unset($title['recommendations'], $title['similar']);
        $title['recommended'] = array_slice($recommended, 0, 20);

        return $title;
    }

    /** @param  array<string, mixed>  $item */
    private static function date(array $item): string
    {
        return (string) ($item['release_date'] ?? $item['first_air_date'] ?? '');
    }
}
