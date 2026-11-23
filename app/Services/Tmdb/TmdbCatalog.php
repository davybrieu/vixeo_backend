<?php

namespace App\Services\Tmdb;

use App\Enums\CatalogList;
use App\Enums\MediaType;
use App\Enums\PersonCreditsSort;
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

    /**
     * Browsing with a minimum rating: TMDb pages read for one page of results. TMDb only knows its
     * own rating: the API asks it for titles close to the minimum, then keeps those whose rating
     * (IMDb's, else TMDb's) reaches it.
     */
    public const DISCOVER_DEPTH = 3;

    /** Below the minimum rating, TMDb ratings still asked (IMDb's may be higher). */
    public const DISCOVER_RATING_MARGIN = 1.0;

    public function __construct(private TmdbClient $tmdb, private TitleSummaries $summaries) {}

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

        if ($type === null) {
            $sections = $this->withExtraRows($sections);
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

    /**
     * Whether a list of the home (`featured`, or the index of a section) is given with the logo
     * and summary of its titles: the carousel and the curated rows. The many other rows
     * (platforms, networks, studios, languages) are `light`: the app completes them.
     *
     * @param  array{featured: list<array<string, mixed>>, sections: list<array<string, mixed>>}  $home
     */
    public static function summarized(array $home, int|string $list): bool
    {
        return $list === 'featured' || empty($home['sections'][$list]['light']);
    }

    /** @return array{page: int, total_pages: int, total_results: int, results: list<array<string, mixed>>} */
    public function list(MediaType $type, CatalogList $list, int $page = 1): array
    {
        $query = ['page' => $page, 'region' => config('services.tmdb.region')];

        return $this->page($this->tmdb->get($list->path($type), $query, 'lists'), $type->value);
    }

    /**
     * A page of a home row that is not a curated list (platform, network, studio, language…).
     *
     * @return array{page: int, total_pages: int, total_results: int, results: list<array<string, mixed>>}
     */
    public function row(CatalogRow $row, int $page = 1): array
    {
        return $this->page($this->tmdb->get($row->path, ['page' => $page] + $row->query, 'lists'), $row->type->value);
    }

    /**
     * Adds the rows of CatalogRows to the home: the trends of the day right after the trends of
     * the week, the others at the end. Their first pages are asked together; a row TMDb can't
     * give, or an empty one, is left out. They are `light`: their items carry no logo nor summary
     * (one TMDb call per title would be too many for this number of rows).
     *
     * @param  list<array<string, mixed>>  $sections
     * @return list<array<string, mixed>>
     */
    private function withExtraRows(array $sections): array
    {
        $rows = [];
        foreach (CatalogRows::all() as $row) {
            $rows[$row->key()] = $row;
        }

        $pages = $this->tmdb->getEach(
            array_map(fn (CatalogRow $row) => [$row->path, ['page' => 1] + $row->query], $rows),
            'lists',
        );

        $trends = [];
        $others = [];
        foreach ($rows as $key => $row) {
            $items = $this->items($pages[$key]['results'] ?? [], $row->type->value);

            if ($items === []) {
                continue;
            }

            $section = [
                'key' => $key,
                'title' => $row->title,
                'type' => $row->type->value,
                'list' => $row->list,
                'items' => $items,
                'light' => true,
            ];

            if ($row->list === 'trending_day') {
                $trends[] = $section;
            } else {
                $others[] = $section;
            }
        }

        $afterTrending = count(array_filter($sections, fn (array $section) => $section['list'] === 'trending'));

        return [...array_slice($sections, 0, $afterTrending), ...$trends, ...array_slice($sections, $afterTrending), ...$others];
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
        $credits = $this->sortedCredits($person);
        unset($person['combined_credits']);

        return $person + [
            'known_for' => array_slice($credits[PersonCreditsSort::Popular->value], 0, 10),
            'filmography' => array_slice($credits[PersonCreditsSort::Date->value], 0, 100),
        ];
    }

    /**
     * Every title of a person (as cast), each once, in the given order: the whole « Connu pour »
     * or « Filmographie » (the person page only shows their start). Same TMDb call as person().
     *
     * @return list<array<string, mixed>>
     */
    public function personCredits(int $id, PersonCreditsSort $sort): array
    {
        $person = $this->tmdb->get("person/{$id}", ['append_to_response' => 'combined_credits']);

        return $this->sortedCredits($person)[$sort->value];
    }

    /**
     * A page of « Vous pourriez aussi aimer »: TMDb recommendations, or similar titles when TMDb
     * has no recommendation for this title (the same choice as the title page).
     *
     * @return array{page: int, total_pages: int, total_results: int, results: list<array<string, mixed>>}
     */
    public function recommendations(MediaType $type, int $id, int $page = 1): array
    {
        $recommendations = $this->page(
            $this->tmdb->get("{$type->value}/{$id}/recommendations", ['page' => $page], 'lists'),
            $type->value,
        );

        if ($recommendations['total_results'] > 0) {
            return $recommendations;
        }

        return $this->page($this->tmdb->get("{$type->value}/{$id}/similar", ['page' => $page], 'lists'), $type->value);
    }

    /**
     * A person's credits, each title once (an actor can play several roles in the same title),
     * by votes and by date.
     *
     * @param  array<string, mixed>  $person
     * @return array<string, list<array<string, mixed>>>
     */
    private function sortedCredits(array $person): array
    {
        $credits = [];
        foreach ($this->items($person['combined_credits']['cast'] ?? []) as $credit) {
            $credits[$credit['media_type'].'-'.$credit['id']] ??= $credit;
        }
        $credits = array_values($credits);

        $byVotes = $credits;
        usort($byVotes, fn (array $a, array $b) => ($b['vote_count'] ?? 0) <=> ($a['vote_count'] ?? 0));

        $byDate = $credits;
        usort($byDate, fn (array $a, array $b) => self::date($b) <=> self::date($a));

        return [PersonCreditsSort::Popular->value => $byVotes, PersonCreditsSort::Date->value => $byDate];
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
     * neither. Reads the first SEARCH_DEPTH pages (the most relevant results), in one page. The
     * rating (minimum, « Mieux notés ») is IMDb's, else TMDb's: the titles get their summary and
     * IMDb rating first (TitleSummaries, cached; the apps show it anyway).
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
        $results = array_values(array_filter($results, function (array $item) use (&$seen) {
            $key = (int) $item['id'];
            if (isset($seen[$key])) {
                return false;
            }
            $seen[$key] = true;

            return true;
        }));
        if ($filters->hasRatingBounds() || $sort === SearchSort::Rating) {
            $results = $this->summaries->add($results);
        }
        $results = array_values(array_filter($results, $filters->matches(...)));

        $results = self::sort($results, $sort);

        return ['page' => 1, 'total_pages' => $results === [] ? 0 : 1, 'total_results' => count($results), 'results' => $results];
    }

    /**
     * @param  list<array<string, mixed>>  $items
     * @return list<array<string, mixed>>
     */
    private static function sort(array $items, SearchSort $sort): array
    {
        // usort is stable: equal items keep TMDb's relevance order.
        match ($sort) {
            SearchSort::Relevance => null,
            SearchSort::Popularity => usort($items, fn ($a, $b) => (float) ($b['popularity'] ?? 0) <=> (float) ($a['popularity'] ?? 0)),
            // IMDb's rating, else TMDb's (the one the cards show); unrated titles last.
            SearchSort::Rating => usort($items, fn ($a, $b) => (TitleFilters::rating($b) ?? -1) <=> (TitleFilters::rating($a) ?? -1)),
            // Titles without a date come last.
            SearchSort::Recent => usort($items, fn ($a, $b) => [TitleFilters::date($a) === null, TitleFilters::date($b) ?? '']
                <=> [TitleFilters::date($b) === null, TitleFilters::date($a) ?? '']),
            SearchSort::Oldest => usort($items, fn ($a, $b) => [TitleFilters::date($a) === null, TitleFilters::date($a) ?? '']
                <=> [TitleFilters::date($b) === null, TitleFilters::date($b) ?? '']),
        };

        return $items;
    }

    /**
     * First page of movies and of TV shows matching filters, without a text (the search page with
     * filters only): see discover.
     *
     * @return array<string, array{page: int, total_pages: int, total_results: int, results: list<array<string, mixed>>}>
     */
    public function discoverAll(TitleFilters $filters, SearchSort $sort): array
    {
        return [
            'movies' => $this->discover(MediaType::Movie, $filters, $sort),
            'shows' => $this->discover(MediaType::Tv, $filters, $sort),
            'people' => ['page' => 1, 'total_pages' => 0, 'total_results' => 0, 'results' => []],
        ];
    }

    /**
     * Movies or TV shows matching filters, without a text (TMDb discover): genre and years are
     * TMDb's filters, released titles only. The rating is IMDb's, else TMDb's (TitleFilters::rating),
     * and so are its voters: with bounds of the rating or of the number of voters, each page of
     * results reads DISCOVER_DEPTH TMDb pages and keeps the titles within them (TMDb is asked for
     * titles rated within DISCOVER_RATING_MARGIN of the rating bounds; not for a number of voters,
     * IMDb's counts being far higher than TMDb's); « Mieux notés » is TMDb's order, refined page by
     * page.
     *
     * @return array{page: int, total_pages: int, total_results: int, results: list<array<string, mixed>>}
     */
    public function discover(MediaType $type, TitleFilters $filters, SearchSort $sort, int $page = 1): array
    {
        // Bounds of the rating or of the number of voters: checked by the API on IMDb's (else TMDb's).
        $byRating = $filters->hasRatingBounds();
        $depth = $byRating ? self::DISCOVER_DEPTH : 1;
        $query = $this->discoverQuery($type, $filters, $sort);

        $results = [];
        $totalPages = 0;
        $totalResults = 0;
        for ($tmdbPage = ($page - 1) * $depth + 1, $last = $tmdbPage + $depth - 1; $tmdbPage <= min($last, self::MAX_PAGE); $tmdbPage++) {
            $response = $this->page($this->tmdb->get("discover/{$type->value}", $query + ['page' => $tmdbPage], 'lists'), $type->value);
            $results = [...$results, ...$response['results']];
            $totalPages = $response['total_pages'];
            $totalResults = $response['total_results'];
            if ($tmdbPage >= $totalPages) {
                break;
            }
        }

        if ($byRating || $sort === SearchSort::Rating) {
            // IMDb's rating (and the summary the apps show anyway), from the same cache.
            $results = $this->summaries->add($results);
            // Genre and years: already TMDb's filters.
            $results = array_values(array_filter($results, $filters->withinRatingBounds(...)));
            $results = self::sort($results, $sort);
        }

        return [
            'page' => $page,
            'total_pages' => (int) ceil($totalPages / $depth),
            // With a minimum rating: TMDb's count, before the API's own filter (an upper bound).
            'total_results' => $totalResults,
            'results' => $results,
        ];
    }

    /** @return array<string, int|float|string> TMDb discover parameters */
    private function discoverQuery(MediaType $type, TitleFilters $filters, SearchSort $sort): array
    {
        $date = $type === MediaType::Movie ? 'primary_release_date' : 'first_air_date';
        $today = now()->toDateString();
        $query = ['include_adult' => 'false'];

        if ($filters->genre !== null) {
            $query['with_genres'] = $filters->genre;
        }
        if ($filters->yearFrom !== null) {
            $query["{$date}.gte"] = sprintf('%04d-01-01', $filters->yearFrom);
        }
        // Released titles only: « Plus récents » would start with announced ones.
        $query["{$date}.lte"] = $filters->yearTo !== null ? min(sprintf('%04d-12-31', $filters->yearTo), $today) : $today;

        $query['sort_by'] = match ($sort) {
            SearchSort::Relevance, SearchSort::Popularity => 'popularity.desc',
            SearchSort::Rating => 'vote_average.desc',
            SearchSort::Recent => "{$date}.desc",
            SearchSort::Oldest => "{$date}.asc",
        };
        // TMDb's best rated first would be titles with a single vote of 10.
        if ($sort === SearchSort::Rating) {
            $query['vote_count.gte'] = 100;
        }
        if ($filters->minRating !== null) {
            $query['vote_average.gte'] = max(0, $filters->minRating - self::DISCOVER_RATING_MARGIN);
        }
        if ($filters->maxRating !== null) {
            $query['vote_average.lte'] = min(10, $filters->maxRating + self::DISCOVER_RATING_MARGIN);
        }
        // Bounds (rating or voters): titles barely rated on TMDb are left out; the number of voters
        // itself is checked by the API, IMDb's counts being far higher than TMDb's.
        if ($filters->hasRatingBounds()) {
            $query['vote_count.gte'] = max($query['vote_count.gte'] ?? 0, 10);
        }

        return $query;
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
