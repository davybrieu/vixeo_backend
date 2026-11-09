<?php

use App\Models\MissingExtractor;
use App\Models\User;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Laravel\Sanctum\Sanctum;
use Tests\Feature\Api\V1\Catalog\TmdbFixtures as Tmdb;

beforeEach(function () {
    config([
        'services.tmdb.token' => 'test-token',
        'services.tmdb.api_key' => null,
        'services.tmdb.base_url' => 'https://api.themoviedb.org/3',
        'services.scraper.base_url' => 'http://scraper.test',
        'services.scraper.timeout' => 5,
    ]);
    Http::preventStrayRequests();
    $this->user = User::factory()->create();
    Sanctum::actingAs($this->user);
});

function fakeSources(array $sources, bool $extract = false): void
{
    Http::fake([
        'api.themoviedb.org/3/movie/550*' => Http::response(Tmdb::movie()),
        'api.themoviedb.org/3/tv/1399*' => Http::response(Tmdb::show()),
        'scraper.test/sources' => Http::response(['sources' => $sources]),
    ]);
}

it('returns the player links of a movie, without resolving them by default', function () {
    fakeSources([
        ['provider' => 'frembed', 'name' => 'Frembed (VF)', 'url' => 'https://voe.sx/e/abc'],
    ]);

    $this->getJson('/api/v1/catalog/movie/550/sources')
        ->assertOk()
        ->assertExactJson(['data' => [[
            'provider' => 'frembed',
            'name' => 'Frembed (VF)',
            'url' => 'https://voe.sx/e/abc',
            'stream' => null,
            'stream_type' => null,
            'extractor' => null,
        ]]]);

    Http::assertSent(fn (Request $request) => str_ends_with($request->url(), '/sources')
        && $request['type'] === 'movie' && $request['tmdb_id'] === 550 && $request['extract'] === false);
});

it('surfaces the resolved stream when extraction is asked for', function () {
    fakeSources([
        [
            'provider' => 'frembed',
            'name' => 'Frembed (VF)',
            'url' => 'https://voe.sx/e/abc',
            'stream' => 'https://delivery.voe.sx/hls/master.m3u8',
            'streamType' => 'm3u8',
            'extractor' => 'voe',
        ],
    ], extract: true);

    $this->getJson('/api/v1/catalog/movie/550/sources?extract=1')
        ->assertOk()
        ->assertJsonPath('data.0.stream', 'https://delivery.voe.sx/hls/master.m3u8')
        ->assertJsonPath('data.0.stream_type', 'm3u8')
        ->assertJsonPath('data.0.extractor', 'voe');

    Http::assertSent(fn (Request $request) => str_ends_with($request->url(), '/sources') && $request['extract'] === true);
});

it('records links no extractor knows about, and ignores the ones it does', function () {
    fakeSources([
        ['provider' => 'frembed', 'name' => 'Frembed (VF)', 'url' => 'https://voe.sx/e/known'],
        ['provider' => 'flemmix', 'name' => 'Flemmix', 'url' => 'https://flemmix.upns.xyz/e/mirror'],
        ['provider' => 'newhost', 'name' => 'Nouveau', 'url' => 'https://unknown-host.tld/e/xyz'],
    ]);

    $this->getJson('/api/v1/catalog/movie/550/sources')->assertOk();

    $missing = MissingExtractor::all();
    expect($missing)->toHaveCount(1)
        ->and($missing->first()->url)->toBe('https://unknown-host.tld/e/xyz')
        ->and($missing->first()->host)->toBe('unknown-host.tld')
        ->and($missing->first()->user_id)->toBe($this->user->id);
});

it('counts a missing extractor again instead of adding a row twice', function () {
    fakeSources([
        ['provider' => 'newhost', 'name' => 'Nouveau', 'url' => 'https://unknown-host.tld/e/xyz'],
    ]);

    $this->getJson('/api/v1/catalog/movie/550/sources')->assertOk();
    $this->getJson('/api/v1/catalog/movie/550/sources')->assertOk();

    expect(MissingExtractor::count())->toBe(1)
        ->and(MissingExtractor::first()->occurrences)->toBe(2);
});

it('asks for the sources of an episode', function () {
    fakeSources([['provider' => 'frembed', 'name' => 'Frembed (VOSTFR)', 'url' => 'https://voe.sx/e/s2e1']]);

    $this->getJson('/api/v1/catalog/tv/1399/season/2/episode/1/sources')
        ->assertOk()
        ->assertJsonPath('data.0.url', 'https://voe.sx/e/s2e1');

    Http::assertSent(fn (Request $request) => str_ends_with($request->url(), '/sources')
        && $request['type'] === 'tv' && $request['tmdb_id'] === 1399
        && $request['season'] === 2 && $request['episode'] === 1);
});

it('says the service is unavailable when the scraper is down', function () {
    Http::fake([
        'api.themoviedb.org/3/movie/550*' => Http::response(Tmdb::movie()),
        'scraper.test/sources' => Http::response('Boom', 500),
    ]);

    $this->getJson('/api/v1/catalog/movie/550/sources')
        ->assertStatus(503)
        ->assertExactJson(['message' => 'Le service de sources est temporairement indisponible.']);
});
