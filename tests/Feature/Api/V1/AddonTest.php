<?php

use App\Models\Addon;
use App\Models\AddonPreference;
use App\Models\Profile;
use App\Models\User;
use App\Services\AccountExport;
use App\Services\Addons\Addons;
use App\Services\Addons\HostResolver;
use App\Services\Playback\DeadStreams;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Laravel\Sanctum\Sanctum;
use Tests\Feature\Api\V1\Catalog\TmdbFixtures as Tmdb;

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
        'services.scraper.base_url' => '',
        'services.scraper.providers' => [],
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
// The Vixeo catalog, in the Stremio format

it('serves the manifest of the Vixeo catalog, publicly, to any website', function () {
    $this->app['auth']->forgetGuards();

    $response = $this->getJson('/api/v1/addons/vixeo/manifest.json')
        ->assertOk()
        ->assertHeader('Access-Control-Allow-Origin', '*')
        ->assertJsonPath('id', 'app.vixeo.catalog')
        ->assertJsonPath('name', 'Vixeo')
        ->assertJsonPath('resources', ['catalog', 'meta'])
        ->assertJsonPath('types', ['movie', 'series'])
        ->assertJsonPath('idPrefixes', ['tmdb:']);

    $catalogs = collect($response->json('catalogs'));
    expect($catalogs->where('type', 'movie')->pluck('id'))->toContain('vixeo.trending', 'vixeo.popular', 'vixeo.search')
        ->and($catalogs->where('type', 'series')->pluck('id'))->toContain('vixeo.trending', 'vixeo.on_the_air')
        ->and($catalogs->firstWhere('id', 'vixeo.search')['extra'][0])->toBe(['name' => 'search', 'isRequired' => true]);
});

it('serves a catalog of the Vixeo plug-in as Stremio metas, and its next pages through skip', function () {
    Http::fake([
        'api.themoviedb.org/3/trending/movie/week*' => Http::response(Tmdb::page([Tmdb::movie(550)], 3)),
    ]);

    $this->getJson('/api/v1/addons/vixeo/catalog/movie/vixeo.trending.json')
        ->assertOk()
        ->assertJsonPath('metas.0', [
            'id' => 'tmdb:550',
            'type' => 'movie',
            'name' => 'Fight Club',
            'poster' => 'https://image.tmdb.org/t/p/w342/poster.jpg',
            'posterShape' => 'poster',
            'background' => 'https://image.tmdb.org/t/p/w1280/backdrop.jpg',
            'description' => 'Le narrateur, sans identité précise…',
            'releaseInfo' => '1999',
            'imdbRating' => '8.4',
        ]);

    $this->getJson('/api/v1/addons/vixeo/catalog/movie/vixeo.trending/skip=40.json')->assertOk();
    Http::assertSent(fn (Request $request) => str_contains($request->url(), 'trending/movie/week') && $request['page'] === 3);
});

it('searches through the Vixeo plug-in, and gives nothing for an unknown catalog', function () {
    Http::fake([
        'api.themoviedb.org/3/search/tv*' => Http::response(Tmdb::page([Tmdb::show(1399)])),
    ]);

    $this->getJson('/api/v1/addons/vixeo/catalog/series/vixeo.search/search=game%20of%20thrones.json')
        ->assertOk()
        ->assertJsonPath('metas.0.id', 'tmdb:1399')
        ->assertJsonPath('metas.0.type', 'series');
    Http::assertSent(fn (Request $request) => $request['query'] === 'game of thrones');

    $this->getJson('/api/v1/addons/vixeo/catalog/movie/other.json')->assertOk()->assertExactJson(['metas' => []]);
    $this->getJson('/api/v1/addons/vixeo/catalog/channel/vixeo.trending.json')->assertOk()->assertExactJson(['metas' => []]);
});

it('serves the meta of a series with its episodes', function () {
    Http::fake([
        'api.themoviedb.org/3/tv/1399/season/0*' => Http::response(['episodes' => []]),
        'api.themoviedb.org/3/tv/1399/season/1*' => Http::response(['episodes' => [
            ['season_number' => 1, 'episode_number' => 1, 'name' => "L'hiver vient", 'air_date' => '2011-04-17', 'overview' => 'Début.', 'still_path' => '/e1.jpg'],
        ]]),
        'api.themoviedb.org/3/tv/1399*' => Http::response(Tmdb::showDetails()),
    ]);

    $this->getJson('/api/v1/addons/vixeo/meta/series/tmdb:1399.json')
        ->assertOk()
        ->assertJsonPath('meta.id', 'tmdb:1399')
        ->assertJsonPath('meta.imdb_id', 'tt0944947')
        ->assertJsonPath('meta.releaseInfo', '2011-2019')
        ->assertJsonPath('meta.runtime', '60 min')
        ->assertJsonPath('meta.cast', ['Peter Dinklage'])
        ->assertJsonPath('meta.videos', [[
            'id' => 'tmdb:1399:1:1',
            'title' => "L'hiver vient",
            'season' => 1,
            'episode' => 1,
            'released' => '2011-04-17T00:00:00.000Z',
            'overview' => 'Début.',
            'thumbnail' => 'https://image.tmdb.org/t/p/w300/e1.jpg',
        ]]);

    $this->getJson('/api/v1/addons/vixeo/meta/series/tt0944947.json')->assertNotFound();
});

// -------------------------------------------------------------------------------------------
// Installed plug-ins

it('lists the Vixeo catalog first, built in, then those of everyone and the account’s own', function () {
    installAddon(null, ['id' => 'org.example.shared', 'name' => 'Pour tous'], 'shared.test');
    installAddon($this->user);
    installAddon(User::factory()->create(), ['name' => 'Pas le mien'], 'other.test');

    $this->getJson('/api/v1/addons')
        ->assertOk()
        ->assertJsonCount(3, 'data')
        ->assertJsonPath('data.0.id', 'vixeo')
        ->assertJsonPath('data.0.built_in', true)
        ->assertJsonPath('data.0.enabled', true)
        ->assertJsonPath('data.0.host', null)
        ->assertJsonPath('data.0.manifest_url', route('v1.addons.vixeo.manifest'))
        ->assertJsonPath('data.1.name', 'Pour tous')
        ->assertJsonPath('data.1.shared', true)
        ->assertJsonPath('data.2.name', 'Films libres')
        ->assertJsonPath('data.2.shared', false)
        ->assertJsonPath('data.2.host', 'addon.test')
        ->assertJsonPath('data.2.version', '2.1.0')
        ->assertJsonPath('data.2.resources', ['catalog', 'stream'])
        ->assertJsonPath('data.2.types', ['movie', 'tv'])
        ->assertJsonPath('data.2.catalogs', 1)
        ->assertJsonPath('data.2.logo_url', 'https://addon.test/logo.png');
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
        'Ce plug-in ne propose ni catalogue ni flux de films ou de séries.',
    ],
    'the Vixeo catalog itself' => [fn () => Http::response(addonManifest(['id' => 'app.vixeo.catalog'])), 'Ce plug-in est déjà installé.'],
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

    $this->patchJson("/api/v1/addons/{$someoneElse->id}", ['enabled' => false])->assertNotFound();
    $this->deleteJson("/api/v1/addons/{$shared->id}")->assertNotFound();
    $this->deleteJson("/api/v1/addons/{$someoneElse->id}")->assertNotFound();
    $this->deleteJson('/api/v1/addons/vixeo')->assertNotFound();

    $this->deleteJson("/api/v1/addons/{$first->id}")->assertNoContent();
    expect(Addon::find($first->id))->toBeNull()->and(Addon::count())->toBe(3);
});

it('turns the Vixeo catalog off and on for the account: its home rows go, the rest stays', function () {
    $this->patchJson('/api/v1/addons/vixeo', ['enabled' => false])
        ->assertOk()
        ->assertJsonPath('data.id', 'vixeo')
        ->assertJsonPath('data.enabled', false);
    $this->getJson('/api/v1/addons')->assertJsonPath('data.0.enabled', false);

    Http::fake();
    $this->getJson('/api/v1/catalog/home')->assertOk()->assertExactJson(['featured' => [], 'sections' => []]);
    Http::assertNothingSent();
    // The public Stremio plug-in stays (other apps may use it).
    $this->getJson('/api/v1/addons/vixeo/manifest.json')->assertOk();

    // Another account, and a guest, keep it.
    expect(app(Addons::class)->vixeoEnabled(User::factory()->create()))->toBeTrue()
        ->and(app(Addons::class)->vixeoEnabled(null))->toBeTrue();

    $this->patchJson('/api/v1/addons/vixeo', ['enabled' => true])->assertOk()->assertJsonPath('data.enabled', true);
    expect(app(Addons::class)->vixeoEnabled($this->user))->toBeTrue();
    $this->deleteJson('/api/v1/addons/vixeo')->assertNotFound();
});

it('turns a plug-in of every account off for this account only', function () {
    $shared = installAddon(null, ['id' => 'shared', 'name' => 'Partagé'], 'shared.test');
    $other = User::factory()->create();

    $this->patchJson("/api/v1/addons/{$shared->id}", ['enabled' => false])
        ->assertOk()
        ->assertJsonPath('data.enabled', false)
        ->assertJsonPath('data.shared', true);
    expect($shared->fresh()->enabled)->toBeTrue();

    Http::fake();
    $this->getJson('/api/v1/catalog/sources/providers')->assertOk()->assertExactJson(['data' => []]);
    $this->getJson("/api/v1/catalog/movie/550/sources?provider=addon:{$shared->id}")->assertNotFound();
    $this->getJson('/api/v1/catalog/addons/sections')->assertOk()->assertJsonCount(0, 'sections');
    Http::assertNothingSent();

    Sanctum::actingAs($other);
    $this->getJson('/api/v1/catalog/sources/providers')
        ->assertOk()
        ->assertExactJson(['data' => [['id' => "addon:{$shared->id}", 'name' => 'Partagé']]]);
});

it('needs an account to manage plug-ins', function () {
    $this->app['auth']->forgetGuards();

    $this->getJson('/api/v1/addons')->assertUnauthorized();
    $this->postJson('/api/v1/addons', ['url' => 'https://addon.test/manifest.json'])->assertUnauthorized();
});

// -------------------------------------------------------------------------------------------
// Catalogs of plug-ins: rows of the home and "see all"

it('shows the catalogs of the plug-ins as rows of TMDb titles, after the home', function () {
    $addon = installAddon($this->user);
    Http::fake([
        'addon.test/catalog/movie/top.json' => Http::response(['metas' => [
            ['id' => 'tt0137523', 'type' => 'movie', 'name' => 'Fight Club'],
            ['id' => 'tmdb:551', 'type' => 'movie', 'name' => 'Autre'],
            ['id' => 'kitsu:1', 'type' => 'movie', 'name' => 'Inconnu de TMDb'],
            ['id' => 'tt0000001', 'type' => 'movie', 'name' => 'Absent de TMDb'],
        ]]),
        'api.themoviedb.org/3/find/tt0137523*' => Http::response(['movie_results' => [Tmdb::movie(550)], 'tv_results' => []]),
        'api.themoviedb.org/3/find/tt0000001*' => Http::response(['movie_results' => [], 'tv_results' => []]),
        'api.themoviedb.org/3/movie/551*' => Http::response(Tmdb::movie(551, ['title' => 'Autre', 'genres' => [['id' => 18, 'name' => 'Drame']]])),
    ]);

    $this->getJson('/api/v1/catalog/addons/sections')
        ->assertOk()
        ->assertJsonCount(1, 'sections')
        ->assertJsonPath('sections.0.key', "movie_addon:{$addon->id}:top")
        ->assertJsonPath('sections.0.title', 'Les meilleurs · Films libres')
        ->assertJsonPath('sections.0.type', 'movie')
        ->assertJsonPath('sections.0.list', "addon:{$addon->id}:top")
        ->assertJsonPath('sections.0.items.0.id', 550)
        ->assertJsonPath('sections.0.items.0.title', 'Fight Club')
        ->assertJsonPath('sections.0.items.1.id', 551)
        ->assertJsonPath('sections.0.items.1.genre_ids', [18])
        ->assertJsonCount(2, 'sections.0.items');
});

it('leaves out the rows of a plug-in that fails or is turned off, and adult plug-ins for a restricted profile', function () {
    installAddon($this->user);
    $adult = installAddon($this->user, ['id' => 'adult', 'name' => 'Adulte', 'behaviorHints' => ['adult' => true]], 'adult.test');
    installAddon($this->user, ['id' => 'off'], 'off.test')->update(['enabled' => false]);
    $kid = Profile::factory()->for($this->user)->kid(12)->create();
    Http::fake([
        'addon.test/*' => Http::response('', 500),
        'adult.test/catalog/movie/top.json' => Http::response(['metas' => [['id' => 'tt0137523']]]),
        'api.themoviedb.org/3/find/*' => Http::response(['movie_results' => [Tmdb::movie(550)]]),
        'api.themoviedb.org/3/movie/550/release_dates*' => Http::response(['results' => [['iso_3166_1' => 'FR', 'release_dates' => [['certification' => 'U']]]]]),
    ]);

    $this->getJson('/api/v1/catalog/addons/sections')
        ->assertOk()
        ->assertJsonCount(1, 'sections')
        ->assertJsonPath('sections.0.list', "addon:{$adult->id}:top");

    $this->withHeader('X-Profile-Id', (string) $kid->id)
        ->getJson('/api/v1/catalog/addons/sections')
        ->assertOk()
        ->assertJsonCount(0, 'sections');

    Http::assertNotSent(fn (Request $request) => str_contains($request->url(), 'off.test'));
});

it('pages a plug-in catalog with skip, the size of its first page', function () {
    $addon = installAddon($this->user);
    Http::fake([
        'addon.test/catalog/movie/top/skip=2.json' => Http::response(['metas' => [['id' => 'tmdb:552']]]),
        'addon.test/catalog/movie/top.json' => Http::response(['metas' => [['id' => 'tmdb:550'], ['id' => 'tmdb:551']]]),
        'api.themoviedb.org/3/movie/*' => fn (Request $request) => Http::response(Tmdb::movie((int) basename(parse_url($request->url(), PHP_URL_PATH)))),
    ]);

    $this->getJson("/api/v1/catalog/movie/lists/addon:{$addon->id}:top")
        ->assertOk()
        ->assertJsonCount(2, 'data')
        ->assertJsonPath('meta.page', 1)
        ->assertJsonPath('meta.total_pages', 2);

    $this->getJson("/api/v1/catalog/movie/lists/addon:{$addon->id}:top?page=2")
        ->assertOk()
        ->assertJsonPath('data.0.id', 552)
        ->assertJsonPath('meta.total_pages', 2);

    $this->getJson("/api/v1/catalog/movie/lists/addon:{$addon->id}:unknown")->assertNotFound();
    $this->getJson('/api/v1/catalog/movie/lists/addon:999:top')->assertNotFound();
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

// -------------------------------------------------------------------------------------------
// Account

it('removes the plug-ins with the account and puts them in its export', function () {
    installAddon($this->user);
    $this->patchJson('/api/v1/addons/vixeo', ['enabled' => false])->assertOk();

    $export = app(AccountExport::class)->build($this->user);
    expect($export['plugin_settings'])->toBe([['plugin' => 'vixeo', 'enabled' => false]])
        ->and($export['plugins'])->toHaveCount(1)
        ->and($export['plugins'][0]['name'])->toBe('Films libres')
        ->and($export['plugins'][0]['address'])->toBe('https://addon.test/manifest.json');

    $this->user->delete();
    expect(Addon::count())->toBe(0)->and(AddonPreference::count())->toBe(0);
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
