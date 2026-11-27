<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Middleware\ResolveProfile;
use App\Http\Requests\Api\V1\RegisterPushDeviceRequest;
use App\Http\Resources\V1\PushDeviceResource;
use App\Models\PushDevice;
use App\Services\Push\PushDevices;
use App\Services\Push\PushMessage;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Laravel\Sanctum\PersonalAccessToken;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;

/**
 * Push notifications of the signed-in app (docs/notifications.md): the app registers its
 * Firebase installation ID (or, before firebase_messaging 17, its registration token) with the
 * account's single profile and whether it wants new episodes of its
 * « À voir » shows. Windows has no Firebase: its registration has no ID and its notifications
 * come by the real-time channel `push-devices.{id}` (Reverb) while the app runs. One registration
 * per signed-in app: signing out removes it.
 *
 * @tags Notifications
 */
class PushDeviceController extends Controller
{
    public function __construct(private PushDevices $devices) {}

    /**
     * This app's registration.
     *
     * `available`: false when the server sends no Firebase notification (not configured);
     * `realtime`: the same for the real-time channel of Windows; `data` is null when the app is not
     * registered.
     */
    public function show(Request $request): JsonResponse
    {
        $device = $this->devices->current($this->access($request));

        return response()->json([
            'data' => $device === null ? null : new PushDeviceResource($device),
            /** Firebase notifications sent by the server (Android, iOS). */
            'available' => $this->devices->supports(PushDevice::FCM_FID),
            /** Notifications of the real-time channel sent by the server (Windows). */
            'realtime' => $this->devices->supports(PushDevice::REALTIME),
        ]);
    }

    /**
     * Register this app.
     *
     * At sign-in, at launch, when the installation ID changes and
     * when the new-episode alerts are turned on or off. `fid` (or `token` for the older apps) on
     * Android and iOS, neither on Windows. Episodes out before the alerts are turned on (or before
     * the profile is used on the app) are not announced. 201 the first time, 200 after.
     */
    public function update(RegisterPushDeviceRequest $request): PushDeviceResource
    {
        $device = $this->devices->register(
            $request->user(),
            $this->access($request),
            ResolveProfile::profile($request),
            $request->string('platform')->toString(),
            $request->target(),
            $request->registration(),
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
     * no notification, FCM refuses or (Windows) the app does not listen to its real-time channel.
     */
    public function test(Request $request): JsonResponse
    {
        abort_unless($this->devices->available(), 503, __('Notifications are not available right now.'));
        $device = $this->devices->current($this->access($request));
        abort_if($device === null, 409, __('This device is not registered for notifications.'));
        abort_unless($this->devices->supports($device->target), 503, __('Notifications are not available right now.'));

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
