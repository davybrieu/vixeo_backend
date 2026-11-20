<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Profile;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Gate;

/**
 * Linking a TMDb account to a profile no longer exists (docs/avis.md): the reviews are kept by
 * Vixeo only. These two routes stay for the apps installed before, which still have a
 * « Connexions » page: no account is ever linked, and starting a link says why it can't.
 *
 * @tags Avis
 */
class TmdbAccountController extends Controller
{
    /**
     * Linked TMDb account (former apps).
     *
     * Always `linked: false`: a TMDb account can no longer be linked.
     *
     * @deprecated
     */
    public function show(Profile $profile): JsonResponse
    {
        Gate::authorize('view', $profile);

        return response()->json(['data' => [
            'linked' => false,
            'account_id' => null,
            'username' => null,
            'name' => null,
            'avatar_url' => null,
            'linked_at' => null,
        ]]);
    }

    /**
     * Start linking a TMDb account (former apps).
     *
     * Always 410: a TMDb account can no longer be linked; the reviews stay in Vixeo.
     *
     * @deprecated
     */
    public function start(Profile $profile): JsonResponse
    {
        Gate::authorize('update', $profile);

        return response()->json([
            'message' => __('Linking a TMDb account is no longer offered: your reviews stay in Vixeo.'),
        ], Response::HTTP_GONE);
    }
}
