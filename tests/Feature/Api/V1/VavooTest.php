<?php

use App\Models\User;
use Illuminate\Support\Facades\Http;
use Laravel\Sanctum\Sanctum;

beforeEach(function () {
    config([
        'services.vavoo.base_urls' => ['https://vavoo.test'],
        'services.vavoo.ping_urls' => ['https://ping.test/api/app/ping'],
        'services.vavoo.user_agent' => 'MediaHubMX/2',
        'services.vavoo.country' => 'France',
        'services.vavoo.max_streams' => 3,
        'services.vavoo.timeout' => 5,
        'services.vavoo.ttl.signature' => 300,
        'services.vavoo.ttl.catalog' => 1800,
    ]);
    Http::preventStrayRequests();

    $this->user = User::factory()->create();
});

function vavooChannel(string $id, string $name, string $group = 'France'): array
{
    return [
        'type' => 'iptv',
        'ids' => ['id' => $id],
        'name' => $name,
        'url' => "https://vavoo.test/stream/{$id}",
        'group' => $group,
        'logo' => "https://logo.test/{$id}.png",
    ];
}

function fakeVavoo(array $items, ?string $resolveUrl = 'https://cdn.vavoo.test/tf1/master.m3u8'): void
{
    Http::fake([
        'ping.test/api/app/ping' => Http::response(['addonSig' => 'sig-123']),
        'vavoo.test/mediahubmx-catalog.json' => Http::response(['items' => $items]),
        'vavoo.test/mediahubmx-resolve.json' => Http::response($resolveUrl === null ? [] : ['url' => $resolveUrl]),
    ]);
}

it('requires a signed-in account', function () {
    $this->getJson('/api/v1/tv/vavoo/channels')
        ->assertUnauthorized()
        ->assertExactJson(['message' => 'Non authentifié.']);
});

it('lists the channel categories', function () {
    Sanctum::actingAs($this->user);
    fakeVavoo([vavooChannel('tf1', 'TF1'), vavooChannel('eurosport', 'Eurosport 1')]);

    $this->getJson('/api/v1/tv/vavoo/categories')
        ->assertOk()
        ->assertExactJson(['data' => [
            ['id' => 'Généralistes', 'name' => 'Généralistes'],
            ['id' => 'Sport', 'name' => 'Sport'],
        ]]);

    Http::assertSent(fn ($request) => $request->url() === 'https://ping.test/api/app/ping');
});

it('lists the channels, sorted by name, and filters by category and search', function () {
    Sanctum::actingAs($this->user);
    fakeVavoo([vavooChannel('tf1', 'TF1'), vavooChannel('eurosport', 'Eurosport 1')]);

    $this->getJson('/api/v1/tv/vavoo/channels')
        ->assertOk()
        ->assertJsonPath('data.*.name', ['Eurosport 1', 'TF1'])
        ->assertJsonPath('data.1.id', 'tf1')
        ->assertJsonPath('data.1.logo', 'https://logo.test/tf1.png');

    $this->getJson('/api/v1/tv/vavoo/channels?category_id=Sport')
        ->assertOk()
        ->assertJsonPath('data.*.name', ['Eurosport 1']);

    $this->getJson('/api/v1/tv/vavoo/channels?q=tf')
        ->assertOk()
        ->assertJsonPath('data.*.name', ['TF1']);
});

it('resolves a channel into a playable stream with the headers to send', function () {
    Sanctum::actingAs($this->user);
    fakeVavoo([vavooChannel('tf1', 'TF1')]);

    $this->getJson('/api/v1/tv/vavoo/channels/tf1/stream')
        ->assertOk()
        ->assertJsonPath('data.url', 'https://cdn.vavoo.test/tf1/master.m3u8')
        ->assertJsonPath('data.format', 'hls')
        ->assertJsonPath('data.headers.User-Agent', 'MediaHubMX/2');

    Http::assertSent(fn ($request) => $request->url() === 'https://vavoo.test/mediahubmx-resolve.json'
        && $request['url'] === 'https://vavoo.test/stream/tf1');
});

it('answers 404 for an unknown channel', function () {
    Sanctum::actingAs($this->user);
    fakeVavoo([vavooChannel('tf1', 'TF1')]);

    $this->getJson('/api/v1/tv/vavoo/channels/nope/stream')
        ->assertNotFound()
        ->assertExactJson(['message' => 'Chaîne introuvable.']);
});

it('says the service is unavailable when the signature cannot be obtained', function () {
    Sanctum::actingAs($this->user);
    Http::fake(['ping.test/api/app/ping' => Http::response('Boom', 500)]);

    $this->getJson('/api/v1/tv/vavoo/categories')
        ->assertStatus(503)
        ->assertExactJson(['message' => 'Impossible de joindre le serveur Vavoo. Réessayez dans quelques instants.']);
});

it('says Vavoo is not configured when no mirror is set', function () {
    Sanctum::actingAs($this->user);
    config(['services.vavoo.base_urls' => [], 'services.vavoo.ping_urls' => []]);

    $this->getJson('/api/v1/tv/vavoo/channels')
        ->assertStatus(503)
        ->assertExactJson(['message' => "La TV Vavoo n'est pas configurée sur le serveur."]);
});
