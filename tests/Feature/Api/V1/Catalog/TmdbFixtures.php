<?php

namespace Tests\Feature\Api\V1\Catalog;

/**
 * Minimal TMDb payloads, shaped like the real API (only the fields we read).
 */
final class TmdbFixtures
{
    public static function movie(int $id = 550, array $overrides = []): array
    {
        return array_merge([
            'id' => $id,
            'title' => 'Fight Club',
            'original_title' => 'Fight Club',
            'overview' => 'Le narrateur, sans identité précise…',
            'release_date' => '1999-10-15',
            'poster_path' => '/poster.jpg',
            'backdrop_path' => '/backdrop.jpg',
            'vote_average' => 8.438,
            'vote_count' => 30000,
            'genre_ids' => [18, 53],
        ], $overrides);
    }

    public static function show(int $id = 1399, array $overrides = []): array
    {
        return array_merge([
            'id' => $id,
            'name' => 'Game of Thrones',
            'original_name' => 'Game of Thrones',
            'overview' => 'Neuf familles nobles…',
            'first_air_date' => '2011-04-17',
            'poster_path' => '/got.jpg',
            'backdrop_path' => '/got-backdrop.jpg',
            'vote_average' => 8.4,
            'vote_count' => 25000,
            'genre_ids' => [10765],
        ], $overrides);
    }

    public static function page(array $results, int $totalPages = 1, int $page = 1): array
    {
        return [
            'page' => $page,
            'results' => $results,
            'total_pages' => $totalPages,
            'total_results' => count($results) * $totalPages,
        ];
    }

    public static function movieDetails(): array
    {
        return self::movie(550, [
            'tagline' => 'Mischief. Mayhem. Soap.',
            'runtime' => 139,
            'status' => 'Released',
            'imdb_id' => 'tt0137523',
            'original_language' => 'en',
            'genres' => [['id' => 18, 'name' => 'Drame']],
            'credits' => [
                'cast' => [
                    ['id' => 819, 'name' => 'Edward Norton', 'character' => 'The Narrator', 'profile_path' => '/norton.jpg', 'order' => 0],
                    ['id' => 287, 'name' => 'Brad Pitt', 'character' => 'Tyler Durden', 'profile_path' => null, 'order' => 1],
                ],
                'crew' => [
                    ['id' => 7467, 'name' => 'David Fincher', 'job' => 'Director', 'profile_path' => '/fincher.jpg'],
                    ['id' => 7468, 'name' => 'Someone', 'job' => 'Producer', 'profile_path' => null],
                ],
            ],
            'videos' => ['results' => [
                ['key' => 'en-trailer', 'name' => 'Trailer', 'site' => 'YouTube', 'type' => 'Trailer', 'iso_639_1' => 'en', 'official' => true],
                ['key' => 'fr-teaser', 'name' => 'Teaser VF', 'site' => 'YouTube', 'type' => 'Teaser', 'iso_639_1' => 'fr', 'official' => true],
                ['key' => 'fr-trailer', 'name' => 'Bande-annonce VF', 'site' => 'YouTube', 'type' => 'Trailer', 'iso_639_1' => 'fr', 'official' => true],
                ['key' => 'vimeo', 'name' => 'Vimeo', 'site' => 'Vimeo', 'type' => 'Trailer', 'iso_639_1' => 'fr', 'official' => true],
            ]],
            'release_dates' => ['results' => [
                ['iso_3166_1' => 'US', 'release_dates' => [['certification' => 'R']]],
                ['iso_3166_1' => 'FR', 'release_dates' => [['certification' => ''], ['certification' => '16']]],
            ]],
            'recommendations' => ['results' => []],
            'similar' => ['results' => [self::movie(807, ['title' => 'Seven'])]],
        ]);
    }

    public static function showDetails(): array
    {
        return self::show(1399, [
            'last_air_date' => '2019-05-19',
            'status' => 'Ended',
            'in_production' => false,
            'number_of_seasons' => 8,
            'number_of_episodes' => 73,
            'episode_run_time' => [60],
            'genres' => [['id' => 10765, 'name' => 'Science-Fiction & Fantastique']],
            'created_by' => [['id' => 9813, 'name' => 'David Benioff', 'profile_path' => null]],
            'aggregate_credits' => ['cast' => [
                ['id' => 22970, 'name' => 'Peter Dinklage', 'profile_path' => '/pd.jpg', 'roles' => [['character' => 'Tyrion Lannister']]],
            ]],
            'seasons' => [
                ['id' => 3627, 'season_number' => 0, 'name' => 'Épisodes spéciaux', 'episode_count' => 14, 'air_date' => '2010-12-05', 'poster_path' => null, 'overview' => ''],
                ['id' => 3624, 'season_number' => 1, 'name' => 'Saison 1', 'episode_count' => 10, 'air_date' => '2011-04-17', 'poster_path' => '/s1.jpg', 'overview' => ''],
            ],
            'next_episode_to_air' => null,
            'content_ratings' => ['results' => [
                ['iso_3166_1' => 'US', 'rating' => 'TV-MA'],
            ]],
            'external_ids' => ['imdb_id' => 'tt0944947', 'tvdb_id' => 121361],
            'videos' => ['results' => []],
            'recommendations' => ['results' => [self::show(1402, ['name' => 'The Walking Dead'])]],
            'similar' => ['results' => []],
        ]);
    }
}
