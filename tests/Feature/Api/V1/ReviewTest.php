<?php

use App\Models\Profile;
use App\Models\TmdbRating;
use App\Models\User;
use App\Services\AccountExport;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Laravel\Sanctum\Sanctum;
use Tests\Feature\Api\V1\Catalog\TmdbFixtures as Tmdb;

const TMDB = 'api.themoviedb.org/3/';

/** TMDb answering the title pages (what « Mes avis » shows of a title). `$state->down`: TMDb fails. */
function fakeTitles(?object $state = null): void
{
    $state ??= (object) ['down' => false];
    $answer = fn (callable $body) => fn () => $state->down ? Http::response('', 502) : $body();
    Http::fake([
        TMDB.'movie/550?*' => $answer(fn () => Http::response(Tmdb::movieDetails())),
        TMDB.'movie/551?*' => $answer(fn () => Http::response(['id' => 551, 'title' => 'Dune'] + Tmdb::movieDetails())),
        TMDB.'movie/552?*' => $answer(fn () => Http::response(['id' => 552, 'title' => 'Alien'] + Tmdb::movieDetails())),
        TMDB.'tv/1399?*' => $answer(fn () => Http::response(Tmdb::showDetails())),
        TMDB.'tv/1399/season/1/episode/*' => $answer(fn () => Http::response(['id' => 63057, 'name' => 'La route royale', 'air_date' => '2011-04-24', 'still_path' => '/e2.jpg', 'vote_average' => 8.1])),
        TMDB.'movie/*/release_dates*' => $answer(fn () => Http::response(['results' => []])),
        TMDB.'tv/*/content_ratings*' => $answer(fn () => Http::response(['results' => []])),
    ]);
}

/** A review written some minutes ago (the most recent first in « Mes avis »). */
function review(Profile $profile, string $target, float $value, int $minutesAgo, array $attributes = []): TmdbRating
{
    [$type, $id] = explode('/', $target);

    return TmdbRating::forceCreate($attributes + [
        'profile_id' => $profile->id, 'target' => $target, 'type' => $type, 'tmdb_id' => (int) $id,
        'value' => $value, 'title' => 'Fight Club', 'rated_at' => now()->subMinutes($minutesAgo),
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

it('rates, updates and removes the review of a movie, a TV show and an episode, in Vixeo only', function () {
    fakeTitles();

    $this->putJson("{$this->url}/ratings/movie/550", ['value' => 8.5])->assertOk()->assertJsonPath('data.value', 8.5);
    $this->putJson("{$this->url}/ratings/movie/550", ['value' => 6])->assertOk()->assertJsonPath('data.value', 6);
    $this->putJson("{$this->url}/ratings/tv/1399", ['value' => 10])->assertOk();
    $this->putJson("{$this->url}/ratings/tv/1399/season/1/episode/2", ['value' => 7.5])->assertOk();
    expect($this->profile->tmdbRatings()->orderBy('target')->pluck('value', 'target')->all())
        ->toBe(['movie/550' => 6.0, 'tv/1399' => 10.0, 'tv/1399/season/1/episode/2' => 7.5]);

    $this->deleteJson("{$this->url}/ratings/movie/550")->assertNoContent();
    $this->deleteJson("{$this->url}/ratings/tv/1399/season/1/episode/2")->assertNoContent();
    expect($this->profile->tmdbRatings()->pluck('target')->all())->toBe(['tv/1399']);

    // Nothing is sent to a TMDb account: only title pages are read.
    Http::assertNotSent(fn (Request $r) => $r->method() !== 'GET' || str_contains($r->url(), '/rating') || str_contains($r->url(), 'account'));
});

it('refuses ratings outside the scale, in French', function () {
    $this->putJson("{$this->url}/ratings/movie/550", ['value' => 7.3])->assertUnprocessable()
        ->assertJsonPath('errors.value.0', 'Le champ note doit être un multiple de 0.5.');
    $this->putJson("{$this->url}/ratings/movie/550", ['value' => 11])->assertUnprocessable();
    $this->putJson("{$this->url}/ratings/movie/550", ['value' => 0])->assertUnprocessable();
    Http::assertNothingSent();
});

it('removing a review that does not exist changes nothing', function () {
    $this->deleteJson("{$this->url}/ratings/movie/550")->assertNoContent();
    Http::assertNothingSent();
});

it('reads my rating of a title and of the episodes of a season', function () {
    fakeTitles();
    $this->putJson("{$this->url}/ratings/movie/550", ['value' => 8, 'comment' => 'Revu.'])->assertOk();
    $this->putJson("{$this->url}/ratings/tv/1399/season/1/episode/3", ['value' => 9.5])->assertOk();
    $this->putJson("{$this->url}/ratings/tv/1399/season/1/episode/1", ['value' => 7])->assertOk();

    $this->getJson("{$this->url}/ratings/movie/550")->assertOk()
        ->assertJsonPath('data.value', 8)
        ->assertJsonPath('data.comment', 'Revu.');
    $this->getJson("{$this->url}/ratings/tv/1399")->assertOk()->assertJsonPath('data.value', null);
    $this->getJson("{$this->url}/ratings/tv/1399/season/1")->assertOk()->assertExactJson(['data' => [
        ['episode_number' => 1, 'value' => 7, 'comment' => null],
        ['episode_number' => 3, 'value' => 9.5, 'comment' => null],
    ]]);
});

it('keeps an optional comment with the rating', function () {
    fakeTitles();

    $this->putJson("{$this->url}/ratings/movie/550", ['value' => 8, 'comment' => '  Une claque.  '])->assertOk()
        ->assertJsonPath('data.value', 8)
        ->assertJsonPath('data.comment', 'Une claque.');

    // A new rating without the comment field keeps the comment; empty removes it.
    $this->putJson("{$this->url}/ratings/movie/550", ['value' => 9])->assertOk()->assertJsonPath('data.comment', 'Une claque.');
    $this->getJson("{$this->url}/ratings/movie/550")->assertOk()->assertJsonPath('data.comment', 'Une claque.');
    $this->putJson("{$this->url}/ratings/movie/550", ['value' => 9, 'comment' => ''])->assertOk()->assertJsonPath('data.comment', null);

    // An episode, then removed: its comment goes with it.
    $this->putJson("{$this->url}/ratings/tv/1399/season/1/episode/2", ['value' => 7.5, 'comment' => 'Le meilleur.'])->assertOk();
    expect(TmdbRating::where('target', 'tv/1399/season/1/episode/2')->value('episode_name'))->toBe('La route royale');
    $this->deleteJson("{$this->url}/ratings/tv/1399/season/1/episode/2")->assertNoContent();
    expect(TmdbRating::where('target', 'tv/1399/season/1/episode/2')->exists())->toBeFalse();

    $this->putJson("{$this->url}/ratings/movie/550", ['value' => 9, 'comment' => str_repeat('a', 2001)])->assertUnprocessable()
        ->assertJsonPath('errors.comment.0', 'Le champ commentaire ne doit pas contenir plus de 2000 caractères.');
});

it('lists every review with its comment, the most recently rated first, by page', function () {
    fakeTitles();
    $this->putJson("{$this->url}/ratings/tv/1399", ['value' => 9])->assertOk();
    $this->travel(1)->minutes();
    $this->putJson("{$this->url}/ratings/movie/551", ['value' => 6])->assertOk();
    $this->travel(1)->minutes();
    $this->putJson("{$this->url}/ratings/movie/550", ['value' => 8.5])->assertOk();

    $this->getJson("{$this->url}/ratings?per_page=500")->assertOk()
        ->assertJsonPath('data.*.title', ['Fight Club', 'Dune', 'Game of Thrones'])
        ->assertJsonPath('data.0.type', 'movie')
        ->assertJsonPath('data.0.value', 8.5)
        ->assertJsonPath('data.0.year', 1999)
        ->assertJsonPath('meta.total_results', 3);

    // Rated again, with a comment: on top.
    $this->travel(1)->minutes();
    $this->putJson("{$this->url}/ratings/tv/1399", ['value' => 10, 'comment' => 'Culte.'])->assertOk();
    $this->getJson("{$this->url}/ratings")->assertOk()
        ->assertJsonPath('data.0.title', 'Game of Thrones')
        ->assertJsonPath('data.0.value', 10)
        ->assertJsonPath('data.0.comment', 'Culte.');

    $this->getJson("{$this->url}/ratings?type=movie&per_page=1&page=2")->assertOk()
        ->assertJsonPath('data.*.title', ['Dune'])
        ->assertJsonPath('meta.total_pages', 2);
    $this->getJson("{$this->url}/ratings?type=person")->assertUnprocessable();
});

it('lists the reviews of episodes with their show', function () {
    fakeTitles();
    $this->putJson("{$this->url}/ratings/tv/1399/season/1/episode/2", ['value' => 9])->assertOk();

    $this->getJson("{$this->url}/ratings?type=episode")->assertOk()
        ->assertJsonPath('data.0.type', 'episode')
        ->assertJsonPath('data.0.id', 1399)
        ->assertJsonPath('data.0.title', 'Game of Thrones')
        ->assertJsonPath('data.0.episode_name', 'La route royale')
        ->assertJsonPath('data.0.season_number', 1)
        ->assertJsonPath('data.0.episode_number', 2)
        ->assertJsonPath('data.0.still_url', 'https://image.tmdb.org/t/p/w300/e2.jpg')
        ->assertJsonPath('data.0.value', 9);
});

it('sorts my reviews by date or by rating, equal ratings the most recent first', function () {
    review($this->profile, 'movie/550', 8.5, 1);
    review($this->profile, 'movie/551', 6, 2, ['title' => 'Dune']);
    review($this->profile, 'movie/552', 8.5, 3, ['title' => 'Alien']);
    review($this->profile, 'tv/1399', 9, 4, ['title' => 'Game of Thrones']);
    fakeTitles();
    $titles = fn (string $query) => $this->getJson("{$this->url}/ratings?per_page=500{$query}")->assertOk()->json('data.*.title');

    expect($titles(''))->toBe(['Fight Club', 'Dune', 'Alien', 'Game of Thrones'])
        ->and($titles('&sort=recent'))->toBe(['Fight Club', 'Dune', 'Alien', 'Game of Thrones'])
        ->and($titles('&sort=oldest'))->toBe(['Game of Thrones', 'Alien', 'Dune', 'Fight Club'])
        ->and($titles('&sort=highest'))->toBe(['Game of Thrones', 'Fight Club', 'Alien', 'Dune'])
        ->and($titles('&sort=lowest'))->toBe(['Dune', 'Fight Club', 'Alien', 'Game of Thrones'])
        ->and($titles('&sort=highest&type=movie'))->toBe(['Fight Club', 'Alien', 'Dune']);

    // Sorted before paging.
    $this->getJson("{$this->url}/ratings?sort=lowest&per_page=2&page=2")->assertOk()
        ->assertJsonPath('data.*.title', ['Alien', 'Game of Thrones']);

    $this->getJson("{$this->url}/ratings?sort=title")->assertUnprocessable()
        ->assertJsonPath('errors.sort.0', 'La valeur sélectionnée pour tri n\'est pas valide.');
});

it('hides the reviews a kid profile may not see', function () {
    $kid = Profile::factory()->for($this->user)->kid(12)->create();
    review($kid, 'movie/1', 8, 1);
    review($kid, 'movie/2', 5, 2);
    Http::fake([
        TMDB.'movie/1/release_dates*' => Http::response(['results' => [['iso_3166_1' => 'FR', 'release_dates' => [['certification' => 'U']]]]]),
        TMDB.'movie/2/release_dates*' => Http::response(['results' => [['iso_3166_1' => 'FR', 'release_dates' => [['certification' => '16']]]]]),
    ]);

    $this->getJson("/api/v1/profiles/{$kid->id}/tmdb/ratings?type=movie")->assertOk()->assertJsonPath('data.*.id', [1]);
});

it('keeps the review when TMDb is down, and completes its title later', function () {
    $tmdb = (object) ['down' => true];
    fakeTitles($tmdb);

    $this->putJson("{$this->url}/ratings/movie/550", ['value' => 5, 'comment' => 'Revu.'])->assertOk()->assertJsonPath('data.value', 5);
    expect($this->profile->tmdbRatings()->value('title'))->toBe('');

    $tmdb->down = false;
    $this->getJson("{$this->url}/ratings")->assertOk()
        ->assertJsonPath('data.0.title', 'Fight Club')
        ->assertJsonPath('data.0.comment', 'Revu.');
    expect($this->profile->tmdbRatings()->value('title'))->toBe('Fight Club');
});

it('refuses other accounts\' profiles', function () {
    $stranger = Profile::factory()->create();

    $this->getJson("/api/v1/profiles/{$stranger->id}/tmdb/ratings")->assertForbidden();
    $this->putJson("/api/v1/profiles/{$stranger->id}/tmdb/ratings/movie/550", ['value' => 5])->assertForbidden();
    $this->deleteJson("/api/v1/profiles/{$stranger->id}/tmdb/ratings/movie/550")->assertForbidden();
});

it('exports the reviews, and deletes them with the profile', function () {
    review($this->profile, 'movie/550', 8.5, 1, ['comment' => 'Une claque.']);

    $export = app(AccountExport::class)->build($this->user);
    expect($export['profiles'][0]['tmdb_ratings'][0])->toMatchArray(['title' => 'Fight Club', 'value' => 8.5, 'comment' => 'Une claque.'])
        ->and($export['profiles'][0])->not->toHaveKey('tmdb_account');

    $this->profile->delete();
    expect(TmdbRating::count())->toBe(0)->and(DB::table('tmdb_ratings')->count())->toBe(0);
});
