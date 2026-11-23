<?php

use App\Models\Profile;
use App\Models\Report;
use App\Models\SocialNotification;
use App\Models\User;
use App\Services\ProfilePhotos;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;
use Laravel\Sanctum\Sanctum;

beforeEach(function () {
    Storage::fake(ProfilePhotos::DISK);
});

function photoMember(string $username, array $attributes = []): Profile
{
    return Profile::factory()->for(User::factory()->create(['password' => 'secret-password']))
        ->create(['username' => $username, 'avatar' => 'quest-knight', ...$attributes]);
}

/** Acts as [profile]: its account signed in, its id in X-Profile-Id. */
function asPhotoMember(Profile $profile): mixed
{
    Sanctum::actingAs($profile->user);

    return test()->withHeader('X-Profile-Id', (string) $profile->id);
}

function sendPhoto(Profile $profile, UploadedFile $photo): mixed
{
    return asPhotoMember($profile)->post("/api/v1/profiles/{$profile->id}/photo", ['photo' => $photo], ['Accept' => 'application/json']);
}

/** The stored file of [profile]'s photo. */
function storedPhoto(Profile $profile): string
{
    return Storage::disk(ProfilePhotos::DISK)->get(ProfilePhotos::DIRECTORY.'/'.$profile->refresh()->photo_path);
}

/** Red on its left half, blue on its right half; a JPEG with an EXIF orientation when given. */
function twoColorPhoto(int $width, int $height, ?int $orientation = null): UploadedFile
{
    $image = imagecreatetruecolor($width, $height);
    imagefilledrectangle($image, 0, 0, intdiv($width, 2) - 1, $height - 1, imagecolorallocate($image, 255, 0, 0));
    imagefilledrectangle($image, intdiv($width, 2), 0, $width - 1, $height - 1, imagecolorallocate($image, 0, 0, 255));
    ob_start();
    imagejpeg($image, null, 95);
    $jpeg = (string) ob_get_clean();
    if ($orientation !== null) {
        // An APP1 segment right after SOI: « Exif », a big-endian TIFF header, one IFD entry (0x0112).
        $tiff = "MM\x00\x2A\x00\x00\x00\x08".pack('n', 1).pack('nnNn', 0x0112, 3, 1, $orientation)."\x00\x00".pack('N', 0);
        $exif = "Exif\x00\x00".$tiff;
        $jpeg = "\xFF\xD8\xFF\xE1".pack('n', strlen($exif) + 2).$exif.substr($jpeg, 2);
    }

    return UploadedFile::fake()->createWithContent('photo.jpg', $jpeg);
}

/** Red, green and blue of a pixel. */
function pixel(string $bytes, int $x, int $y): array
{
    $rgb = imagecolorat(imagecreatefromstring($bytes), $x, $y);

    return [($rgb >> 16) & 0xFF, ($rgb >> 8) & 0xFF, $rgb & 0xFF];
}

it('keeps the central square of a photo, 512 pixels, re-encoded, and serves it to anyone', function () {
    $lea = photoMember('lea');

    $url = sendPhoto($lea, UploadedFile::fake()->image('photo.png', 1200, 800))
        ->assertOk()
        ->assertJsonPath('data.avatar', 'quest-knight')
        ->json('data.photo_url');

    expect($url)->toMatch('#^https://api\.vixeo\.test/api/v1/profile-photos/[A-Za-z0-9]{40}\.(webp|jpg)$#');
    $bytes = storedPhoto($lea);
    expect(getimagesizefromstring($bytes))->toMatchArray([0 => 512, 1 => 512])
        ->and($bytes)->not->toContain('Exif');

    // Public, like any image the apps load; cached for a year (a new photo has a new address).
    $this->app['auth']->forgetGuards();
    $this->get($url)
        ->assertOk()
        ->assertHeader('Content-Type', str_ends_with($url, '.webp') ? 'image/webp' : 'image/jpeg')
        ->assertHeader('Cache-Control', 'immutable, max-age=31536000, public');
});

it('turns upright a photo taken sideways, and keeps a small one at its size', function () {
    $lea = photoMember('lea');

    // Stored landscape (red left, blue right), shown portrait: turned clockwise, red on top.
    sendPhoto($lea, twoColorPhoto(400, 200, orientation: 6))->assertOk();

    $bytes = storedPhoto($lea);
    expect(getimagesizefromstring($bytes))->toMatchArray([0 => 200, 1 => 200]);
    [$red, , $blue] = pixel($bytes, 170, 30);
    expect($red)->toBeGreaterThan(200)->and($blue)->toBeLessThan(60);
    [$red, , $blue] = pixel($bytes, 30, 170);
    expect($blue)->toBeGreaterThan(200)->and($red)->toBeLessThan(60);
});

it('replaces the previous photo, and goes back to the avatar when it is removed', function () {
    $lea = photoMember('lea');
    $first = sendPhoto($lea, UploadedFile::fake()->image('a.png', 300, 300))->json('data.photo_url');
    $firstPath = ProfilePhotos::DIRECTORY.'/'.$lea->refresh()->photo_path;

    $second = sendPhoto($lea, UploadedFile::fake()->image('b.png', 300, 300))->json('data.photo_url');
    expect($second)->not->toBe($first);
    Storage::disk(ProfilePhotos::DISK)->assertMissing($firstPath);
    $this->get($first)->assertNotFound();

    $secondPath = ProfilePhotos::DIRECTORY.'/'.$lea->refresh()->photo_path;
    asPhotoMember($lea)->deleteJson("/api/v1/profiles/{$lea->id}/photo")
        ->assertOk()
        ->assertJsonPath('data.photo_url', null)
        ->assertJsonPath('data.avatar', 'quest-knight');
    Storage::disk(ProfilePhotos::DISK)->assertMissing($secondPath);
});

it('refuses what is not a photo, a photo too small, and the profiles of another account', function () {
    $lea = photoMember('lea');
    $max = photoMember('max');

    sendPhoto($lea, UploadedFile::fake()->createWithContent('notes.txt', 'not an image'))
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['photo' => 'Le fichier photo doit être de type : jpg, jpeg, png, webp.']);
    sendPhoto($lea, UploadedFile::fake()->createWithContent('photo.png', 'not an image'))->assertUnprocessable();
    sendPhoto($lea, UploadedFile::fake()->image('small.png', 100, 100))
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['photo' => 'Les dimensions de l\'image photo ne sont pas valides.']);

    asPhotoMember($lea)->post("/api/v1/profiles/{$max->id}/photo", ['photo' => UploadedFile::fake()->image('p.png', 300, 300)], ['Accept' => 'application/json'])
        ->assertForbidden();
    asPhotoMember($lea)->deleteJson("/api/v1/profiles/{$max->id}/photo")->assertForbidden();
    expect($max->refresh()->photo_path)->toBeNull();
});

it('shows the photo wherever the profile appears', function () {
    $lea = photoMember('lea');
    $max = photoMember('max');
    $url = sendPhoto($lea, UploadedFile::fake()->image('p.png', 300, 300))->json('data.photo_url');

    asPhotoMember($lea)->getJson('/api/v1/profiles')->assertJsonPath('data.0.photo_url', $url);
    asPhotoMember($max)->getJson('/api/v1/social/search?query=lea')->assertJsonPath('data.0.photo_url', $url);
    asPhotoMember($max)->getJson('/api/v1/social/profiles/lea')->assertJsonPath('data.photo_url', $url);

    asPhotoMember($lea)->postJson('/api/v1/social/profiles/max/follow')->assertSuccessful();
    asPhotoMember($max)->getJson('/api/v1/notifications')->assertJsonPath('data.0.actor.photo_url', $url);

    // The site's page of the profile (www.vixeo.app/lea).
    $this->get('https://www.vixeo.test/lea')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->component('Member')->where('member.photoUrl', $url));
});

it('deletes the photo with its profile and with the account', function () {
    $lea = photoMember('lea');
    sendPhoto($lea, UploadedFile::fake()->image('p.png', 300, 300));
    $path = ProfilePhotos::DIRECTORY.'/'.$lea->refresh()->photo_path;
    $other = Profile::factory()->for($lea->user)->create(['username' => 'lea_bis']);
    sendPhoto($other, UploadedFile::fake()->image('p.png', 300, 300));
    $otherPath = ProfilePhotos::DIRECTORY.'/'.$other->refresh()->photo_path;

    asPhotoMember($lea)->deleteJson("/api/v1/profiles/{$lea->id}")->assertNoContent();
    Storage::disk(ProfilePhotos::DISK)->assertMissing($path);
    Storage::disk(ProfilePhotos::DISK)->assertExists($otherPath);

    asPhotoMember($other)->postJson('/api/v1/me/delete', ['password' => 'secret-password'])->assertNoContent();
    Storage::disk(ProfilePhotos::DISK)->assertMissing($otherPath);
});

it('lets the moderation remove a reported photo', function () {
    $lea = photoMember('lea');
    $max = photoMember('max');
    sendPhoto($lea, UploadedFile::fake()->image('p.png', 300, 300));
    asPhotoMember($max)->postJson('/api/v1/social/reports', ['type' => 'profile', 'id' => $lea->id, 'reason' => 'sexual'])->assertSuccessful();
    $report = Report::query()->value('id');

    $this->artisan('vixeo:moderate', ['report' => $report, 'action' => 'photo', '--reason' => 'Photo inappropriée'])
        ->expectsOutputToContain('photo retirée')
        ->assertSuccessful();

    expect($lea->refresh()->photo_path)->toBeNull()
        ->and($lea->suspended_at)->toBeNull()
        ->and(SocialNotification::query()->where('profile_id', $lea->id)->value('data'))
        ->toBe(['text' => 'Votre photo de profil a été retirée par la modération : Photo inappropriée'])
        ->and(Report::query()->find($report)->status)->toBe(Report::HIDDEN);

    // Nothing left to remove.
    $this->artisan('vixeo:moderate', ['report' => $report, 'action' => 'photo', '--reason' => 'Encore'])->assertFailed();
});
