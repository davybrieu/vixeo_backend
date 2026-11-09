<?php

use App\Models\Profile;
use App\Models\TmdbLink;
use App\Models\User;
use App\Services\AccountExport;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Laravel\Sanctum\Sanctum;
use Tests\Feature\Api\V1\Catalog\TmdbFixtures as Tmdb;

const TMDB = 'api.themoviedb.org/3/';

/** TMDb account of the fake session. */
function tmdbAccount(int $id = 42): array
{
    return ['id' => $id, 'username' => 'davy', 'name' => 'Davy B.', 'avatar' => ['tmdb' => ['avatar_path' => '/me.png']]];
}

function linkProfile(Profile $profile, string $session = 'sess-1', int $accountId = 42): TmdbLink
{
    return $profile->tmdbLink()->create([
        'account_id' => $accountId, 'username' => 'davy', 'session_id' => $session, 'linked_at' => now(),
    ]);
}

beforeEach(function () {
    config(['services.tmdb.token' => 'test-token', 'services.tmdb.base_url' => 'https://api.themoviedb.org/3']);
    Http::preventStrayRequests();

    $this->user = User::factory()->create();
    $this->profile = Profile::factory()->for($this->user)->create();
    Sanctum::actingAs($this->user);
    $this->url = "/api/v1/profiles/{$this->profile->id}/tmdb";
});

it('says when no TMDb account is linked', function () {
    $this->getJson($this->url)->assertOk()->assertJsonPath('data.linked', false)->assertJsonPath('data.username', null);
});

it('links a TMDb account once the person approved Vixeo on TMDb', function () {
    Http::fake([
        TMDB.'authentication/token/new*' => Http::response(['success' => true, 'expires_at' => now()->addHour()->format('Y-m-d H:i:s').' UTC', 'request_token' => 'tok-1']),
        TMDB.'authentication/session/new*' => Http::sequence()
            ->push(['success' => false, 'status_code' => 17, 'status_message' => 'Session denied.'], 401)
            ->push(['success' => true, 'session_id' => 'sess-1']),
        TMDB.'account?*' => Http::response(tmdbAccount()),
    ]);

    $this->postJson("{$this->url}/link")->assertOk()
        ->assertJsonPath('authorize_url', 'https://www.themoviedb.org/authenticate/tok-1')
        ->assertJsonPath('interval', 5);

    // Not approved yet.
    $this->postJson("{$this->url}/link/complete")->assertAccepted()->assertJsonPath('status', 'pending');

    $this->postJson("{$this->url}/link/complete")->assertOk()
        ->assertJsonPath('status', 'linked')
        ->assertJsonPath('data.linked', true)
        ->assertJsonPath('data.account_id', 42)
        ->assertJsonPath('data.username', 'davy')
        ->assertJsonPath('data.avatar_url', 'https://image.tmdb.org/t/p/w185/me.png');

    // A late poll (another device) still gets the account.
    $this->postJson("{$this->url}/link/complete")->assertOk()->assertJsonPath('data.username', 'davy');

    $response = $this->getJson($this->url)->assertOk()->assertJsonPath('data.linked', true);
    // The session never leaves the server, and is encrypted in the database.
    expect($response->getContent())->not->toContain('sess-1')
        ->and(DB::table('tmdb_links')->value('session_id'))->not->toBe('sess-1')
        ->and($this->profile->tmdbLink->session_id)->toBe('sess-1');
    Http::assertSent(fn (Request $r) => str_contains($r->url(), 'account?') && str_contains($r->url(), 'session_id=sess-1'));
});

it('asks to start again when the approval page expired', function () {
    Http::fake([
        TMDB.'authentication/token/new*' => Http::response(['success' => true, 'expires_at' => now()->addHour()->format('Y-m-d H:i:s').' UTC', 'request_token' => 'tok-1']),
        TMDB.'authentication/session/new*' => Http::response(['success' => false, 'status_code' => 33], 401),
    ]);

    // Nothing started.
    $this->postJson("{$this->url}/link/complete")->assertGone()->assertJsonPath('status', 'expired');

    $this->postJson("{$this->url}/link");
    $this->postJson("{$this->url}/link/complete")->assertGone()
        ->assertJsonPath('message', 'La page d’autorisation TMDb a expiré : recommencez.');
    expect($this->profile->tmdbLink()->exists())->toBeFalse();
});

it('links the same TMDb account to profiles of other Vixeo accounts', function () {
    Http::fake([
        TMDB.'authentication/token/new*' => Http::response(['success' => true, 'expires_at' => now()->addHour()->format('Y-m-d H:i:s').' UTC', 'request_token' => 'tok']),
        TMDB.'authentication/session/new*' => Http::sequence()->push(['success' => true, 'session_id' => 'sess-a'])->push(['success' => true, 'session_id' => 'sess-b']),
        TMDB.'account?*' => Http::response(tmdbAccount()),
    ]);
    $this->postJson("{$this->url}/link");
    $this->postJson("{$this->url}/link/complete")->assertOk();

    $other = User::factory()->create();
    $otherProfile = Profile::factory()->for($other)->create();
    Sanctum::actingAs($other);
    $this->postJson("/api/v1/profiles/{$otherProfile->id}/tmdb/link");
    $this->postJson("/api/v1/profiles/{$otherProfile->id}/tmdb/link/complete")->assertOk()->assertJsonPath('data.account_id', 42);

    expect(TmdbLink::where('account_id', 42)->count())->toBe(2)
        ->and($otherProfile->tmdbLink->session_id)->toBe('sess-b');
});

it('replaces the account when linking again, and ends the old session', function () {
    linkProfile($this->profile, 'old-session');
    Http::fake([
        TMDB.'authentication/token/new*' => Http::response(['success' => true, 'expires_at' => now()->addHour()->format('Y-m-d H:i:s').' UTC', 'request_token' => 'tok']),
        TMDB.'authentication/session/new*' => Http::response(['success' => true, 'session_id' => 'new-session']),
        TMDB.'account?*' => Http::response(tmdbAccount(77)),
        TMDB.'authentication/session?*' => Http::response(['success' => true]),
    ]);

    $this->postJson("{$this->url}/link");
    $this->postJson("{$this->url}/link/complete")->assertOk()->assertJsonPath('data.account_id', 77);

    expect(TmdbLink::count())->toBe(1)->and($this->profile->tmdbLink->session_id)->toBe('new-session');
    Http::assertSent(fn (Request $r) => $r->method() === 'DELETE' && $r['session_id'] === 'old-session');
});

it('unlinks the account and ends its session on TMDb', function () {
    linkProfile($this->profile);
    Http::fake([TMDB.'authentication/session?*' => Http::response(['success' => true])]);

    $this->deleteJson($this->url)->assertNoContent();

    expect($this->profile->tmdbLink()->exists())->toBeFalse();
    Http::assertSent(fn (Request $r) => $r->method() === 'DELETE' && $r['session_id'] === 'sess-1');
});

it('rates, updates and removes the rating of a movie, a TV show and an episode', function () {
    linkProfile($this->profile);
    Http::fake([TMDB.'*' => Http::response(['success' => true, 'status_code' => 1])]);

    $this->putJson("{$this->url}/ratings/movie/550", ['value' => 8.5])->assertOk()->assertJsonPath('data.value', 8.5);
    $this->putJson("{$this->url}/ratings/movie/550", ['value' => 6])->assertOk()->assertJsonPath('data.value', 6);
    $this->deleteJson("{$this->url}/ratings/movie/550")->assertNoContent();
    $this->putJson("{$this->url}/ratings/tv/1399", ['value' => 10])->assertOk();
    $this->putJson("{$this->url}/ratings/tv/1399/season/1/episode/2", ['value' => 7.5])->assertOk();
    $this->deleteJson("{$this->url}/ratings/tv/1399/season/1/episode/2")->assertNoContent();

    $sent = Http::recorded()->map(fn ($pair) => $pair[0]->method().' '.strtok(substr($pair[0]->url(), strlen('https://'.TMDB)), '?').' '.json_encode($pair[0]->data()))->all();
    expect($sent)->toBe([
        'POST movie/550/rating {"value":8.5}',
        'POST movie/550/rating {"value":6}',
        'DELETE movie/550/rating []',
        'POST tv/1399/rating {"value":10}',
        'POST tv/1399/season/1/episode/2/rating {"value":7.5}',
        'DELETE tv/1399/season/1/episode/2/rating []',
    ]);
    Http::assertSent(fn (Request $r) => str_contains($r->url(), 'session_id=sess-1'));
});

it('refuses ratings outside TMDb scale, in French', function () {
    linkProfile($this->profile);

    $this->putJson("{$this->url}/ratings/movie/550", ['value' => 7.3])->assertUnprocessable()
        ->assertJsonPath('errors.value.0', 'Le champ note doit être un multiple de 0.5.');
    $this->putJson("{$this->url}/ratings/movie/550", ['value' => 11])->assertUnprocessable();
    $this->putJson("{$this->url}/ratings/movie/550", ['value' => 0])->assertUnprocessable();
    Http::assertNothingSent();
});

it('removing a rating that does not exist changes nothing', function () {
    linkProfile($this->profile);
    Http::fake([TMDB.'movie/550/rating*' => Http::response(['success' => false, 'status_code' => 34], 404)]);

    $this->deleteJson("{$this->url}/ratings/movie/550")->assertNoContent();
});

it('reads my rating of a title and of the episodes of a season', function () {
    linkProfile($this->profile);
    Http::fake([
        TMDB.'movie/550/account_states*' => Http::response(['id' => 550, 'favorite' => false, 'rated' => ['value' => 8.0], 'watchlist' => false]),
        TMDB.'tv/1399/account_states*' => Http::response(['id' => 1399, 'rated' => false]),
        TMDB.'tv/1399/season/1/account_states*' => Http::response(['id' => 1, 'results' => [
            ['id' => 11, 'episode_number' => 3, 'rated' => ['value' => 9.5]],
            ['id' => 12, 'episode_number' => 1, 'rated' => ['value' => 7.0]],
            ['id' => 13, 'episode_number' => 2, 'rated' => false],
        ]]),
    ]);

    $this->getJson("{$this->url}/ratings/movie/550")->assertOk()->assertJsonPath('data.value', 8);
    $this->getJson("{$this->url}/ratings/tv/1399")->assertOk()->assertJsonPath('data.value', null);
    $this->getJson("{$this->url}/ratings/tv/1399/season/1")->assertOk()->assertExactJson(['data' => [
        ['episode_number' => 1, 'value' => 7],
        ['episode_number' => 3, 'value' => 9.5],
    ]]);
});

it('lists my ratings, the most recent first', function () {
    linkProfile($this->profile);
    Http::fake([
        TMDB.'account/42/rated/movies*' => Http::response(Tmdb::page([
            Tmdb::movie(550, ['rating' => 8.5]), Tmdb::movie(551, ['title' => 'Dune', 'rating' => 6.0]),
        ])),
        TMDB.'account/42/rated/tv/episodes*' => Http::response(Tmdb::page([
            ['id' => 63056, 'show_id' => 1399, 'season_number' => 1, 'episode_number' => 2, 'name' => 'La route royale',
                'air_date' => '2011-04-24', 'still_path' => '/e2.jpg', 'vote_average' => 8.1, 'rating' => 9.0],
        ])),
        TMDB.'tv/1399?*' => Http::response(Tmdb::show(1399)),
    ]);

    $this->getJson("{$this->url}/ratings?type=movie")->assertOk()
        ->assertJsonPath('data.*.title', ['Fight Club', 'Dune'])
        ->assertJsonPath('data.0.type', 'movie')
        ->assertJsonPath('data.0.value', 8.5)
        ->assertJsonPath('data.0.year', 1999)
        ->assertJsonPath('meta.page', 1);
    Http::assertSent(fn (Request $r) => str_contains($r->url(), 'rated/movies') && $r['sort_by'] === 'created_at.desc' && $r['page'] === '1');

    $this->getJson("{$this->url}/ratings?type=episode")->assertOk()
        ->assertJsonPath('data.0.type', 'episode')
        ->assertJsonPath('data.0.id', 1399)
        ->assertJsonPath('data.0.title', 'Game of Thrones')
        ->assertJsonPath('data.0.episode_name', 'La route royale')
        ->assertJsonPath('data.0.season_number', 1)
        ->assertJsonPath('data.0.episode_number', 2)
        ->assertJsonPath('data.0.poster_url', 'https://image.tmdb.org/t/p/w342/got.jpg')
        ->assertJsonPath('data.0.still_url', 'https://image.tmdb.org/t/p/w300/e2.jpg')
        ->assertJsonPath('data.0.value', 9);

    $this->getJson("{$this->url}/ratings?type=person")->assertUnprocessable();
});

it('hides the ratings a kid profile may not see', function () {
    $kid = Profile::factory()->for($this->user)->kid(12)->create();
    linkProfile($kid);
    Http::fake([
        TMDB.'account/42/rated/movies*' => Http::response(Tmdb::page([Tmdb::movie(1, ['rating' => 8.0]), Tmdb::movie(2, ['rating' => 5.0])])),
        TMDB.'movie/1/release_dates*' => Http::response(['results' => [['iso_3166_1' => 'FR', 'release_dates' => [['certification' => 'U']]]]]),
        TMDB.'movie/2/release_dates*' => Http::response(['results' => [['iso_3166_1' => 'FR', 'release_dates' => [['certification' => '16']]]]]),
    ]);

    $this->getJson("/api/v1/profiles/{$kid->id}/tmdb/ratings?type=movie")->assertOk()->assertJsonPath('data.*.id', [1]);
});

it('asks to link again when TMDb no longer accepts the session', function () {
    linkProfile($this->profile);
    Http::fake([TMDB.'*' => Http::response(['success' => false, 'status_code' => 3, 'status_message' => 'Authentication failed'], 401)]);

    $this->getJson("{$this->url}/ratings/movie/550")->assertConflict()
        ->assertJsonPath('message', 'Votre compte TMDb n’est plus lié à ce profil : liez-le à nouveau.');
    expect($this->profile->tmdbLink()->exists())->toBeFalse();
});

it('refuses ratings without a linked account, and other accounts\' profiles', function () {
    $this->getJson("{$this->url}/ratings/movie/550")->assertConflict()
        ->assertJsonPath('message', 'Liez d’abord un compte TMDb à ce profil.');

    $stranger = Profile::factory()->create();
    linkProfile($stranger);
    $this->getJson("/api/v1/profiles/{$stranger->id}/tmdb")->assertForbidden();
    $this->putJson("/api/v1/profiles/{$stranger->id}/tmdb/ratings/movie/550", ['value' => 5])->assertForbidden();
    $this->deleteJson("/api/v1/profiles/{$stranger->id}/tmdb")->assertForbidden();
    Http::assertNothingSent();
});

it('answers 503 when TMDb is down', function () {
    linkProfile($this->profile);
    Http::fake([TMDB.'*' => Http::response('', 502)]);

    $this->putJson("{$this->url}/ratings/movie/550", ['value' => 5])->assertServiceUnavailable();
});

it('exports the linked account without its session, and deletes it with the profile', function () {
    linkProfile($this->profile);

    $export = app(AccountExport::class)->build($this->user);
    expect($export['profiles'][0]['tmdb_account'])->toMatchArray(['account_id' => 42, 'username' => 'davy'])
        ->and(json_encode($export))->not->toContain('sess-1');

    $this->profile->delete();
    expect(TmdbLink::count())->toBe(0);
});
