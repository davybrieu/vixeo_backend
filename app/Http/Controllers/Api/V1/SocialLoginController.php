<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\SocialLoginStartRequest;
use App\Http\Requests\Api\V1\SocialLoginTokenRequest;
use App\Http\Resources\V1\UserResource;
use App\Services\SocialLogin\SocialLogins;
use App\Services\SocialLogin\SocialProvider;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Validation\ValidationException;

/**
 * « Continuer avec Google / Apple »: the sign-in runs in a browser, the app gets its token with a
 * code and the secret it kept (docs/connexion-sociale.md, App\Services\SocialLogin\SocialLogins).
 *
 * @tags Authentification
 */
class SocialLoginController extends Controller
{
    public function __construct(private readonly SocialLogins $logins) {}

    /**
     * List the sign-in providers.
     *
     * `available`: the server has its settings; otherwise the app says the sign-in isn't
     * available yet, without opening the browser.
     *
     * @unauthenticated
     */
    public function index(): JsonResponse
    {
        return response()->json([
            'data' => array_values(array_map(fn (SocialProvider $provider) => [
                /** `google` or `apple`. */
                'provider' => $provider->name(),
                'name' => $provider->label(),
                'available' => $provider->available(),
            ], $this->logins->providers())),
        ]);
    }

    /**
     * Start a sign-in (in a browser).
     *
     * Opened by the app in a browser: sends it to Google or Apple. A provider the server isn't
     * set up for sends it straight back to `redirect_uri` with `?error=unavailable`.
     *
     * @unauthenticated
     */
    public function start(SocialLoginStartRequest $request, string $provider): RedirectResponse
    {
        return redirect()->away($this->logins->start(
            $this->logins->provider($provider),
            $request->validated('redirect_uri'),
            $request->validated('code_challenge'),
        ));
    }

    /**
     * Come back from Google or Apple (in the browser).
     *
     * Called by the provider (Google: GET; Apple: POST, `form_post`), never by the app. Sends the
     * browser back to the app's `redirect_uri` with `?code=` (5 minutes, once) or `?error=`
     * (`cancelled`, `failed`, `email`: no address, `unverified`: the provider didn't check the
     * address of an existing account). A sign-in that has expired gets a page saying so.
     *
     * @unauthenticated
     */
    public function callback(Request $request, string $provider): RedirectResponse|Response
    {
        $to = $this->logins->finish($this->logins->provider($provider), $request->all());
        if ($to === null) {
            return response()->view('auth.social-login-expired', status: Response::HTTP_GONE);
        }

        // 303: after Apple's POST, the browser comes back with a GET.
        return redirect()->away($to, Response::HTTP_SEE_OTHER);
    }

    /**
     * Get a token with the code.
     *
     * The code the browser brought back and the secret of the `code_challenge`. Same answer as
     * `auth/login`, plus `created` (a new account, with its first profile).
     *
     * @unauthenticated
     */
    public function token(SocialLoginTokenRequest $request): JsonResponse
    {
        $result = $this->logins->redeem($request->validated('code'), $request->validated('code_verifier'));
        if ($result === null) {
            throw ValidationException::withMessages(['code' => [__('This sign-in has expired. Try again.')]]);
        }

        $user = $result['user'];

        return response()->json([
            /** Bearer token to send in the `Authorization` header. */
            'token' => (string) $user->createToken($request->validated('device_name'))->plainTextToken,
            'user' => new UserResource($user),
            /** A new account (and its first profile). */
            'created' => (bool) $result['created'],
            'needs_profile_setup' => $user->profiles()->first()?->registration_completed_at === null,
        ]);
    }
}
