<?php

use App\Enums\ItemType;
use App\Events\PushNotified;
use App\Models\EpisodeNotification;
use App\Models\Profile;
use App\Models\ProfileItem;
use App\Models\PushDevice;
use App\Models\User;
use App\Services\AccountExport;
use App\Services\Push\PushDevices;
use App\Services\Push\PushMessage;
use App\Services\Push\RealtimePush;
use Illuminate\Http\Client\Request as ClientRequest;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Http;
use Mockery\MockInterface;
use Tests\Feature\Api\V1\Catalog\TmdbFixtures as Tmdb;

/** A Firebase service account with a real RSA key (the JWT is signed), in base64. */
function pushServiceAccount(): string
{
    static $value = null;
    if ($value === null) {
        $key = openssl_pkey_new(['private_key_bits' => 2048, 'private_key_type' => OPENSSL_KEYTYPE_RSA]);
        openssl_pkey_export($key, $pem);
        $value = 'base64:'.base64_encode((string) json_encode([
            'type' => 'service_account',
            'project_id' => 'vixeo-test',
            'client_email' => 'push@vixeo-test.iam.gserviceaccount.com',
            'private_key' => $pem,
            'token_uri' => 'https://oauth2.googleapis.com/token',
        ]));
    }

    return $value;
}

/**
 * Google sign-in and FCM, faked: every message sent is recorded; tokens in [$unregistered] are
 * answered UNREGISTERED (app uninstalled), those in [$failing] 503.
 *
 * @param  list<string>  $unregistered
 * @return ArrayObject<int, array<string, mixed>>
 */
function fakePush(array $unregistered = [], array $more = [], array $failing = []): ArrayObject
{
    $sent = new ArrayObject;
    Http::fake($more + [
        'oauth2.googleapis.com/token' => Http::response(['access_token' => 'ya29.test', 'expires_in' => 3600]),
        'fcm.googleapis.com/v1/projects/vixeo-test/messages:send' => function (ClientRequest $request) use ($sent, $unregistered, $failing) {
            expect($request->header('Authorization')[0])->toBe('Bearer ya29.test');
            $message = $request->data()['message'];
            // The installation ID (current apps) or the registration token (older ones), never both.
            $target = $message['fid'] ?? $message['token'];
            expect(isset($message['fid'], $message['token']))->toBeFalse();
            if (in_array($target, $failing, true)) {
                return Http::response(['error' => ['code' => 503, 'status' => 'UNAVAILABLE', 'message' => 'The service is currently unavailable.']], 503);
            }
            if (in_array($target, $unregistered, true)) {
                return Http::response(['error' => [
                    'code' => 404,
                    'status' => 'NOT_FOUND',
                    'message' => 'Requested entity was not found.',
                    'details' => [['@type' => 'type.googleapis.com/google.firebase.fcm.v1.FcmError', 'errorCode' => 'UNREGISTERED']],
                ]], 404);
            }
            $sent[] = $message;

            return Http::response(['name' => 'projects/vixeo-test/messages/1']);
        },
    ]);

    return $sent;
}

/** A signed-in app: its user, profile and API token. */
function pushApp(?User $user = null, array $profile = []): array
{
    $user ??= User::factory()->create();
    $profile = Profile::factory()->for($user)->create($profile);
    $token = $user->createToken('Pixel 9');

    return [$user, $profile, $token->plainTextToken, $token->accessToken];
}

/** A registered app, as PUT /push/device leaves it. */
function pushDevice(Profile $profile, string $token, array $attributes = []): PushDevice
{
    $access = $profile->user->createToken('Pixel 9')->accessToken;

    return PushDevice::create($attributes + [
        'user_id' => $profile->user_id,
        'profile_id' => $profile->id,
        'personal_access_token_id' => $access->id,
        'token' => $token,
        'token_hash' => PushDevice::hashToken($token),
        'platform' => 'android',
        'new_episodes' => true,
        'new_episodes_since' => now()->subDays(10),
        'utc_offset' => 60,
        'last_seen_at' => now(),
    ]);
}

/** TMDb: Lupin (Netflix, season 4 out this morning), Breaking (US channel, out last night) and Ended. */
function fakeShows(array $unregistered = [], array $failing = []): ArrayObject
{
    $episode = fn (int $season, int $number, string $date, ?string $name = null) => [
        'id' => $season * 100 + $number,
        'season_number' => $season,
        'episode_number' => $number,
        'name' => $name ?? "Épisode {$number}",
        'air_date' => $date,
    ];
    $show = fn (int $id, array $overrides) => Tmdb::show($id, array_merge([
        'networks' => [],
        'origin_country' => ['US'],
        'last_episode_to_air' => null,
        'next_episode_to_air' => null,
        'content_ratings' => ['results' => []],
        'images' => ['logos' => [], 'backdrops' => [], 'posters' => []],
    ], $overrides));

    return fakePush(unregistered: $unregistered, failing: $failing, more: [
        'api.themoviedb.org/3/tv/100088?*' => Http::response($show(100088, [
            'name' => 'Lupin',
            'networks' => [['id' => 213, 'name' => 'Netflix']],
            'last_episode_to_air' => $episode(4, 2, '2026-11-20'),
        ])),
        'api.themoviedb.org/3/tv/100088/season/4*' => Http::response(['season_number' => 4, 'episodes' => [
            $episode(4, 1, '2026-11-20'),
            $episode(4, 2, '2026-11-20'),
        ]]),
        // 21:00 in New York on the 19th = 02:00 UTC on the 20th. Still "next" in TMDb's cache.
        'api.themoviedb.org/3/tv/1399?*' => Http::response($show(1399, [
            'name' => 'Breaking',
            'networks' => [['id' => 174, 'name' => 'AMC']],
            'last_episode_to_air' => $episode(2, 2, '2026-11-12'),
            'next_episode_to_air' => $episode(2, 3, '2026-11-19', 'Le Pont'),
        ])),
        'api.themoviedb.org/3/tv/1399/season/2*' => Http::response(['season_number' => 2, 'episodes' => [
            $episode(2, 2, '2026-11-12'),
            $episode(2, 3, '2026-11-19', 'Le Pont'),
            $episode(2, 4, '2026-11-26'),
        ]]),
        'api.themoviedb.org/3/tv/5?*' => Http::response($show(5, ['last_episode_to_air' => $episode(3, 10, '2025-06-01')])),
    ]);
}

function favoriteShow(Profile $profile, int $id, string $at = '2026-11-01 10:00:00'): void
{
    ProfileItem::factory()->for($profile)->favorite($at)->create(['item_id' => "tv:{$id}", 'type' => ItemType::Tv]);
}

beforeEach(function () {
    config([
        'services.fcm.credentials' => pushServiceAccount(),
        'services.tmdb.token' => 'test-token',
        'services.tmdb.api_key' => null,
        'services.tmdb.base_url' => 'https://api.themoviedb.org/3',
    ]);
    Http::preventStrayRequests();
});

describe('the app’s registration', function () {
    it('registers the app with the active profile, the token kept encrypted', function () {
        [, $profile, $bearer] = pushApp();

        $this->withToken($bearer)->withHeader('X-Profile-Id', (string) $profile->id)
            ->putJson('/api/v1/push/device', ['token' => 'fcm-token-1', 'platform' => 'android', 'new_episodes' => true, 'utc_offset' => 60])
            ->assertCreated()
            ->assertJsonPath('data.profile_id', $profile->id)
            ->assertJsonPath('data.new_episodes', true)
            ->assertJsonMissingPath('data.token');

        $device = PushDevice::sole();
        expect($device->token)->toBe('fcm-token-1')
            ->and(DB::table('push_devices')->value('token'))->not->toContain('fcm-token-1')
            ->and($device->new_episodes_since)->not->toBeNull()
            ->and($device->utc_offset)->toBe(60);

        $this->withToken($bearer)->getJson('/api/v1/push/device')
            ->assertOk()
            ->assertJsonPath('available', true)
            ->assertJsonPath('data.platform', 'android');
    });

    it('keeps one registration per app; the same app signed in again replaces the old one', function () {
        [$user, $profile, $bearer] = pushApp();
        $register = fn (string $bearer, string $token, bool $episodes = true) => $this->withToken($bearer)
            ->withHeader('X-Profile-Id', (string) $profile->id)
            ->putJson('/api/v1/push/device', ['token' => $token, 'platform' => 'android', 'new_episodes' => $episodes])
            ->assertSuccessful();

        $register($bearer, 'fcm-a');
        // New token (FCM rotates them): the same registration.
        $register($bearer, 'fcm-b');
        expect(PushDevice::count())->toBe(1)->and(PushDevice::sole()->token)->toBe('fcm-b');

        // Signed in again on the same phone: the old registration goes.
        $again = $user->createToken('Pixel 9')->plainTextToken;
        $this->app['auth']->forgetGuards();
        $register($again, 'fcm-b');
        expect(PushDevice::count())->toBe(1);

        // Alerts off: nothing to announce.
        $this->app['auth']->forgetGuards();
        $register($again, 'fcm-b', false);
        expect(PushDevice::sole()->new_episodes)->toBeFalse()->and(PushDevice::sole()->new_episodes_since)->toBeNull();
    });

    it('is removed when the app signs out, or on request', function () {
        [, $profile, $bearer] = pushApp();
        $this->withToken($bearer)->putJson('/api/v1/push/device', ['token' => 'fcm-a', 'platform' => 'android', 'new_episodes' => false])->assertSuccessful();
        expect(PushDevice::sole()->profile_id)->toBeNull();

        $this->withToken($bearer)->deleteJson('/api/v1/push/device')->assertNoContent();
        expect(PushDevice::count())->toBe(0);

        $this->withToken($bearer)->putJson('/api/v1/push/device', ['token' => 'fcm-a', 'platform' => 'android', 'new_episodes' => false])->assertSuccessful();
        $this->withToken($bearer)->postJson('/api/v1/auth/logout')->assertNoContent();
        expect(PushDevice::count())->toBe(0);
    });

    it('is in the account export without its token, and goes with the account', function () {
        $profile = Profile::factory()->create(['name' => 'Davy']);
        pushDevice($profile, 'fcm-secret');
        EpisodeNotification::create(['profile_id' => $profile->id, 'show_id' => 1399, 'season_number' => 2, 'episode_number' => 3, 'available_at' => now(), 'sent_at' => now()]);

        $export = app(AccountExport::class)->build($profile->user);
        expect($export['notification_devices'])->toHaveCount(1)
            ->and($export['notification_devices'][0]['profile'])->toBe('Davy')
            ->and(json_encode($export))->not->toContain('fcm-secret')
            ->and($export['profiles'][0]['episode_notifications'][0]['show_id'])->toBe(1399);

        $profile->user->delete();
        expect(PushDevice::count())->toBe(0)->and(EpisodeNotification::count())->toBe(0);
    });

    it('checks what it receives, in French', function () {
        [, , $bearer] = pushApp();

        $this->withToken($bearer)->putJson('/api/v1/push/device', ['token' => '', 'platform' => 'web', 'new_episodes' => 'peut-être'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['token', 'fid', 'platform', 'new_episodes'])
            ->assertJsonPath('errors.platform.0', 'La valeur sélectionnée pour plateforme n\'est pas valide.');
        $this->app['auth']->forgetGuards();
        $this->withoutToken()->putJson('/api/v1/push/device', [])->assertUnauthorized();
    });

    it('says when the server sends no notification', function () {
        config(['services.fcm.credentials' => null]);
        [, , $bearer] = pushApp();

        $this->withToken($bearer)->getJson('/api/v1/push/device')->assertOk()->assertJsonPath('available', false)->assertJsonPath('data', null);
        $this->withToken($bearer)->postJson('/api/v1/push/device/test')->assertStatus(503)
            ->assertJsonPath('message', 'Les notifications ne sont pas disponibles pour le moment.');
    });

    it('sends a test notification to this app only', function () {
        $sent = fakePush();
        [, $profile, $bearer] = pushApp();
        $this->withToken($bearer)->postJson('/api/v1/push/device/test')->assertStatus(409)
            ->assertJsonPath('message', 'Cet appareil n’est pas inscrit aux notifications.');

        pushDevice($profile, 'fcm-other');
        $this->withToken($bearer)->putJson('/api/v1/push/device', ['token' => 'fcm-mine', 'platform' => 'android', 'new_episodes' => false])->assertSuccessful();
        $this->withToken($bearer)->postJson('/api/v1/push/device/test')->assertOk()->assertJsonPath('message', 'Notification de test envoyée.');

        expect($sent)->toHaveCount(1)
            ->and($sent[0]['token'])->toBe('fcm-mine')
            ->and($sent[0]['notification']['title'])->toBe('Notification de test')
            ->and($sent[0]['android']['notification']['channel_id'])->toBe('general')
            ->and($sent[0]['data']['path'])->toBe('/profil/notifications');
    });

    it('forgets an app FCM no longer knows', function () {
        fakePush(unregistered: ['fcm-gone']);
        [, , $bearer] = pushApp();
        $this->withToken($bearer)->putJson('/api/v1/push/device', ['token' => 'fcm-gone', 'platform' => 'android', 'new_episodes' => true])->assertSuccessful();

        $this->withToken($bearer)->postJson('/api/v1/push/device/test')->assertStatus(409);
        expect(PushDevice::count())->toBe(0);
    });
});

describe('the Firebase installation ID (firebase_messaging 17)', function () {
    it('registers the installation ID and sends with it', function () {
        $sent = fakePush();
        [, , $bearer] = pushApp();

        $this->withToken($bearer)->putJson('/api/v1/push/device', ['fid' => 'fid-1', 'platform' => 'android', 'new_episodes' => false])
            ->assertCreated()
            ->assertJsonPath('data.target', 'fid')
            ->assertJsonMissingPath('data.fid');
        expect(PushDevice::sole()->token)->toBe('fid-1')->and(DB::table('push_devices')->value('token'))->not->toContain('fid-1');

        $this->withToken($bearer)->postJson('/api/v1/push/device/test')->assertOk();
        expect($sent)->toHaveCount(1)->and($sent[0]['fid'])->toBe('fid-1')->and($sent[0])->not->toHaveKey('token');
    });

    it('replaces the token of an updated app by its installation ID', function () {
        $sent = fakePush(unregistered: ['fid-gone']);
        [, , $bearer] = pushApp();
        $register = fn (array $target) => $this->withToken($bearer)
            ->putJson('/api/v1/push/device', $target + ['platform' => 'android', 'new_episodes' => false])->assertSuccessful();

        $register(['token' => 'fcm-old']);
        $register(['fid' => 'fid-new']);
        expect(PushDevice::count())->toBe(1)->and(PushDevice::sole()->target)->toBe('fid');
        $this->withToken($bearer)->postJson('/api/v1/push/device/test')->assertOk();
        expect(collect($sent)->pluck('fid')->all())->toBe(['fid-new']);

        // An installation ID FCM no longer knows: forgotten, like a token.
        $register(['fid' => 'fid-gone']);
        $this->withToken($bearer)->postJson('/api/v1/push/device/test')->assertStatus(409);
        expect(PushDevice::count())->toBe(0);
    });
});

describe('the real-time channel of Windows', function () {
    beforeEach(function () {
        config([
            'broadcasting.default' => 'reverb',
            'broadcasting.connections.reverb.key' => 'key',
            'broadcasting.connections.reverb.secret' => 'secret',
            'broadcasting.connections.reverb.app_id' => '1',
        ]);
        Event::fake([PushNotified::class]);
    });

    /** Reverb, faked: the registrations whose app listens to its channel, at each send. */
    function listeningApps(array ...$answers): void
    {
        test()->partialMock(RealtimePush::class, fn (MockInterface $mock) => $mock->shouldReceive('listening')->andReturn(...$answers));
    }

    it('registers Windows without Firebase', function () {
        [, $profile, $bearer] = pushApp();

        $id = $this->withToken($bearer)->withHeader('X-Profile-Id', (string) $profile->id)
            ->putJson('/api/v1/push/device', ['platform' => 'windows', 'token' => 'ignored', 'new_episodes' => true, 'social' => true])
            ->assertCreated()
            ->assertJsonPath('data.target', 'realtime')
            ->assertJsonPath('data.platform', 'windows')
            ->json('data.id');

        $device = PushDevice::sole();
        expect($device->id)->toBe($id)->and($device->token)->toBeNull()->and($device->token_hash)->toBeNull();
        $this->withToken($bearer)->getJson('/api/v1/push/device')->assertOk()->assertJsonPath('realtime', true);

        config(['broadcasting.default' => 'null']);
        $this->withToken($bearer)->getJson('/api/v1/push/device')->assertOk()->assertJsonPath('realtime', false);
    });

    it('sends to the app while it listens to its channel', function () {
        config(['services.fcm.credentials' => null]);
        [, , $bearer] = pushApp();
        $id = $this->withToken($bearer)->putJson('/api/v1/push/device', ['platform' => 'windows', 'new_episodes' => false])->json('data.id');

        listeningApps([], [$id, 999]);
        $this->withToken($bearer)->postJson('/api/v1/push/device/test')->assertStatus(503)
            ->assertJsonPath('message', 'La notification n’a pas pu être envoyée.');
        Event::assertNotDispatched(PushNotified::class);

        $this->withToken($bearer)->postJson('/api/v1/push/device/test')->assertOk();
        Event::assertDispatched(PushNotified::class, function (PushNotified $event) use ($id) {
            $channels = array_map(fn ($channel) => $channel->name, $event->broadcastOn());

            return $channels === ["private-push-devices.{$id}"]
                && $event->broadcastAs() === 'notification'
                && $event->broadcastWith() === [
                    'title' => 'Notification de test',
                    'body' => 'Les notifications de Vixeo fonctionnent sur cet appareil.',
                    'path' => '/profil/notifications',
                    'channel' => 'general',
                    'tag' => 'test',
                    'image' => null,
                ];
        });
    });

    it('authorizes the channel for its app only', function () {
        [$user, , $bearer] = pushApp();
        $id = $this->withToken($bearer)->putJson('/api/v1/push/device', ['platform' => 'windows', 'new_episodes' => false])->json('data.id');
        $other = $user->createToken('Pixel 9')->plainTextToken;
        // The channels were registered on the broadcaster of the tests (null): again on Reverb's.
        require base_path('routes/channels.php');
        $auth = ['socket_id' => '1234.5678', 'channel_name' => "private-push-devices.{$id}"];

        $this->withToken($bearer)->postJson('/api/broadcasting/auth', $auth)->assertOk()->assertJsonStructure(['auth']);
        $this->app['auth']->forgetGuards();
        $this->withToken($other)->postJson('/api/broadcasting/auth', $auth)->assertForbidden();
    });

    it('announces the new episodes once the app is open', function () {
        $this->travelTo('2026-11-20 14:00:00');
        $sent = fakeShows();
        $profile = Profile::factory()->create(['name' => 'Davy']);
        favoriteShow($profile, 100088);
        $windows = pushDevice($profile, 'unused', ['token' => null, 'token_hash' => null, 'target' => 'realtime', 'platform' => 'windows']);

        listeningApps([], [$windows->id]);
        $this->artisan('vixeo:push-episodes')->assertSuccessful();
        expect(EpisodeNotification::count())->toBe(0)->and($sent)->toHaveCount(0);

        $this->artisan('vixeo:push-episodes')->assertSuccessful();
        expect(EpisodeNotification::count())->toBe(2);
        Event::assertDispatched(PushNotified::class, fn (PushNotified $event) => $event->devices === [$windows->id]
            && $event->message->channel === 'episodes'
            && $event->message->path === '/titre/tv/100088');
    });

    it('sends the social activity to the apps of both kinds', function () {
        $sent = fakePush();
        $profile = Profile::factory()->create();
        pushDevice($profile, 'fid-phone', ['target' => 'fid']);
        $windows = pushDevice($profile, 'unused', ['token' => null, 'token_hash' => null, 'target' => 'realtime', 'platform' => 'windows']);
        listeningApps([$windows->id]);

        $report = app(PushDevices::class)->send(PushDevice::all(), new PushMessage('Nouvel abonné', 'Léa vous suit.', '/membre/lea', PushMessage::SOCIAL));

        expect($report)->toBe(['sent' => 2, 'failed' => 0, 'removed' => 0])
            ->and(collect($sent)->pluck('fid')->all())->toBe(['fid-phone']);
        Event::assertDispatched(PushNotified::class, fn (PushNotified $event) => $event->devices === [$windows->id]);
    });
});

describe('new episodes (vixeo:push-episodes)', function () {
    beforeEach(function () {
        // Friday 20 November 2026, 10:00 UTC: 11:00 in Paris.
        $this->travelTo(new DateTimeImmutable('2026-11-20 10:00:00', new DateTimeZone('UTC')));
    });

    it('announces the episodes out of the « Ma liste » shows, one notification per show, once', function () {
        $sent = fakeShows();
        $profile = Profile::factory()->create(['name' => 'Davy']);
        foreach ([100088, 1399, 5] as $id) {
            favoriteShow($profile, $id);
        }
        // A movie and a show only watched: nothing.
        ProfileItem::factory()->for($profile)->favorite()->create(['item_id' => 'movie:550']);
        pushDevice($profile, 'fcm-phone');
        pushDevice($profile, 'fcm-tv');

        $this->artisan('vixeo:push-episodes')
            ->expectsOutputToContain('Davy : Lupin, 2 nouveaux épisodes de la saison 4 sont disponibles.')
            ->expectsOutputToContain('Davy : Breaking, S2 · É3 « Le Pont » est disponible.')
            ->assertSuccessful();

        expect($sent)->toHaveCount(4);
        $lupin = collect($sent)->firstWhere('notification.title', 'Lupin');
        expect($lupin['data']['path'])->toBe('/titre/tv/100088')
            ->and($lupin['android']['notification']['channel_id'])->toBe('episodes')
            ->and($lupin['android']['notification']['tag'])->toBe('tv-100088')
            ->and($lupin['notification']['image'])->toContain('/w780/');
        expect(EpisodeNotification::count())->toBe(3);

        // Never twice.
        $this->artisan('vixeo:push-episodes')->assertSuccessful();
        expect($sent)->toHaveCount(4);
    });

    it('waits for the morning on the device', function () {
        $sent = fakeShows();
        $profile = Profile::factory()->create();
        favoriteShow($profile, 100088);
        // UTC+14: midnight on the device.
        pushDevice($profile, 'fcm-phone', ['utc_offset' => 840]);

        $this->artisan('vixeo:push-episodes')->expectsOutputToContain('en attente (nuit sur l\'appareil)')->assertSuccessful();
        expect($sent)->toHaveCount(0)->and(EpisodeNotification::count())->toBe(0);

        // 08:00 on the device: it goes.
        $this->travelTo(new DateTimeImmutable('2026-11-20 18:00:00', new DateTimeZone('UTC')));
        $this->artisan('vixeo:push-episodes')->assertSuccessful();
        expect($sent)->toHaveCount(1);
    });

    it('announces nothing out before the show was added or the alerts turned on', function () {
        $sent = fakeShows();
        $added = Profile::factory()->create();
        favoriteShow($added, 100088, '2026-11-20 09:00:00');
        pushDevice($added, 'fcm-a');
        $late = Profile::factory()->create();
        favoriteShow($late, 1399);
        pushDevice($late, 'fcm-b', ['new_episodes_since' => now()->subHour()]);
        $off = Profile::factory()->create();
        favoriteShow($off, 100088);
        pushDevice($off, 'fcm-c', ['new_episodes' => false]);

        $this->artisan('vixeo:push-episodes')->assertSuccessful();
        expect($sent)->toHaveCount(0);
    });

    it('can be run for one account, or without sending', function () {
        $sent = fakeShows();
        $davy = Profile::factory()->for(User::factory()->create(['email' => 'davy@exemple.fr']))->create(['name' => 'Davy']);
        $lea = Profile::factory()->create(['name' => 'Léa']);
        foreach ([$davy, $lea] as $i => $profile) {
            favoriteShow($profile, 100088);
            pushDevice($profile, "fcm-{$i}");
        }

        $this->artisan('vixeo:push-episodes', ['--user' => 'Davy@exemple.fr', '--dry-run' => true])
            ->expectsOutputToContain('Davy : Lupin')
            ->doesntExpectOutputToContain('Léa')
            ->expectsOutputToContain('simulation')
            ->assertSuccessful();
        expect($sent)->toHaveCount(0)->and(EpisodeNotification::count())->toBe(0);

        $this->artisan('vixeo:push-episodes', ['--user' => 'davy@exemple.fr'])->assertSuccessful();
        expect(collect($sent)->pluck('token')->all())->toBe(['fcm-0']);

        $this->artisan('vixeo:push-episodes', ['--user' => 'personne@exemple.fr'])->assertFailed();
    });

    it('tries again at the next run when FCM fails', function () {
        $sent = fakeShows(failing: ['fcm-phone']);
        $profile = Profile::factory()->create();
        favoriteShow($profile, 100088);
        pushDevice($profile, 'fcm-phone');

        $this->artisan('vixeo:push-episodes')->expectsOutputToContain('0 envoi(s) réussi(s), 1 échec(s)')->assertSuccessful();
        expect($sent)->toHaveCount(0)->and(EpisodeNotification::count())->toBe(0)->and(PushDevice::count())->toBe(1);
    });

    it('forgets the apps FCM no longer knows', function () {
        $sent = fakeShows(unregistered: ['fcm-gone']);
        $profile = Profile::factory()->create();
        favoriteShow($profile, 100088);
        pushDevice($profile, 'fcm-phone');
        pushDevice($profile, 'fcm-gone');

        $this->artisan('vixeo:push-episodes')->expectsOutputToContain('1 appareil(s) oublié(s)')->assertSuccessful();
        expect($sent)->toHaveCount(1)->and(PushDevice::count())->toBe(1)->and(EpisodeNotification::count())->toBe(2);
    });

    it('is not run without FCM nor Reverb', function () {
        config(['services.fcm.credentials' => null]);
        $this->artisan('vixeo:push-episodes')->assertFailed();
    });
});

describe('a message by hand (vixeo:push)', function () {
    it('sends to every app, after a confirmation', function () {
        $sent = fakePush(unregistered: ['fcm-gone']);
        $a = Profile::factory()->create();
        $b = Profile::factory()->create();
        pushDevice($a, 'fcm-a', ['new_episodes' => false]);
        pushDevice($b, 'fcm-b');
        pushDevice($b, 'fcm-gone');

        $this->artisan('vixeo:push', ['message' => 'Nouvelle version disponible', '--all' => true, '--path' => '/accueil'])
            ->expectsConfirmation('Envoyer à 3 appareil(s) (tous les comptes) ?', 'yes')
            ->expectsOutputToContain('2 envoi(s) réussi(s), 0 échec(s), 1 appareil(s) oublié(s)')
            ->assertSuccessful();

        expect(collect($sent)->pluck('token')->sort()->values()->all())->toBe(['fcm-a', 'fcm-b'])
            ->and($sent[0]['notification'])->toBe(['title' => 'Vixeo', 'body' => 'Nouvelle version disponible'])
            ->and($sent[0]['data']['path'])->toBe('/accueil')
            ->and(PushDevice::count())->toBe(2);
    });

    it('sends to the apps of one account', function () {
        $sent = fakePush();
        $davy = Profile::factory()->for(User::factory()->create(['email' => 'davy@exemple.fr']))->create();
        pushDevice($davy, 'fcm-davy');
        pushDevice(Profile::factory()->create(), 'fcm-other');

        $this->artisan('vixeo:push', ['message' => 'Bonjour', '--user' => ['davy@exemple.fr'], '--title' => 'Coucou', '--force' => true])
            ->assertSuccessful();

        expect(collect($sent)->pluck('token')->all())->toBe(['fcm-davy'])
            ->and($sent[0]['notification']['title'])->toBe('Coucou');
    });

    it('refuses what it can’t send', function () {
        fakePush();
        $this->artisan('vixeo:push', ['message' => 'Bonjour'])->assertExitCode(2);
        $this->artisan('vixeo:push', ['message' => 'Bonjour', '--all' => true, '--user' => ['1']])->assertExitCode(2);
        $this->artisan('vixeo:push', ['message' => 'Bonjour', '--all' => true, '--path' => 'accueil'])->assertExitCode(2);
        $this->artisan('vixeo:push', ['message' => 'Bonjour', '--user' => ['personne@exemple.fr']])->assertFailed();
        $this->artisan('vixeo:push', ['message' => 'Bonjour', '--all' => true, '--force' => true])
            ->expectsOutputToContain('Aucun appareil inscrit')
            ->assertSuccessful();
    });
});
