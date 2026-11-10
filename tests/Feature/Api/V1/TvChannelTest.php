<?php

use App\Models\User;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Laravel\Sanctum\Sanctum;

/**
 * The channel page of the app (`/tv/channels/{id}`) and the channel logos, served from our origin
 * (`/tv/logo/{hash}`, App\Services\Tv\ChannelLogos).
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

it('gives a channel with its logo on our origin, or none', function () {
    $this->getJson('/api/v1/tv/channels/arte')
        ->assertOk()
        ->assertJsonPath('data.name', 'Arte')
        ->assertJsonPath('data.logo', proxiedLogo('https://logo.test/arte.png'));
    $this->getJson('/api/v1/tv/channels/tf1')
        ->assertOk()
        ->assertJsonPath('data.name', 'TF1 HD')
        ->assertJsonPath('data.logo', null);
});

it('answers 404 for an unknown channel', function () {
    $this->getJson('/api/v1/tv/channels/nope')->assertNotFound()->assertJsonPath('message', 'Chaîne introuvable.');
});

/** A 1×1 PNG. */
function tinyPng(): string
{
    return base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8BQDwAEhQGAhKmMIQAAAABJRU5ErkJggg==');
}

it('relays the logo from our origin, without the provider address, and never stores it', function () {
    Http::fake(['logo.test/arte.png' => Http::response(tinyPng(), 200, ['Content-Type' => 'text/plain'])]);

    $channels = $this->getJson('/api/v1/tv/channels')->assertOk();
    expect($channels->getContent())->not->toContain('logo.test');

    $logo = $this->getJson('/api/v1/tv/channels/arte')->json('data.logo');
    // Public: an image is loaded without a bearer token.
    $this->app['auth']->forgetGuards();
    $this->get($logo)
        ->assertOk()
        ->assertHeader('Content-Type', 'image/png')
        ->assertHeader('Cache-Control', 'max-age=604800, public');
    // Flutter web downloads images with fetch: the web app's origin must be allowed (CORS).
    $this->get($logo, ['Origin' => 'https://app.vixeo.test'])
        ->assertOk()
        ->assertHeader('Access-Control-Allow-Origin', 'https://app.vixeo.test');

    // Asked twice, fetched twice from the provider: nothing is kept on our server (the apps and
    // browsers cache it 7 days).
    Http::assertSentCount(4); // ping and catalog of the provider, then the logo for each request
    expect(Cache::get('tv:logos:image:'.substr(hash('sha256', 'https://logo.test/arte.png'), 0, 32)))->toBeNull();
});

it('answers 404 for a logo that is not an image, too big, or unknown', function () {
    Http::fake([
        'logo.test/arte.png' => Http::response('<html>Not found</html>', 200, ['Content-Type' => 'image/png']),
    ]);
    $logo = $this->getJson('/api/v1/tv/channels/arte')->json('data.logo');

    $this->get($logo)->assertNotFound();
    $this->get('/api/v1/tv/logo/'.str_repeat('0', 32))->assertNotFound();
    $this->get('/api/v1/tv/logo/not-a-hash')->assertNotFound();
});

it('refuses a logo bigger than 512 KB', function () {
    Http::fake(['logo.test/arte.png' => Http::response(tinyPng().str_repeat("\0", 600 * 1024))]);
    $logo = $this->getJson('/api/v1/tv/channels/arte')->json('data.logo');

    $this->get($logo)->assertNotFound();
});

it('diagnoses the logos from the command line (php artisan tv:logos)', function () {
    Http::fake(['logo.test/arte.png' => Http::response(tinyPng())]);

    $this->artisan('tv:logos')
        ->expectsOutputToContain('2 chaînes, 1 avec un logo du fournisseur.')
        ->expectsOutputToContain('Arte : OK, image/png')
        ->assertSuccessful();
});

it('tells why a logo is refused (php artisan tv:logos)', function () {
    Http::fake(['logo.test/arte.png' => Http::response('<svg xmlns="http://www.w3.org/2000/svg"></svg>', 200, ['Content-Type' => 'image/svg+xml'])]);

    $this->artisan('tv:logos')
        ->expectsOutputToContain('Arte : REFUSÉ, SVG image (not accepted) (HTTP 200, type image/svg+xml')
        ->expectsOutputToContain('adresse du fournisseur : https://logo.test/arte.png')
        ->assertSuccessful();
});

it('falls back to plain HTTP when the logo host breaks the TLS handshake', function () {
    Http::fake(function (Request $request) {
        if ($request->url() === 'https://logo.test/arte.png') {
            throw new ConnectionException('cURL error 35: TLS connect error');
        }

        return $request->url() === 'http://logo.test/arte.png' ? Http::response(tinyPng()) : null;
    });
    Http::fake([
        'ping.test/api/app/ping' => Http::response(['addonSig' => 'sig']),
        'vavoo.test/mediahubmx-catalog.json' => Http::response(['items' => [
            ['type' => 'iptv', 'ids' => ['id' => 'arte'], 'name' => 'Arte', 'url' => 'https://vavoo.test/s/arte', 'group' => 'France', 'logo' => 'https://logo.test/arte.png'],
        ]]),
    ]);

    $logo = $this->getJson('/api/v1/tv/channels/arte')->json('data.logo');

    // The app still gets it over https, from our origin.
    expect($logo)->toStartWith('https://api.vixeo.test/');
    $this->get($logo)->assertOk()->assertHeader('Content-Type', 'image/png');
    $this->artisan('tv:logos')
        ->expectsOutputToContain('Arte : OK, image/png')
        ->expectsOutputToContain('adresse du fournisseur : http://logo.test/arte.png')
        ->assertSuccessful();
});
