<?php

use App\Models\DeadStream;
use App\Models\User;
use App\Services\Playback\DeadStreams;
use Illuminate\Support\Facades\Http;
use Laravel\Sanctum\Sanctum;
use Tests\Feature\Api\V1\Catalog\TmdbFixtures;

beforeEach(function () {
    $this->user = User::factory()->create();
    Sanctum::actingAs($this->user);
});

it('records a stream as dead and shares it with everyone', function () {
    $this->postJson('/api/v1/catalog/streams/dead', [
        'source' => 'frenchstream',
        'stream_id' => 'https://voe.sx/e/dead',
    ])->assertOk()->assertExactJson(['message' => 'Le flux a été signalé comme mort.']);

    expect(DeadStream::count())->toBe(1)
        ->and(app(DeadStreams::class)->isDead('frenchstream', 'https://voe.sx/e/dead'))->toBeTrue()
        ->and(app(DeadStreams::class)->isDead('wiflix', 'https://voe.sx/e/dead'))->toBeFalse();
});

it('keeps only the latest report of the same stream', function () {
    $this->postJson('/api/v1/catalog/streams/dead', ['source' => 'frenchstream', 'stream_id' => 'https://voe.sx/e/dead'])->assertOk();
    $this->postJson('/api/v1/catalog/streams/dead', ['source' => 'frenchstream', 'stream_id' => 'https://voe.sx/e/dead'])->assertOk();

    expect(DeadStream::count())->toBe(1);
});

it('ignores a report older than a day', function () {
    DeadStream::create(['source' => 'frenchstream', 'stream_id' => 'https://voe.sx/e/dead', 'dead_at' => now()->subHours(25)]);

    expect(app(DeadStreams::class)->isDead('frenchstream', 'https://voe.sx/e/dead'))->toBeFalse();
});

it('drops a stream reported as dead from the sources list', function () {
    config([
        'services.tmdb.token' => 'test-token',
        'services.tmdb.api_key' => null,
        'services.tmdb.base_url' => 'https://api.themoviedb.org/3',
        'services.scraper.base_url' => 'http://scraper.test',
        'services.scraper.timeout' => 5,
    ]);
    Http::preventStrayRequests();
    Http::fake([
        'api.themoviedb.org/3/movie/550*' => Http::response(TmdbFixtures::movie()),
        'scraper.test/sources' => Http::response(['sources' => [
            ['provider' => 'frembed', 'name' => 'Frembed (VF)', 'url' => 'https://voe.sx/e/dead'],
            ['provider' => 'frembed', 'name' => 'Frembed (VOSTFR)', 'url' => 'https://voe.sx/e/ok'],
        ]]),
    ]);

    $this->postJson('/api/v1/catalog/streams/dead', ['source' => 'frembed', 'stream_id' => 'https://voe.sx/e/dead'])->assertOk();

    $this->getJson('/api/v1/catalog/movie/550/sources')
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.url', 'https://voe.sx/e/ok');
});

it('validates the report', function () {
    $this->postJson('/api/v1/catalog/streams/dead', [])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['source', 'stream_id']);
});
