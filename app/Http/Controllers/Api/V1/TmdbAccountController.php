<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\V1\Tmdb\TmdbAccountResource;
use App\Models\Profile;
use App\Services\TmdbLinks;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Gate;

/**
 * TMDb account of a profile, to rate movies, TV shows and episodes (docs/compte-tmdb.md).
 * The same TMDb account can be linked to several profiles, of any Vixeo account.
 *
 * @tags Compte TMDb
 */
class TmdbAccountController extends Controller
{
    public function __construct(private TmdbLinks $links) {}

    /**
     * Linked TMDb account.
     *
     * `linked: false` (other fields null) when the profile has none.
     */
    public function show(Profile $profile): TmdbAccountResource
    {
        Gate::authorize('view', $profile);

        return new TmdbAccountResource($profile->tmdbLink);
    }

    /**
     * Start linking a TMDb account.
     *
     * Open `authorize_url` (or show it as a QR code on a TV): the person signs in to TMDb and
     * approves Vixeo, then the app polls POST …/tmdb/link/complete every `interval` seconds.
     * Linking again replaces the current account.
     */
    public function start(Profile $profile): JsonResponse
    {
        Gate::authorize('update', $profile);
        ['authorize_url' => $url, 'expires_in' => $expiresIn] = $this->links->start($profile);

        return response()->json([
            /** TMDb page where the person approves Vixeo. */
            'authorize_url' => $url,
            /** Seconds before this page expires. */
            'expires_in' => $expiresIn,
            /** Seconds between two polls. */
            'interval' => TmdbLinks::INTERVAL_SECONDS,
        ]);
    }

    /**
     * Finish linking a TMDb account.
     *
     * 202 `{"status":"pending"}` until the person approved Vixeo on TMDb, then 200 with the
     * account. 410 when the approval page expired: start again.
     */
    public function complete(Profile $profile): JsonResponse
    {
        Gate::authorize('update', $profile);
        $result = $this->links->complete($profile);

        if ($result['status'] === 'linked') {
            return response()->json(['status' => 'linked', 'data' => new TmdbAccountResource($result['link'])]);
        }

        if ($result['status'] === 'pending') {
            return response()->json(['status' => 'pending'], Response::HTTP_ACCEPTED);
        }

        return response()->json([
            'status' => 'expired',
            'message' => __('The TMDb approval page has expired: start again.'),
        ], Response::HTTP_GONE);
    }

    /**
     * Unlink the TMDb account.
     *
     * The ratings stay on TMDb; Vixeo's session on the account is ended.
     */
    public function destroy(Profile $profile): Response
    {
        Gate::authorize('update', $profile);
        $this->links->unlink($profile);

        return response()->noContent();
    }
}
