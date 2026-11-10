<?php

use App\Models\User;
use App\Services\Tv\Epg;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Laravel\Sanctum\Sanctum;

/**
 * Programme guide (EPG) of the live channels, from an XMLTV file configured on the server, and the
 * channel page (`/tv/channels/{id}`).
 */
beforeEach(function () {
    config([
        'services.vavoo.base_urls' => ['https://vavoo.test'],
        'services.vavoo.ping_urls' => ['https://ping.test/api/app/ping'],
        'services.vavoo.country' => 'France',
        'services.vavoo.timeout' => 5,
        'services.vavoo.ttl.signature' => 300,
        'services.vavoo.ttl.catalog' => 1800,
        'services.tv.epg_url' => 'https://guide.test/xmltv.xml',
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

function xmltv(): string
{
    return '<?xml version="1.0" encoding="UTF-8"?>
<tv>
  <channel id="TF1.fr"><display-name>TF1</display-name><icon src="https://guide.test/tf1.png"/></channel>
  <channel id="France2.fr"><display-name>France 2</display-name></channel>
  <programme start="'.xmltvTime(-3).'" stop="'.xmltvTime(-1).'" channel="TF1.fr"><title>Fini</title></programme>
  <programme start="'.xmltvTime(1).'" stop="'.xmltvTime(2).'" channel="TF1.fr">
    <title>Le film du soir</title><sub-title>Épisode 2</sub-title><desc>Un résumé.</desc><category>Film</category>
    <icon src="https://guide.test/film.jpg"/>
  </programme>
  <programme start="'.xmltvTime(-0.5).'" stop="'.xmltvTime(1).'" channel="TF1.fr"><title>Le journal</title></programme>
  <programme start="'.xmltvTime(0).'" stop="'.xmltvTime(1).'" channel="France2.fr"><title>Autre chaîne</title></programme>
</tv>';
}

function fakeTv(?string $guide = null, int $status = 200): void
{
    Http::fake([
        'ping.test/api/app/ping' => Http::response(['addonSig' => 'sig']),
        'vavoo.test/mediahubmx-catalog.json' => Http::response(['items' => [
            ['type' => 'iptv', 'ids' => ['id' => 'tf1'], 'name' => 'TF1 HD', 'url' => 'https://vavoo.test/s/tf1', 'group' => 'France', 'logo' => ''],
            ['type' => 'iptv', 'ids' => ['id' => 'arte'], 'name' => 'Arte', 'url' => 'https://vavoo.test/s/arte', 'group' => 'France', 'logo' => 'https://logo.test/arte.png'],
        ]]),
        'guide.test/xmltv.xml' => Http::response($guide ?? xmltv(), $status),
    ]);
}

it('normalises channel names to match the guide', function (string $name, string $key) {
    expect(Epg::normalize($name))->toBe($key);
})->with([
    ['TF1 HD', 'tf1'],
    ['TF1.fr', 'tf1'],
    ['FR | TF1', 'tf1'],
    ['France 2', 'france2'],
    ['Canal+ Sport', 'canalplussport'],
    ['Équipe 21', 'equipe21'],
]);

it('gives the programmes of a channel matched by its name, from the one on now', function () {
    fakeTv();

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
        ->assertJsonPath('data.1.start', gmdate('Y-m-d\TH:i:s\Z', time() + 3600));

    // The guide is downloaded once, then read from the cache.
    $this->getJson('/api/v1/tv/channels/tf1/epg')->assertOk();
    Http::assertSentCount(3);
});

it('gives a channel, with the guide icon when the provider has no logo', function () {
    fakeTv();

    $this->getJson('/api/v1/tv/channels/tf1')
        ->assertOk()
        ->assertJsonPath('data.name', 'TF1 HD')
        ->assertJsonPath('data.logo', 'https://guide.test/tf1.png');
    // The provider's own logo is kept.
    $this->getJson('/api/v1/tv/channels')
        ->assertOk()
        ->assertJsonPath('data.0.logo', 'https://logo.test/arte.png');
});

it('reads a gzipped guide', function () {
    fakeTv(gzencode(xmltv()));

    $this->getJson('/api/v1/tv/channels/tf1/epg')->assertOk()->assertJsonPath('data.0.title', 'Le journal');
});

it('has an empty guide for a channel it does not know', function () {
    fakeTv();

    $this->getJson('/api/v1/tv/channels/arte/epg')->assertOk()->assertJsonPath('data', [])->assertJsonPath('available', true);
});

it('says when the server has no guide, without asking for one', function () {
    config(['services.tv.epg_url' => null]);
    fakeTv();

    $this->getJson('/api/v1/tv/channels/tf1/epg')->assertOk()->assertJsonPath('data', [])->assertJsonPath('available', false);
    $this->getJson('/api/v1/tv/channels/tf1')->assertOk()->assertJsonPath('data.logo', null);
    Http::assertNotSent(fn ($request) => str_contains($request->url(), 'guide.test'));
});

it('keeps live TV working when the guide fails', function () {
    fakeTv('oops', 500);

    $this->getJson('/api/v1/tv/channels/tf1/epg')->assertOk()->assertJsonPath('data', []);
    $this->getJson('/api/v1/tv/channels')->assertOk()->assertJsonCount(2, 'data');
});

it('answers 404 for an unknown channel', function () {
    fakeTv();

    $this->getJson('/api/v1/tv/channels/nope')->assertNotFound()->assertJsonPath('message', 'Chaîne introuvable.');
    $this->getJson('/api/v1/tv/channels/nope/epg')->assertNotFound();
});
