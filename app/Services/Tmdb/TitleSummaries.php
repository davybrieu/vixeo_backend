<?php

namespace App\Services\Tmdb;

use App\Enums\MediaType;
use App\Services\Imdb\ImdbRatings;
use Illuminate\Support\Facades\Cache;

/**
 * What the large-screen hero and the phone carousel show of a title, given with the list items
 * (home, search) so that everything appears at once, without a request per card: title logo,
 * poster without text, length (runtime or seasons), genres, age rating and IMDb rating (one database
 * query per list, docs/notes.md). One TMDb call per title (details with images, certifications and
 * External IDs), in batches, cached a day and shared by all accounts: only titles new to the lists
 * cost a request.
 *
 * Every list of titles has the IMDb rating (developer's request, 21/11/2026: TMDb's is only a
 * fallback): with the summary ([add]), or alone for long lists ([ratings], a person's titles).
 */
class TitleSummaries
{
    private const QUERY = [
        'movie' => ['append_to_response' => 'images,release_dates', 'include_image_language' => 'fr,en,null'],
        'tv' => ['append_to_response' => 'images,content_ratings,external_ids', 'include_image_language' => 'fr,en,null'],
    ];

    /** Cache of the IMDb id of a TMDb title ('' when IMDb doesn't know it): it never changes. */
    private const IMDB_ID_KEY = 'imdb-id:';

    private const IMDB_ID_TTL = 60 * 60 * 24 * 90;

    public function __construct(private TmdbClient $tmdb, private ImdbRatings $imdb) {}

    /**
     * Sets `logo`, `summary` and `imdb_rating` on each movie / TV show (null when TMDb has
     * none, or could not be reached: the app then reads the title page).
     *
     * @param  array<array-key, list<array<string, mixed>>>  $lists
     * @return array<array-key, list<array<string, mixed>>>
     */
    public function addMany(array $lists): array
    {
        $paths = ['movie' => [], 'tv' => []];
        foreach ($lists as $items) {
            foreach ($items as $item) {
                if ($path = self::path($item)) {
                    $paths[$item['media_type']][$path] = true;
                }
            }
        }

        $details = $this->fetch($paths);
        if ($details === []) {
            return $lists;
        }
        $ratings = $this->imdbRatings($details);

        return array_map(fn (array $items) => array_map(function (array $item) use ($details, $ratings) {
            $path = self::path($item);
            $title = $path === null ? null : ($details[$path] ?? null);
            $item['logo'] = is_array($title) ? TmdbFormat::logo($title) : null;
            $item['summary'] = is_array($title) ? self::summary($title) : null;
            if (is_array($title)) {
                $item['imdb_rating'] = $ratings[$path] ?? null;
            }

            return $item;
        }, $items), $lists);
    }

    /**
     * @param  list<array<string, mixed>>  $items
     * @return list<array<string, mixed>>
     */
    public function add(array $items): array
    {
        return $this->addMany([$items])[0];
    }

    /**
     * Sets `imdb_rating` only (no logo nor summary) on each movie / TV show, for long lists (all
     * the titles of a person). The IMDb id of a title is kept 90 days: TMDb is only asked for the
     * details of titles never seen (same cache as the summaries), the ratings come from the IMDb
     * tables in one query. A title TMDb could not give keeps no `imdb_rating` (no `ratings`).
     *
     * @param  list<array<string, mixed>>  $items
     * @return list<array<string, mixed>>
     */
    public function ratings(array $items): array
    {
        $paths = [];
        foreach ($items as $item) {
            if ($path = self::path($item)) {
                $paths[$path] = true;
            }
        }
        if ($paths === []) {
            return $items;
        }
        $ids = $this->imdbIds(array_keys($paths));
        $ratings = $this->imdb->many(array_values(array_filter($ids)));

        return array_map(function (array $item) use ($ids, $ratings) {
            $path = self::path($item);
            if ($path !== null && array_key_exists($path, $ids)) {
                $item['imdb_rating'] = $ids[$path] === '' ? null : ($ratings[$ids[$path]] ?? null);
            }

            return $item;
        }, $items);
    }

    /**
     * Titles by type and id, as list items with their logo and summary (« À voir », Continuer à
     * regarder), in the order asked. Titles TMDb doesn't know are left out.
     *
     * @param  list<array{MediaType, int}>  $titles
     * @return list<array<string, mixed>>
     */
    public function titles(array $titles): array
    {
        $paths = ['movie' => [], 'tv' => []];
        foreach ($titles as [$type, $id]) {
            $paths[$type->value]["{$type->value}/{$id}"] = true;
        }
        $details = $this->fetch($paths);
        $ratings = $this->imdbRatings($details);

        $items = [];
        foreach ($titles as [$type, $id]) {
            $title = $details["{$type->value}/{$id}"] ?? null;
            if (! is_array($title) || ! isset($title['id'])) {
                continue;
            }
            // TMDb pages give `genres`; lists give `genre_ids`.
            $items[] = [...$title, ...[
                'media_type' => $type->value,
                'genre_ids' => array_values(array_filter(array_map(
                    fn (mixed $genre) => is_array($genre) ? TmdbFormat::int($genre, 'id') : null,
                    TmdbFormat::array($title, 'genres'),
                ))),
                'logo' => TmdbFormat::logo($title),
                'summary' => self::summary($title),
                'imdb_rating' => $ratings["{$type->value}/{$id}"] ?? null,
            ]];
        }

        return $items;
    }

    /**
     * TMDb details of TV shows by id, from the same cache as the summaries (with images and
     * certifications). Null for a show TMDb doesn't know or could not give.
     *
     * @param  list<int>  $ids
     * @return array<int, array<string, mixed>|null>
     */
    public function shows(array $ids): array
    {
        $paths = [];
        foreach ($ids as $id) {
            $paths["tv/{$id}"] = true;
        }
        $details = $this->fetch(['movie' => [], 'tv' => $paths]);

        $shows = [];
        foreach ($ids as $id) {
            $show = $details["tv/{$id}"] ?? null;
            $shows[$id] = is_array($show) && isset($show['id']) ? $show : null;
        }

        return $shows;
    }

    /**
     * TMDb details (with images and certifications) of each path, null when TMDb fails.
     *
     * @param  array{movie: array<string, true>, tv: array<string, true>}  $paths
     * @return array<string, array<string, mixed>|null>
     */
    private function fetch(array $paths): array
    {
        $details = [];
        foreach ($paths as $type => $typePaths) {
            if ($typePaths !== []) {
                $details += $this->tmdb->getMany(array_keys($typePaths), 'details', 20, self::QUERY[$type]);
            }
        }

        return $details;
    }

    /**
     * IMDb id of each path ('' when IMDb doesn't know the title): from the cache, else from TMDb's
     * details, then kept. A title TMDb could not give is left out.
     *
     * @param  list<string>  $paths
     * @return array<string, string>
     */
    private function imdbIds(array $paths): array
    {
        $cached = Cache::many(array_map(fn (string $path) => self::IMDB_ID_KEY.$path, $paths));
        $ids = [];
        $missing = ['movie' => [], 'tv' => []];
        foreach ($paths as $path) {
            $id = $cached[self::IMDB_ID_KEY.$path] ?? null;
            if (is_string($id)) {
                $ids[$path] = $id;
            } else {
                $missing[str_starts_with($path, 'tv/') ? 'tv' : 'movie'][$path] = true;
            }
        }
        if ($missing['movie'] === [] && $missing['tv'] === []) {
            return $ids;
        }

        $found = [];
        foreach ($this->fetch($missing) as $path => $title) {
            if (is_array($title) && isset($title['id'])) {
                $id = $title['imdb_id'] ?? $title['external_ids']['imdb_id'] ?? null;
                $found[$path] = is_string($id) ? $id : '';
            }
        }
        if ($found !== []) {
            Cache::putMany(array_combine(
                array_map(fn (string $path) => self::IMDB_ID_KEY.$path, array_keys($found)),
                array_values($found),
            ), self::IMDB_ID_TTL);
        }

        return $ids + $found;
    }

    /**
     * IMDb rating of each title of [details] that IMDb rates, by path, in one query.
     *
     * @param  array<string, array<string, mixed>|null>  $details
     * @return array<string, array{id: string, rating: float, votes: int}>
     */
    private function imdbRatings(array $details): array
    {
        $ids = [];
        foreach ($details as $path => $title) {
            $id = is_array($title) ? ($title['imdb_id'] ?? $title['external_ids']['imdb_id'] ?? null) : null;
            if (is_string($id)) {
                $ids[$path] = $id;
            }
        }
        $ratings = $this->imdb->many(array_values($ids));

        return array_filter(array_map(fn (string $id) => $ratings[$id] ?? null, $ids));
    }

    /**
     * @param  array<string, mixed>  $title  TMDb details with release_dates / content_ratings
     * @return array{runtime: int|null, number_of_seasons: int|null, genres: list<string>, age_rating: int|null}
     */
    private static function summary(array $title): array
    {
        $runtime = TmdbFormat::int($title, 'runtime');
        $seasons = TmdbFormat::int($title, 'number_of_seasons');

        return [
            'runtime' => $runtime > 0 ? $runtime : null,
            'number_of_seasons' => $seasons > 0 ? $seasons : null,
            'genres' => array_values(array_filter(array_map(
                fn (mixed $genre) => is_array($genre) ? TmdbFormat::string($genre, 'name') : null,
                TmdbFormat::array($title, 'genres'),
            ))),
            'age_rating' => TmdbFormat::ageRating(TmdbFormat::certification($title)),
        ];
    }

    /** @param  array<string, mixed>  $item */
    private static function path(array $item): ?string
    {
        $type = $item['media_type'] ?? null;

        return in_array($type, ['movie', 'tv'], true) && isset($item['id']) ? $type.'/'.(int) $item['id'] : null;
    }
}
