<?php

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Schema;

// The update check only reads the GitHub releases published by the CI (docs/mise-a-jour.md).

function checkUpdate(string $platform, int $build)
{
    return test()->getJson("/api/v1/app/update?platform={$platform}&build={$build}");
}

function githubRelease(int $build, array $extra = []): array
{
    return array_merge([
        'tag_name' => "android-{$build}",
        'name' => "Vixeo v1.{$build}.0 (build {$build})",
        'draft' => false,
        'prerelease' => false,
        'body' => "Nouveautés du build {$build}",
        'published_at' => '2026-11-08T10:00:00Z',
        'assets' => [
            ['name' => 'vixeo.apk', 'browser_download_url' => "https://github.com/davybrieu/vixeo_frontend/releases/download/android-{$build}/vixeo.apk"],
            ['name' => 'vixeo.apk.sha256', 'browser_download_url' => "https://github.com/davybrieu/vixeo_frontend/releases/download/android-{$build}/vixeo.apk.sha256"],
        ],
    ], $extra);
}

/** GitHub answers with these releases (and any checksum file). */
function fakeGithub(array $releases): void
{
    config(['services.app_updates.github_repository' => 'davybrieu/vixeo_frontend']);
    Http::preventStrayRequests();
    Http::fake([
        'api.github.com/repos/davybrieu/vixeo_frontend/releases*' => Http::response($releases),
        'github.com/davybrieu/vixeo_frontend/releases/download/*' => Http::response(str_repeat('ab', 32).'  vixeo.apk'),
    ]);
}

it('says up to date without any release on GitHub', function () {
    fakeGithub([]);

    checkUpdate('android', 1)
        ->assertOk()
        ->assertJsonPath('status', 'up_to_date')
        ->assertJsonPath('latest', null)
        ->assertJsonPath('minimum_build', null)
        ->assertJsonPath('update_url', 'http://localhost:8100/installer#android');
});

it('offers the newest Android release published on GitHub, with its APK and checksum', function () {
    fakeGithub([
        githubRelease(12),
        githubRelease(15, ['draft' => true]),
        githubRelease(14, ['prerelease' => true]),
        githubRelease(13),
        ['tag_name' => 'web-99', 'draft' => false, 'prerelease' => false],
    ]);

    checkUpdate('android', 10)
        ->assertOk()
        ->assertJsonPath('status', 'update_available')
        ->assertJsonPath('current_build', 10)
        ->assertJsonPath('latest.build', 13)
        ->assertJsonPath('latest.version', '1.13.0')
        ->assertJsonPath('latest.notes', 'Nouveautés du build 13')
        ->assertJsonPath('latest.download_url', 'https://github.com/davybrieu/vixeo_frontend/releases/download/android-13/vixeo.apk')
        ->assertJsonPath('latest.sha256', str_repeat('ab', 32))
        ->assertJsonPath('minimum_build', null);

    // Cached: GitHub is asked once (list + checksum).
    checkUpdate('android', 13)->assertJsonPath('status', 'up_to_date');
    // A build newer than the published ones (a test build) is up to date.
    checkUpdate('android', 99)->assertJsonPath('status', 'up_to_date');
    Http::assertSentCount(2);
});

it('forces installs older than a release marked [obligatoire] in its GitHub title', function () {
    fakeGithub([
        githubRelease(12),
        githubRelease(14, ['name' => 'Vixeo v1.14.0 (build 14) [Obligatoire]']),
        githubRelease(16),
        // A draft or a pre-release never forces anything.
        githubRelease(18, ['name' => 'Vixeo v1.18.0 [obligatoire]', 'draft' => true]),
    ]);

    checkUpdate('android', 13)
        ->assertJsonPath('status', 'update_required')
        ->assertJsonPath('minimum_build', 14)
        ->assertJsonPath('latest.build', 16);
    // At or above the required build: only suggested.
    checkUpdate('android', 14)->assertJsonPath('status', 'update_available');
    checkUpdate('android', 16)->assertJsonPath('status', 'up_to_date');
});

it('never blocks the apps when GitHub fails', function () {
    config(['services.app_updates.github_repository' => 'davybrieu/vixeo_frontend']);
    Http::preventStrayRequests();
    Http::fake(['api.github.com/*' => Http::response(['message' => 'API rate limit exceeded'], 403)]);

    checkUpdate('android', 1)
        ->assertOk()
        ->assertJsonPath('status', 'up_to_date')
        ->assertJsonPath('latest', null);
});

it('has no iOS app yet: iOS is up to date, without asking GitHub', function () {
    config([
        'services.app_updates.github_repository' => 'davybrieu/vixeo_frontend',
        'services.app_updates.ios_url' => 'https://vixeo.app/telecharger',
    ]);
    Http::preventStrayRequests();

    checkUpdate('ios', 1)
        ->assertJsonPath('status', 'up_to_date')
        ->assertJsonPath('latest', null)
        ->assertJsonPath('update_url', 'https://vixeo.app/telecharger');
    Http::assertNothingSent();
});

it('validates the platform and the build', function () {
    $this->getJson('/api/v1/app/update?platform=windows&build=x')
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['platform', 'build']);
});

it('works without signing in and is rate limited', function () {
    fakeGithub([]);

    foreach (range(1, 30) as $i) {
        checkUpdate('android', 1)->assertOk();
    }
    checkUpdate('android', 1)->assertTooManyRequests();
});

it('has no release table nor release command any more', function () {
    expect(Schema::hasTable('app_releases'))->toBeFalse()
        ->and(Artisan::all())->not->toHaveKeys(['vixeo:release', 'vixeo:releases']);
});
