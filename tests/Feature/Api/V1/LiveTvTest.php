<?php

use App\Models\User;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Laravel\Sanctum\Sanctum;

/**
 * The `/tv/…` API is provider-neutral: whichever provider is configured behind it, the app sees
 * the same routes, the same shape and neutral messages. This locks the contract for the panel
 * provider (the other provider is covered by VavooTest).
 */
beforeEach(function () {
    Cache::flush();
    config([
        'services.vavoo.base_urls' => [],
        'services.vavoo.ping_urls' => [],
        'services.xtream.base_url' => 'https://panel.test',
        'services.xtream.username' => 'user',
        'services.xtream.password' => 'pass',
        'services.xtream.stream_format' => 'm3u8',
        'services.xtream.timeout' => 5,
    ]);
    Http::preventStrayRequests();

    $this->user = User::factory()->create();
});

function fakePanel(): void
{
    Http::fake([
        'panel.test/player_api.php*' => function ($request) {
            $action = $request->data()['action'] ?? ($request->query()['action'] ?? null);

            return match ($action) {
                'get_live_categories' => Http::response([
                    ['category_id' => '1', 'category_name' => 'Sport', 'parent_id' => '0'],
                ]),
                'get_live_streams' => Http::response([
                    ['stream_id' => '1399', 'name' => 'TF1', 'category_id' => '1', 'stream_icon' => 'https://logo.test/tf1.png', 'num' => 1],
                    ['stream_id' => '1400', 'name' => 'France 2', 'category_id' => '1', 'stream_icon' => null, 'num' => 2],
                ]),
                default => Http::response([]),
            };
        },
    ]);
}

it('serves the panel categories behind the neutral endpoint', function () {
    Sanctum::actingAs($this->user);
    fakePanel();

    $this->getJson('/api/v1/tv/categories')
        ->assertOk()
        ->assertExactJson(['data' => [
            ['id' => '1', 'name' => 'Sport', 'parent_id' => null],
        ]]);
});

it('serves the panel channels with the common shape and filters them', function () {
    Sanctum::actingAs($this->user);
    fakePanel();

    $this->getJson('/api/v1/tv/channels')
        ->assertOk()
        ->assertJsonPath('data.0.id', '1399')
        ->assertJsonPath('data.0.name', 'TF1')
        ->assertJsonPath('data.0.logo', 'https://logo.test/tf1.png')
        ->assertJsonPath('data.0.number', 1);

    $this->getJson('/api/v1/tv/channels?q=france')
        ->assertOk()
        ->assertJsonPath('data.*.name', ['France 2']);
});

it('resolves a panel channel into a playable stream', function () {
    Sanctum::actingAs($this->user);
    fakePanel();

    $this->getJson('/api/v1/tv/channels/1399/stream')
        ->assertOk()
        ->assertJsonPath('data.url', 'https://panel.test/live/user/pass/1399.m3u8')
        ->assertJsonPath('data.format', 'hls');
});

it('answers 404 for an unknown panel channel', function () {
    Sanctum::actingAs($this->user);
    fakePanel();

    $this->getJson('/api/v1/tv/channels/9999/stream')
        ->assertNotFound()
        ->assertExactJson(['message' => 'Chaîne introuvable.']);
});

it('says live TV is not configured when no provider is set at all', function () {
    Sanctum::actingAs($this->user);
    config([
        'services.xtream.base_url' => '',
        'services.xtream.username' => '',
        'services.xtream.password' => '',
    ]);

    $this->getJson('/api/v1/tv/channels')
        ->assertStatus(503)
        ->assertExactJson(['message' => "La TV en direct n'est pas configurée sur le serveur."]);
});
