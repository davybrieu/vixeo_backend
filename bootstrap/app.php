<?php

use App\Http\Middleware\ResolveProfile;
use App\Support\Hosts;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Symfony\Component\HttpKernel\Exception\MethodNotAllowedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\Exception\TooManyRequestsHttpException;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        commands: __DIR__.'/../routes/console.php',
        // Health check (Forge, monitoring): answers on every host.
        health: '/up',
        // This Laravel application serves the API only.
        then: function () {
            $api = Hosts::apiDomain();

            Route::middleware('api')->prefix('api')->domain($api)->group(base_path('routes/api.php'));

            if ($api !== null) {
                Route::domain($api)->group(base_path('routes/api-host.php'));
            }
        },
    )
    ->withBroadcasting(
        __DIR__.'/../routes/channels.php',
        ['prefix' => 'api', 'middleware' => ['api', 'auth:sanctum', ResolveProfile::class]],
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // The API runs behind Cloudflare and Laravel Forge's Nginx, which terminate TLS. Trust
        // their X-Forwarded-* headers so route() builds https URLs: the live-TV proxy hands the
        // player an absolute URL on our own origin, and an http:// one is blocked as mixed content
        // by browsers and as cleartext traffic by a release Android build.
        $middleware->trustProxies(at: '*');

        // No sign-in page anywhere: an unauthenticated API call gets a JSON 401, even from a
        // browser (otherwise Laravel looks for a `login` route and fails with a 500).
        $middleware->redirectGuestsTo(fn () => null);


        $exceptions->render(function (HttpExceptionInterface $e, Request $request) {
            if (! Hosts::isApi($request)) {
                return null;
            }

            $message = match (true) {
                // Hides model class names ("No query results for model [App\Models\...]").
                $e instanceof NotFoundHttpException => __('Not Found.'),
                $e instanceof MethodNotAllowedHttpException => __('Method Not Allowed.'),
                $e instanceof TooManyRequestsHttpException => __(
                    'Too Many Attempts. Please try again in :seconds seconds.',
                    ['seconds' => $e->getHeaders()['Retry-After'] ?? 60],
                ),
                // Policy messages are already translated; only the default one is not.
                $e instanceof AccessDeniedHttpException => __($e->getMessage() ?: 'This action is unauthorized.'),
                default => $e->getMessage() !== '' ? __($e->getMessage()) : __('Server Error.'),
            };

            return response()->json(['message' => $message], $e->getStatusCode(), $e->getHeaders());
        });

        // Unexpected errors: generic French message, unless debugging (keep the details).
        $exceptions->render(function (Throwable $e, Request $request) {
            if (! Hosts::isApi($request) || config('app.debug')
                || $e instanceof ValidationException || $e instanceof HttpResponseException) {
                return null;
            }

            return response()->json(['message' => __('Server Error.')], 500);
        });

    })->create();
