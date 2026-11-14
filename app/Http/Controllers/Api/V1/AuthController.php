<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\LoginRequest;
use App\Http\Requests\Api\V1\RegisterRequest;
use App\Http\Resources\V1\UserResource;
use App\Models\User;
use App\Models\Profile;
use App\Services\AvatarCatalog;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * @tags Authentification
 */
class AuthController extends Controller
{
    /**
     * Create an account.
     *
     * Also creates a first profile named after the user (with an avatar of the catalog, at random)
     * and returns an API token.
     *
     * @unauthenticated
     */
    public function register(RegisterRequest $request, AvatarCatalog $avatars): JsonResponse
    {
        $user = DB::transaction(function () use ($request, $avatars) {
            $user = User::create($request->safe()->only(['name', 'email', 'password']));
            // The first profile gets an avatar of the catalog, at random.
            $user->profiles()->create([\n                'name' => $user->name,\n                'username' => $request->validated('username') ?? $this->uniqueUsername($user->name),\n                'avatar' => $avatars->randomId(),\n            ]);

            return $user;
        });

        return $this->tokenResponse($user, $request->validated('device_name'), Response::HTTP_CREATED);
    }

    /**
     * Log in and get an API token.
     *
     * @unauthenticated
     */
    public function login(LoginRequest $request): JsonResponse
    {
        $user = User::where('email', $request->validated('email'))->first();

        if (! $user || ! Hash::check($request->validated('password'), $user->password)) {
            throw ValidationException::withMessages([
                'email' => [__('auth.failed')],
            ]);
        }

        return $this->tokenResponse($user, $request->validated('device_name'));
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

    private function uniqueUsername(string $name): string
    {
        $base = Str::limit(Str::lower(Str::slug($name, '_')) ?: 'membre', 22, '');
        do {
            $username = $base.'_'.Str::lower(Str::random(6));
        } while (Profile::query()->where('username', $username)->exists());

        return $username;
    }

    private function tokenResponse(User $user, string $deviceName, int $status = Response::HTTP_OK): JsonResponse
    {
        $token = (string) $user->createToken($deviceName)->plainTextToken;

        return response()->json([
            /** Bearer token to send in the `Authorization` header. */
            'token' => $token,
            'user' => new UserResource($user),
        ], $status);
    }
}
