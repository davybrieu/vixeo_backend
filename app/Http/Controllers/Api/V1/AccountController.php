<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\DeleteAccountRequest;
use App\Http\Requests\Api\V1\UpdateEmailRequest;
use App\Http\Requests\Api\V1\UpdatePasswordRequest;
use App\Http\Resources\V1\DeviceResource;
use App\Http\Resources\V1\UserResource;
use App\Notifications\AccountDataExport;
use App\Services\AccountExport;
use App\Services\Messaging\Conversations;
use App\Services\ProfilePhotos;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;
use Laravel\Sanctum\PersonalAccessToken;

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
     * device. Cannot be undone. The password is sent in the body (POST, not DELETE: never a
     * secret in a URL).
     */
    public function destroy(DeleteAccountRequest $request): Response
    {
        $user = $request->user();

        $this->checkPassword($user->password, $request->validated('password'), 'password');

        // The files of the profiles' messages and their photos (the rows go with the profiles).
        app(Conversations::class)->forgetProfiles($user->profiles()->pluck('id')->map(fn ($id) => (int) $id)->all());
        app(ProfilePhotos::class)->forget($user->profiles()->pluck('photo_path')->all());

        DB::transaction(function () use ($user) {
            $user->tokens()->delete();
            DB::table('password_reset_tokens')->where('email', $user->email)->delete();
            // Profiles, their items and pending TV sign-ins go with it (foreign keys).
            $user->delete();
        });

        return response()->noContent();
    }

    /**
     * Change the e-mail address.
     *
     * Confirmed with the account password. The address is used to sign in and to receive the
     * password reset codes; a pending reset code for the old address is cancelled. 5 tries per
     * minute (wrong password: 422).
     */
    public function updateEmail(UpdateEmailRequest $request): UserResource
    {
        $user = $request->user();
        $this->checkPassword($user->password, $request->validated('password'), 'password');

        $email = $request->validated('email');
        if ($email !== $user->email) {
            DB::transaction(function () use ($user, $email) {
                DB::table('password_reset_tokens')->where('email', $user->email)->delete();
                $user->forceFill(['email' => $email, 'email_verified_at' => null])->save();
            });
        }

        return new UserResource($user->fresh());
    }

    /**
     * Change the password.
     *
     * Confirmed with the current password. Every other device is signed out; this one stays
     * signed in. 5 tries per minute (wrong password: 422).
     */
    public function updatePassword(UpdatePasswordRequest $request): Response
    {
        $user = $request->user();
        $this->checkPassword($user->password, $request->validated('current_password'), 'current_password');

        DB::transaction(function () use ($user, $request) {
            $user->forceFill(['password' => $request->validated('password')])->save();
            $current = $user->currentAccessToken();
            $user->tokens()
                ->when($current instanceof PersonalAccessToken, fn ($q) => $q->whereKeyNot($current->getKey()))
                ->delete();
            DB::table('password_reset_tokens')->where('email', $user->email)->delete();
        });

        return response()->noContent();
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

    /** 422 on [$field] when [$password] is not the account's. */
    private function checkPassword(string $hash, string $password, string $field): void
    {
        if (! Hash::check($password, $hash)) {
            throw ValidationException::withMessages([$field => [__('Incorrect password.')]]);
        }
    }
}
