<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\DeleteAccountRequest;
use App\Http\Resources\V1\DeviceResource;
use App\Notifications\AccountDataExport;
use App\Services\AccountExport;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

/**
 * The account's data and devices (docs/compte.md).
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

        if (! Hash::check($request->validated('password'), $user->password)) {
            throw ValidationException::withMessages(['password' => [__('Incorrect password.')]]);
        }

        DB::transaction(function () use ($user) {
            $user->tokens()->delete();
            DB::table('password_reset_tokens')->where('email', $user->email)->delete();
            // Profiles, their items and pending TV sign-ins go with it (foreign keys).
            $user->delete();
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
}
