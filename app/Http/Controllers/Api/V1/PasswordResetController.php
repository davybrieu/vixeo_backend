<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\ForgotPasswordRequest;
use App\Http\Requests\Api\V1\ResetPasswordRequest;
use App\Http\Resources\V1\UserResource;
use App\Services\PasswordResets;
use Illuminate\Http\JsonResponse;

/**
 * "Mot de passe oublié": a code sent by e-mail, then a new password (docs/compte.md).
 *
 * @tags Authentification
 */
class PasswordResetController extends Controller
{
    public function __construct(private readonly PasswordResets $resets) {}

    /**
     * Send a password reset code.
     *
     * E-mails a 6-digit code, valid 15 minutes, if an account uses this address. The answer is the
     * same whether or not the address has an account.
     *
     * @unauthenticated
     */
    public function forgot(ForgotPasswordRequest $request): JsonResponse
    {
        $this->resets->send($request->validated('email'));

        return response()->json([
            'message' => __('If an account uses this address, a code has been sent to it.'),
            /** Minutes before the code expires. */
            'expires_in_minutes' => PasswordResets::TTL_MINUTES,
        ]);
    }

    /**
     * Choose a new password with the code.
     *
     * Signs out every device of the account, then returns a token for this one, like `login`.
     * 5 wrong codes invalidate the code.
     *
     * @unauthenticated
     */
    public function reset(ResetPasswordRequest $request): JsonResponse
    {
        $user = $this->resets->reset(
            $request->validated('email'),
            $request->validated('code'),
            $request->validated('password'),
        );

        return response()->json([
            /** Bearer token to send in the `Authorization` header. */
            'token' => (string) $user->createToken($request->validated('device_name'))->plainTextToken,
            'user' => new UserResource($user),
        ]);
    }
}
