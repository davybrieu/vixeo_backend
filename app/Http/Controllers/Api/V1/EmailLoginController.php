<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\V1\UserResource;
use App\Services\Accounts;
use App\Services\EmailLogins;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\ValidationException;

class EmailLoginController extends Controller
{
    /** Send a six-digit code without opening a browser or an API web page. */
    public function sendCode(Request $request, EmailLogins $logins): JsonResponse
    {
        $data = $request->validate(['email' => ['required', 'email', 'max:255']]);
        $email = mb_strtolower(trim($data['email']));
        $emailKey = 'email-login-send:'.hash('sha256', $email);
        $ipKey = 'email-login-send-ip:'.hash('sha256', (string) $request->ip());

        if (RateLimiter::tooManyAttempts($emailKey, 3) || RateLimiter::tooManyAttempts($ipKey, 10)) {
            throw ValidationException::withMessages(['email' => ['Trop de demandes. Réessayez dans quelques minutes.']]);
        }

        RateLimiter::hit($emailKey, EmailLogins::OTP_TTL);
        RateLimiter::hit($ipKey, EmailLogins::OTP_TTL);
        $logins->sendCode($email);

        return response()->json([
            'message' => 'Si cette adresse peut être utilisée, un code de connexion vient de lui être envoyé.',
            'expires_in_seconds' => EmailLogins::OTP_TTL,
        ]);
    }

    /** Verify the six-digit code and issue the Sanctum token directly to the app. */
    public function verifyCode(Request $request, EmailLogins $logins, Accounts $accounts): JsonResponse
    {
        $data = $request->validate([
            'email' => ['required', 'email', 'max:255'],
            'code' => ['required', 'digits:6'],
            'device_name' => ['required', 'string', 'max:100'],
        ]);
        $email = mb_strtolower(trim($data['email']));
        $key = 'email-login-verify:'.hash('sha256', $email.'|'.$request->ip());
        $ipKey = 'email-login-verify-ip:'.hash('sha256', (string) $request->ip());

        if (RateLimiter::tooManyAttempts($key, 5) || RateLimiter::tooManyAttempts($ipKey, 20)) {
            throw ValidationException::withMessages(['code' => ['Trop de tentatives. Réessayez dans 10 minutes.']]);
        }

        $result = $logins->verifyCode($email, $data['code'], $accounts);
        if ($result === null) {
            RateLimiter::hit($key, EmailLogins::OTP_TTL);
            RateLimiter::hit($ipKey, EmailLogins::OTP_TTL);
            throw ValidationException::withMessages(['code' => ['Code incorrect ou expiré. Demandez un nouveau code.']]);
        }

        RateLimiter::clear($key);
        $user = $result['user'];

        return response()->json([
            'token' => (string) $user->createToken($data['device_name'])->plainTextToken,
            'user' => new UserResource($user),
            'created' => (bool) $result['created'],
            'needs_profile_setup' => $user->profiles()->first()?->registration_completed_at === null,
        ]);
    }
}
