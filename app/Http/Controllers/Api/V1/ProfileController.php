<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\ProfileRequest;
use App\Http\Resources\V1\ProfileResource;
use App\Models\Profile;
use App\Services\Accounts;
use App\Services\AvatarCatalog;
use App\Services\CoverCatalog;
use App\Services\Social\Follows;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Gate;

/**
 * @tags Profil
 */
class ProfileController extends Controller
{
    /** Return the account's single profile as a one-item collection for existing clients. */
    public function index(Request $request): AnonymousResourceCollection
    {
        return ProfileResource::collection($request->user()->profiles);
    }

    /** Create the account's initial profile. Further profile creation is forbidden by ProfilePolicy. */
    public function store(ProfileRequest $request, AvatarCatalog $avatars, CoverCatalog $covers): JsonResponse
    {
        Gate::authorize('create', Profile::class);

        $data = $request->profileData();
        $data['username'] ??= Accounts::uniqueUsername($data['name']);
        $data['avatar'] ??= $avatars->randomId();
        $data['cover'] ??= $covers->randomId();
        $profile = $request->user()->profiles()->create($data);

        return (new ProfileResource($profile))->response()->setStatusCode(Response::HTTP_CREATED);
    }

    public function show(Profile $profile): ProfileResource
    {
        Gate::authorize('view', $profile);

        return new ProfileResource($profile);
    }

    public function update(ProfileRequest $request, Profile $profile, Follows $follows): ProfileResource
    {
        Gate::authorize('update', $profile);

        $wasPrivate = $profile->is_private;
        $profile->update($request->profileData());
        if ($wasPrivate && ! $profile->is_private) {
            $follows->acceptAll($profile);
        }

        return new ProfileResource($profile);
    }
}
