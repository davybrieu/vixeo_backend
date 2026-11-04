<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\ProfileRequest;
use App\Http\Requests\Api\V1\VerifyPinRequest;
use App\Http\Resources\V1\ProfileResource;
use App\Models\Profile;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

/**
 * @tags Profils
 */
class ProfileController extends Controller
{
    /**
     * List the account's profiles ("Qui regarde ?").
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        return ProfileResource::collection($request->user()->profiles);
    }

    /**
     * Create a profile.
     *
     * An account can have at most 5 profiles.
     */
    public function store(ProfileRequest $request): JsonResponse
    {
        Gate::authorize('create', Profile::class);

        $profile = $request->user()->profiles()->create($request->validated());

        return (new ProfileResource($profile))
            ->response()
            ->setStatusCode(Response::HTTP_CREATED);
    }

    /**
     * Get a profile.
     */
    public function show(Profile $profile): ProfileResource
    {
        Gate::authorize('view', $profile);

        return new ProfileResource($profile);
    }

    /**
     * Update a profile.
     */
    public function update(ProfileRequest $request, Profile $profile): ProfileResource
    {
        Gate::authorize('update', $profile);

        $profile->update($request->validated());

        return new ProfileResource($profile);
    }

    /**
     * Delete a profile.
     *
     * The last profile of an account cannot be deleted.
     */
    public function destroy(Profile $profile): Response
    {
        Gate::authorize('delete', $profile);

        $profile->delete();

        return response()->noContent();
    }

    /**
     * Check a profile's PIN.
     *
     * Returns 204 if the PIN is correct, 422 otherwise.
     */
    public function verifyPin(VerifyPinRequest $request, Profile $profile): Response
    {
        Gate::authorize('view', $profile);

        if (! $profile->checkPin($request->validated('pin'))) {
            throw ValidationException::withMessages([
                'pin' => ['Code PIN incorrect.'],
            ]);
        }

        return response()->noContent();
    }
}
