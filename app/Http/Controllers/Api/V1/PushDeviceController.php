<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Middleware\ResolveProfile;
use App\Http\Requests\Api\V1\RegisterPushDeviceRequest;
use App\Http\Resources\V1\PushDeviceResource;
use App\Services\Push\PushDevices;
use App\Services\Push\PushMessage;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Laravel\Sanctum\PersonalAccessToken;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;

/**
 * Push notifications of the signed-in app (docs/notifications.md): the app registers its
 * Firebase Cloud Messaging token with the active profile (`X-Profile-Id`) and whether it wants
 * the new episodes of the profile's « Ma liste » shows. One registration per signed-in app:
 * signing out removes it.
 *
 * @tags Notifications
 */
class PushDeviceController extends Controller
{
    public function __construct(private PushDevices $devices) {}

    /**
     * This app's registration.
     *
     * `available`: false when the server sends no notification (not configured); `data` is null
     * when the app is not registered.
     */
    public function show(Request $request): JsonResponse
    {
        $device = $this->devices->current($this->access($request));

        return response()->json([
            'data' => $device === null ? null : new PushDeviceResource($device),
            /** Notifications sent by the server. */
            'available' => $this->devices->available(),
        ]);
    }

    /**
     * Register this app.
     *
     * At sign-in, at launch, when the token changes, when the profile in use changes and when
     * the new-episode alerts are turned on or off. Episodes out before the alerts are turned on
     * (or before the profile is used on the app) are not announced. 201 the first time, 200 after.
     */
    public function update(RegisterPushDeviceRequest $request): PushDeviceResource
    {
        $device = $this->devices->register(
            $request->user(),
            $this->access($request),
            ResolveProfile::profile($request),
            $request->string('token')->toString(),
            $request->string('platform')->toString(),
            $request->boolean('new_episodes'),
            $request->integer('utc_offset'),
            $request->has('social') ? $request->boolean('social') : null,
        );

        return new PushDeviceResource($device);
    }

    /**
     * Unregister this app.
     */
    public function destroy(Request $request): Response
    {
        $this->devices->forget($this->access($request));

        return response()->noContent();
    }

    /**
     * Send a test notification to this app.
     *
     * 409 when the app is not registered (or no longer known by FCM), 503 when the server sends
     * no notification or FCM refuses.
     */
    public function test(Request $request): JsonResponse
    {
        abort_unless($this->devices->available(), 503, __('Notifications are not available right now.'));
        $device = $this->devices->current($this->access($request));
        abort_if($device === null, 409, __('This device is not registered for notifications.'));

        $report = $this->devices->send(collect([$device]), new PushMessage(
            title: __('Test notification'),
            body: __('Vixeo notifications work on this device.'),
            path: '/profil/notifications',
            tag: 'test',
        ));
        abort_if($report['removed'] > 0, 409, __('This device is not registered for notifications.'));
        abort_if($report['sent'] === 0, 503, __('The notification could not be sent.'));

        return response()->json(['message' => __('Test notification sent.')]);
    }

    private function access(Request $request): PersonalAccessToken
    {
        $token = $request->user()->currentAccessToken();
        if (! $token instanceof PersonalAccessToken) {
            throw new ConflictHttpException(__('Notifications need an app signed in with a token.'));
        }

        return $token;
    }
}
