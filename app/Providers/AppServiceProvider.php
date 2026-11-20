<?php

namespace App\Providers;

use App\Http\Controllers\SiteController;
use App\Services\LatestReleases;
use App\Support\Platform;
use Dedoc\Scramble\Scramble;
use Dedoc\Scramble\Support\Generator\OpenApi;
use Dedoc\Scramble\Support\Generator\SecurityScheme;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;
use Inertia\Inertia;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // Once per request: the site's layout and page both ask for the latest release.
        $this->app->scoped(LatestReleases::class);
    }

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

        RateLimiter::for('catalog', fn (Request $request) => Limit::perMinute(240)->by($request->user()?->id ?: $request->ip()));
        // Ratings of other sites (MDBList, Vixeo's daily quota): a home or a page of cards asks
        // its ratings in a few requests.
        RateLimiter::for('ratings', fn (Request $request) => Limit::perMinute(60)->by($request->user()?->id ?: $request->ip()));

        // Each new subtitle file uses Vixeo's OpenSubtitles daily quota (cached ones don't).
        RateLimiter::for('subtitles', fn (Request $request) => Limit::perHour(40)->by($request->user()?->id ?: $request->ip()));

        // The profile's reviews: title pages read them, « Mes avis » lists them.
        RateLimiter::for('reviews', fn (Request $request) => Limit::perMinute(120)->by($request->user()?->id ?: $request->ip()));

        // Apps register for push notifications at launch and when the profile changes.
        RateLimiter::for('push', fn (Request $request) => Limit::perMinute(30)->by($request->user()?->id ?: $request->ip()));

        // Players save progress regularly; pulls are paginated.
        RateLimiter::for('sync', fn (Request $request) => Limit::perMinute(120)->by($request->user()?->id ?: $request->ip()));

        $this->shareSiteProps();

        Scramble::configure()
            ->routes(fn ($route) => str_starts_with($route->uri, 'api/v1'))
            ->withDocumentTransformers(function (OpenApi $openApi) {
                $openApi->secure(SecurityScheme::http('bearer'));
            });
    }

    /** Presentation site (routes/site.php): props shared with every page, error pages included. */
    private function shareSiteProps(): void
    {
        Inertia::share([
            'site' => function () {
                // No store: the APK and the Windows installer of the latest GitHub releases (the APK
                // may also come from the .env). No web app any more (developer's request).
                $releases = app(LatestReleases::class);
                $apk = $releases->apk();
                $installer = $releases->windowsInstaller();

                return [
                    'url' => SiteController::siteUrl(),
                    'android' => [
                        'apkUrl' => $apk['url'] ?? null,
                        'apkSha256' => $apk['sha256'] ?? null,
                        'minimum' => config('vixeo.android.minimum'),
                    ],
                    'windows' => [
                        'installerUrl' => $installer['url'] ?? null,
                        'installerSha256' => $installer['sha256'] ?? null,
                        'minimum' => config('vixeo.windows.minimum'),
                    ],
                    'contactEmail' => config('vixeo.publisher.email') ?: null,
                    'publisher' => config('vixeo.publisher.name') ?: null,
                ];
            },
            'platform' => fn () => Platform::fromUserAgent(request()->userAgent())->value,
            'path' => fn () => '/'.ltrim(request()->path(), '/'),
        ]);
    }
}
