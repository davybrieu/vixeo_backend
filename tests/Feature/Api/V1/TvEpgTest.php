<?php

use App\Models\User;
use App\Services\Tv\ChannelName;
use App\Services\Tv\Epg;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Laravel\Sanctum\Sanctum;

/**
 * Programme guide (EPG) of the live channels, from the XMLTV files of `TV_EPG_URLS`, and the
 * channel logos of the guide and of the logo library (`TV_LOGOS_REPOSITORY`).
 */
beforeEach(function () {
    config([
        'services.vavoo.base_urls' => ['https://vavoo.test'],
        'services.vavoo.ping_urls' => ['https://ping.test/api/app/ping'],
        'services.vavoo.country' => 'France',
        'services.vavoo.timeout' => 5,
        'services.vavoo.ttl.signature' => 300,
        'services.vavoo.ttl.catalog' => 1800,
        'services.tv.epg_urls' => ['https://guide.test/one.xml.gz', 'https://other.test/two.xml'],
        'services.tv.logos_repository' => '',
    ]);
    Http::preventStrayRequests();
    Cache::flush();
    Sanctum::actingAs(User::factory()->create());
});

/** XMLTV time, `$hours` from now. */
function xmltvTime(float $hours): string
{
    return gmdate('YmdHis', (int) (time() + $hours * 3600)).' +0000';
}

function firstGuide(): string
{
    return '<?xml version="1.0" encoding="UTF-8"?>
<tv>
  <channel id="TF1.fr"><display-name>TF1</display-name></channel>
  <channel id="France2.fr"><display-name>France 2</display-name></channel>
  <programme start="'.xmltvTime(-3).'" stop="'.xmltvTime(-1).'" channel="TF1.fr"><title>Fini</title></programme>
  <programme start="'.xmltvTime(1).'" stop="'.xmltvTime(2).'" channel="TF1.fr">
    <title>Le film du soir</title><sub-title>Épisode 2</sub-title><desc>Un résumé.</desc><category>Film</category>
    <icon src="https://guide.test/film.jpg"/>
  </programme>
  <programme start="'.xmltvTime(-0.5).'" stop="'.xmltvTime(1).'" channel="TF1.fr"><title>Le journal</title></programme>
</tv>';
}

function secondGuide(): string
{
    return '<?xml version="1.0" encoding="UTF-8"?>
<tv>
  <channel id="tf1-alt"><display-name>TF1 HD</display-name><icon src="https://other.test/tf1.png"/></channel>
  <channel id="arte-1"><display-name>ARTE</display-name><icon src="https://other.test/arte.png"/></channel>
  <programme start="'.xmltvTime(-0.5).'" stop="'.xmltvTime(1).'" channel="tf1-alt"><title>Pas celui-ci</title></programme>
  <programme start="'.xmltvTime(-0.5).'" stop="'.xmltvTime(2).'" channel="arte-1"><title>Documentaire</title></programme>
</tv>';
}

function fakeTv(mixed $first = null, int $status = 200, mixed $second = null): void
{
    Http::fake([
        'ping.test/api/app/ping' => Http::response(['addonSig' => 'sig']),
        'vavoo.test/mediahubmx-catalog.json' => Http::response(['items' => [
            ['type' => 'iptv', 'ids' => ['id' => 'tf1'], 'name' => 'TF1 HD', 'url' => 'https://vavoo.test/s/tf1', 'group' => 'France', 'logo' => ''],
            ['type' => 'iptv', 'ids' => ['id' => 'arte'], 'name' => 'Arte', 'url' => 'https://vavoo.test/s/arte', 'group' => 'France', 'logo' => 'https://logo.test/arte.png'],
            ['type' => 'iptv', 'ids' => ['id' => 'm6'], 'name' => 'M6', 'url' => 'https://vavoo.test/s/m6', 'group' => 'France', 'logo' => ''],
        ]]),
        'guide.test/one.xml.gz' => is_object($first) ? $first : Http::response($first ?? gzencode(firstGuide()), $status),
        'other.test/two.xml' => $second ?? Http::response(secondGuide()),
        'api.github.com/repos/tv-logo/tv-logos/contents/countries/france' => Http::response([
            ['name' => 'tf1-fr.png', 'type' => 'file', 'download_url' => 'https://raw.githubusercontent.com/tv-logo/tv-logos/main/countries/france/tf1-fr.png'],
            ['name' => 'm6-fr.png', 'type' => 'file', 'download_url' => 'https://raw.githubusercontent.com/tv-logo/tv-logos/main/countries/france/m6-fr.png'],
            ['name' => '0_all_logos_mosaic.md', 'type' => 'file', 'download_url' => 'https://raw.githubusercontent.com/x.md'],
            ['name' => 'hd', 'type' => 'dir', 'download_url' => null],
        ]),
        'api.github.com/repos/tv-logo/tv-logos/contents/countries/international' => Http::response([
            ['name' => 'tf1-int.png', 'type' => 'file', 'download_url' => 'https://raw.githubusercontent.com/tv-logo/tv-logos/main/countries/international/tf1-int.png'],
        ]),
    ]);
}

it('gives one key per channel name', function (string $name, string $key) {
    expect(ChannelName::key($name))->toBe($key);
})->with([
    ['TF1 HD', 'tf1'],
    ['TF1.fr', 'tf1'],
    ['FR | TF1', 'tf1'],
    ['tf1', 'tf1'],
    ['France 2', 'france2'],
    ['france-2', 'france2'],
    ['Canal+ Sport', 'canalplussport'],
    ['canal-plus-sport', 'canalplussport'],
    ['Équipe 21', 'equipe21'],
    ["L'Équipe", 'lequipe'],
    ['BFMTV', 'bfm'],
    ['BFM TV', 'bfm'],
    ['MTV', 'mtv'],
]);

it('gives the programmes of a channel from the first guide that has them', function () {
    fakeTv();
    app(Epg::class)->refresh();

    $this->getJson('/api/v1/tv/channels/tf1/epg')
        ->assertOk()
        ->assertJsonPath('available', true)
        ->assertJsonCount(2, 'data')
        ->assertJsonPath('data.0.title', 'Le journal')
        ->assertJsonPath('data.1.title', 'Le film du soir')
        ->assertJsonPath('data.1.subtitle', 'Épisode 2')
        ->assertJsonPath('data.1.description', 'Un résumé.')
        ->assertJsonPath('data.1.category', 'Film')
        ->assertJsonPath('data.1.image', 'https://guide.test/film.jpg')
        ->assertJsonPath('data.1.start', fn (string $start) => abs(strtotime($start) - (time() + 3600)) <= 5 && str_ends_with($start, 'Z'));
    // Only the second guide knows Arte.
    $this->getJson('/api/v1/tv/channels/arte/epg')->assertOk()->assertJsonPath('data.0.title', 'Documentaire');
    $this->getJson('/api/v1/tv/channels/m6/epg')->assertOk()->assertJsonPath('data', [])->assertJsonPath('available', true);
});

it('downloads the guide after the response when nothing is cached yet', function () {
    fakeTv();

    // First request: no guide yet, it is downloaded once the response is sent.
    $this->getJson('/api/v1/tv/channels/tf1/epg')->assertOk()->assertJsonPath('data', []);
    $this->getJson('/api/v1/tv/channels/tf1/epg')->assertOk()->assertJsonPath('data.0.title', 'Le journal');
    Http::assertSentCount(4); // ping, catalog, then the two guides once
});

it('takes the logos from the library, then the guide, then the provider, all from our origin', function () {
    config(['services.tv.logos_repository' => 'tv-logo/tv-logos', 'services.tv.logos_directories' => ['countries/france', 'countries/international']]);
    fakeTv();
    app(Epg::class)->refresh();

    $channels = collect($this->getJson('/api/v1/tv/channels')->assertOk()->json('data'))->keyBy('id');
    expect($channels['tf1']['logo'])->toBe(proxiedLogo(implode("\n", [
        'https://raw.githubusercontent.com/tv-logo/tv-logos/main/countries/france/tf1-fr.png',
        'https://other.test/tf1.png',
    ])))
        ->and($channels['arte']['logo'])->toBe(proxiedLogo("https://other.test/arte.png\nhttps://logo.test/arte.png"))
        ->and($channels['m6']['logo'])->toBe(proxiedLogo('https://raw.githubusercontent.com/tv-logo/tv-logos/main/countries/france/m6-fr.png'))
        ->and($channels['tf1'])->not->toHaveKey('epg_channel_id');

    // The relay serves the first candidate that gives an image.
    Http::fake([
        'raw.githubusercontent.com/*' => Http::response('', 404),
        'other.test/tf1.png' => Http::response(base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8BQDwAEhQGAhKmMIQAAAABJRU5ErkJggg==')),
    ]);
    $this->get($channels['tf1']['logo'])->assertOk()->assertHeader('Content-Type', 'image/png');
    $this->get($channels['m6']['logo'])->assertNotFound();
});

it('downloads the guide and the logo library from the command line (php artisan tv:epg)', function () {
    config(['services.tv.logos_repository' => 'tv-logo/tv-logos', 'services.tv.logos_directories' => ['countries/france']]);
    fakeTv();

    $this->artisan('tv:epg')
        ->expectsOutputToContain('https://guide.test/one.xml.gz : 2 chaînes, 2 programmes')
        ->expectsOutputToContain('https://other.test/two.xml : 2 chaînes, 2 programmes')
        ->expectsOutputToContain('Bibliothèque de logos : 2 logos')
        ->assertSuccessful();
});

it('says when the server has no guide, without asking for one', function () {
    config(['services.tv.epg_urls' => []]);
    fakeTv();

    $this->getJson('/api/v1/tv/channels/tf1/epg')->assertOk()->assertJsonPath('data', [])->assertJsonPath('available', false);
    $this->getJson('/api/v1/tv/channels/tf1')->assertOk()->assertJsonPath('data.logo', null);
    Http::assertNotSent(fn ($request) => str_contains($request->url(), 'guide.test') || str_contains($request->url(), 'github'));
});

it('keeps the other guides, and live TV, when a guide fails', function () {
    fakeTv('oops', 500);

    $report = app(Epg::class)->refresh();
    expect($report[0]['error'])->not->toBeNull()->and($report[1]['error'])->toBeNull();
    $this->getJson('/api/v1/tv/channels/tf1/epg')->assertOk()->assertJsonPath('data.0.title', 'Pas celui-ci');
    $this->getJson('/api/v1/tv/channels')->assertOk()->assertJsonCount(3, 'data');
});

it('keeps the previous guide when no file can be read', function () {
    fakeTv(
        Http::sequence()->push(gzencode(firstGuide()))->push('', 500),
        second: Http::sequence()->push(secondGuide())->push('not xml'),
    );
    app(Epg::class)->refresh();

    $report = app(Epg::class)->refresh();
    expect(array_column($report, 'error'))->each->not->toBeNull();
    $this->getJson('/api/v1/tv/channels/tf1/epg')->assertOk()->assertJsonPath('data.0.title', 'Le journal');
});

it('answers 404 for the guide of an unknown channel', function () {
    fakeTv();

    $this->getJson('/api/v1/tv/channels/nope/epg')->assertNotFound()->assertJsonPath('message', 'Chaîne introuvable.');
});
