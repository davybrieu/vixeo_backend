<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\V1\UserResource;
use App\Services\Accounts;
use App\Services\EmailLogins;
use App\Services\SocialLogin\SocialLogins;
use App\Models\User;
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

    /** Legacy link endpoint retained for older installed app versions; new apps use OTP. */
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
    public function verifyCode(Request $request, EmailLogins $logins): JsonResponse
    {
        $data = $request->validate([
            'email' => ['required', 'email', 'max:255'],
            'code' => ['required', 'digits:6'],
            'device_name' => ['required', 'string', 'max:100'],
        ]);
        $email = mb_strtolower(trim($data['email']));
        $key = 'email-login-verify:'.hash('sha256', $email);
        $ipKey = 'email-login-verify-ip:'.hash('sha256', (string) $request->ip());

        if (RateLimiter::tooManyAttempts($key, 5) || RateLimiter::tooManyAttempts($ipKey, 20)) {
            throw ValidationException::withMessages(['code' => ['Trop de tentatives. Réessayez dans 10 minutes.']]);
        }

        $result = $logins->verifyCode($email, $data['code']);
        if ($result === null) {
            RateLimiter::hit($key, EmailLogins::OTP_TTL);
            RateLimiter::hit($ipKey, EmailLogins::OTP_TTL);
            throw ValidationException::withMessages(['code' => ['Code incorrect ou expiré. Demandez un nouveau code.']]);
        }

        RateLimiter::clear($key);
        if (isset($result['registration_ticket'])) {
            return response()->json([
                'registration_required' => true,
                'registration_ticket' => $result['registration_ticket'],
                'created' => true,
            ]);
        }

        $user = $result['user'];

        return response()->json([
            'token' => (string) $user->createToken($data['device_name'])->plainTextToken,
            'user' => new UserResource($user),
            'created' => false,
            'needs_profile_setup' => false,
        ]);
    }
    /** Create the account and its one and only profile after e-mail verification. */
    public function completeRegistration(Request $request, EmailLogins $logins, Accounts $accounts, AvatarCatalog $avatars, CoverCatalog $covers): JsonResponse
    {
        $data = $request->validate([
            'registration_ticket' => ['required', 'string', 'min:40', 'max:100'],
            'device_name' => ['required', 'string', 'max:100'],
            'name' => ['required', 'string', 'max:50'],
            'username' => ['required', 'string', 'min:3', 'max:30', 'regex:/^[a-zA-Z0-9_]+$/', Rule::notIn(Usernames::RESERVED), 'unique:profiles,username'],
            'bio' => ['nullable', 'string', 'max:160'],
            'avatar' => ['required', 'string', Rule::in($avatars->ids())],
            'cover' => ['required', 'string', Rule::in($covers->ids())],
            'social_links' => ['nullable', 'array:'.implode(',', array_keys(SocialLinks::NETWORKS))],
            'social_links.*' => ['nullable', 'string', 'max:200'],
        ]);

        $links = [];
        foreach (($data['social_links'] ?? []) as $network => $value) {
            if ($value !== null && ! SocialLinks::valid($network, $value)) {
                throw ValidationException::withMessages(['social_links.'.$network => ['Lien de réseau social invalide.']]);
            }
            $links[$network] = $value === null ? null : SocialLinks::normalize($network, $value);
        }

        $ticket = $logins->consumeRegistrationTicket($data['registration_ticket']);
        if ($ticket === null) {
            throw ValidationException::withMessages(['registration_ticket' => ['Cette inscription a expiré. Recommencez la vérification de votre adresse e-mail.']]);
        }

        $email = (string) $ticket['email'];
        if (User::query()->whereRaw('lower(email) = ?', [mb_strtolower($email)])->exists()) {
            throw ValidationException::withMessages(['email' => ['Un compte existe déjà avec cette adresse. Connectez-vous.']]);
        }

        $user = $accounts->create($data['name'], $email, $data['username'], true, [
            'name' => $data['name'],
            'username' => mb_strtolower($data['username']),
            'bio' => $data['bio'] ?? null,
            'avatar' => $data['avatar'],
            'cover' => $data['cover'],
            'social_links' => $links,
        ]);

        return response()->json([
            'token' => (string) $user->createToken($data['device_name'])->plainTextToken,
            'user' => new UserResource($user),
            'created' => true,
            'needs_profile_setup' => false,
        ], 201);
    }

    /** Legacy PKCE exchange retained for email-change links and older installed apps. */
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
