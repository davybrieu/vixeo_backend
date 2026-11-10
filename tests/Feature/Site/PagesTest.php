<?php

use App\Services\GitHubReleases;
use App\Support\AppRelease;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;
use Inertia\Testing\AssertableInertia as Assert;

/*
| Presentation site, served on SITE_DOMAIN (www.vixeo.test in phpunit.xml). Relative URLs hit the
| API host (APP_URL), so every request here is absolute.
*/

const ANDROID_UA = 'Mozilla/5.0 (Linux; Android 14; Pixel 8) AppleWebKit/537.36 Chrome/130.0 Mobile Safari/537.36';

function site(string $path = '/'): string
{
    return 'https://www.vixeo.test'.$path;
}

/** The GitHub release the site shows (null: none published, or GitHub down). */
function withLatestAndroid(?AppRelease $release): void
{
    test()->mock(GitHubReleases::class)
        ->shouldReceive('android')
        ->andReturn(['latest' => $release, 'minimum_build' => null]);
}

function androidRelease(?string $url = 'https://github.com/davybrieu/vixeo_frontend/releases/download/android-14/vixeo.apk', ?string $sha = null): AppRelease
{
    return new AppRelease(
        version: '1.2.0',
        build: 14,
        required: false,
        notes: 'Page acteur.',
        downloadUrl: $url,
        sha256: $sha,
        publishedAt: Carbon::parse('2026-11-05T10:00:00Z'),
    );
}

beforeEach(fn () => Http::preventStrayRequests());

it('renders every page with the shared site settings', function (string $uri, string $component) {
    withLatestAndroid(null);

    $this->get(site($uri))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component($component)
            ->where('site.appUrl', 'https://app.vixeo.test')
            ->where('site.url', 'https://www.vixeo.test')
            ->where('platform', 'web')
            ->where('path', $uri)
            ->has('site.android'));
})->with([
    ['/', 'Home'],
    ['/mentions-legales', 'Legal'],
    ['/confidentialite', 'Privacy'],
]);

it('sets no cookie and sends hardening headers', function () {
    withLatestAndroid(null);

    $response = $this->get(site('/'));

    expect($response->headers->getCookies())->toBeEmpty();
    $response->assertHeader('X-Content-Type-Options', 'nosniff')
        ->assertHeader('X-Frame-Options', 'DENY')
        ->assertHeader('Referrer-Policy', 'strict-origin-when-cross-origin');
    expect($response->headers->get('Vary'))->toContain('User-Agent');
});

it('tells the pages which device is visiting', function () {
    withLatestAndroid(null);

    $this->withHeader('User-Agent', ANDROID_UA)->get(site('/'))
        ->assertInertia(fn (Assert $page) => $page->where('platform', 'android'));
});

it('offers the APK of the latest GitHub release (no Play Store)', function () {
    $sha = str_repeat('ab', 32);
    withLatestAndroid(androidRelease(sha: $sha));
    config(['vixeo.android.apk_url' => 'https://www.vixeo.test/old.apk']);

    $this->get(site('/'))->assertInertia(fn (Assert $page) => $page
        ->where('site.android.apkUrl', 'https://github.com/davybrieu/vixeo_frontend/releases/download/android-14/vixeo.apk')
        ->where('site.android.apkSha256', $sha)
        ->where('latestAndroid.version', '1.2.0')
        ->where('latestAndroid.notes', 'Page acteur.')
        ->missing('site.android.playUrl'));
});

it('falls back to the APK of the .env, or offers none', function () {
    withLatestAndroid(androidRelease(url: null));

    $this->get(site('/'))->assertInertia(fn (Assert $page) => $page->where('site.android.apkUrl', null));

    config(['vixeo.android.apk_url' => 'https://www.vixeo.test/vixeo.apk', 'vixeo.android.apk_sha256' => 'abc']);
    $this->get(site('/'))->assertInertia(fn (Assert $page) => $page
        ->where('site.android.apkUrl', 'https://www.vixeo.test/vixeo.apk')
        ->where('site.android.apkSha256', 'abc'));
});

it('reads the GitHub releases once per page', function () {
    test()->mock(GitHubReleases::class)
        ->shouldReceive('android')->once()
        ->andReturn(['latest' => androidRelease(), 'minimum_build' => null]);

    $this->get(site('/'))->assertOk();
});

it('still renders when the releases cannot be read', function () {
    test()->mock(GitHubReleases::class)
        ->shouldReceive('android')
        ->andThrow(new RuntimeException('Redis is down'));

    $this->get(site('/'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->where('latestAndroid', null)->where('site.android.apkUrl', null));
});

it('gives a short link to type on a TV', function () {
    withLatestAndroid(androidRelease());

    $this->get(site('/apk'))->assertStatus(302)
        ->assertRedirect('https://github.com/davybrieu/vixeo_frontend/releases/download/android-14/vixeo.apk');
});

it('answers 404 on the short link without APK', function () {
    withLatestAndroid(null);

    $this->get(site('/apk'))
        ->assertNotFound()
        ->assertInertia(fn (Assert $page) => $page->component('Error')->where('status', 404));
});

it('lists only the filled legal information', function () {
    withLatestAndroid(null);
    config(['vixeo.publisher.name' => 'Vixeo SAS', 'vixeo.publisher.email' => '', 'vixeo.host.name' => 'Hébergeur SA']);

    $this->get(site('/mentions-legales'))->assertInertia(fn (Assert $page) => $page
        ->where('publisher', ['name' => 'Vixeo SAS'])
        ->where('host', ['name' => 'Hébergeur SA']));
});

it('answers 404 on the old Installer and FAQ pages and short links', function (string $uri) {
    withLatestAndroid(null);

    $this->get(site($uri))
        ->assertNotFound()
        ->assertInertia(fn (Assert $page) => $page->component('Error')->where('status', 404));
})->with(['/installer', '/faq', '/telecharger', '/android', '/tv']);

it('answers the FAQ on the home page, with the install details', function () {
    withLatestAndroid(androidRelease(sha: str_repeat('ab', 32)));

    // The FAQ (resources/js/data/faq.js) is built by the page itself: the home page is the one
    // page of the site, the old ones are gone.
    $this->get(site('/'))->assertOk()->assertInertia(fn (Assert $page) => $page
        ->component('Home')
        ->where('site.android.apkSha256', str_repeat('ab', 32)));
    expect(file_exists(resource_path('js/pages/Faq.vue')))->toBeFalse()
        ->and(file_exists(resource_path('js/pages/Install.vue')))->toBeFalse();
});

it('renders styled error pages', function () {
    withLatestAndroid(null);

    $this->get(site('/nexiste-pas'))
        ->assertNotFound()
        ->assertInertia(fn (Assert $page) => $page->component('Error')->where('status', 404)->has('site'));

    $this->post(site('/mentions-legales'))
        ->assertStatus(405)
        ->assertInertia(fn (Assert $page) => $page->component('Error')->where('status', 405));
});

it('does not serve the API on the site host', function () {
    $this->getJson(site('/api/v1/avatars'))->assertNotFound()->assertJsonPath('message', 'Ressource introuvable.');
});

it('publishes a sitemap and robots.txt with the site address', function () {
    $this->get(site('/sitemap.xml'))
        ->assertOk()
        ->assertHeader('Content-Type', 'application/xml; charset=UTF-8')
        ->assertSee('<loc>https://www.vixeo.test/</loc>', false)
        ->assertSee('<loc>https://www.vixeo.test/mentions-legales</loc>', false)
        ->assertDontSee('/installer', false)
        ->assertDontSee('/faq', false);

    $this->get(site('/robots.txt'))->assertOk()->assertSee('Sitemap: https://www.vixeo.test/sitemap.xml');
});

it('redirects the bare domain to www, keeping the path and query', function () {
    $this->get('https://vixeo.test/')->assertStatus(301)->assertRedirect('https://www.vixeo.test/');
    $this->get('https://vixeo.test/mentions-legales?ref=tv')->assertStatus(301)->assertRedirect('https://www.vixeo.test/mentions-legales?ref=tv');
});

it('answers the health check on both hosts', function () {
    $this->get(site('/up'))->assertOk();
    $this->get('/up')->assertOk();
});
