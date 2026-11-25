<?php

use App\Models\User;
use App\Services\Tmdb\WeeklyReleases;
use Illuminate\Support\Facades\Http;
use Laravel\Sanctum\Sanctum;

beforeEach(function () {
    Http::preventStrayRequests();
    Sanctum::actingAs(User::factory()->create());
});

it('returns normalized worldwide releases for a seven-day period', function () {
    $items = [
        [
            'type' => 'movie', 'media_type' => 'movie', 'tmdb_id' => 550, 'id' => 550,
            'title' => 'Fight Club', 'poster_url' => 'https://image.tmdb.org/t/p/w342/poster.jpg',
            'release_date' => '2026-11-25', 'release_type' => 'Cinéma', 'country_code' => 'JP',
            'overview' => null, 'navigation' => ['type' => 'movie', 'id' => 550],
        ],
        [
            'type' => 'tv', 'media_type' => 'tv', 'tmdb_id' => 1399, 'id' => 1399,
            'title' => 'Nouvelle série', 'poster_url' => null,
            'release_date' => '2026-11-26', 'release_type' => 'Première diffusion', 'country_code' => 'US',
            'overview' => null, 'navigation' => ['type' => 'tv', 'id' => 1399],
        ],
        [
            'type' => 'episode', 'media_type' => 'tv', 'tmdb_id' => 1399, 'id' => 1399,
            'episode_id' => 9001, 'title' => 'Nouvelle série', 'episode_name' => 'Épisode 2',
            'poster_url' => null, 'release_date' => '2026-11-27', 'release_type' => 'Épisode',
            'country_code' => 'US', 'overview' => 'Description', 'season_number' => 1,
            'episode_number' => 2, 'navigation' => ['type' => 'tv', 'id' => 1399],
        ],
    ];

    $service = Mockery::mock(WeeklyReleases::class);
    $service->shouldReceive('between')
        ->once()
        ->with('2026-11-23', '2026-11-29', 'all')
        ->andReturn($items);
    app()->instance(WeeklyReleases::class, $service);

    $this->getJson('/api/v1/catalog/releases?from=2026-11-23&to=2026-11-29')
        ->assertOk()
        ->assertJsonPath('meta.from', '2026-11-23')
        ->assertJsonPath('meta.to', '2026-11-29')
        ->assertJsonPath('meta.count', 3)
        ->assertJsonPath('data.0.type', 'movie')
        ->assertJsonPath('data.0.country_code', 'JP')
        ->assertJsonPath('data.1.type', 'tv')
        ->assertJsonPath('data.2.type', 'episode')
        ->assertJsonPath('data.2.season_number', 1)
        ->assertJsonPath('data.2.episode_number', 2);
});

it('passes the requested content filter to the aggregator', function () {
    $service = Mockery::mock(WeeklyReleases::class);
    $service->shouldReceive('between')
        ->once()
        ->with('2026-11-23', '2026-11-29', 'episode')
        ->andReturn([]);
    app()->instance(WeeklyReleases::class, $service);

    $this->getJson('/api/v1/catalog/releases?from=2026-11-23&to=2026-11-29&type=episode')
        ->assertOk()
        ->assertJsonPath('data', []);
});

it('rejects periods longer than one calendar week', function () {
    $this->getJson('/api/v1/catalog/releases?from=2026-11-23&to=2026-11-30')
        ->assertUnprocessable();
});
