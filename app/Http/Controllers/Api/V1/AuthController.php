<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\LoginRequest;
use App\Http\Requests\Api\V1\RegisterRequest;
use App\Http\Resources\V1\UserResource;
use App\Models\User;
use App\Services\Accounts;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

/**
 * @tags Authentification
 */
class AuthController extends Controller
{
    /**
     * Create an account.
     *
     * Also creates a first profile named after the user (with an avatar and a cover of the catalogs,
     * at random) and returns an API token.
     *
     * @unauthenticated
     */
    public function register(RegisterRequest $request, Accounts $accounts, EmailLogins $logins): JsonResponse
    {
        abort_unless(SocialLogins::validRedirect($request->validated('redirect_uri')), 422, 'Adresse de retour invalide.');

        $user = $accounts->create(
            $request->validated('name'),
            $request->validated('email'),
            null,
            $request->validated('username'),
        );
        $logins->send($user, $request->validated('redirect_uri'), $request->validated('code_challenge'));

        return response()->json([
            'message' => 'Votre compte a été créé. Consultez votre boîte e-mail pour vous connecter.',
            'expires_in_minutes' => EmailLogins::LINK_TTL / 60,
        ], Response::HTTP_CREATED);
    }

    /**
     * Log out (revoke the current token).
     */
    public function logout(Request $request): Response
    {
        $request->user()->currentAccessToken()->delete();

        return response()->noContent();
    }

    /**
     * Get the authenticated user.
     */
    public function me(Request $request): UserResource
    {
        return new UserResource($request->user());
    }

}
