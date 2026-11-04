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
        ->assertJsonPath('update_url', 'https://play.google.com/store/apps/details?id=app.vixeo.vixeo');
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

it('refuses bad artisan input', function () {
    $this->artisan('vixeo:release', ['platform' => 'windows', 'version' => '1.0.0', 'build' => 1])->assertFailed();
    $this->artisan('vixeo:release', ['platform' => 'ios', 'version' => '1.0', 'build' => 1])->assertFailed();
    $this->artisan('vixeo:release', ['platform' => 'ios', 'version' => '1.0.0', 'build' => 0])->assertFailed();

    expect(AppRelease::count())->toBe(0);
});
