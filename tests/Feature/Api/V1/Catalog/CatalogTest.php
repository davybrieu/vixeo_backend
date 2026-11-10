<?php

use App\Models\User;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Sleep;
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

it('exposes image configuration and the TMDb attribution', function () {
    $this->getJson('/api/v1/catalog/config')
        ->assertOk()
        ->assertJsonPath('image_base_url', 'https://image.tmdb.org/t/p/')
        ->assertJsonPath('attribution', 'This product uses the TMDB API but is not endorsed or certified by TMDB.');
});

it('builds the mixed home with featured items and French row titles', function () {
    Http::fake([
        'api.themoviedb.org/3/trending/all/week*' => Http::response(Tmdb::page([
            Tmdb::movie(1, ['media_type' => 'movie']),
            Tmdb::show(2, ['media_type' => 'tv']),
            Tmdb::movie(3, ['media_type' => 'movie', 'backdrop_path' => null]),
            ['id' => 4, 'media_type' => 'person', 'name' => 'Someone'],
        ])),
        'api.themoviedb.org/3/trending/movie/week*' => Http::response(Tmdb::page([Tmdb::movie(10)])),
        'api.themoviedb.org/3/trending/tv/week*' => Http::response(Tmdb::page([Tmdb::show(20)])),
        'api.themoviedb.org/3/movie/popular*' => Http::response(Tmdb::page([Tmdb::movie(11)])),
        'api.themoviedb.org/3/tv/popular*' => Http::response(Tmdb::page([Tmdb::show(21)])),
        'api.themoviedb.org/3/movie/now_playing*' => Http::response(Tmdb::page([])),
        'api.themoviedb.org/3/movie/top_rated*' => Http::response(Tmdb::page([Tmdb::movie(12)])),
        'api.themoviedb.org/3/tv/top_rated*' => Http::response(Tmdb::page([Tmdb::show(22)])),
    ]);

    $response = $this->getJson('/api/v1/catalog/home')->assertOk();

    // Only movies/TV shows with a backdrop are featured.
    expect($response->json('featured.*.id'))->toBe([1, 2])
        ->and($response->json('featured.1.type'))->toBe('tv')
        ->and($response->json('featured.1.title'))->toBe('Game of Thrones');

    // Empty rows are dropped.
    expect($response->json('sections.*.key'))->toBe([
        'movie_trending', 'tv_trending', 'movie_popular', 'tv_popular', 'movie_top_rated', 'tv_top_rated',
    ])->and($response->json('sections.0.title'))->toBe('Films tendance')
        ->and($response->json('sections.1.title'))->toBe('Séries tendance')
        ->and($response->json('sections.1.list'))->toBe('trending')
        ->and($response->json('sections.1.type'))->toBe('tv');

    $item = $response->json('sections.0.items.0');
    expect($item)->toMatchArray([
        'id' => 10,
        'type' => 'movie',
        'title' => 'Fight Club',
        'date' => '1999-10-15',
        'year' => 1999,
        'vote_average' => 8.4,
        'genre_ids' => [18, 53],
        'poster_path' => '/poster.jpg',
        'poster_url' => 'https://image.tmdb.org/t/p/w342/poster.jpg',
        'backdrop_url' => 'https://image.tmdb.org/t/p/w1280/backdrop.jpg',
    ]);

    Http::assertSent(fn (Request $request) => $request->hasHeader('Authorization', 'Bearer test-token')
        && $request['language'] === 'fr-FR'
        && ! isset($request['api_key']));
});

it('caches TMDb responses', function () {
    Http::fake(['api.themoviedb.org/3/movie/550*' => Http::response(Tmdb::movieDetails())]);

    $this->getJson('/api/v1/catalog/movie/550')->assertOk();
    $this->getJson('/api/v1/catalog/movie/550')->assertOk();

    Http::assertSentCount(1);
});

it('builds a movies-only home', function () {
    Http::fake(['api.themoviedb.org/3/*' => Http::response(Tmdb::page([Tmdb::movie(1)]))]);

    $response = $this->getJson('/api/v1/catalog/home?type=movie')->assertOk();

    expect($response->json('sections.*.list'))->toBe(['trending', 'popular', 'top_rated', 'now_playing', 'upcoming'])
        ->and($response->json('sections.3.title'))->toBe('Actuellement au cinéma');
    Http::assertNotSent(fn (Request $request) => str_contains($request->url(), '/tv/'));
});

it('validates the home type', function () {
    $this->getJson('/api/v1/catalog/home?type=radio')
        ->assertUnprocessable()
        ->assertJsonPath('errors.type.0', 'La valeur sélectionnée pour type n\'est pas valide.');
});

it('returns a movie page', function () {
    Http::fake(['api.themoviedb.org/3/movie/550*' => Http::response(Tmdb::movieDetails())]);

    $movie = $this->getJson('/api/v1/catalog/movie/550')->assertOk()->json('data');

    expect($movie)->toMatchArray([
        'id' => 550,
        'type' => 'movie',
        'title' => 'Fight Club',
        'year' => 1999,
        'runtime' => 139,
        'tagline' => 'Mischief. Mayhem. Soap.',
        'imdb_id' => 'tt0137523',
        // French certification wins over the American one.
        'certification' => '16',
        'age_rating' => 16,
        'poster_url' => 'https://image.tmdb.org/t/p/w500/poster.jpg',
    ])->and($movie['genres'])->toBe([['id' => 18, 'name' => 'Drame']])
        ->and($movie['directors'])->toBe([['id' => 7467, 'name' => 'David Fincher', 'profile_path' => '/fincher.jpg', 'profile_url' => 'https://image.tmdb.org/t/p/w185/fincher.jpg']])
        ->and($movie['cast'][1])->toBe(['id' => 287, 'name' => 'Brad Pitt', 'character' => 'Tyler Durden', 'profile_path' => null, 'profile_url' => null])
        // French trailer first, trailers before teasers, YouTube only.
        ->and($movie['trailer'])->toBe([
            'name' => 'Bande-annonce VF', 'site' => 'YouTube', 'key' => 'fr-trailer',
            'url' => 'https://www.youtube.com/watch?v=fr-trailer', 'language' => 'fr',
        ])
        // No recommendations: similar titles are used instead.
        ->and($movie['recommended'][0]['title'])->toBe('Seven');

    Http::assertSent(fn (Request $request) => str_contains($request['append_to_response'], 'release_dates'));
});

it('returns a TV show page', function () {
    Http::fake(['api.themoviedb.org/3/tv/1399*' => Http::response(Tmdb::showDetails())]);

    $show = $this->getJson('/api/v1/catalog/tv/1399')->assertOk()->json('data');

    expect($show)->toMatchArray([
        'id' => 1399,
        'type' => 'tv',
        'title' => 'Game of Thrones',
        'number_of_seasons' => 8,
        'episode_runtime' => 60,
        'status' => 'Ended',
        'imdb_id' => 'tt0944947',
        'tvdb_id' => 121361,
        // No French rating: American TV-MA → 17.
        'certification' => 'TV-MA',
        'age_rating' => 17,
        'trailer' => null,
        'next_episode' => null,
    ])->and($show['cast'][0]['character'])->toBe('Tyrion Lannister')
        ->and($show['creators'][0]['name'])->toBe('David Benioff')
        ->and($show['seasons'][1])->toMatchArray(['season_number' => 1, 'episode_count' => 10, 'poster_url' => 'https://image.tmdb.org/t/p/w342/s1.jpg'])
        ->and($show['recommended'][0])->toMatchArray(['type' => 'tv', 'title' => 'The Walking Dead']);
});

it('returns a season with its episodes', function () {
    Http::fake(['api.themoviedb.org/3/tv/1399/season/1*' => Http::response([
        'id' => 3624, 'season_number' => 1, 'name' => 'Saison 1', 'overview' => '', 'air_date' => '2011-04-17', 'poster_path' => '/s1.jpg',
        'episodes' => [
            ['id' => 63056, 'season_number' => 1, 'episode_number' => 1, 'name' => "L'hiver vient", 'overview' => 'Eddard Stark…', 'air_date' => '2011-04-17', 'runtime' => 62, 'still_path' => '/e1.jpg', 'vote_average' => 7.9],
        ],
    ])]);

    $this->getJson('/api/v1/catalog/tv/1399/season/1')
        ->assertOk()
        ->assertJsonPath('data.show_id', 1399)
        ->assertJsonPath('data.overview', null)
        ->assertJsonPath('data.episodes.0.name', "L'hiver vient")
        ->assertJsonPath('data.episodes.0.runtime', 62)
        ->assertJsonPath('data.episodes.0.still_url', 'https://image.tmdb.org/t/p/w300/e1.jpg');
});

it('returns a person with a deduplicated filmography, most recent first', function () {
    Http::fake(['api.themoviedb.org/3/person/287*' => Http::response([
        'id' => 287, 'name' => 'Brad Pitt', 'biography' => '', 'birthday' => '1963-12-18', 'deathday' => null,
        'place_of_birth' => 'Shawnee, Oklahoma, USA', 'known_for_department' => 'Acting', 'profile_path' => '/pitt.jpg',
        'combined_credits' => ['cast' => [
            Tmdb::movie(550, ['media_type' => 'movie', 'vote_count' => 30000]),
            Tmdb::movie(550, ['media_type' => 'movie', 'vote_count' => 30000, 'character' => 'Second role']),
            Tmdb::movie(1, ['media_type' => 'movie', 'title' => 'F1', 'release_date' => '2025-06-25', 'vote_count' => 2000]),
            Tmdb::show(2, ['media_type' => 'tv', 'name' => 'Friends', 'first_air_date' => '1994-09-22', 'vote_count' => 9000]),
        ]],
    ])]);

    $person = $this->getJson('/api/v1/catalog/person/287')->assertOk()->json('data');

    expect($person['name'])->toBe('Brad Pitt')
        ->and($person['biography'])->toBeNull()
        ->and($person['profile_url'])->toBe('https://image.tmdb.org/t/p/h632/pitt.jpg')
        ->and(array_column($person['filmography'], 'title'))->toBe(['F1', 'Fight Club', 'Friends'])
        ->and(array_column($person['known_for'], 'title'))->toBe(['Fight Club', 'Friends', 'F1']);
});

it('lists a catalog page and caps TMDb page counts', function () {
    Http::fake(['api.themoviedb.org/3/tv/on_the_air*' => Http::response(Tmdb::page([Tmdb::show(1)], totalPages: 900, page: 2))]);

    $this->getJson('/api/v1/catalog/tv/lists/on_the_air?page=2')
        ->assertOk()
        ->assertJsonPath('data.0.type', 'tv')
        ->assertJsonPath('meta.page', 2)
        ->assertJsonPath('meta.total_pages', 500);

    Http::assertSent(fn (Request $request) => $request['page'] == 2 && $request['region'] === 'FR');
});

it('rejects lists that do not exist for a type', function (string $url) {
    $this->getJson($url)->assertNotFound()->assertExactJson(['message' => 'Ressource introuvable.']);
})->with([
    '/api/v1/catalog/tv/lists/now_playing',
    '/api/v1/catalog/movie/lists/airing_today',
    '/api/v1/catalog/movie/lists/nope',
    '/api/v1/catalog/radio/lists/popular',
]);

it('validates pages', function () {
    $this->getJson('/api/v1/catalog/movie/lists/popular?page=501')
        ->assertUnprocessable()->assertJsonValidationErrors('page');
});

it('discovers by genre with sensible TMDb filters', function () {
    Http::fake(['api.themoviedb.org/3/discover/movie*' => Http::response(Tmdb::page([Tmdb::movie(1)]))]);

    $this->getJson('/api/v1/catalog/movie/discover?genre=28&sort=rating')->assertOk()->assertJsonPath('data.0.id', 1);
    $this->getJson('/api/v1/catalog/movie/discover?sort=recent')->assertOk();

    Http::assertSent(fn (Request $request) => ($request->data()['with_genres'] ?? null) == 28
        && $request['sort_by'] === 'vote_average.desc'
        && $request['vote_count.gte'] == 200
        && $request['include_adult'] === 'false');
    Http::assertSent(fn (Request $request) => $request['sort_by'] === 'primary_release_date.desc'
        && ($request->data()['primary_release_date.lte'] ?? null) === now()->toDateString()
        && ! isset($request->data()['with_genres']));
});

it('returns movie and TV genres', function () {
    Http::fake([
        'api.themoviedb.org/3/genre/movie/list*' => Http::response(['genres' => [['id' => 28, 'name' => 'Action']]]),
        'api.themoviedb.org/3/genre/tv/list*' => Http::response(['genres' => [['id' => 10759, 'name' => 'Action & Adventure']]]),
    ]);

    $this->getJson('/api/v1/catalog/genres')
        ->assertOk()
        ->assertExactJson([
            'movie' => [['id' => 28, 'name' => 'Action']],
            'tv' => [['id' => 10759, 'name' => 'Action & Adventure']],
        ]);
});

it('searches movies, TV shows and people at once', function () {
    Http::fake([
        'api.themoviedb.org/3/search/movie*' => Http::response(Tmdb::page([Tmdb::movie(1)], totalPages: 3)),
        'api.themoviedb.org/3/search/tv*' => Http::response(Tmdb::page([])),
        'api.themoviedb.org/3/search/person*' => Http::response(Tmdb::page([
            ['id' => 287, 'name' => 'Brad Pitt', 'known_for_department' => 'Acting', 'profile_path' => '/pitt.jpg'],
        ])),
    ]);

    $this->getJson('/api/v1/catalog/search?query='.urlencode('  fight  '))
        ->assertOk()
        ->assertJsonPath('movies.data.0.title', 'Fight Club')
        ->assertJsonPath('movies.total_results', 3)
        ->assertJsonPath('shows.data', [])
        ->assertJsonPath('people.data.0', [
            'id' => 287, 'name' => 'Brad Pitt', 'known_for_department' => 'Acting',
            'profile_path' => '/pitt.jpg', 'profile_url' => 'https://image.tmdb.org/t/p/w185/pitt.jpg',
        ]);

    Http::assertSent(fn (Request $request) => $request['query'] === 'fight' && $request['include_adult'] === 'false');
});

it('searches one type with pagination', function () {
    Http::fake(['api.themoviedb.org/3/search/tv*' => Http::response(Tmdb::page([Tmdb::show(1)], totalPages: 4, page: 2))]);

    $this->getJson('/api/v1/catalog/search/tv?query=thrones&page=2')
        ->assertOk()
        ->assertJsonPath('data.0.type', 'tv')
        ->assertJsonPath('meta.total_pages', 4);
});

it('lists popular people without a text, adult profiles left out', function () {
    Http::fake(['api.themoviedb.org/3/person/popular*' => Http::response(Tmdb::page([
        ['id' => 287, 'name' => 'Brad Pitt', 'known_for_department' => 'Acting', 'profile_path' => '/pitt.jpg'],
        ['id' => 9, 'name' => 'Adult', 'adult' => true, 'known_for_department' => 'Acting', 'profile_path' => null],
    ], totalPages: 3, page: 2))]);

    $this->getJson('/api/v1/catalog/people/popular?page=2')
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0', [
            'id' => 287, 'name' => 'Brad Pitt', 'known_for_department' => 'Acting',
            'profile_path' => '/pitt.jpg', 'profile_url' => 'https://image.tmdb.org/t/p/w185/pitt.jpg',
        ])
        ->assertJsonPath('meta.page', 2)
        ->assertJsonPath('meta.total_pages', 3);

    Http::assertSent(fn (Request $request) => str_contains($request->url(), 'person/popular') && $request['page'] == 2);
});

it('requires a search query', function () {
    $this->getJson('/api/v1/catalog/search?query=%20')
        ->assertUnprocessable()
        ->assertJsonPath('errors.query.0', 'Le champ recherche est obligatoire.');
});

it('turns a TMDb 404 into our 404', function () {
    Http::fake(['api.themoviedb.org/3/movie/999999*' => Http::response(['status_code' => 34], 404)]);

    $this->getJson('/api/v1/catalog/movie/999999')
        ->assertNotFound()
        ->assertExactJson(['message' => 'Ressource introuvable.']);
});

it('answers 503 when TMDb fails, and does not cache the failure', function () {
    Sleep::fake();
    Http::fakeSequence('api.themoviedb.org/3/movie/550*')
        ->push(['status_message' => 'Internal error'], 500)
        ->push(['status_message' => 'Internal error'], 500)
        ->push(['status_message' => 'Internal error'], 500)
        ->push(Tmdb::movieDetails());

    $this->getJson('/api/v1/catalog/movie/550')
        ->assertStatus(503)
        ->assertExactJson(['message' => 'Le service de métadonnées est momentanément indisponible. Réessayez dans quelques instants.']);

    // 1 request + 2 retries.
    Http::assertSentCount(3);

    $this->getJson('/api/v1/catalog/movie/550')->assertOk();
});

it('answers 503 when TMDb is not configured', function () {
    config(['services.tmdb.token' => null, 'services.tmdb.api_key' => null]);

    $this->getJson('/api/v1/catalog/movie/550')->assertStatus(503);
    Http::assertNothingSent();
});

it('can use a v3 API key instead of a token', function () {
    config(['services.tmdb.token' => null, 'services.tmdb.api_key' => 'v3-key']);
    Http::fake(['api.themoviedb.org/3/movie/550*' => Http::response(Tmdb::movieDetails())]);

    $this->getJson('/api/v1/catalog/movie/550')->assertOk();

    Http::assertSent(fn (Request $request) => $request['api_key'] === 'v3-key' && ! $request->hasHeader('Authorization'));
});
