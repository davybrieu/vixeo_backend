<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\V1\UserResource;
use App\Services\Accounts;
use App\Services\EmailLogins;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
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

    /** Browser waiting page used only for confirming a changed account e-mail address. */
    public function waiting(): Response
    {
        return response('<!doctype html><html lang="fr"><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Vixeo</title><body style="font-family:system-ui;background:#080b09;color:#fff;padding:3rem;text-align:center"><h1>Confirmation de votre adresse</h1><p>La confirmation vous ramènera dans Vixeo.</p></body></html>')
            ->header('Content-Type', 'text/html; charset=UTF-8');
    }

    /** Consume the one-time link used only for changing an account e-mail address. */
    public function consume(Request $request, EmailLogins $logins): Response
    {
        $token = (string) $request->query('token', '');
        $redirect = $token === '' ? null : $logins->consume($token);
        if ($redirect === null) {
            return response('Ce lien est invalide, expiré ou déjà utilisé. Demandez-en un nouveau dans Vixeo.', 410)
                ->header('Content-Type', 'text/plain; charset=UTF-8');
        }
        return response('', Response::HTTP_SEE_OTHER)->header('Location', $redirect);
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
