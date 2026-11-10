<?php

use App\Models\Profile;
use App\Models\ProfileStream;
use App\Models\User;
use App\Services\Playback\DeadStreams;
use Laravel\Sanctum\Sanctum;

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->profile = Profile::factory()->for($this->user)->create();
    $this->url = "/api/v1/profiles/{$this->profile->id}/streams";
    Sanctum::actingAs($this->user);
});

it('returns nothing for a content without preferences', function () {
    $this->getJson("{$this->url}?source=tmdb&item_id=movie:550")
        ->assertOk()
        ->assertExactJson(['data' => []]);
});

it('marks a stream as favorite and returns it', function () {
    $this->postJson("{$this->url}/favorite", [
        'source' => 'tmdb',
        'item_id' => 'movie:550',
        'stream_id' => 'https://voe.sx/e/abc',
        'favorite' => true,
    ])->assertCreated()
        ->assertJsonPath('data.stream_id', 'https://voe.sx/e/abc')
        ->assertJsonPath('data.is_favorite', true);

    expect(ProfileStream::count())->toBe(1);
    $this->getJson("{$this->url}?source=tmdb&item_id=movie:550")
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.is_favorite', true);
});

it('removes a favorite without forgetting the stream', function () {
    ProfileStream::factory()->for($this->profile)->favorite()->worked()->create([
        'source' => 'tmdb',
        'item_id' => 'movie:550',
        'stream_id' => 'https://voe.sx/e/abc',
    ]);

    $this->postJson("{$this->url}/favorite", [
        'source' => 'tmdb',
        'item_id' => 'movie:550',
        'stream_id' => 'https://voe.sx/e/abc',
        'favorite' => false,
    ])->assertOk()->assertJsonPath('data.is_favorite', false);

    // The stream still has a working record, so it is still returned (favorites first).
    $this->getJson("{$this->url}?source=tmdb&item_id=movie:550")
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.is_favorite', false)
        ->assertJsonPath('data.0.last_worked_at', fn ($v) => $v !== null);
});

it('remembers the stream that worked, favorites first', function () {
    $this->postJson("{$this->url}/worked", [
        'source' => 'tmdb',
        'item_id' => 'movie:550',
        'stream_id' => 'https://voe.sx/e/old',
        'stream_url' => 'https://cdn.voe.sx/old/master.m3u8',
        'stream_type' => 'm3u8',
    ])->assertCreated()
        ->assertJsonPath('data.stream_url', 'https://cdn.voe.sx/old/master.m3u8')
        ->assertJsonPath('data.last_worked_at', fn ($v) => $v !== null);

    ProfileStream::factory()->for($this->profile)->favorite(now()->subDay()->toDateTimeString())->create([
        'source' => 'tmdb',
        'item_id' => 'movie:550',
        'stream_id' => 'https://voe.sx/e/fav',
    ]);

    $this->getJson("{$this->url}?source=tmdb&item_id=movie:550")
        ->assertOk()
        ->assertJsonPath('data.0.stream_id', 'https://voe.sx/e/fav')
        ->assertJsonPath('data.1.stream_id', 'https://voe.sx/e/old');
});

it('reports the stream that worked as no longer dead', function () {
    app(DeadStreams::class)->markDead('vavoo', 'channel-1');

    $this->postJson("{$this->url}/worked", [
        'source' => 'vavoo',
        'item_id' => 'channel-1',
        'stream_id' => 'channel-1',
    ])->assertCreated();

    expect(app(DeadStreams::class)->isDead('vavoo', 'channel-1'))->toBeFalse();
});

it('validates the content identity', function () {
    $this->getJson("{$this->url}")
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['source', 'item_id']);
});

it('refuses the streams of another account', function () {
    $other = Profile::factory()->for(User::factory())->create();

    $this->getJson("/api/v1/profiles/{$other->id}/streams?source=tmdb&item_id=movie:550")
        ->assertForbidden();
});
