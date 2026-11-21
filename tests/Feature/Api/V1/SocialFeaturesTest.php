<?php

use App\Enums\ItemType;
use App\Models\Profile;
use App\Models\ProfileItem;
use App\Models\PushDevice;
use App\Models\SocialNotification;
use App\Models\TmdbRating;
use App\Models\User;
use App\Services\AccountExport;
use App\Services\Social\SocialNotifications;
use Illuminate\Http\Client\Request as ClientRequest;
use Illuminate\Support\Facades\Http;
use Laravel\Sanctum\Sanctum;
use Tests\Feature\Api\V1\Catalog\TmdbFixtures as Tmdb;

// Social features (developer's request, 21/11/2026; docs/social.md).

/** An adult profile with a username, its own account. */
function member(string $username, array $attributes = []): Profile
{
    return Profile::factory()->for(User::factory())->create($attributes + ['username' => $username, 'name' => ucfirst($username)]);
}

/** Requests as [profile] (its account, X-Profile-Id). */
function actingAsMember(Profile $profile): void
{
    Sanctum::actingAs($profile->user);
    test()->withHeader('X-Profile-Id', (string) $profile->id);
}

function memberReview(Profile $author, array $attributes = []): TmdbRating
{
    return TmdbRating::forceCreate($attributes + [
        'profile_id' => $author->id, 'target' => 'movie/550', 'type' => 'movie', 'tmdb_id' => 550,
        'value' => 8.5, 'comment' => 'Une claque.', 'title' => 'Fight Club', 'date' => '1999-10-15',
        'poster_path' => '/fc.jpg', 'rated_at' => now(),
    ]);
}

describe('social networks', function () {
    it('keeps a handle per network, whatever was typed, and shows them as links', function () {
        $lea = member('lea');
        actingAsMember($lea);

        $this->putJson("/api/v1/profiles/{$lea->id}", ['social_links' => [
            'instagram' => '@lea.films',
            'x' => 'https://twitter.com/lea_x',
            'letterboxd' => 'letterboxd.com/leab/',
            'bluesky' => 'https://bsky.app/profile/lea.bsky.social',
            'website' => 'lea.example.com',
        ]])->assertOk()->assertJsonPath('data.social_links.0', [
            'network' => 'instagram', 'label' => 'Instagram', 'handle' => 'lea.films', 'url' => 'https://www.instagram.com/lea.films',
        ]);

        $links = $this->getJson('/api/v1/social/profiles/lea')->assertOk()->json('data.social_links');
        expect(array_column($links, 'url'))->toBe([
            'https://www.instagram.com/lea.films',
            'https://x.com/lea_x',
            'https://letterboxd.com/leab/',
            'https://bsky.app/profile/lea.bsky.social',
            'https://lea.example.com',
        ]);

        // Only the networks given change; null removes one.
        $this->putJson("/api/v1/profiles/{$lea->id}", ['social_links' => ['x' => null, 'tiktok' => 'lea.tok']])->assertOk();
        expect(array_column($this->getJson('/api/v1/social/profiles/lea')->json('data.social_links'), 'network'))
            ->toBe(['instagram', 'tiktok', 'letterboxd', 'bluesky', 'website']);

        $this->putJson("/api/v1/profiles/{$lea->id}", ['social_links' => ['x' => 'pas un compte !']])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['social_links.x' => "Ce compte X n'est pas valide."]);
        $this->putJson("/api/v1/profiles/{$lea->id}", ['social_links' => ['myspace' => 'lea']])
            ->assertUnprocessable()->assertJsonValidationErrors('social_links');
    });
});

describe('private accounts', function () {
    it('asks to follow a private account, which accepts or declines; its lists wait for it', function () {
        $lea = member('lea', ['is_private' => true]);
        $max = member('max');
        $paul = member('paul');
        memberReview($lea);
        actingAsMember($max);

        // Its page shows who it is and its counters, not its lists nor its reviews.
        $this->getJson('/api/v1/social/profiles/lea')->assertOk()
            ->assertJsonPath('data.is_private', true)
            ->assertJsonPath('data.can_view', false)
            ->assertJsonPath('data.follow_status', 'none')
            ->assertJsonPath('data.reviews_count', 1);
        foreach (['reviews', 'list', 'liked', 'people', 'followers', 'following'] as $list) {
            $this->getJson("/api/v1/social/profiles/lea/{$list}")->assertForbidden()->assertJsonPath('message', 'Ce compte est privé.');
        }

        $this->postJson('/api/v1/social/profiles/lea/follow')->assertOk()
            ->assertJsonPath('following', false)->assertJsonPath('follow_status', 'requested');
        $this->getJson('/api/v1/social/profiles/lea')->assertJsonPath('data.follow_status', 'requested')
            ->assertJsonPath('data.followers_count', 0);
        actingAsMember($paul);
        $this->postJson('/api/v1/social/profiles/lea/follow')->assertJsonPath('follow_status', 'requested');

        // Léa sees both requests, as notifications too.
        actingAsMember($lea);
        expect(array_column($this->getJson('/api/v1/social/requests')->assertOk()->json('data'), 'username'))->toBe(['paul', 'max']);
        $this->getJson('/api/v1/notifications')->assertOk()
            ->assertJsonPath('unread_count', 2)
            ->assertJsonPath('data.0.type', 'follow_request')
            ->assertJsonPath('data.0.actor.username', 'paul');

        $this->postJson('/api/v1/social/requests/max/accept')->assertOk();
        $this->deleteJson('/api/v1/social/requests/paul')->assertNoContent();
        expect($this->getJson('/api/v1/social/requests')->json('data'))->toBe([]);
        // The request became a follower (already seen); Paul's is gone.
        expect(collect($this->getJson('/api/v1/notifications')->json('data'))->map(fn ($n) => [$n['type'], $n['actor']['username'], $n['read']])->all())
            ->toBe([['follow', 'max', true]]);

        // Max follows Léa: he sees her reviews, and he is told.
        actingAsMember($max);
        $this->getJson('/api/v1/social/profiles/lea')->assertJsonPath('data.follow_status', 'following')
            ->assertJsonPath('data.can_view', true)->assertJsonPath('data.followers_count', 1);
        $this->getJson('/api/v1/social/profiles/lea/reviews')->assertOk()->assertJsonCount(1, 'data');
        $this->getJson('/api/v1/notifications')->assertJsonPath('data.0.type', 'follow_accepted')
            ->assertJsonPath('data.0.actor.username', 'lea');

        // Paul asks again, then withdraws: no request left.
        actingAsMember($paul);
        $this->postJson('/api/v1/social/profiles/lea/follow')->assertJsonPath('follow_status', 'requested');
        $this->deleteJson('/api/v1/social/profiles/lea/follow')->assertJsonPath('follow_status', 'none');
        expect(SocialNotification::where('type', 'follow_request')->count())->toBe(0);

        // Made public: the requests waiting are accepted.
        $this->postJson('/api/v1/social/profiles/lea/follow');
        actingAsMember($lea);
        $this->putJson("/api/v1/profiles/{$lea->id}", ['is_private' => false])->assertOk()->assertJsonPath('data.is_private', false);
        actingAsMember($paul);
        $this->getJson('/api/v1/social/profiles/lea')->assertJsonPath('data.follow_status', 'following');
    });

    it('follows a public account at once and tells it', function () {
        member('lea');
        $max = member('max');
        actingAsMember($max);

        $this->postJson('/api/v1/social/profiles/lea/follow')->assertJsonPath('follow_status', 'following');
        actingAsMember(Profile::where('username', 'lea')->first());
        $this->getJson('/api/v1/notifications/count')->assertOk()->assertJsonPath('unread_count', 1);
        $this->getJson('/api/v1/notifications')->assertJsonPath('data.0.type', 'follow')->assertJsonPath('data.0.read', false);
        $this->postJson('/api/v1/notifications/read')->assertNoContent();
        $this->getJson('/api/v1/notifications/count')->assertJsonPath('unread_count', 0);

        // Unfollowed: the notification goes.
        actingAsMember($max);
        $this->deleteJson('/api/v1/social/profiles/lea/follow');
        expect(SocialNotification::count())->toBe(0);
    });
});

describe('likes of reviews', function () {
    it('likes another member\'s review, which tells its author', function () {
        $lea = member('lea');
        $max = member('max');
        $rating = memberReview($lea);
        actingAsMember($max);

        $this->putJson("/api/v1/social/reviews/{$rating->id}/like")->assertOk()
            ->assertJsonPath('liked', true)->assertJsonPath('likes_count', 1);
        // Twice: still one.
        $this->putJson("/api/v1/social/reviews/{$rating->id}/like")->assertJsonPath('likes_count', 1);
        $this->getJson('/api/v1/social/profiles/lea/reviews')->assertOk()
            ->assertJsonPath('data.0.review_id', $rating->id)
            ->assertJsonPath('data.0.likes_count', 1)
            ->assertJsonPath('data.0.liked', true);
        // In the export of the account (GDPR).
        expect(app(AccountExport::class)->build($max->user)['profiles'][0]['review_likes'][0])
            ->toMatchArray(['review_of' => 'lea', 'title' => 'Fight Club']);

        actingAsMember($lea);
        $this->getJson('/api/v1/notifications')
            ->assertJsonPath('data.0.type', 'review_like')
            ->assertJsonPath('data.0.actor.username', 'max')
            ->assertJsonPath('data.0.review.title', 'Fight Club')
            ->assertJsonPath('data.0.review.review_id', $rating->id);
        // « Mes avis » counts them.
        $this->getJson("/api/v1/profiles/{$lea->id}/tmdb/ratings")->assertOk()->assertJsonPath('data.0.likes_count', 1);
        // Not one's own review.
        $this->putJson("/api/v1/social/reviews/{$rating->id}/like")->assertUnprocessable()
            ->assertJsonPath('message', 'Vous ne pouvez pas aimer votre propre avis.');

        actingAsMember($max);
        $this->deleteJson("/api/v1/social/reviews/{$rating->id}/like")->assertOk()
            ->assertJsonPath('liked', false)->assertJsonPath('likes_count', 0);
        expect(SocialNotification::count())->toBe(0);
    });

    it('never likes a private account\'s review without following it', function () {
        $lea = member('lea', ['is_private' => true]);
        actingAsMember(member('max'));

        $this->putJson('/api/v1/social/reviews/'.memberReview($lea)->id.'/like')->assertForbidden();
    });
});

describe('push', function () {
    it('pushes the social activity to the apps that keep it on', function () {
        config(['services.fcm.credentials' => 'base64:'.base64_encode((string) json_encode([
            'type' => 'service_account', 'project_id' => 'vixeo-test',
            'client_email' => 'push@vixeo-test.iam.gserviceaccount.com',
            'private_key' => (function () {
                openssl_pkey_export(openssl_pkey_new(['private_key_bits' => 2048, 'private_key_type' => OPENSSL_KEYTYPE_RSA]), $pem);

                return $pem;
            })(),
            'token_uri' => 'https://oauth2.googleapis.com/token',
        ]))]);
        $sent = new ArrayObject;
        Http::fake([
            'oauth2.googleapis.com/token' => Http::response(['access_token' => 'ya29.test', 'expires_in' => 3600]),
            'fcm.googleapis.com/*' => function (ClientRequest $request) use ($sent) {
                $sent[] = $request->data()['message'];

                return Http::response(['name' => 'projects/vixeo-test/messages/1']);
            },
        ]);
        $lea = member('lea');
        foreach (['on' => true, 'off' => false] as $token => $social) {
            PushDevice::create([
                'user_id' => $lea->user_id, 'profile_id' => $lea->id,
                'personal_access_token_id' => $lea->user->createToken($token)->accessToken->id,
                'token' => $token, 'token_hash' => PushDevice::hashToken($token), 'platform' => 'android',
                'new_episodes' => false, 'social' => $social, 'utc_offset' => 60, 'last_seen_at' => now(),
            ]);
        }
        actingAsMember(member('max', ['name' => 'Max']));

        $this->postJson('/api/v1/social/profiles/lea/follow')->assertOk();

        expect($sent)->toHaveCount(1)
            ->and($sent[0]['token'])->toBe('on')
            ->and($sent[0]['notification'])->toBe(['title' => 'Nouvel abonné', 'body' => 'Max (@max) vous suit.'])
            ->and($sent[0]['data'])->toBe(['path' => '/membre/max'])
            ->and($sent[0]['android']['notification']['channel_id'])->toBe('social');
    });

    it('writes every kind of social notification', function () {
        $lea = member('lea', ['name' => 'Léa']);
        $max = member('max');
        $rating = memberReview($max);
        $message = fn (string $type, ?TmdbRating $r = null) => SocialNotifications::message(
            SocialNotification::create(['profile_id' => $max->id, 'actor_profile_id' => $lea->id, 'type' => $type, 'tmdb_rating_id' => $r?->id, 'created_at' => now()])->load(['actor', 'rating']),
        );

        expect($message('follow_request')->body)->toBe('Léa (@lea) demande à vous suivre.')
            ->and($message('follow_accepted')->body)->toBe('Léa (@lea) a accepté votre demande d’abonnement.')
            ->and($message('review_like', $rating)->body)->toBe('Léa (@lea) aime votre avis sur Fight Club.')
            ->and($message('review_like', $rating)->path)->toBe('/notifications');
    });
});

describe('next episodes', function () {
    it('lists the next episodes of the « Ma liste » TV shows, and those just out', function () {
        config(['services.tmdb.token' => 'test-token', 'services.tmdb.api_key' => null, 'services.tmdb.base_url' => 'https://api.themoviedb.org/3']);
        Http::preventStrayRequests();
        $this->travelTo(now()->setDate(2026, 11, 21)->setTime(12, 0));
        $show = fn (int $id, string $name, ?array $next, ?array $last) => Http::response([
            ...Tmdb::showDetails(), 'id' => $id, 'name' => $name, 'networks' => [['id' => 213]], 'origin_country' => ['US'],
            'next_episode_to_air' => $next, 'last_episode_to_air' => $last,
        ]);
        Http::fake([
            'api.themoviedb.org/3/tv/1*' => $show(1, 'Bientôt', ['season_number' => 2, 'episode_number' => 3, 'name' => 'La suite', 'air_date' => '2026-11-25', 'still_path' => '/s.jpg'], null),
            'api.themoviedb.org/3/tv/2*' => $show(2, 'Demain', ['season_number' => 1, 'episode_number' => 8, 'name' => 'Final', 'air_date' => '2026-11-22'], ['season_number' => 1, 'episode_number' => 7, 'name' => 'Avant', 'air_date' => '2026-11-19']),
            'api.themoviedb.org/3/tv/3*' => $show(3, 'Finie', null, ['season_number' => 5, 'episode_number' => 10, 'name' => 'Fin', 'air_date' => '2025-01-01']),
        ]);
        $lea = member('lea');
        foreach ([1, 2, 3] as $id) {
            ProfileItem::factory()->for($lea)->favorite()->create(['item_id' => "tv:{$id}", 'type' => ItemType::Tv]);
        }
        actingAsMember($lea);

        $response = $this->getJson('/api/v1/notifications/episodes')->assertOk();

        expect(array_map(fn ($e) => [$e['show']['name'], $e['season_number'], $e['episode_number'], $e['available_at']], $response->json('upcoming')))->toBe([
            ['Demain', 1, 8, '2026-11-22T08:00:00+00:00'],
            ['Bientôt', 2, 3, '2026-11-25T08:00:00+00:00'],
        ])->and(array_map(fn ($e) => [$e['show']['name'], $e['episode_number']], $response->json('recent')))->toBe([['Demain', 7]])
            ->and($response->json('upcoming.1.still_url'))->toBe('https://image.tmdb.org/t/p/w300/s.jpg');
    });
});
