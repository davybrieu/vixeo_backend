<?php

use App\Enums\AppPlatform;
use App\Models\Profile;
use App\Services\GitHubReleases;
use App\Support\AppRelease;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;
use Inertia\Testing\AssertableInertia as Assert;

/*
| Presentation site, served on APP_URL (www.vixeo.test in phpunit.xml). Relative URLs hit the
| API host (API_URL, tests/TestCase.php), so every request here is absolute.
*/

const ANDROID_UA = 'Mozilla/5.0 (Linux; Android 14; Pixel 8) AppleWebKit/537.36 Chrome/130.0 Mobile Safari/537.36';

function site(string $path = '/'): string
{
    return 'https://www.vixeo.test'.$path;
}

/** The GitHub releases the site shows (null: none published, or GitHub down). */
function withLatestAndroid(?AppRelease $release, ?AppRelease $windows = null): void
{
    test()->mock(GitHubReleases::class)
        ->shouldReceive('for')
        ->andReturnUsing(fn (AppPlatform $platform) => [
            'latest' => $platform === AppPlatform::Windows ? $windows : $release,
            'minimum_build' => null,
        ]);
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
            ->missing('site.appUrl')
            ->where('site.url', 'https://www.vixeo.test')
            ->where('platform', 'windows')
            ->where('path', $uri)
            ->has('site.android')
            ->has('site.windows'));
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

it('offers the Windows installer of the latest GitHub release', function () {
    $sha = str_repeat('cd', 32);
    $windows = new AppRelease(
        version: '1.2.0',
        build: 14,
        required: false,
        notes: null,
        downloadUrl: 'https://github.com/davybrieu/vixeo_frontend/releases/download/windows-14/vixeo-setup-14.exe',
        sha256: $sha,
        publishedAt: Carbon::parse('2026-11-05T10:00:00Z'),
    );
    withLatestAndroid(androidRelease(), $windows);

    $this->get(site('/'))->assertInertia(fn (Assert $page) => $page
        ->where('site.windows.installerUrl', 'https://github.com/davybrieu/vixeo_frontend/releases/download/windows-14/vixeo-setup-14.exe')
        ->where('site.windows.installerSha256', $sha)
        ->where('site.windows.minimum', 'Windows 10 (64 bits)')
        ->where('latestWindows.version', '1.2.0'));
});

it('offers no Windows installer before the first Windows release', function () {
    withLatestAndroid(androidRelease());

    $this->get(site('/'))->assertInertia(fn (Assert $page) => $page
        ->where('site.windows.installerUrl', null)
        ->where('latestWindows', null));
});

it('reads the GitHub releases once per page and platform', function () {
    $github = test()->mock(GitHubReleases::class);
    $github->shouldReceive('for')->with(AppPlatform::Android)->once()
        ->andReturn(['latest' => androidRelease(), 'minimum_build' => null]);
    $github->shouldReceive('for')->with(AppPlatform::Windows)->once()
        ->andReturn(['latest' => null, 'minimum_build' => null]);

    $this->get(site('/'))->assertOk();
});

it('still renders when the releases cannot be read', function () {
    test()->mock(GitHubReleases::class)
        ->shouldReceive('for')
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

it('answers the health check on both hosts', function () {
    $this->get(site('/up'))->assertOk();
    $this->get('/up')->assertOk();
});

it('shows the public page of a profile, shared from the app', function () {
    withLatestAndroid(null);
    $profile = Profile::factory()->create([
        'name' => 'Léa', 'username' => 'lea', 'bio' => 'Fan de science-fiction.', 'avatar' => 'quest-knight',
    ]);

    $this->get(site('/@lea'))
        ->assertOk()
        // Not offered to search engines, nor in the sitemap.
        ->assertHeader('X-Robots-Tag', 'noindex')
        ->assertInertia(fn (Assert $page) => $page
            ->component('Member')
            ->where('member.name', 'Léa')
            ->where('member.username', 'lea')
            ->where('member.bio', 'Fan de science-fiction.')
            ->where('member.avatar.background', '#1E2A3A')
            ->has('member.avatar.pixels', 12)
            ->where('openInApp', null)
            ->missing('member.email')
            ->missing('member.id'));

    // The username is found whatever its case; Android gets « Ouvrir dans Vixeo » (intent://).
    $this->withHeader('User-Agent', ANDROID_UA)->get(site('/@LEA'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->where(
            'openInApp',
            'intent://www.vixeo.test/@lea#Intent;scheme=https;package=app.vixeo.vixeo;S.browser_fallback_url='
                .rawurlencode('https://www.vixeo.test/#installer').';end',
        ));

    $this->get(site('/sitemap.xml'))->assertDontSee('/@lea', false);
    expect($profile->user->email)->not->toBeEmpty();
});

it('has no public page for a child profile or an unknown username', function () {
    withLatestAndroid(null);
    Profile::factory()->create(['username' => 'petit', 'is_kid' => true]);

    $this->get(site('/@petit'))->assertNotFound();
    $this->get(site('/@personne'))->assertNotFound();
    // Not a username: never a profile page.
    $this->get(site('/@a'))->assertNotFound();
});

it('publishes the Android app links of the release key', function () {
    $this->getJson(site('/.well-known/assetlinks.json'))
        ->assertOk()
        ->assertExactJson([[
            'relation' => ['delegate_permission/common.handle_all_urls'],
            'target' => [
                'namespace' => 'android_app',
                'package_name' => 'app.vixeo.vixeo',
                'sha256_cert_fingerprints' => ['B6:3F:7D:E4:B2:CF:E1:7D:EF:D2:03:CF:12:3D:1E:6A:26:FB:51:13:C1:85:21:74:A1:80:7E:E3:40:38:7C:7B'],
            ],
        ]]);
});
