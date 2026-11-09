<?php

namespace App\Services\Tmdb;

use App\Enums\MediaType;

/**
 * What the large-screen hero and the phone carousel show of a title, given with the list items
 * (home, search) so that everything appears at once, without a request per card: title logo,
 * poster without text, length (runtime or seasons), genres and age rating. One TMDb call per title
 * (details with images and certifications), in batches, cached a day and shared by all accounts:
 * only titles new to the lists cost a request.
 */
class TitleSummaries
{
    private const QUERY = [
        'movie' => ['append_to_response' => 'images,release_dates', 'include_image_language' => 'fr,en,null'],
        'tv' => ['append_to_response' => 'images,content_ratings', 'include_image_language' => 'fr,en,null'],
    ];

    public function __construct(private TmdbClient $tmdb) {}

    /**
     * Sets `logo`, `textless_poster` and `summary` on each movie / TV show (null when TMDb has
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

        return array_map(fn (array $items) => array_map(function (array $item) use ($details) {
            $path = self::path($item);
            $title = $path === null ? null : ($details[$path] ?? null);
            $item['logo'] = is_array($title) ? TmdbFormat::logo($title) : null;
            $item['textless_poster'] = is_array($title) ? TmdbFormat::textlessPoster($title) : null;
            $item['summary'] = is_array($title) ? self::summary($title) : null;

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
     * Titles by type and id, as list items with their logo and summary (Ma liste, Continuer à
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
                'textless_poster' => TmdbFormat::textlessPoster($title),
                'summary' => self::summary($title),
            ]];
        }

        return $items;
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
