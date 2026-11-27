<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\V1\UserResource;
use App\Models\User;
use App\Services\EmailLogins;
use App\Services\SocialLogin\SocialLogins;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\ValidationException;

class EmailLoginController extends Controller
{
    public function request(Request $request, EmailLogins $logins): JsonResponse
    {
        $data = $request->validate([
            'email' => ['required', 'email', 'max:255'],
            'redirect_uri' => ['required', 'string', 'max:255'],
            'code_challenge' => ['required', 'string', 'regex:/^[A-Za-z0-9_-]{43}$/'],
        ]);

        abort_unless(SocialLogins::validRedirect($data['redirect_uri']), 422, 'Adresse de retour invalide.');

        $key = 'email-login-send:'.sha1(mb_strtolower($data['email']));
        if (RateLimiter::tooManyAttempts($key, 3)) {
            throw ValidationException::withMessages(['email' => ['Trop de demandes. Réessayez dans quelques minutes.']]);
        }
        RateLimiter::hit($key, EmailLogins::LINK_TTL);

        $user = User::query()->whereRaw('lower(email) = ?', [mb_strtolower($data['email'])])->first();
        if ($user !== null) {
            $logins->send($user, $data['redirect_uri'], $data['code_challenge']);
        }

        return response()->json([
            'message' => 'Si un compte utilise cette adresse, un lien de connexion vient de lui être envoyé.',
            'expires_in_minutes' => EmailLogins::LINK_TTL / 60,
        ]);
    }

    public function waiting(): Response
    {
        return response('<!doctype html><html lang="fr"><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Connexion Vixeo</title><body style="font-family:system-ui;background:#080b09;color:#fff;padding:3rem;text-align:center"><h1>Consultez votre e-mail</h1><p>Ouvrez le lien envoyé par Vixeo. Cette fenêtre se fermera automatiquement après la connexion.</p></body></html>')
            ->header('Content-Type', 'text/html; charset=UTF-8');
    }

    public function consume(Request $request, EmailLogins $logins): Response
    {
        $token = (string) $request->query('token', '');
        $redirect = $token === '' ? null : $logins->consume($token);

        if ($redirect === null) {
            return response('<!doctype html><html lang="fr"><meta charset="utf-8"><title>Vixeo</title><body>Ce lien de connexion est invalide, expiré ou déjà utilisé. Demandez-en un nouveau dans Vixeo.</body></html>', 410)
                ->header('Content-Type', 'text/html; charset=UTF-8');
        }

        return response('', Response::HTTP_SEE_OTHER)->header('Location', $redirect);
    }

    public function token(Request $request, EmailLogins $logins): JsonResponse
    {
        $data = $request->validate([
            'code' => ['required', 'string', 'max:100'],
            'code_verifier' => ['required', 'string', 'min:43', 'max:128'],
            'device_name' => ['required', 'string', 'max:100'],
        ]);

        $user = $logins->redeem($data['code'], $data['code_verifier']);
        if ($user === null) {
            throw ValidationException::withMessages(['code' => ['Ce lien de connexion a expiré. Demandez-en un nouveau.']]);
        }

        return response()->json([
            'token' => (string) $user->createToken($data['device_name'])->plainTextToken,
            'user' => new UserResource($user),
        ]);
    }
}
