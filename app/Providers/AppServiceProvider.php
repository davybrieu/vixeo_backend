<?php

namespace App\Providers;

use Dedoc\Scramble\Scramble;
use Dedoc\Scramble\Support\Generator\OpenApi;
use Dedoc\Scramble\Support\Generator\SecurityScheme;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;

class AppServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        Password::defaults(fn () => Password::min(8));

        // Outside production, a relation read in a loop without `with()` (one query per row, N+1)
        // throws instead of slowing the API down quietly: the tests catch it.
        Model::preventLazyLoading(! $this->app->isProduction());

        RateLimiter::for('auth', fn (Request $request) => Limit::perMinute(10)->by($request->ip()));

        RateLimiter::for('pin', fn (Request $request) => Limit::perMinute(5)->by(
            $request->user()?->id.'|'.$request->route()?->originalParameter('profile'),
        ));

        // TV polls every 5 s; a little slack for clock drift.
        RateLimiter::for('device-poll', fn (Request $request) => Limit::perMinute(20)->by($request->ip()));
        // Guessing a 6-character code from a signed-in account.
        RateLimiter::for('device-approve', fn (Request $request) => Limit::perMinute(10)->by($request->user()?->id ?: $request->ip()));

        // Also slows down account password guesses ("code parental oublié").
        RateLimiter::for('parental', fn (Request $request) => Limit::perMinute(10)->by($request->user()?->id ?: $request->ip()));

        // Apps check at launch and when they come back to the foreground.
        RateLimiter::for('app-update', fn (Request $request) => Limit::perMinute(30)->by($request->ip()));

        // Account deletion asks the password: slow down guesses.
        RateLimiter::for('account', fn (Request $request) => Limit::perMinute(5)->by($request->user()?->id ?: $request->ip()));
        // Each export sends an e-mail with all the account's data.
        RateLimiter::for('account-export', fn (Request $request) => Limit::perHour(3)->by($request->user()?->id ?: $request->ip()));

        // Catalog screens fire several requests at once (home, details, seasons…).
        // A screen of channels loads a few dozen logos at once; the apps then cache them.
        RateLimiter::for('tv-logos', fn (Request $request) => Limit::perMinute(600)->by($request->ip()));

        // Profile photos: loaded like any image (feed, lists of accounts); a new photo now and then.
        RateLimiter::for('profile-photos', fn (Request $request) => Limit::perMinute(600)->by($request->ip()));
        RateLimiter::for('profile-photo-upload', fn (Request $request) => Limit::perHour(30)->by($request->user()?->id ?: $request->ip()));

        RateLimiter::for('catalog', fn (Request $request) => Limit::perMinute(240)->by($request->user()?->id ?: $request->ip()));

        // Messaging (docs/messagerie.md): reading, writing, answering; files are rarer and heavier.
        RateLimiter::for('messages', fn (Request $request) => Limit::perMinute(180)->by($request->user()?->id ?: $request->ip()));
        RateLimiter::for('message-attachments', fn (Request $request) => Limit::perMinute(20)->by($request->user()?->id ?: $request->ip()));

        // Each new subtitle file uses Vixeo's OpenSubtitles daily quota (cached ones don't).
        RateLimiter::for('subtitles', fn (Request $request) => Limit::perHour(40)->by($request->user()?->id ?: $request->ip()));

        // The profile's reviews: title pages read them, « Mes avis » lists them.
        RateLimiter::for('reviews', fn (Request $request) => Limit::perMinute(120)->by($request->user()?->id ?: $request->ip()));
        // Comments, lists, likes, blocks (docs/social.md); reports (docs/moderation.md).
        RateLimiter::for('social', fn (Request $request) => Limit::perMinute(120)->by($request->user()?->id ?: $request->ip()));
        RateLimiter::for('reports', fn (Request $request) => Limit::perHour(30)->by($request->user()?->id ?: $request->ip()));

        // Apps register for push notifications at launch and when the profile changes.
        RateLimiter::for('push', fn (Request $request) => Limit::perMinute(30)->by($request->user()?->id ?: $request->ip()));

        // Players save progress regularly; pulls are paginated.
        RateLimiter::for('sync', fn (Request $request) => Limit::perMinute(120)->by($request->user()?->id ?: $request->ip()));

        Scramble::configure()
            ->routes(fn ($route) => str_starts_with($route->uri, 'api/v1'))
            ->withDocumentTransformers(function (OpenApi $openApi) {
                $openApi->secure(SecurityScheme::http('bearer'));
            });
    }

}
