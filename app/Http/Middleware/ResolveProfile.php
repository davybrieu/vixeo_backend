<?php

namespace App\\Http\\Middleware;

use App\\Models\\Profile;
use Closure;
use Illuminate\\Auth\\AuthenticationException;
use Illuminate\\Http\\Request;
use Symfony\\Component\\HttpFoundation\\Response;
use Symfony\\Component\\HttpKernel\\Exception\\AccessDeniedHttpException;

/**
 * Resolves the account's default profile for authenticated API requests.
 *
 * During the single-profile transition, older clients may still send X-Profile-Id.
 * When they do, it is validated as before. Requests without the header now use the
 * first profile automatically, so clients no longer need profile-selection state.
 */
class ResolveProfile
{
    public const HEADER = 'X-Profile-Id';

    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user('sanctum') ?? $request->user();
        $id = $request->header(self::HEADER);

        if ($id !== null && $id !== '') {
            if ($user === null && $request->bearerToken() !== null) {
                throw new AuthenticationException;
            }

            $profile = ctype_digit($id) ? Profile::query()->find((int) $id) : null;

            if ($profile === null || $profile->user_id !== $user?->id) {
                throw new AccessDeniedHttpException;
            }
        } else {
            // A profile-less authenticated account is invalid for profile-scoped endpoints.
            $profile = $user?->profiles()->first();
        }

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
