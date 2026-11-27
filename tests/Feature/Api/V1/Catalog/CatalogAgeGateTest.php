<?php

use App\Models\Profile;
use App\Models\User;
use Illuminate\Support\Facades\Http;
use Laravel\Sanctum\Sanctum;
use Tests\Feature\Api\V1\Catalog\TmdbFixtures as Tmdb;

beforeEach(function () {
    config(['services.tmdb.token' => 'test-token', 'services.tmdb.base_url' => 'https://api.themoviedb.org/3']);
    Http::preventStrayRequests();

    $this->user = User::factory()->create();
    $this->profile = Profile::factory()->for($this->user)->create();
    Sanctum::actingAs($this->user);

    Http::fake([
        'api.themoviedb.org/3/movie/popular*' => Http::response(Tmdb::page([Tmdb::movie(1), Tmdb::movie(2), Tmdb::movie(3)])),
    ]);
});

it('returns every catalog title without age filtering or certification lookups', function () {
    $this->getJson('/api/v1/catalog/movie/lists/popular')
        ->assertOk()
        ->assertJsonPath('data.*.id', [1, 2, 3]);

    Http::assertNotSent(fn ($request) => str_contains($request->url(), 'release_dates')
        || str_contains($request->url(), 'content_ratings'));
});

it('ignores legacy profile-selection headers', function () {
    $this->withHeader('X-Profile-Id', '999999')
        ->getJson('/api/v1/catalog/movie/lists/popular')
        ->assertOk()
        ->assertJsonPath('data.*.id', [1, 2, 3]);
});
