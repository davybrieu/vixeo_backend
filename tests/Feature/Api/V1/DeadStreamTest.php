<?php

use App\Models\DeadStream;
use App\Models\User;
use App\Services\Playback\DeadStreams;
use Laravel\Sanctum\Sanctum;

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

it('drops a stream reported as dead from a list of sources', function () {
    $this->postJson('/api/v1/catalog/streams/dead', ['source' => 'addon:1', 'stream_id' => 'https://cdn.test/dead.mp4'])->assertOk();

    expect(app(DeadStreams::class)->filter([
        ['provider' => 'addon:1', 'name' => 'Mort', 'url' => 'https://cdn.test/dead.mp4'],
        ['provider' => 'addon:1', 'name' => 'Vivant', 'url' => 'https://cdn.test/ok.mp4'],
    ]))->toBe([['provider' => 'addon:1', 'name' => 'Vivant', 'url' => 'https://cdn.test/ok.mp4']]);
});

it('validates the report', function () {
    $this->postJson('/api/v1/catalog/streams/dead', [])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['source', 'stream_id']);
});
