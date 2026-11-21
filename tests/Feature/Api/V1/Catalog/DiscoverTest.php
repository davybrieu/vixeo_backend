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
    $this->travelTo('2026-11-21 10:00:00');
});

/** TMDb discover pages by page number (movies), no TV show, and title pages with an IMDb id. */
function fakeDiscover(array $pages, int $totalPages = 0): void
{
    Http::fake([
        'api.themoviedb.org/3/discover/movie*' => fn (Request $request) => Http::response(
            Tmdb::page($pages[(int) $request['page']] ?? [], totalPages: $totalPages ?: count($pages), page: (int) $request['page']),
        ),
        'api.themoviedb.org/3/discover/tv*' => Http::response(Tmdb::page([])),
        'api.themoviedb.org/3/movie/*' => fn (Request $request) => Http::response([
            'id' => $id = (int) Str::between($request->url(), 'movie/', '?'),
            'imdb_id' => sprintf('tt%07d', $id),
        ]),
    ]);
}

it('browses movies with filters and no text: genre, years and order go to TMDb, released titles only', function () {
    fakeDiscover([1 => [Tmdb::movie(1, ['title' => 'Horreur 2015'])]], totalPages: 7);

    $this->getJson('/api/v1/catalog/discover/movie?genre=27&year_from=2010&sort=recent')
        ->assertOk()
        ->assertJsonPath('data.0.title', 'Horreur 2015')
        ->assertJsonPath('meta.total_pages', 7);

    Http::assertSent(fn (Request $request) => str_contains($request->url(), 'discover/movie')
        && $request['with_genres'] == 27
        && $request['primary_release_date.gte'] === '2010-01-01'
        && $request['primary_release_date.lte'] === '2026-11-21'
        && $request['sort_by'] === 'primary_release_date.desc'
        && ! isset($request->data()['vote_average.gte']));
});

it('ends a past decade at its last day, and asks enough votes for the best rated', function () {
    Http::fake(['api.themoviedb.org/3/discover/tv*' => Http::response(Tmdb::page([]))]);

    $this->getJson('/api/v1/catalog/discover/tv?year_from=2010&year_to=2019&sort=rating')->assertOk();

    Http::assertSent(fn (Request $request) => $request['first_air_date.gte'] === '2010-01-01'
        && $request['first_air_date.lte'] === '2019-12-31'
        && $request['sort_by'] === 'vote_average.desc'
        && $request['vote_count.gte'] == 100);
});

it('keeps the titles whose rating (IMDb, else TMDb) reaches the minimum, three TMDb pages at a time', function () {
    DB::table('imdb_ratings')->insert([
        ['tconst' => 'tt0000001', 'rating' => 8.4, 'votes' => 9000, 'import_id' => 1],
        ['tconst' => 'tt0000002', 'rating' => 7.1, 'votes' => 9000, 'import_id' => 1],
    ]);
    fakeDiscover([
        // IMDb 8.4 although TMDb says 7.5: kept.
        1 => [Tmdb::movie(1, ['title' => 'IMDb 8,4', 'vote_average' => 7.5])],
        // IMDb 7.1 although TMDb says 8.2: left out.
        2 => [Tmdb::movie(2, ['title' => 'IMDb 7,1', 'vote_average' => 8.2])],
        // No IMDb rating: TMDb's 8.6.
        3 => [Tmdb::movie(3, ['title' => 'TMDb 8,6', 'vote_average' => 8.6])],
        4 => [Tmdb::movie(4, ['title' => 'Page 2', 'vote_average' => 9.0])],
    ], totalPages: 4);

    $this->getJson('/api/v1/catalog/discover/movie?genre=27&min_rating=8')
        ->assertOk()
        ->assertJsonPath('data.*.title', ['IMDb 8,4', 'TMDb 8,6'])
        ->assertJsonPath('meta.total_pages', 2);

    // TMDb is asked for titles rated 7 and more (IMDb's may be higher), pages 1 to 3.
    Http::assertSent(fn (Request $request) => str_contains($request->url(), 'discover/movie')
        && $request['vote_average.gte'] == 7 && $request['vote_count.gte'] == 10 && $request['page'] == 3);
    Http::assertNotSent(fn (Request $request) => str_contains($request->url(), 'discover/movie') && $request['page'] == 4);

    $this->getJson('/api/v1/catalog/discover/movie?genre=27&min_rating=8&page=2')
        ->assertOk()
        ->assertJsonPath('data.*.title', ['Page 2']);
});

it('keeps the titles whose voters (IMDb, else TMDb) reach the minimum', function () {
    DB::table('imdb_ratings')->insert([
        ['tconst' => 'tt0000001', 'rating' => 7.0, 'votes' => 250000, 'import_id' => 1],
        ['tconst' => 'tt0000002', 'rating' => 7.0, 'votes' => 800, 'import_id' => 1],
    ]);
    fakeDiscover([
        // IMDb's 250 000 voters although TMDb has 900: kept.
        1 => [Tmdb::movie(1, ['title' => 'IMDb 250 000', 'vote_count' => 900])],
        // IMDb's 800 voters although TMDb has 20 000: left out.
        2 => [Tmdb::movie(2, ['title' => 'IMDb 800', 'vote_count' => 20000])],
        // No IMDb rating: TMDb's 15 000 voters.
        3 => [Tmdb::movie(3, ['title' => 'TMDb 15 000', 'vote_count' => 15000])],
    ]);

    $this->getJson('/api/v1/catalog/discover/movie?min_votes=10000')
        ->assertOk()
        ->assertJsonPath('data.*.title', ['IMDb 250 000', 'TMDb 15 000']);

    // TMDb is not asked for a number of voters (IMDb's are far higher), only rated titles.
    Http::assertSent(fn (Request $request) => str_contains($request->url(), 'discover/movie')
        && $request['vote_count.gte'] == 10 && ! isset($request->data()['vote_average.gte']) && $request['page'] == 3);
});

it('asks TMDb for titles rated within a point of the rating range', function () {
    fakeDiscover([1 => [
        Tmdb::movie(1, ['title' => 'TMDb 6', 'vote_average' => 6.0]),
        Tmdb::movie(2, ['title' => 'TMDb 7,8', 'vote_average' => 7.8]),
    ]]);

    $this->getJson('/api/v1/catalog/discover/movie?min_rating=5&max_rating=7.5&max_votes=50000')
        ->assertOk()
        ->assertJsonPath('data.*.title', ['TMDb 6']);

    Http::assertSent(fn (Request $request) => str_contains($request->url(), 'discover/movie')
        && $request['vote_average.gte'] == 4 && $request['vote_average.lte'] == 8.5 && $request['vote_count.gte'] == 10);
});

it('browses movies and TV shows at once, never with people', function () {
    fakeDiscover([1 => [Tmdb::movie(1, ['title' => 'Film'])]]);

    $this->getJson('/api/v1/catalog/discover?year_from=2010')
        ->assertOk()
        ->assertJsonPath('movies.data.0.title', 'Film')
        ->assertJsonPath('shows.data', [])
        ->assertJsonPath('people.data', [])
        ->assertJsonPath('people.total_results', 0);
});

it('has no relevance order without a text', function () {
    $this->getJson('/api/v1/catalog/discover/movie?sort=relevance')
        ->assertUnprocessable()
        ->assertJsonPath('errors.sort.0', "La valeur sélectionnée pour tri n'est pas valide.");
    $this->getJson('/api/v1/catalog/discover/person')->assertNotFound();
});
