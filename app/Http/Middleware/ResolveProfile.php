<?php

namespace App\Http\Middleware;

use App\Models\Profile;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/** Resolves the one profile belonging to the authenticated account. */
class ResolveProfile
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user('sanctum') ?? $request->user();
        $profile = $user?->profiles()->first();

        if ($profile !== null) {
            $request->attributes->set('profile', $profile);
        }

        return $next($request);
    }

    public static function profile(Request $request): ?Profile
    {
        $profile = $request->attributes->get('profile');

        return $profile instanceof Profile ? $profile : null;
    }
}
