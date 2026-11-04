<?php

namespace App\Http\Middleware;

use App\Models\Profile;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

/**
 * Reads the active profile from the `X-Profile-Id` header (the app sends it on every
 * catalog request) and makes it available through ResolveProfile::profile($request).
 */
class ResolveProfile
{
    public const HEADER = 'X-Profile-Id';

    public function handle(Request $request, Closure $next): Response
    {
        $id = $request->header(self::HEADER);

        if ($id !== null && $id !== '') {
            $profile = ctype_digit($id) ? Profile::find((int) $id) : null;

            if ($profile === null || $profile->user_id !== $request->user()?->id) {
                throw new AccessDeniedHttpException;
            }

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
