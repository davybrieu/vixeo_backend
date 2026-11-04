<?php

namespace App\Providers;

use Dedoc\Scramble\Scramble;
use Dedoc\Scramble\Support\Generator\OpenApi;
use Dedoc\Scramble\Support\Generator\SecurityScheme;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        Password::defaults(fn () => Password::min(8));

        RateLimiter::for('auth', fn (Request $request) => Limit::perMinute(10)->by($request->ip()));

        RateLimiter::for('pin', fn (Request $request) => Limit::perMinute(5)->by(
            $request->user()?->id.'|'.$request->route()?->originalParameter('profile'),
        ));

        // Catalog screens fire several requests at once (home, details, seasons…).
        RateLimiter::for('catalog', fn (Request $request) => Limit::perMinute(240)->by($request->user()?->id ?: $request->ip()));

        Scramble::configure()
            ->routes(fn ($route) => str_starts_with($route->uri, 'api/v1'))
            ->withDocumentTransformers(function (OpenApi $openApi) {
                $openApi->secure(SecurityScheme::http('bearer'));
            });
    }
}
