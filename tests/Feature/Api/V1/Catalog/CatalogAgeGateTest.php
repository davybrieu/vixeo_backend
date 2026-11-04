<?php

use App\Models\Profile;
use App\Models\User;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Laravel\Sanctum\Sanctum;
use Tests\Feature\Api\V1\Catalog\TmdbFixtures as Tmdb;

/** TMDb answer for movie/{id}/release_dates with a French certification. */
function frRelease(?string $certification): array
{
    return ['results' => $certification === null ? [] : [
        ['iso_3166_1' => 'FR', 'release_dates' => [['certification' => $certification]]],
    ]];
}

beforeEach(function () {
    config(['services.tmdb.token' => 'test-token', 'services.tmdb.base_url' => 'https://api.themoviedb.org/3']);
    Http::preventStrayRequests();

    $this->user = User::factory()->create();
    $this->adult = Profile::factory()->for($this->user)->create();
    $this->kid = Profile::factory()->for($this->user)->kid(12)->create();
    Sanctum::actingAs($this->user);

    // 1: all audiences, 2: 16+, 3: unknown rating, 4: TV show TV-PG (10).
    Http::fake([
        'api.themoviedb.org/3/movie/1/release_dates*' => Http::response(frRelease('U')),
        'api.themoviedb.org/3/movie/2/release_dates*' => Http::response(frRelease('16')),
        'api.themoviedb.org/3/movie/3/release_dates*' => Http::response(frRelease(null)),
        'api.themoviedb.org/3/tv/4/content_ratings*' => Http::response(['results' => [['iso_3166_1' => 'US', 'rating' => 'TV-PG']]]),
        'api.themoviedb.org/3/movie/popular*' => Http::response(Tmdb::page([Tmdb::movie(1), Tmdb::movie(2), Tmdb::movie(3)])),
        'api.themoviedb.org/3/search/movie*' => Http::response(Tmdb::page([Tmdb::movie(1), Tmdb::movie(2)])),
        'api.themoviedb.org/3/search/tv*' => Http::response(Tmdb::page([Tmdb::show(4)])),
        'api.themoviedb.org/3/search/person*' => Http::response(Tmdb::page([['id' => 9, 'name' => 'Someone']])),
    ]);
});

it('does not filter without a profile header or for an adult profile', function () {
    $this->getJson('/api/v1/catalog/movie/lists/popular')->assertJsonCount(3, 'data');
    $this->withHeader('X-Profile-Id', (string) $this->adult->id)
        ->getJson('/api/v1/catalog/movie/lists/popular')->assertJsonCount(3, 'data');

    Http::assertNotSent(fn (Request $request) => str_contains($request->url(), 'release_dates'));
});

it('filters lists for a kid profile, hiding unrated titles', function () {
    $this->withHeader('X-Profile-Id', (string) $this->kid->id)
        ->getJson('/api/v1/catalog/movie/lists/popular')
        ->assertOk()
        ->assertJsonPath('data.*.id', [1]);
});

it('shows unrated titles when the profile allows it', function () {
    $this->kid->update(['allow_unrated' => true]);

    $this->withHeader('X-Profile-Id', (string) $this->kid->id)
        ->getJson('/api/v1/catalog/movie/lists/popular')
        ->assertJsonPath('data.*.id', [1, 3]);
});

it('caches ratings across requests', function () {
    $kid = fn () => $this->withHeader('X-Profile-Id', (string) $this->kid->id);

    $kid()->getJson('/api/v1/catalog/movie/lists/popular');
    $kid()->getJson('/api/v1/catalog/movie/lists/popular');

    Http::assertSentCount(4); // 1 list + 3 ratings, then everything from the cache.
});

it('filters search results but keeps people', function () {
    $this->withHeader('X-Profile-Id', (string) $this->kid->id)
        ->getJson('/api/v1/catalog/search?query=x')
        ->assertJsonPath('movies.data.*.id', [1])
        ->assertJsonPath('shows.data.*.id', [4])
        ->assertJsonPath('people.data.*.id', [9]);
});

it('refuses a page the profile may not see', function () {
    Http::fake(['api.themoviedb.org/3/movie/550*' => Http::response(Tmdb::movieDetails())]); // 16

    $this->withHeader('X-Profile-Id', (string) $this->kid->id)
        ->getJson('/api/v1/catalog/movie/550')
        ->assertForbidden()
        ->assertExactJson(['message' => "Ce contenu n'est pas disponible sur ce profil."]);

    $this->withHeader('X-Profile-Id', (string) $this->adult->id)->getJson('/api/v1/catalog/movie/550')->assertOk();
});

it('refuses seasons of a TV show the profile may not see', function () {
    $this->kid->update(['max_age_rating' => 7]);

    $this->withHeader('X-Profile-Id', (string) $this->kid->id)
        ->getJson('/api/v1/catalog/tv/4/season/1')
        ->assertForbidden();
});

it('filters the home rows and drops empty ones', function () {
    $this->kid->update(['max_age_rating' => 0]);
    Http::fake([
        'api.themoviedb.org/3/trending/movie/week*' => Http::response(Tmdb::page([Tmdb::movie(2)])),
        'api.themoviedb.org/3/movie/top_rated*' => Http::response(Tmdb::page([Tmdb::movie(1)])),
        'api.themoviedb.org/3/movie/*' => Http::response(Tmdb::page([])),
    ]);

    $response = $this->withHeader('X-Profile-Id', (string) $this->kid->id)
        ->getJson('/api/v1/catalog/home?type=movie')
        ->assertOk();

    expect($response->json('featured'))->toBe([])
        ->and($response->json('sections.*.key'))->toBe(['movie_popular', 'movie_top_rated'])
        ->and($response->json('sections.0.items.*.id'))->toBe([1]);
});

it('refuses the profile of another account', function () {
    $this->withHeader('X-Profile-Id', (string) Profile::factory()->create()->id)
        ->getJson('/api/v1/catalog/movie/lists/popular')
        ->assertForbidden();

    $this->withHeader('X-Profile-Id', 'abc')->getJson('/api/v1/catalog/movie/lists/popular')->assertForbidden();
});
