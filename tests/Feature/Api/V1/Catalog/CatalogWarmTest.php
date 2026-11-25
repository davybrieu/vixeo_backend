<?php

use App\Models\User;
use Cron\CronExpression;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Sleep;
use Tests\Feature\Api\V1\Catalog\TmdbFixtures as Tmdb;

beforeEach(function () {
    config([
        'services.tmdb.token' => 'test-token',
        'services.tmdb.api_key' => null,
        'services.tmdb.base_url' => 'https://api.themoviedb.org/3',
    ]);
    Http::preventStrayRequests();
    Sleep::fake();
    Sanctum::actingAs(User::factory()->create());
});

/**
 * TMDb for the whole home: the curated lists give movies 1 and 3 and show 2 (with their details
 * and a logo), the light rows (trends of the day, platforms…) movie 40 and show 41. While $down,
 * every request fails.
 */
function warmTmdb(bool &$down): void
{
    Http::fake(function (Request $request) use (&$down) {
        if ($down) {
            return Http::response(['status_message' => 'Error'], 500);
        }
        $path = (string) parse_url($request->url(), PHP_URL_PATH);
        if (preg_match('#/3/(movie|tv)/(\d+)$#', $path, $match)) {
            $details = $match[1] === 'movie' ? Tmdb::movieDetails() : Tmdb::showDetails();

            return Http::response(array_merge($details, ['id' => (int) $match[2], 'images' => [
                'logos' => [['file_path' => "/logo-{$match[2]}.png", 'iso_639_1' => 'fr', 'vote_average' => 5.0, 'aspect_ratio' => 4.0]],
                'backdrops' => [],
                'posters' => [],
            ]]));
        }

        return Http::response(Tmdb::page(match (true) {
            str_contains($path, '/day') || str_contains($path, '/discover/') => [
                str_contains($path, '/tv') ? Tmdb::show(41) : Tmdb::movie(40),
            ],
            str_contains($path, '/all/') => [Tmdb::movie(1, ['media_type' => 'movie']), Tmdb::show(2, ['media_type' => 'tv'])],
            str_contains($path, '/tv') => [Tmdb::show(2)],
            default => [Tmdb::movie(1), Tmdb::movie(3)],
        }));
    });
}

/** Titles whose details were asked to TMDb, once per request ("movie/1"). */
function warmedDetails(): array
{
    return collect(Http::recorded())
        ->map(fn (array $pair) => (string) parse_url($pair[0]->url(), PHP_URL_PATH))
        ->filter(fn (string $path) => preg_match('#/3/(movie|tv)/\d+$#', $path))
        ->map(fn (string $path) => substr($path, strlen('/3/')))
        ->values()
        ->all();
}

it('prepares the home: the first person to open the app asks TMDb nothing', function () {
    $down = false;
    warmTmdb($down);

    $this->artisan('catalog:warm')->expectsOutputToContain('3 titres avec leur résumé')->assertSuccessful();

    // The details of the titles shown with their summary; not those of the light rows.
    expect(warmedDetails())->toEqualCanonicalizing(['movie/1', 'movie/3', 'tv/2']);
    $sent = count(Http::recorded());

    $home = $this->getJson('/api/v1/catalog/home')->assertOk();

    expect(Http::recorded())->toHaveCount($sent)
        ->and($home->json('featured.*.id'))->toBe([1, 2])
        ->and($home->json('featured.0.logo_url'))->toBe('https://image.tmdb.org/t/p/w500/logo-1.png')
        ->and($home->json('sections.0.items.1.summary.runtime'))->toBe(139);
});

it('asks TMDb again for the lists at each run, for the details when missing or every 12 hours', function () {
    $down = false;
    warmTmdb($down);

    $this->artisan('catalog:warm')->expectsOutputToContain('fiches redemandées à TMDb')->assertSuccessful();
    $lists = count(Http::recorded()) - count(warmedDetails());
    expect(warmedDetails())->toHaveCount(3);

    // Half an hour later: the lists again, before they expire (an hour); the details are cached.
    $this->travel(30)->minutes();
    $this->artisan('catalog:warm')->expectsOutputToContain('fiches manquantes seulement')->assertSuccessful();
    expect(count(Http::recorded()) - count(warmedDetails()))->toBe(2 * $lists)
        ->and(warmedDetails())->toHaveCount(3);

    // 12 hours after they were asked, well before they expire (a day): asked again.
    $this->travel(12)->hours();
    $this->artisan('catalog:warm')->expectsOutputToContain('fiches redemandées à TMDb')->assertSuccessful();
    expect(warmedDetails())->toHaveCount(6);

    // Whenever asked.
    $this->artisan('catalog:warm --details')->expectsOutputToContain('fiches redemandées à TMDb')->assertSuccessful();
    expect(warmedDetails())->toHaveCount(9);
});

it('keeps the prepared home when TMDb fails', function () {
    $down = false;
    warmTmdb($down);
    $this->artisan('catalog:warm')->assertSuccessful();

    $down = true;
    $this->travel(30)->minutes();
    $this->artisan('catalog:warm --details')->expectsOutputToContain('Accueil prêt')->assertSuccessful();

    $this->getJson('/api/v1/catalog/home')
        ->assertOk()
        ->assertJsonPath('featured.0.logo_url', 'https://image.tmdb.org/t/p/w500/logo-1.png')
        ->assertJsonPath('sections.0.items.0.id', 1);
});

it('prepares nothing without a TMDb key', function () {
    config(['services.tmdb.token' => null]);
    Http::fake();

    $this->artisan('catalog:warm')
        ->expectsOutputToContain('TMDb n’est pas configuré')
        ->assertFailed();

    Http::assertNothingSent();
});

it('runs more often than the home lists expire', function () {
    $events = collect(app(Schedule::class)->events())
        ->filter(fn ($event) => str_contains((string) $event->command, 'catalog:warm'));
    expect($events)->toHaveCount(1);

    $cron = new CronExpression($events->first()->expression);
    $next = $cron->getNextRunDate('2026-11-23 10:05:00');
    $after = $cron->getNextRunDate($next);

    expect($after->getTimestamp() - $next->getTimestamp())->toBeLessThan(config('services.tmdb.ttl.lists'));
});
