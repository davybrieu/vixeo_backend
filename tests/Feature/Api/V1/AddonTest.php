<?php

use App\Models\Addon;
use App\Models\User;
use App\Services\AccountExport;
use App\Services\Addons\HostResolver;
use App\Services\Playback\DeadStreams;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Laravel\Sanctum\Sanctum;

/** A plug-in manifest in the Stremio format. */
function addonManifest(array $overrides = []): array
{
    return array_merge([
        'id' => 'org.example.films',
        'version' => '2.1.0',
        'name' => 'Films libres',
        'description' => 'Des films du domaine public.',
        'logo' => 'https://addon.test/logo.png',
        'resources' => ['catalog', 'stream'],
        'types' => ['movie', 'series'],
        'idPrefixes' => ['tt'],
        'catalogs' => [
            ['type' => 'movie', 'id' => 'top', 'name' => 'Les meilleurs', 'extra' => [['name' => 'skip']]],
            ['type' => 'movie', 'id' => 'search', 'name' => 'Recherche', 'extra' => [['name' => 'search', 'isRequired' => true]]],
            ['type' => 'channel', 'id' => 'tv', 'name' => 'Chaînes'],
        ],
    ], $overrides);
}

function installAddon(?User $user, array $manifest = [], string $host = 'addon.test'): Addon
{
    $url = "https://{$host}/manifest.json";

    return Addon::create([
        'user_id' => $user?->id,
        'transport_url' => $url,
        'url_hash' => Addon::hashUrl($url),
        'addon_id' => $manifest['id'] ?? 'org.example.films',
        'manifest' => addonManifest($manifest),
        'position' => 1,
        'enabled' => true,
        'manifest_checked_at' => now(),
    ]);
}

beforeEach(function () {
    config([
        'services.tmdb.token' => 'test-token',
        'services.tmdb.base_url' => 'https://api.themoviedb.org/3',
    ]);
    Http::preventStrayRequests();

    // No real DNS in the tests: every host is public, but these.
    app()->instance(HostResolver::class, new class extends HostResolver
    {
        public function resolve(string $host): array
        {
            return match ($host) {
                'private.test' => ['10.0.0.5'],
                'metadata.test' => ['169.254.169.254'],
                'loopback.test' => ['::1'],
                'nowhere.test' => [],
                default => ['93.184.216.34'],
            };
        }
    });

    $this->user = User::factory()->create();
    Sanctum::actingAs($this->user);
});

// -------------------------------------------------------------------------------------------
// Installed plug-ins

it('lists the plug-ins of everyone, then the account’s own, without a built-in one', function () {
    installAddon(null, ['id' => 'org.example.shared', 'name' => 'Pour tous'], 'shared.test');
    installAddon($this->user);
    installAddon($this->user, ['id' => 'catalog-only', 'name' => 'Catalogue seul', 'resources' => ['catalog']], 'catalog.test')
        ->update(['position' => 2]);
    installAddon(User::factory()->create(), ['name' => 'Pas le mien'], 'other.test');

    $this->getJson('/api/v1/addons')
        ->assertOk()
        ->assertJsonCount(3, 'data')
        ->assertJsonPath('data.0.name', 'Pour tous')
        ->assertJsonPath('data.0.shared', true)
        ->assertJsonPath('data.1.name', 'Films libres')
        ->assertJsonPath('data.1.shared', false)
        ->assertJsonPath('data.1.host', 'addon.test')
        ->assertJsonPath('data.1.version', '2.1.0')
        ->assertJsonPath('data.1.resources', ['catalog', 'stream'])
        ->assertJsonPath('data.1.types', ['movie', 'tv'])
        ->assertJsonPath('data.1.streams', true)
        ->assertJsonPath('data.1.enabled', true)
        ->assertJsonPath('data.1.logo_url', 'https://addon.test/logo.png')
        // Kept for the apps installed before, with the values of a plug-in that only gives sources.
        ->assertJsonPath('data.1.catalogs', 0)
        ->assertJsonPath('data.1.built_in', false)
        ->assertJsonPath('data.1.manifest_url', null)
        // Installed before plug-ins were sources only: listed, so that it can be removed.
        ->assertJsonPath('data.2.name', 'Catalogue seul')
        ->assertJsonPath('data.2.streams', false);
});

it('installs a plug-in from its address, keeps the address encrypted and never sends it back', function () {
    Http::fake(['addon.test/u=secret-key/manifest.json' => Http::response(addonManifest())]);

    $this->postJson('/api/v1/addons', ['url' => 'stremio://addon.test/u=secret-key/'])
        ->assertCreated()
        ->assertJsonPath('data.name', 'Films libres')
        ->assertJsonPath('data.host', 'addon.test')
        ->assertJsonMissingPath('data.url')
        ->assertDontSee('secret-key');

    $addon = $this->user->addons()->sole();
    expect($addon->transport_url)->toBe('https://addon.test/u=secret-key/manifest.json')
        ->and(DB::table('addons')->value('transport_url'))->not->toContain('secret-key');

    Http::assertSent(fn (Request $request) => $request->url() === 'https://addon.test/u=secret-key/manifest.json');
});

it('refuses addresses the server must not call', function (string $url, string $message) {
    Http::fake();

    $this->postJson('/api/v1/addons', ['url' => $url])
        ->assertUnprocessable()
        ->assertJsonPath('errors.url.0', $message);

    Http::assertNothingSent();
    expect(Addon::count())->toBe(0);
})->with([
    'plain http' => ['http://addon.test/manifest.json', 'L’adresse d’un plug-in commence par https://.'],
    'another scheme' => ['file:///etc/passwd', 'L’adresse d’un plug-in commence par https://.'],
    'private network' => ['https://private.test/manifest.json', 'Cette adresse de plug-in n’est pas autorisée.'],
    'cloud metadata' => ['https://metadata.test/manifest.json', 'Cette adresse de plug-in n’est pas autorisée.'],
    'loopback IPv6' => ['https://loopback.test/manifest.json', 'Cette adresse de plug-in n’est pas autorisée.'],
    'an IP of the server' => ['https://127.0.0.1/manifest.json', 'Cette adresse de plug-in n’est pas autorisée.'],
    'credentials in the address' => ['https://user:pass@addon.test/manifest.json', 'L’adresse d’un plug-in commence par https://.'],
    'unknown host' => ['https://nowhere.test/manifest.json', 'L’adresse de ce plug-in est introuvable.'],
]);

it('refuses what is not a usable plug-in', function (mixed $answer, string $message) {
    Http::fake(['addon.test/*' => $answer]);

    $this->postJson('/api/v1/addons', ['url' => 'https://addon.test/manifest.json'])
        ->assertUnprocessable()
        ->assertJsonPath('errors.url.0', $message);

    expect(Addon::count())->toBe(0);
})->with([
    'not json' => [fn () => Http::response('<html>', 200), 'Cette adresse n’est pas celle d’un plug-in.'],
    'not a manifest' => [fn () => Http::response(['hello' => 'world']), 'Cette adresse n’est pas celle d’un plug-in.'],
    'an error' => [fn () => Http::response('', 500), 'Ce plug-in ne répond pas.'],
    'a redirect' => [fn () => Http::response('', 302, ['Location' => 'https://private.test/']), 'Ce plug-in ne répond pas.'],
    'to configure first' => [
        fn () => Http::response(addonManifest(['behaviorHints' => ['configurationRequired' => true]])),
        'Ce plug-in doit d’abord être configuré sur son site : collez ensuite l’adresse qu’il vous donne.',
    ],
    'nothing for movies or series' => [
        fn () => Http::response(addonManifest(['types' => ['tv'], 'resources' => ['stream'], 'catalogs' => []])),
        'Ce plug-in ne propose pas de sources de films ou de séries.',
    ],
    'a catalog only' => [
        fn () => Http::response(addonManifest(['resources' => ['catalog', 'meta']])),
        'Ce plug-in ne propose pas de sources de films ou de séries.',
    ],
]);

it('refuses a plug-in installed twice, and more plug-ins than allowed', function () {
    config(['services.addons.max_per_user' => 1]);
    Http::fake(['addon.test/*' => Http::response(addonManifest())]);
    installAddon($this->user);

    $this->postJson('/api/v1/addons', ['url' => 'https://addon.test/manifest.json'])
        ->assertUnprocessable()
        ->assertJsonPath('errors.url.0', 'Vous avez atteint le nombre maximum de plug-ins.');

    config(['services.addons.max_per_user' => 5]);
    $this->postJson('/api/v1/addons', ['url' => 'https://addon.test/'])
        ->assertUnprocessable()
        ->assertJsonPath('errors.url.0', 'Ce plug-in est déjà installé.');
});

it('turns off, orders and removes the account’s own plug-ins only', function () {
    $first = installAddon($this->user);
    $second = installAddon($this->user, ['id' => 'b', 'name' => 'Second'], 'second.test');
    $shared = installAddon(null, ['id' => 'c'], 'shared.test');
    $someoneElse = installAddon(User::factory()->create(), ['id' => 'd'], 'other.test');

    $this->patchJson("/api/v1/addons/{$first->id}", ['enabled' => false])->assertOk()->assertJsonPath('data.enabled', false);
    expect($first->fresh()->enabled)->toBeFalse();

    $this->putJson('/api/v1/addons/order', ['ids' => [(string) $second->id, (string) $first->id, (string) $someoneElse->id]])->assertNoContent();
    expect($second->fresh()->position)->toBe(1)->and($first->fresh()->position)->toBe(2)
        ->and($someoneElse->fresh()->position)->toBe(1);

    $this->patchJson("/api/v1/addons/{$shared->id}", ['enabled' => false])->assertNotFound();
    $this->deleteJson("/api/v1/addons/{$someoneElse->id}")->assertNotFound();
    $this->deleteJson('/api/v1/addons/vixeo')->assertNotFound();

    $this->deleteJson("/api/v1/addons/{$first->id}")->assertNoContent();
    expect(Addon::find($first->id))->toBeNull()->and(Addon::count())->toBe(3);
});

it('needs an account to manage plug-ins', function () {
    $this->app['auth']->forgetGuards();

    $this->getJson('/api/v1/addons')->assertUnauthorized();
    $this->postJson('/api/v1/addons', ['url' => 'https://addon.test/manifest.json'])->assertUnauthorized();
});

// -------------------------------------------------------------------------------------------
// The catalog stays TMDb's

it('never changes the catalog: no rows, no lists and no Vixeo plug-in', function () {
    $addon = installAddon($this->user);
    Http::fake();

    // Kept for the apps installed before, always empty, without calling any plug-in.
    $this->getJson('/api/v1/catalog/addons/sections')
        ->assertOk()
        ->assertExactJson(['sections' => []]);
    $this->getJson("/api/v1/catalog/movie/lists/addon:{$addon->id}:top")->assertNotFound();
    $this->getJson('/api/v1/addons/vixeo/manifest.json')->assertNotFound();

    Http::assertNothingSent();
});

// -------------------------------------------------------------------------------------------
// Streams of plug-ins in the sources panel

it('offers the plug-ins that serve streams as providers of the sources panel', function () {
    $addon = installAddon($this->user);
    installAddon($this->user, ['id' => 'catalog-only', 'resources' => ['catalog']], 'catalog.test');
    $shared = installAddon(null, ['id' => 'shared', 'name' => 'Partagé'], 'shared.test');

    $this->getJson('/api/v1/catalog/sources/providers')
        ->assertOk()
        ->assertExactJson(['data' => [
            ['id' => "addon:{$shared->id}", 'name' => 'Partagé'],
            ['id' => "addon:{$addon->id}", 'name' => 'Films libres'],
        ]]);

    // Without an account: those of everyone only.
    $this->app['auth']->forgetGuards();
    $this->getJson('/api/v1/catalog/sources/providers')
        ->assertOk()
        ->assertExactJson(['data' => [['id' => "addon:{$shared->id}", 'name' => 'Partagé']]]);
});

it('gives the playable streams of a plug-in for an episode, by its IMDb id', function () {
    $addon = installAddon($this->user);
    Http::fake([
        'api.themoviedb.org/3/tv/1399/external_ids*' => Http::response(['imdb_id' => 'tt0944947']),
        'addon.test/stream/series/tt0944947:1:2.json' => Http::response(['streams' => [
            [
                'name' => "Films libres\n1080p",
                'title' => "Episode 2\n💾 1.2 GB",
                'url' => 'https://cdn.test/got/s1e2/index.m3u8',
                'behaviorHints' => ['proxyHeaders' => ['request' => ['Referer' => 'https://cdn.test/', 'Bad Header' => 'x']]],
            ],
            ['name' => 'Torrent', 'infoHash' => 'abcdef'],
            ['name' => 'YouTube', 'ytId' => 'xyz'],
            ['name' => 'Ailleurs', 'externalUrl' => 'https://example.com'],
            ['name' => 'Mort', 'url' => 'https://cdn.test/dead.mp4'],
        ]]),
    ]);
    app(DeadStreams::class)->markDead("addon:{$addon->id}", 'https://cdn.test/dead.mp4');

    $this->getJson("/api/v1/catalog/tv/1399/season/1/episode/2/sources?provider=addon:{$addon->id}")
        ->assertOk()
        ->assertExactJson(['data' => [[
            'provider' => "addon:{$addon->id}",
            'name' => 'Films libres 1080p · Episode 2 · 💾 1.2 GB',
            'url' => 'https://cdn.test/got/s1e2/index.m3u8',
            'stream' => 'https://cdn.test/got/s1e2/index.m3u8',
            'stream_type' => 'm3u8',
            'extractor' => null,
            'headers' => ['Referer' => 'https://cdn.test/'],
        ]]]);
});

it('asks a plug-in that only knows TMDb ids by its TMDb id, and reports a plug-in that does not answer', function () {
    $addon = installAddon($this->user, ['idPrefixes' => ['tmdb:']]);
    Http::fake([
        'api.themoviedb.org/3/movie/550/external_ids*' => Http::response(['imdb_id' => 'tt0137523']),
        'addon.test/stream/movie/tmdb:550.json' => Http::response(['streams' => [['url' => 'https://cdn.test/fc.mp4']]]),
        'addon.test/*' => Http::response('', 503),
    ]);

    $this->getJson("/api/v1/catalog/movie/550/sources?provider=addon:{$addon->id}")
        ->assertOk()
        ->assertJsonPath('data.0.stream', 'https://cdn.test/fc.mp4')
        ->assertJsonPath('data.0.stream_type', 'mp4')
        ->assertJsonPath('data.0.name', 'Films libres');

    $this->getJson("/api/v1/catalog/movie/551/sources?provider=addon:{$addon->id}")->assertStatus(503);
    $this->getJson('/api/v1/catalog/movie/550/sources?provider=addon:999')->assertNotFound();
});

it('only has the plug-ins\' streams: every plug-in without a provider, nothing for a former provider of the server', function () {
    installAddon($this->user, ['idPrefixes' => ['tmdb:']]);
    installAddon($this->user, ['id' => 'down', 'name' => 'En panne', 'idPrefixes' => ['tmdb:']], 'down.test');
    Http::fake([
        'api.themoviedb.org/3/movie/550/external_ids*' => Http::response(['imdb_id' => 'tt0137523']),
        'addon.test/stream/movie/tmdb:550.json' => Http::response(['streams' => [['url' => 'https://cdn.test/fc.mp4']]]),
        'down.test/*' => Http::response('', 503),
    ]);

    // Every plug-in; the one down leaves the others.
    $this->getJson('/api/v1/catalog/movie/550/sources')
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.stream', 'https://cdn.test/fc.mp4');

    // No scraper, no extractor on the server any more (developer's request).
    $this->getJson('/api/v1/catalog/movie/550/sources?provider=frenchstream')->assertOk()->assertExactJson(['data' => []]);
    $this->postJson('/api/v1/catalog/resolve', ['url' => 'https://voe.sx/e/abc'])
        ->assertStatus(502)
        ->assertJsonPath('message', 'Cette source n’est pas lisible.');
    Http::assertNotSent(fn ($request) => str_contains($request->url(), '3100') || str_contains($request->url(), 'scraper'));
});

// -------------------------------------------------------------------------------------------
// Account

it('removes the plug-ins with the account and puts them in its export', function () {
    installAddon($this->user);

    $export = app(AccountExport::class)->build($this->user);
    expect($export['plugins'])->toHaveCount(1)
        ->and($export['plugins'][0]['name'])->toBe('Films libres')
        ->and($export['plugins'][0]['address'])->toBe('https://addon.test/manifest.json');

    $this->user->delete();
    expect(Addon::count())->toBe(0);
});

it('installs, lists and removes the plug-ins of every account from the command line', function () {
    Http::fake(['shared.test/*' => Http::response(addonManifest(['name' => 'Pour tous']))]);

    $this->artisan('addons:server', ['action' => 'install', 'target' => 'https://shared.test/manifest.json'])
        ->expectsOutputToContain('« Pour tous » installé pour tous les comptes')
        ->assertSuccessful();
    $addon = Addon::sole();
    expect($addon->user_id)->toBeNull();

    $this->artisan('addons:server', ['action' => 'list'])->expectsOutputToContain('shared.test')->assertSuccessful();
    $this->artisan('addons:server', ['action' => 'install', 'target' => 'https://private.test/manifest.json'])->assertFailed();
    $this->artisan('addons:server', ['action' => 'remove', 'target' => (string) $addon->id])->assertSuccessful();
    expect(Addon::count())->toBe(0);
});
