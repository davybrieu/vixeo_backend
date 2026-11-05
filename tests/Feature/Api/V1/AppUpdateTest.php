<?php

use App\Enums\AppPlatform;
use App\Models\AppRelease;

function release(string $platform, string $version, int $build, bool $required = false, ?string $notes = null, $at = null): AppRelease
{
    return AppRelease::create([
        'platform' => $platform, 'version' => $version, 'build' => $build,
        'required' => $required, 'notes' => $notes, 'published_at' => $at ?? now()->subHour(),
    ]);
}

function checkUpdate(string $platform, int $build)
{
    return test()->getJson("/api/v1/app/update?platform={$platform}&build={$build}");
}

it('says up to date without any release', function () {
    checkUpdate('android', 1)
        ->assertOk()
        ->assertJsonPath('status', 'up_to_date')
        ->assertJsonPath('latest', null)
        ->assertJsonPath('minimum_build', null)
        ->assertJsonPath('update_url', 'http://localhost:8100/installer#android');
});

it('suggests a newer release', function () {
    release('android', '1.0.0', 1);
    release('android', '1.1.0', 3, notes: 'Page acteur et connexion TV.');

    checkUpdate('android', 1)
        ->assertOk()
        ->assertJsonPath('status', 'update_available')
        ->assertJsonPath('current_build', 1)
        ->assertJsonPath('latest.version', '1.1.0')
        ->assertJsonPath('latest.build', 3)
        ->assertJsonPath('latest.notes', 'Page acteur et connexion TV.');

    checkUpdate('android', 3)->assertJsonPath('status', 'up_to_date');
    // A build newer than the published ones (beta, store review) is up to date.
    checkUpdate('android', 9)->assertJsonPath('status', 'up_to_date');
});

it('forces installs older than a required release', function () {
    release('ios', '1.0.0', 1);
    release('ios', '1.1.0', 4, required: true);
    release('ios', '1.2.0', 6);

    checkUpdate('ios', 3)->assertJsonPath('status', 'update_required')->assertJsonPath('minimum_build', 4);
    // At or above the required build: only suggested.
    checkUpdate('ios', 4)->assertJsonPath('status', 'update_available')->assertJsonPath('latest.build', 6);
    checkUpdate('ios', 6)->assertJsonPath('status', 'up_to_date');
});

it('keeps platforms apart', function () {
    release('ios', '2.0.0', 20, required: true);

    checkUpdate('android', 1)->assertJsonPath('status', 'up_to_date');
    checkUpdate('ios', 1)->assertJsonPath('status', 'update_required');
});

it('ignores releases scheduled for later', function () {
    release('android', '1.1.0', 2, at: now()->addDay());

    checkUpdate('android', 1)->assertJsonPath('status', 'up_to_date');

    $this->travel(2)->days();
    checkUpdate('android', 1)->assertJsonPath('status', 'update_available');
});

it('sends iOS to the download page of the website', function () {
    config(['services.app_updates.ios_url' => 'https://vixeo.app/telecharger']);

    checkUpdate('ios', 1)->assertJsonPath('update_url', 'https://vixeo.app/telecharger');
});

it('validates the platform and the build', function () {
    $this->getJson('/api/v1/app/update?platform=windows&build=x')
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['platform', 'build']);
});

it('works without signing in and is rate limited', function () {
    foreach (range(1, 30) as $i) {
        checkUpdate('android', 1)->assertOk();
    }
    checkUpdate('android', 1)->assertTooManyRequests();
});

it('publishes, corrects and lists releases with artisan', function () {
    $this->artisan('vixeo:release', ['platform' => 'ios', 'version' => '1.1.0', 'build' => 4, '--notes' => 'Nouveautés'])
        ->expectsOutputToContain('iOS 1.1.0 (build 4) publiée, facultative')
        ->assertSuccessful();
    $this->artisan('vixeo:release', ['platform' => 'ios', 'version' => '1.1.0', 'build' => 4, '--required' => true])
        ->expectsOutputToContain('mise à jour, obligatoire')
        ->assertSuccessful();

    $release = AppRelease::sole();
    expect($release->platform)->toBe(AppPlatform::Ios)
        ->and($release->required)->toBeTrue()
        ->and($release->notes)->toBeNull();

    $this->artisan('vixeo:releases')->expectsOutputToContain('1.1.0')->assertSuccessful();
});

it('gives the APK to download and its checksum (no Play Store)', function () {
    $sha = str_repeat('ab', 32);
    $this->artisan('vixeo:release', [
        'platform' => 'android', 'version' => '1.2.0', 'build' => 14,
        '--apk' => 'https://vixeo.app/telechargements/vixeo-1.2.0.apk', '--sha256' => strtoupper($sha),
    ])->assertSuccessful();

    checkUpdate('android', 3)
        ->assertJsonPath('status', 'update_available')
        ->assertJsonPath('latest.download_url', 'https://vixeo.app/telechargements/vixeo-1.2.0.apk')
        ->assertJsonPath('latest.sha256', $sha);

    release('android', '1.3.0', 15);
    checkUpdate('android', 3)->assertJsonPath('latest.download_url', null)->assertJsonPath('latest.sha256', null);
});

it('checks the APK options', function (array $options) {
    $this->artisan('vixeo:release', ['platform' => 'android', 'version' => '1.2.0', 'build' => 14, ...$options])->assertFailed();

    expect(AppRelease::count())->toBe(0);
})->with([
    'APK without checksum' => [['--apk' => 'https://vixeo.app/vixeo.apk']],
    'checksum without APK' => [['--sha256' => str_repeat('a', 64)]],
    'bad checksum' => [['--apk' => 'https://vixeo.app/vixeo.apk', '--sha256' => 'abc']],
    'not https' => [['--apk' => 'ftp://vixeo.app/vixeo.apk', '--sha256' => str_repeat('a', 64)]],
]);

it('keeps APK options for Android only', function () {
    $this->artisan('vixeo:release', [
        'platform' => 'ios', 'version' => '1.0.0', 'build' => 1,
        '--apk' => 'https://vixeo.app/vixeo.apk', '--sha256' => str_repeat('a', 64),
    ])->assertFailed();
});

it('refuses bad artisan input', function () {
    $this->artisan('vixeo:release', ['platform' => 'windows', 'version' => '1.0.0', 'build' => 1])->assertFailed();
    $this->artisan('vixeo:release', ['platform' => 'ios', 'version' => '1.0', 'build' => 1])->assertFailed();
    $this->artisan('vixeo:release', ['platform' => 'ios', 'version' => '1.0.0', 'build' => 0])->assertFailed();

    expect(AppRelease::count())->toBe(0);
});
