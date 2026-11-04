<?php

namespace App\Services\Tmdb;

use App\Enums\CatalogList;
use App\Enums\DiscoverSort;
use App\Enums\MediaType;
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
    public function discover(MediaType $type, ?int $genre, DiscoverSort $sort, int $page = 1): array
    {
        $query = [
            'page' => $page,
            'include_adult' => 'false',
            'sort_by' => $sort->sortBy($type),
        ];

        if ($genre !== null) {
            $query['with_genres'] = $genre;
        }

        if ($sort === DiscoverSort::Rating) {
            // Without a floor, the top is made of titles with 1 vote at 10/10.
            $query['vote_count.gte'] = 200;
        }

        if ($sort === DiscoverSort::Recent) {
            // Already released only, not announced titles dated in years.
            $field = $type === MediaType::Movie ? 'primary_release_date.lte' : 'first_air_date.lte';
            $query[$field] = now()->toDateString();
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
            'append_to_response' => 'credits,videos,release_dates,recommendations,similar',
            'include_video_language' => 'fr,en,null',
        ]);

        return $this->withRecommendations($movie, 'movie');
    }

    /** @return array<string, mixed> */
    public function show(int $id): array
    {
        $show = $this->tmdb->get("tv/{$id}", [
            'append_to_response' => 'aggregate_credits,videos,content_ratings,recommendations,similar,external_ids',
            'include_video_language' => 'fr,en,null',
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
     * First page of each kind of result, for the global search screen.
     *
     * @return array<string, array{page: int, total_pages: int, total_results: int, results: list<array<string, mixed>>}>
     */
    public function search(string $query): array
    {
        return [
            'movies' => $this->searchType(SearchType::Movie, $query),
            'shows' => $this->searchType(SearchType::Tv, $query),
            'people' => $this->searchType(SearchType::Person, $query),
        ];
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
