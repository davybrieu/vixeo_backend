<?php

use App\Models\User;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Laravel\Sanctum\Sanctum;

/**
 * The channel page of the app (`/tv/channels/{id}`). There is no programme guide (EPG) any more:
 * removed at the developer's request.
 */
beforeEach(function () {
    config([
        'services.vavoo.base_urls' => ['https://vavoo.test'],
        'services.vavoo.ping_urls' => ['https://ping.test/api/app/ping'],
        'services.vavoo.country' => 'France',
        'services.vavoo.timeout' => 5,
        'services.vavoo.ttl.signature' => 300,
        'services.vavoo.ttl.catalog' => 1800,
    ]);
    Http::preventStrayRequests();
    Cache::flush();
    Sanctum::actingAs(User::factory()->create());
    Http::fake([
        'ping.test/api/app/ping' => Http::response(['addonSig' => 'sig']),
        'vavoo.test/mediahubmx-catalog.json' => Http::response(['items' => [
            ['type' => 'iptv', 'ids' => ['id' => 'tf1'], 'name' => 'TF1 HD', 'url' => 'https://vavoo.test/s/tf1', 'group' => 'France', 'logo' => ''],
            ['type' => 'iptv', 'ids' => ['id' => 'arte'], 'name' => 'Arte', 'url' => 'https://vavoo.test/s/arte', 'group' => 'France', 'logo' => 'https://logo.test/arte.png'],
        ]]),
    ]);
});

it('gives a channel with the provider logo, or none', function () {
    $this->getJson('/api/v1/tv/channels/arte')
        ->assertOk()
        ->assertJsonPath('data.name', 'Arte')
        ->assertJsonPath('data.logo', 'https://logo.test/arte.png');
    $this->getJson('/api/v1/tv/channels/tf1')
        ->assertOk()
        ->assertJsonPath('data.name', 'TF1 HD')
        ->assertJsonPath('data.logo', null);
});

it('answers 404 for an unknown channel', function () {
    $this->getJson('/api/v1/tv/channels/nope')->assertNotFound()->assertJsonPath('message', 'Chaîne introuvable.');
});

it('has no programme guide any more', function () {
    $this->getJson('/api/v1/tv/channels/tf1/epg')->assertNotFound();
});
