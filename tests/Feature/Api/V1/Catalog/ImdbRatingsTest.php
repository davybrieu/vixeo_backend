<?php

use App\Models\User;
use App\Services\Imdb\ImdbDatasets;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Laravel\Sanctum\Sanctum;
use Tests\Feature\Api\V1\Catalog\TmdbFixtures as Tmdb;

beforeEach(function () {
    config([
        'services.tmdb.token' => 'test-token',
        'services.tmdb.api_key' => null,
        'services.tmdb.base_url' => 'https://api.themoviedb.org/3',
        'services.imdb.datasets_url' => 'https://datasets.imdbws.com',
    ]);
    Http::preventStrayRequests();
});

/** A gzipped TSV dataset of IMDb, with its header. */
function imdbDataset(string $header, array $rows): string
{
    return gzencode($header."\n".implode("\n", array_map(fn (array $row) => implode("\t", $row), $rows))."\n");
}

function imdbRating(string $tconst, float $rating, int $votes): void
{
    DB::table('imdb_ratings')->insert(['tconst' => $tconst, 'rating' => $rating, 'votes' => $votes, 'import_id' => 1]);
}

function imdbEpisode(string $tconst, string $show, ?int $season, ?int $episode): void
{
    DB::table('imdb_episodes')->insert(['tconst' => $tconst, 'parent_tconst' => $show, 'season' => $season, 'episode' => $episode, 'import_id' => 1]);
}

it('imports the ratings and the rated episodes of the IMDb datasets', function () {
    // Rows of a previous import that IMDb no longer has are removed.
    imdbRating('tt0000001', 5.0, 10);
    imdbEpisode('tt0000002', 'tt0000003', 1, 1);

    Http::fake([
        'datasets.imdbws.com/title.ratings.tsv.gz' => Http::response(imdbDataset("tconst\taverageRating\tnumVotes", [
            ['tt0137523', '8.8', '2400000'],
            ['tt0944947', '9.2', '2300000'],
            ['tt1480055', '8.9', '50000'],
            ['broken'],
            ['nm0000093', '7.0', '12'],
        ])),
        'datasets.imdbws.com/title.episode.tsv.gz' => Http::response(imdbDataset("tconst\tparentTconst\tseasonNumber\tepisodeNumber", [
            ['tt1480055', 'tt0944947', '1', '1'],
            // No rating: not kept.
            ['tt9999999', 'tt0944947', '1', '2'],
            ['tt0000004', 'tt0944947', '\N', '\N'],
        ])),
    ]);

    $this->artisan('imdb:import')->expectsOutputToContain('3 ratings and 1 episodes imported')->assertSuccessful();

    expect(DB::table('imdb_ratings')->orderBy('tconst')->get(['tconst', 'rating', 'votes'])->map(fn ($row) => (array) $row)->all())
        ->toEqual([
            ['tconst' => 'tt0137523', 'rating' => 8.8, 'votes' => 2400000],
            ['tconst' => 'tt0944947', 'rating' => 9.2, 'votes' => 2300000],
            ['tconst' => 'tt1480055', 'rating' => 8.9, 'votes' => 50000],
        ])
        ->and(DB::table('imdb_episodes')->get(['tconst', 'parent_tconst', 'season', 'episode'])->map(fn ($row) => (array) $row)->all())
        ->toEqual([['tconst' => 'tt1480055', 'parent_tconst' => 'tt0944947', 'season' => 1, 'episode' => 1]]);
});

it('keeps the previous ratings when an import fails', function () {
    imdbRating('tt0137523', 8.8, 2400000);
    Http::fake(['datasets.imdbws.com/*' => Http::response(imdbDataset("tconst\taverageRating\tnumVotes", []))]);

    $this->artisan('imdb:import')->assertFailed();

    expect(DB::table('imdb_ratings')->count())->toBe(1);
});

it('checks IMDb title ids', function () {
    expect(ImdbDatasets::isTitleId('tt0137523'))->toBeTrue()
        ->and(ImdbDatasets::isTitleId('nm0000093'))->toBeFalse()
        ->and(ImdbDatasets::isTitleId('tt'))->toBeFalse()
        ->and(ImdbDatasets::isTitleId(null))->toBeFalse();
});

it('gives the IMDb and TMDb ratings of a movie, linked by its IMDb id', function () {
    Sanctum::actingAs(User::factory()->create());
    imdbRating('tt0137523', 8.8, 2400000);
    Http::fake(['api.themoviedb.org/3/movie/550*' => Http::response(['vote_average' => 8.438, 'vote_count' => 30000] + Tmdb::movieDetails())]);

    $this->getJson('/api/v1/catalog/movie/550')
        ->assertOk()
        ->assertJsonPath('data.ratings', [
            'imdb' => ['id' => 'tt0137523', 'rating' => 8.8, 'votes' => 2400000],
            'tmdb' => ['rating' => 8.4, 'votes' => 30000],
        ]);

    // The IMDb id was in the details: no External IDs request.
    Http::assertNotSent(fn (Request $request) => str_contains($request->url(), 'external_ids'));
});

it('finds the IMDb id of a movie in its TMDb External IDs', function () {
    Sanctum::actingAs(User::factory()->create());
    imdbRating('tt0137523', 8.8, 2400000);
    Http::fake([
        'api.themoviedb.org/3/movie/550/external_ids*' => Http::response(['id' => 550, 'imdb_id' => 'tt0137523']),
        'api.themoviedb.org/3/movie/550*' => Http::response(['imdb_id' => null] + Tmdb::movieDetails()),
    ]);

    $this->getJson('/api/v1/catalog/movie/550')
        ->assertOk()
        ->assertJsonPath('data.imdb_id', 'tt0137523')
        ->assertJsonPath('data.ratings.imdb.rating', 8.8);
});

it('gives only the TMDb rating of a title IMDb does not rate', function () {
    Sanctum::actingAs(User::factory()->create());
    Http::fake([
        'api.themoviedb.org/3/movie/550/external_ids*' => Http::response(['id' => 550, 'imdb_id' => null]),
        'api.themoviedb.org/3/movie/550*' => Http::response(['imdb_id' => null, 'vote_average' => 0, 'vote_count' => 0] + Tmdb::movieDetails()),
        'api.themoviedb.org/3/tv/1399*' => Http::response(['vote_average' => 8.456, 'vote_count' => 25000] + Tmdb::showDetails()),
    ]);

    $this->getJson('/api/v1/catalog/movie/550')
        ->assertOk()
        ->assertJsonPath('data.imdb_id', null)
        ->assertJsonPath('data.ratings', ['imdb' => null, 'tmdb' => ['rating' => null, 'votes' => 0]]);

    // The show's IMDb id has no rating in the database.
    $this->getJson('/api/v1/catalog/tv/1399')
        ->assertOk()
        ->assertJsonPath('data.imdb_id', 'tt0944947')
        ->assertJsonPath('data.ratings', ['imdb' => null, 'tmdb' => ['rating' => 8.5, 'votes' => 25000]]);
});

it('gives the IMDb rating of a TV show', function () {
    Sanctum::actingAs(User::factory()->create());
    imdbRating('tt0944947', 9.2, 2300000);
    Http::fake(['api.themoviedb.org/3/tv/1399*' => Http::response(Tmdb::showDetails())]);

    $this->getJson('/api/v1/catalog/tv/1399')
        ->assertOk()
        ->assertJsonPath('data.ratings.imdb', ['id' => 'tt0944947', 'rating' => 9.2, 'votes' => 2300000]);
});

it('gives the IMDb rating of each episode, by its number or its External IDs', function () {
    Sanctum::actingAs(User::factory()->create());
    imdbRating('tt1480055', 8.9, 50000);
    imdbEpisode('tt1480055', 'tt0944947', 1, 1);
    // Episode 2 is not in IMDb's episode list under that number: found by its External IDs.
    imdbRating('tt1668746', 8.6, 40000);
    $episode = fn (int $number, string $airDate, float $vote = 0, int $votes = 0) => [
        'id' => 63055 + $number, 'season_number' => 1, 'episode_number' => $number, 'name' => "Épisode {$number}",
        'air_date' => $airDate, 'vote_average' => $vote, 'vote_count' => $votes,
    ];
    Http::fake([
        'api.themoviedb.org/3/tv/1399/external_ids*' => Http::response(['id' => 1399, 'imdb_id' => 'tt0944947']),
        'api.themoviedb.org/3/tv/1399/season/1/episode/2/external_ids*' => Http::response(['id' => 63057, 'imdb_id' => 'tt1668746']),
        'api.themoviedb.org/3/tv/1399/season/1/episode/3/external_ids*' => Http::response(['id' => 63058, 'imdb_id' => null]),
        'api.themoviedb.org/3/tv/1399/season/1*' => Http::response([
            'id' => 3624, 'season_number' => 1, 'name' => 'Saison 1',
            'episodes' => [
                $episode(1, '2011-04-17', 7.9, 300),
                $episode(2, '2011-04-24', 7.8, 250),
                $episode(3, '2011-05-01', 7.7, 200),
                // Not aired yet: nothing asked.
                $episode(4, now()->addMonth()->toDateString()),
            ],
        ]),
    ]);

    $episodes = $this->getJson('/api/v1/catalog/tv/1399/season/1')->assertOk()->json('data.episodes');

    expect(array_column($episodes, 'ratings'))->toBe([
        ['imdb' => ['id' => 'tt1480055', 'rating' => 8.9, 'votes' => 50000], 'tmdb' => ['rating' => 7.9, 'votes' => 300]],
        ['imdb' => ['id' => 'tt1668746', 'rating' => 8.6, 'votes' => 40000], 'tmdb' => ['rating' => 7.8, 'votes' => 250]],
        ['imdb' => null, 'tmdb' => ['rating' => 7.7, 'votes' => 200]],
        ['imdb' => null, 'tmdb' => ['rating' => null, 'votes' => 0]],
    ])->and($episodes[0]['vote_count'])->toBe(300);

    Http::assertNotSent(fn (Request $request) => str_contains($request->url(), 'episode/1/external_ids')
        || str_contains($request->url(), 'episode/4/external_ids'));
});

it('keeps the TMDb ratings of the episodes when TMDb gives no External IDs', function () {
    Sanctum::actingAs(User::factory()->create());
    Http::fake([
        'api.themoviedb.org/3/tv/1399/external_ids*' => Http::response(['status_code' => 7], 401),
        'api.themoviedb.org/3/tv/1399/season/1/episode/*' => Http::response([], 500),
        'api.themoviedb.org/3/tv/1399/season/1*' => Http::response([
            'id' => 3624, 'season_number' => 1, 'name' => 'Saison 1',
            'episodes' => [['id' => 63056, 'episode_number' => 1, 'air_date' => '2011-04-17', 'vote_average' => 7.9, 'vote_count' => 300]],
        ]),
    ]);

    $this->getJson('/api/v1/catalog/tv/1399/season/1')
        ->assertOk()
        ->assertJsonPath('data.episodes.0.ratings', ['imdb' => null, 'tmdb' => ['rating' => 7.9, 'votes' => 300]]);
});

it('gives the IMDb rating of the list items that have a summary, in one query', function () {
    Sanctum::actingAs(User::factory()->create());
    imdbRating('tt0137523', 8.8, 2400000);
    imdbRating('tt0944947', 9.2, 2300000);
    Http::fake([
        'api.themoviedb.org/3/movie/550*' => Http::response(['vote_average' => 8.438, 'vote_count' => 30000] + Tmdb::movieDetails()),
        'api.themoviedb.org/3/movie/551*' => Http::response(['id' => 551, 'title' => 'Sans IMDb', 'imdb_id' => null]),
        'api.themoviedb.org/3/tv/1399*' => Http::response(Tmdb::showDetails()),
    ]);

    DB::enableQueryLog();
    $items = $this->getJson('/api/v1/catalog/titles?ids=movie:550,tv:1399,movie:551')->assertOk()->json('data');

    expect(array_column($items, 'ratings'))->toBe([
        ['imdb' => ['id' => 'tt0137523', 'rating' => 8.8, 'votes' => 2400000], 'tmdb' => ['rating' => 8.4, 'votes' => 30000]],
        ['imdb' => ['id' => 'tt0944947', 'rating' => 9.2, 'votes' => 2300000], 'tmdb' => ['rating' => 8.4, 'votes' => 25000]],
        ['imdb' => null, 'tmdb' => ['rating' => null, 'votes' => 0]],
    ])->and(collect(DB::getQueryLog())->filter(fn (array $query) => str_contains($query['query'], 'imdb_ratings')))->toHaveCount(1);

    // The show's External IDs come with its details.
    Http::assertSent(fn (Request $request) => str_contains($request->url(), 'tv/1399') && str_contains($request['append_to_response'] ?? '', 'external_ids'));
});

it('gives no ratings with the list items that have no summary', function () {
    Sanctum::actingAs(User::factory()->create());
    Http::fake(['api.themoviedb.org/3/movie/popular*' => Http::response(Tmdb::page([Tmdb::movie(550)]))]);

    $this->getJson('/api/v1/catalog/movie/lists/popular')
        ->assertOk()
        ->assertJsonPath('data.0.ratings', null);
});
