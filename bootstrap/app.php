<?php

use App\Http\Middleware\HandleInertiaRequests;
use App\Http\Middleware\SecurityHeaders;
use App\Support\Hosts;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Http\Request;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Support\Facades\Route;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Symfony\Component\HttpFoundation\Response;
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
        // One app, two hosts (config/vixeo.php, docs/deploiement.md): the API on the host of
        // API_URL (api.vixeo.app), the presentation site on the host of APP_URL (www.vixeo.app). Without domains
        // (local), one host serves both: the API under /api, the site everywhere else.
        then: function () {
            $api = Hosts::apiDomain();

            Route::middleware('api')->prefix('api')->domain($api)->group(base_path('routes/api.php'));

            if ($api !== null) {
                Route::domain($api)->group(base_path('routes/api-host.php'));
            }

            // vixeo.app → www.vixeo.app is a redirect of the Forge site (docs/deploiement.md).
            Route::middleware('site')->domain(Hosts::siteDomain())->group(base_path('routes/site.php'));
        },
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

        // The presentation site: no account, no form, so no session, no CSRF token and no cookie.
        $middleware->group('site', [
            SubstituteBindings::class,
            HandleInertiaRequests::class,
            SecurityHeaders::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        // Everything on the API host (and under /api) answers JSON, even an unknown path.
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => Hosts::isApi($request) || $request->expectsJson(),
        );

        // Framework error messages are hard-coded in English: translate them for the API
        // (lang/fr.json). Validation errors are already translated by the validator.
        $exceptions->render(function (AuthenticationException $e, Request $request) {
            if (Hosts::isApi($request)) {
                return response()->json(['message' => __('Unauthenticated.')], 401);
            }
        });

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

        // Site: error pages with the site's look (Inertia page `Error`). The debug page stays in
        // local for a 500, to see the exception.
        $exceptions->respond(function (Response $response, Throwable $e, Request $request) {
            $status = $response->getStatusCode();
            if (Hosts::isApi($request) || $request->expectsJson()
                || ! in_array($status, [403, 404, 405, 419, 429, 500, 503], true)
                || (app()->environment('local') && $status >= 500)) {
                return $response;
            }

            try {
                return Inertia::render('Error', ['status' => $status])
                    ->toResponse($request)
                    ->setStatusCode($status)
                    ->withHeaders(array_intersect_key($response->headers->all(), array_flip(['retry-after'])));
            } catch (Throwable) {
                // The error page itself failed (missing build…): keep the framework's response.
                return $response;
            }
        });
    })->create();
