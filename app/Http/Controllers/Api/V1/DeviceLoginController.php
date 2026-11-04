<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\DeviceCodeRequest;
use App\Http\Requests\Api\V1\DevicePollRequest;
use App\Http\Requests\Api\V1\DeviceStartRequest;
use App\Http\Resources\V1\UserResource;
use App\Services\DeviceLogins;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;

/**
 * Sign a TV in with a QR code: the TV shows a code, a signed-in phone approves it.
 * See docs/connexion-tv.md.
 *
 * @tags Authentification
 */
class DeviceLoginController extends Controller
{
    public function __construct(private DeviceLogins $logins) {}

    /**
     * Start a TV sign-in.
     *
     * Returns the code to show (and to put in the QR code: `verification_url_complete`) and
     * the secret `device_code` the TV polls with every `interval` seconds.
     *
     * @unauthenticated
     */
    public function start(DeviceStartRequest $request): JsonResponse
    {
        ['login' => $login, 'device_code' => $deviceCode] = $this->logins->start($request->validated('device_name'));
        $userCode = DeviceLogins::format($login->user_code);
        $url = rtrim((string) config('app.web_url'), '/').'/tv';

        return response()->json([
            /** Secret, for POST /auth/device/token only. */
            'device_code' => $deviceCode,
            /** Code to show on the TV, e.g. "K7F-29Q". */
            'user_code' => $userCode,
            /** Page where the person types the code. */
            'verification_url' => $url,
            /** Same page with the code filled in: put it in the QR code. */
            'verification_url_complete' => $url.'?code='.$login->user_code,
            /** Seconds before the code expires. */
            'expires_in' => DeviceLogins::TTL_SECONDS,
            /** Seconds between two polls. */
            'interval' => DeviceLogins::INTERVAL_SECONDS,
        ]);
    }

    /**
     * Poll a TV sign-in.
     *
     * 202 `{"status":"pending"}` until the code is approved, then 200 with the token (once).
     * 410 when the code expired or was already used: start again.
     *
     * @unauthenticated
     */
    public function poll(DevicePollRequest $request): JsonResponse
    {
        $result = $this->logins->poll($request->validated('device_code'));

        if ($result['status'] === 'approved') {
            return response()->json([
                'status' => 'approved',
                'token' => (string) $result['token'],
                'user' => new UserResource($result['user']),
            ]);
        }

        if ($result['status'] === 'pending') {
            return response()->json(['status' => 'pending'], Response::HTTP_ACCEPTED);
        }

        return response()->json([
            'status' => 'expired',
            'message' => __('This code is invalid or has expired.'),
        ], Response::HTTP_GONE);
    }

    /**
     * Look up a TV code.
     *
     * Called by the signed-in phone before approving, to show which device asks.
     */
    public function lookup(DeviceCodeRequest $request): JsonResponse
    {
        $login = $this->logins->findPending($request->validated('user_code'));

        return response()->json([
            'user_code' => DeviceLogins::format($login->user_code),
            'device_name' => $login->device_name,
            'expires_at' => $login->expires_at,
        ]);
    }

    /**
     * Approve a TV code.
     *
     * The TV is then signed in to the account of the person approving.
     */
    public function approve(DeviceCodeRequest $request): Response
    {
        $this->logins->approve($request->user(), $request->validated('user_code'));

        return response()->noContent();
    }
}
