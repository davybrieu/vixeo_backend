<?php

namespace App\Services\Tmdb;

use App\Enums\MediaType;

/**
 * The home rows built on top of TMDb's curated lists, in display order: trends of the day,
 * streaming platforms, TV networks, studios ("licences") and languages.
 *
 * Ids are TMDb's (watch providers, networks, production companies). To add a row, add a line:
 * its `list` name must stay unique for its type and must never change once published (the apps
 * keep the order and visibility of rows by key).
 */
class CatalogRows
{
    /**
     * Streaming platforms, as TMDb watch providers (JustWatch data, for the configured region).
     *
     * @var array<string, array{string, int}>
     */
    private const PLATFORMS = [
        'netflix' => ['Netflix', 8],
        'prime_video' => ['Prime Video', 119],
        'disney_plus' => ['Disney+', 337],
        'apple_tv' => ['Apple TV+', 350],
        'canal_plus' => ['Canal+', 381],
        'paramount_plus' => ['Paramount+', 531],
        'max' => ['Max', 1899],
    ];

    /**
     * TV networks (the channel a TV show was made for).
     *
     * @var array<string, array{string, int}>
     */
    private const NETWORKS = [
        'hbo' => ['HBO', 49],
        'canal_plus' => ['Canal+', 285],
        'tf1' => ['TF1', 290],
        'bbc_one' => ['BBC One', 4],
        'amc' => ['AMC', 174],
        'fx' => ['FX', 88],
    ];

    /**
     * Studios behind well-known licences (production companies; "|" = one or the other).
     *
     * @var array<string, array{string, string}>
     */
    private const STUDIOS = [
        'marvel' => ['Marvel', '420'],
        'dc' => ['DC', '9993|128064'],
        'lucasfilm' => ['Lucasfilm', '1'],
        'pixar' => ['Pixar', '3'],
        'disney' => ['Disney', '2'],
        'ghibli' => ['Studio Ghibli', '10342'],
        'dreamworks' => ['DreamWorks', '521'],
    ];

    /**
     * Every row, in display order.
     *
     * @return list<CatalogRow>
     */
    public static function all(): array
    {
        $movie = MediaType::Movie;
        $tv = MediaType::Tv;
        $region = (string) config('services.tmdb.region');

        $rows = [
            new CatalogRow($movie, 'trending_day', __('Trending movies today'), 'trending/movie/day'),
            new CatalogRow($tv, 'trending_day', __('Trending TV shows today'), 'trending/tv/day'),
        ];

        foreach (self::PLATFORMS as $slug => [$name, $id]) {
            $query = ['with_watch_providers' => $id, 'watch_region' => $region];
            $rows[] = self::discover($movie, "platform_{$slug}", __(':name movies', ['name' => $name]), $query);
            $rows[] = self::discover($tv, "platform_{$slug}", __(':name TV shows', ['name' => $name]), $query);
        }

        foreach (self::NETWORKS as $slug => [$name, $id]) {
            $rows[] = self::discover($tv, "network_{$slug}", __(':name TV shows', ['name' => $name]), ['with_networks' => $id]);
        }

        foreach (self::STUDIOS as $slug => [$name, $ids]) {
            $rows[] = self::discover($movie, "studio_{$slug}", __(':name movies', ['name' => $name]), ['with_companies' => $ids]);
        }

        // By original language. A floor of votes keeps known titles only.
        $language = fn (string $code) => ['with_original_language' => $code, 'vote_count.gte' => 50];

        return [
            ...$rows,
            self::discover($movie, 'language_fr', __('French movies'), $language('fr')),
            self::discover($tv, 'language_fr', __('French TV shows'), $language('fr')),
            self::discover($tv, 'language_es', __('Spanish TV shows'), $language('es')),
            self::discover($tv, 'language_ko', __('Korean TV shows'), $language('ko')),
            // Animation (genre 16) in Japanese.
            self::discover($tv, 'anime', __('Japanese animation'), $language('ja') + ['with_genres' => 16]),
        ];
    }

    /** The row called `$list` for `$type` (the "see all" page of a home row). */
    public static function find(MediaType $type, string $list): ?CatalogRow
    {
        foreach (self::all() as $row) {
            if ($row->type === $type && $row->list === $list) {
                return $row;
            }
        }

        return null;
    }

    /** @param  array<string, scalar>  $query */
    private static function discover(MediaType $type, string $list, string $title, array $query): CatalogRow
    {
        return new CatalogRow($type, $list, $title, "discover/{$type->value}", $query + [
            'include_adult' => 'false',
            'sort_by' => 'popularity.desc',
        ]);
    }
}
