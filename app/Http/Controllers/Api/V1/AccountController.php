<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\DeleteAccountRequest;
use App\Http\Requests\Api\V1\UpdateEmailRequest;
use App\Http\Resources\V1\DeviceResource;
use App\Http\Resources\V1\UserResource;
use App\Notifications\AccountDataExport;
use App\Services\AccountExport;
use App\Services\Messaging\Conversations;
use App\Services\ProfileCovers;
use App\Services\ProfilePhotos;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;

/**
 * The account's address, password, data and devices (docs/compte.md).
 *
 * @tags Compte
 */
class AccountController extends Controller
{
    /**
     * Delete the account.
     *
     * Deletes the account, its profiles, their list, progress and history, and signs out every
     * device. Cannot be undone; the active authenticated session is the confirmation.
     */
    public function destroy(DeleteAccountRequest $request): Response
    {
        $user = $request->user();


        // The files of the profiles' messages, their photos and covers (the rows go with the profiles).
        app(Conversations::class)->forgetProfiles($user->profiles()->pluck('id')->map(fn ($id) => (int) $id)->all());
        app(ProfilePhotos::class)->forget($user->profiles()->pluck('photo_path')->all());
        app(ProfileCovers::class)->forget($user->profiles()->pluck('cover_path')->all());

        DB::transaction(function () use ($user) {
            $user->tokens()->delete();
            // Profiles, their items and pending TV sign-ins go with it (foreign keys).
            $user->delete();
        });

        return response()->noContent();
    }

    /**
     * Change the e-mail address.
     *
     * The new address becomes the account's sign-in address and is verified through its next
     * one-time email sign-in link.
     */
    public function updateEmail(UpdateEmailRequest $request): UserResource
    {
        $user = $request->user();

        $email = $request->validated('email');
        if ($email !== $user->email) {
            DB::transaction(function () use ($user, $email) {
                $user->forceFill(['email' => $email, 'email_verified_at' => null])->save();
            });
        }

        return new UserResource($user->fresh());
    }

    /**
     * E-mail a copy of the account's data.
     *
     * Sends a JSON file with the account, profiles, list, progress and devices to the account's
     * address (GDPR right of access and portability). Limited to 3 per hour.
     */
    public function export(Request $request, AccountExport $export): JsonResponse
    {
        $user = $request->user();
        $user->notify(new AccountDataExport($export->build($user)));

        return response()->json([
            'message' => __('Your data has been sent to :email.', ['email' => $user->email]),
        ], Response::HTTP_ACCEPTED);
    }

    /**
     * List the signed-in devices.
     *
     * One entry per API token, most recently used first.
     */
    public function devices(Request $request): AnonymousResourceCollection
    {
        $tokens = $request->user()->tokens()
            ->orderByRaw('last_used_at is null')
            ->orderByDesc('last_used_at')
            ->orderByDesc('id')
            ->get();

        return DeviceResource::collection($tokens);
    }

    /**
     * Sign out a device.
     *
     * Revokes one of the account's tokens (a TV, a phone…). To sign out the current device, use
     * `auth/logout`.
     */
    public function revokeDevice(Request $request, int $device): Response
    {
        $request->user()->tokens()->whereKey($device)->firstOrFail()->delete();

        return response()->noContent();
    }

}
