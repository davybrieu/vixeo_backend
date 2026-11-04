<?php

use Illuminate\Auth\AuthenticationException;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Symfony\Component\HttpKernel\Exception\MethodNotAllowedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\Exception\TooManyRequestsHttpException;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        //
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );

        // Framework error messages are hard-coded in English: translate them for the API
        // (lang/fr.json). Validation errors are already translated by the validator.
        $exceptions->render(function (AuthenticationException $e, Request $request) {
            if ($request->is('api/*')) {
                return response()->json(['message' => __('Unauthenticated.')], 401);
            }
        });

        $exceptions->render(function (HttpExceptionInterface $e, Request $request) {
            if (! $request->is('api/*')) {
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
            if (! $request->is('api/*') || config('app.debug')
                || $e instanceof ValidationException || $e instanceof HttpResponseException) {
                return null;
            }

            return response()->json(['message' => __('Server Error.')], 500);
        });
    })->create();
