<?php

use App\Models\User;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use Tests\Feature\Api\V1\Catalog\TmdbFixtures as Tmdb;

beforeEach(function () {
    config([
        'services.tmdb.token' => 'test-token',
        'services.tmdb.api_key' => null,
        'services.tmdb.base_url' => 'https://api.themoviedb.org/3',
    ]);
    Http::preventStrayRequests();
    Sanctum::actingAs(User::factory()->create());
});

/** Search results on several TMDb pages, by page number. */
function fakeMovieSearch(array $pages): void
{
    Http::fake([
        'api.themoviedb.org/3/search/movie*' => fn (Request $request) => Http::response(
            Tmdb::page($pages[(int) $request['page']] ?? [], totalPages: count($pages), page: (int) $request['page']),
        ),
        'api.themoviedb.org/3/search/tv*' => Http::response(Tmdb::page([])),
        'api.themoviedb.org/3/search/person*' => Http::response(Tmdb::page([['id' => 287, 'name' => 'Brad Pitt']])),
    ]);
}

it('filters text search results by genre and years, among the first 3 pages', function () {
    fakeMovieSearch([
        1 => [
            Tmdb::movie(1, ['title' => 'Drame 1995', 'genre_ids' => [18], 'release_date' => '1995-03-01', 'vote_average' => 7.5]),
            Tmdb::movie(2, ['title' => 'Action 1995', 'genre_ids' => [28], 'release_date' => '1995-03-01']),
            Tmdb::movie(3, ['title' => 'Drame 2005', 'genre_ids' => [18], 'release_date' => '2005-03-01']),
        ],
        2 => [
            Tmdb::movie(4, ['title' => 'Drame 1992', 'genre_ids' => [18], 'release_date' => '1992-01-01']),
            // Same title again on the next page: listed once.
            Tmdb::movie(1, ['title' => 'Drame 1995', 'genre_ids' => [18], 'release_date' => '1995-03-01']),
        ],
        3 => [Tmdb::movie(6, ['title' => 'Drame 1999', 'genre_ids' => [18, 53], 'release_date' => '1999-12-31', 'vote_average' => 8.1])],
        4 => [Tmdb::movie(7, ['title' => 'Trop loin', 'genre_ids' => [18], 'release_date' => '1994-01-01'])],
    ]);

    $response = $this->getJson('/api/v1/catalog/search?query=drame&genre=18&year_from=1990&year_to=1999')
        ->assertOk()
        ->assertJsonPath('movies.total_results', 3)
        // No people for a filtered search: the filters are about titles.
        ->assertJsonPath('people.data', [])
        ->assertJsonPath('people.total_results', 0);

    expect($response->json('movies.data.*.title'))->toBe(['Drame 1995', 'Drame 1992', 'Drame 1999']);

    // Pages 1 to 3, never the 4th.
    Http::assertSent(fn (Request $request) => str_contains($request->url(), 'search/movie') && $request['page'] == 3);
    Http::assertNotSent(fn (Request $request) => str_contains($request->url(), 'search/movie') && $request['page'] == 4);
    Http::assertNotSent(fn (Request $request) => str_contains($request->url(), 'search/person'));
});

it('sorts text search results', function (string $sort, array $titles) {
    fakeMovieSearch([1 => [
        Tmdb::movie(1, ['title' => 'A', 'popularity' => 10, 'vote_average' => 9.5, 'vote_count' => 12, 'release_date' => '2001-01-01']),
        Tmdb::movie(2, ['title' => 'B', 'popularity' => 90, 'vote_average' => 6.0, 'release_date' => '2020-05-01']),
        Tmdb::movie(3, ['title' => 'C', 'popularity' => 50, 'vote_average' => 7.2, 'release_date' => '']),
        Tmdb::movie(4, ['title' => 'D', 'popularity' => 70, 'vote_average' => 8.0, 'release_date' => '1972-03-14']),
    ]]);

    $response = $this->getJson("/api/v1/catalog/search?query=x&sort={$sort}")->assertOk();

    expect($response->json('movies.data.*.title'))->toBe($titles)
        // An order alone keeps the people.
        ->and($response->json('people.data.0.name'))->toBe('Brad Pitt');
})->with([
    'relevance' => ['relevance', ['A', 'B', 'C', 'D']],
    'popularity' => ['popularity', ['B', 'D', 'C', 'A']],
    // No IMDb rating here: TMDb's.
    'rating' => ['rating', ['A', 'D', 'C', 'B']],
    // No date: last.
    'recent' => ['recent', ['B', 'A', 'D', 'C']],
    'oldest' => ['oldest', ['D', 'A', 'B', 'C']],
]);

it('filters and sorts by rating: IMDb, else TMDb', function () {
    DB::table('imdb_ratings')->insert([
        ['tconst' => 'tt0000001', 'rating' => 6.5, 'votes' => 900, 'import_id' => 1],
        ['tconst' => 'tt0000002', 'rating' => 8.0, 'votes' => 5000, 'import_id' => 1],
    ]);
    fakeMovieSearch([1 => [
        // IMDb's 6.5, whatever TMDb says.
        Tmdb::movie(1, ['title' => 'IMDb 6,5', 'vote_average' => 9.0, 'vote_count' => 100]),
        Tmdb::movie(2, ['title' => 'IMDb 8', 'vote_average' => 6.0, 'vote_count' => 100]),
        // No IMDb rating: TMDb's.
        Tmdb::movie(3, ['title' => 'TMDb 7,5', 'vote_average' => 7.5, 'vote_count' => 3]),
        Tmdb::movie(4, ['title' => 'Sans note', 'vote_average' => 0, 'vote_count' => 0]),
    ]]);
    Http::fake(['api.themoviedb.org/3/movie/*' => fn (Request $request) => Http::response([
        'id' => $id = (int) Str::between($request->url(), 'movie/', '?'),
        'imdb_id' => sprintf('tt%07d', $id),
    ])]);

    $this->getJson('/api/v1/catalog/search/movie?query=x&sort=rating')
        ->assertOk()
        ->assertJsonPath('data.*.title', ['IMDb 8', 'TMDb 7,5', 'IMDb 6,5', 'Sans note']);

    $this->getJson('/api/v1/catalog/search/movie?query=x&min_rating=7')
        ->assertOk()
        ->assertJsonPath('data.*.title', ['IMDb 8', 'TMDb 7,5']);

    // The global search too, without people (a filter).
    $response = $this->getJson('/api/v1/catalog/search?query=x&min_rating=6.5&sort=rating')->assertOk();
    expect($response->json('movies.data.*.title'))->toBe(['IMDb 8', 'TMDb 7,5', 'IMDb 6,5'])
        ->and($response->json('people.data'))->toBe([]);

    // Voters of the rating shown: IMDb's (900, 5000), else TMDb's (3); unrated titles left out.
    $this->getJson('/api/v1/catalog/search/movie?query=x&min_votes=500')
        ->assertOk()
        ->assertJsonPath('data.*.title', ['IMDb 6,5', 'IMDb 8']);
    $this->getJson('/api/v1/catalog/search/movie?query=x&min_votes=1000&min_rating=6')
        ->assertOk()
        ->assertJsonPath('data.*.title', ['IMDb 8']);
    $this->getJson('/api/v1/catalog/search/movie?query=x&min_votes=0')
        ->assertOk()
        ->assertJsonCount(4, 'data');
});

it('filters and sorts one type in a single page, but not people', function () {
    fakeMovieSearch([
        1 => [Tmdb::movie(1, ['title' => 'Vieux', 'release_date' => '1950-01-01']), Tmdb::movie(2, ['title' => 'Récent', 'release_date' => '2024-01-01'])],
        2 => [Tmdb::movie(3, ['title' => 'Moyen', 'release_date' => '1990-01-01'])],
    ]);

    $this->getJson('/api/v1/catalog/search/movie?query=x&sort=recent&page=2')
        ->assertOk()
        ->assertJsonPath('data.*.title', ['Récent', 'Moyen', 'Vieux'])
        ->assertJsonPath('meta.page', 1)
        ->assertJsonPath('meta.total_pages', 1)
        ->assertJsonPath('meta.total_results', 3);

    $this->getJson('/api/v1/catalog/search/person?query=brad&genre=18')
        ->assertOk()
        ->assertJsonPath('data.0.name', 'Brad Pitt');
});

it('validates the filters, in French', function (string $query, string $field, string $message) {
    $this->getJson('/api/v1/catalog/search/movie?query=x&'.$query)
        ->assertUnprocessable()
        ->assertJsonPath("errors.{$field}.0", $message);
})->with([
    ['year_from=2000&year_to=1990', 'year_to', 'Le champ année de fin doit être supérieur ou égal à 2000.'],
    ['sort=best', 'sort', "La valeur sélectionnée pour tri n'est pas valide."],
    ['min_rating=11', 'min_rating', 'Le champ note minimale doit être compris entre 0 et 10.'],
    ['min_votes=-1', 'min_votes', 'Le champ nombre minimal de votants doit être au moins égal à 0.'],
    ['min_votes=beaucoup', 'min_votes', 'Le champ nombre minimal de votants doit être un nombre entier.'],
    ['year_from=1500', 'year_from', 'Le champ année de début doit être compris entre 1870 et 2100.'],
]);

it('accepts an end year alone', function () {
    fakeMovieSearch([1 => [
        Tmdb::movie(1, ['title' => 'Ancien', 'release_date' => '1975-01-01']),
        Tmdb::movie(2, ['title' => 'Récent', 'release_date' => '2020-01-01']),
    ]]);

    $this->getJson('/api/v1/catalog/search/movie?query=x&year_to=1979')
        ->assertOk()
        ->assertJsonPath('data.*.title', ['Ancien']);
});
