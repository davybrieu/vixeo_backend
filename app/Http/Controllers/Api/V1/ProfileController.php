<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\DeleteProfileRequest;
use App\Http\Requests\Api\V1\ProfileRequest;
use App\Http\Requests\Api\V1\VerifyPinRequest;
use App\Http\Resources\V1\ProfileResource;
use App\Models\Profile;
use App\Services\AvatarCatalog;
use Dedoc\Scramble\Attributes\HeaderParameter;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
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
     * An account can have at most 5 profiles. Requires `parental_pin` when parental control is on.
     * Without `avatar`, the profile gets one of the catalog at random.
     */
    public function store(ProfileRequest $request, AvatarCatalog $avatars): JsonResponse
    {
        Gate::authorize('create', Profile::class);

        $data = $request->profileData();
        $data['username'] ??= $this->uniqueUsername($data['name']);
        // No avatar chosen: one of the catalog, at random.
        $data['avatar'] ??= $avatars->randomId();
        $profile = $request->user()->profiles()->create($data);

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
     *
     * Changing restrictions (`is_kid`, `max_age_rating`, `allow_unrated`, `locked_sources`) requires
     * `parental_pin` when parental control is on. Changing or removing the profile PIN requires
     * `current_pin` (or `parental_pin`).
     */
    public function update(ProfileRequest $request, Profile $profile): ProfileResource
    {
        Gate::authorize('update', $profile);

        $profile->update($request->profileData());

        return new ProfileResource($profile);
    }

    /**
     * Delete a profile.
     *
     * The last profile of an account cannot be deleted. Requires the parental PIN (header
     * `X-Parental-Pin`) when parental control is on.
     */
    #[HeaderParameter('X-Parental-Pin', 'Parental PIN, required when parental control is on.', type: 'string')]
    public function destroy(DeleteProfileRequest $request, Profile $profile): Response
    {
        Gate::authorize('delete', $profile);

        $profile->delete();

        return response()->noContent();
    }

    private function uniqueUsername(string $name): string
    {
        $base = Str::limit(Str::lower(Str::slug($name, '_')) ?: 'membre', 22, '');
        do {
            $username = $base.'_'.Str::lower(Str::random(6));
        } while (Profile::query()->where('username', $username)->exists());

        return $username;
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
                'pin' => [__('Incorrect PIN.')],
            ]);
        }

        return response()->noContent();
    }
}
