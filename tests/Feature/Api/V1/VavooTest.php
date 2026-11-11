<?php

use App\Models\User;
use Illuminate\Support\Facades\Http;
use Laravel\Sanctum\Sanctum;

/**
 * Vavoo served behind the provider-neutral `/tv/…` API: the app never sees the provider name.
 */
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
        // The proxy fetches the provider playlist and its segments server-side.
        'cdn.vavoo.test/tf1/master.m3u8' => Http::response("#EXTM3U\n#EXT-X-VERSION:3\n#EXTINF:9.6,\nseg1.ts\n"),
        'cdn.vavoo.test/tf1/seg1.ts' => Http::response('SEGMENT-BYTES', 200, ['Content-Type' => 'video/mp2t']),
        'cdn.vavoo.test/tf1/live.ts' => Http::response('RAW-TS', 200, ['Content-Type' => 'video/mp2t']),
    ]);
}

it('allows guests to browse the live TV catalogue', function () {
    fakeVavoo([vavooChannel('tf1', 'TF1')]);

    $this->getJson('/api/v1/tv/channels')
        ->assertOk()
        ->assertJsonPath('data.0.name', 'TF1');
});

it('lists the channel categories', function () {
    Sanctum::actingAs($this->user);
    fakeVavoo([vavooChannel('tf1', 'TF1'), vavooChannel('eurosport', 'Eurosport 1')]);

    $this->getJson('/api/v1/tv/categories')
        ->assertOk()
        ->assertExactJson(['data' => [
            ['id' => 'Généralistes', 'name' => 'Généralistes', 'parent_id' => null],
            ['id' => 'Sport', 'name' => 'Sport', 'parent_id' => null],
        ]]);

    Http::assertSent(fn ($request) => $request->url() === 'https://ping.test/api/app/ping');
});

it('lists the channels, sorted by name, and filters by category and search', function () {
    Sanctum::actingAs($this->user);
    fakeVavoo([vavooChannel('tf1', 'TF1'), vavooChannel('eurosport', 'Eurosport 1')]);

    $this->getJson('/api/v1/tv/channels')
        ->assertOk()
        ->assertJsonPath('data.*.name', ['Eurosport 1', 'TF1'])
        ->assertJsonPath('data.1.id', 'tf1')
        ->assertJsonPath('data.1.logo', proxiedLogo('https://logo.test/tf1.png'));

    $this->getJson('/api/v1/tv/channels?category_id=Sport')
        ->assertOk()
        ->assertJsonPath('data.*.name', ['Eurosport 1']);

    $this->getJson('/api/v1/tv/channels?q=tf')
        ->assertOk()
        ->assertJsonPath('data.*.name', ['TF1']);
});

it('resolves a channel into a playable stream proxied through our own origin', function () {
    Sanctum::actingAs($this->user);
    fakeVavoo([vavooChannel('tf1', 'TF1')]);

    $response = $this->getJson('/api/v1/tv/channels/tf1/stream')
        ->assertOk()
        ->assertJsonPath('data.format', 'hls')
        ->assertJsonPath('data.headers', []);

    // The provider URL never reaches the app: only a token on our own origin.
    $url = $response->json('data.url');
    expect($url)->toStartWith(config('vixeo.urls.api').'/api/v1/tv/stream/')
        ->and($url)->not->toContain('cdn.vavoo.test');

    Http::assertSent(fn ($request) => $request->url() === 'https://vavoo.test/mediahubmx-resolve.json'
        && $request['url'] === 'https://vavoo.test/stream/tf1');
});

it('builds the stream url over https when TLS is terminated by a proxy', function () {
    Sanctum::actingAs($this->user);
    fakeVavoo([vavooChannel('tf1', 'TF1')]);

    // Cloudflare and Forge terminate TLS: the origin sees plain HTTP plus the proxy headers.
    $url = $this->withHeaders([
        'X-Forwarded-Proto' => 'https',
        'X-Forwarded-Host' => 'api.vixeo.test',
        'X-Forwarded-For' => '203.0.113.7',
    ])->getJson('http://api.vixeo.test/api/v1/tv/channels/tf1/stream')
        ->assertOk()
        ->json('data.url');

    // An http:// URL would be blocked: mixed content in a browser, cleartext on release Android.
    expect($url)->toStartWith('https://api.vixeo.test/api/v1/tv/stream/');
});

it('serves the proxied playlist with its segment URIs pointing back to us', function () {
    Sanctum::actingAs($this->user);
    fakeVavoo([vavooChannel('tf1', 'TF1')]);

    $token = last(explode('/', $this->getJson('/api/v1/tv/channels/tf1/stream')->json('data.url')));

    // No bearer token: the signed token in the URL is what authorises the media route.
    $playlist = $this->get('/api/v1/tv/stream/'.$token)
        ->assertOk()
        ->assertHeader('Content-Type', 'application/vnd.apple.mpegurl');

    expect($playlist->getContent())->toContain('/api/v1/tv/stream/')
        ->not->toContain('cdn.vavoo.test');

    // The provider is called with the headers the app never sees (its User-Agent).
    Http::assertSent(fn ($request) => $request->url() === 'https://cdn.vavoo.test/tf1/master.m3u8'
        && $request->hasHeader('User-Agent', 'MediaHubMX/2'));
});

it('serves a proxied segment with its content type', function () {
    Sanctum::actingAs($this->user);
    fakeVavoo([vavooChannel('tf1', 'TF1')]);

    $token = last(explode('/', $this->getJson('/api/v1/tv/channels/tf1/stream')->json('data.url')));

    $playlist = $this->get('/api/v1/tv/stream/'.$token)->getContent();
    preg_match('#/api/v1/tv/stream/([^\s"\']+)#', $playlist, $m);

    $this->get('/api/v1/tv/stream/'.$m[1])
        ->assertOk()
        ->assertHeader('Content-Type', 'video/mp2t')
        ->assertContent('SEGMENT-BYTES');
});

it('passes a raw stream through untouched', function () {
    Sanctum::actingAs($this->user);
    fakeVavoo([vavooChannel('tf1', 'TF1')], 'https://cdn.vavoo.test/tf1/live.ts');

    $token = last(explode('/', $this->getJson('/api/v1/tv/channels/tf1/stream')->json('data.url')));

    $this->get('/api/v1/tv/stream/'.$token)
        ->assertOk()
        ->assertHeader('Content-Type', 'video/mp2t');
});

it('rejects a forged or expired media token', function () {
    $this->get('/api/v1/tv/stream/not-a-real-token')->assertStatus(502);
});

it('answers 404 for an unknown channel', function () {
    Sanctum::actingAs($this->user);
    fakeVavoo([vavooChannel('tf1', 'TF1')]);

    $this->getJson('/api/v1/tv/channels/nope/stream')
        ->assertNotFound()
        ->assertExactJson(['message' => 'Chaîne introuvable.']);
});

it('says the service is unavailable when the signature cannot be obtained', function () {
    Sanctum::actingAs($this->user);
    Http::fake(['ping.test/api/app/ping' => Http::response('Boom', 500)]);

    $this->getJson('/api/v1/tv/categories')
        ->assertStatus(503)
        ->assertExactJson(['message' => 'La TV en direct est momentanément indisponible. Réessayez dans quelques instants.']);
});

it('says live TV is not configured when no provider is set', function () {
    Sanctum::actingAs($this->user);
    config(['services.vavoo.base_urls' => [], 'services.vavoo.ping_urls' => []]);

    $this->getJson('/api/v1/tv/channels')
        ->assertStatus(503)
        ->assertExactJson(['message' => "La TV en direct n'est pas configurée sur le serveur."]);
});
