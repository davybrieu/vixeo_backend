<?php

use App\\Http\\Middleware\\ResolveProfile;
use App\\Support\\Hosts;
use Illuminate\\Auth\\AuthenticationException;
use Illuminate\\Foundation\\Application;
use Illuminate\\Foundation\\Configuration\\Exceptions;
use Illuminate\\Foundation\\Configuration\\Middleware;
use Illuminate\\Http\\Exceptions\\HttpResponseException;
use Illuminate\\Http\\Request;
use Illuminate\\Support\\Facades\\Route;
use Illuminate\\Validation\\ValidationException;
use Symfony\\Component\\HttpKernel\\Exception\\AccessDeniedHttpException;
use Symfony\\Component\\HttpKernel\\Exception\\HttpExceptionInterface;
use Symfony\\Component\\HttpKernel\\Exception\\MethodNotAllowedHttpException;
use Symfony\\Component\\HttpKernel\\Exception\\NotFoundHttpException;
use Symfony\\Component\\HttpKernel\\Exception\\TooManyRequestsHttpException;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        commands: __DIR__.'/../routes/console.php',
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
        // Cloudflare and Forge terminate TLS; trust forwarded headers for absolute HTTPS URLs.
        $middleware->trustProxies(at: '*');

        // There is no browser login page: unauthenticated API requests return JSON 401.
        $middleware->redirectGuestsTo(fn () => null);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        // The backend is API-only: even unknown paths and browser requests receive JSON.
        $exceptions->shouldRenderJsonWhen(fn (Request $request) => true);

        $exceptions->render(function (AuthenticationException $e, Request $request) {
            return response()->json(['message' => __('Unauthenticated.')], 401);
        });

        $exceptions->render(function (HttpExceptionInterface $e, Request $request) {
            $message = match (true) {
                $e instanceof NotFoundHttpException => __('Not Found.'),
                $e instanceof MethodNotAllowedHttpException => __('Method Not Allowed.'),
                $e instanceof TooManyRequestsHttpException => __(
                    'Too Many Attempts. Please try again in :seconds seconds.',
                    ['seconds' => $e->getHeaders()['Retry-After'] ?? 60],
                ),
                $e instanceof AccessDeniedHttpException => __($e->getMessage() ?: 'This action is unauthorized.'),
                default => $e->getMessage() !== '' ? __($e->getMessage()) : __('Server Error.'),
            };

            return response()->json(['message' => $message], $e->getStatusCode(), $e->getHeaders());
        });

        $exceptions->render(function (Throwable $e, Request $request) {
            if (config('app.debug') || $e instanceof ValidationException || $e instanceof HttpResponseException) {
                return null;
            }

            return response()->json(['message' => __('Server Error.')], 500);
        });
    })->create();
