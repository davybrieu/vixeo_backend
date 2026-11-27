<?php

use Illuminate\Support\Facades\Route;

/* Root of the API host, outside /api/v1. */
Route::get('/', fn () => response()->json([
    'name' => config('app.name'),
    'api' => url('/api/v1'),
]));

// The API is not meant for search engines.
Route::get('/robots.txt', fn () => response("User-agent: *\nDisallow: /\n", 200, ['Content-Type' => 'text/plain; charset=UTF-8']));
