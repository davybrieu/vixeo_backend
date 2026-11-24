<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\ProfileCoverRequest;
use App\Http\Resources\V1\ProfileResource;
use App\Models\Profile;
use App\Services\ProfileCovers;
use Illuminate\Support\Facades\Gate;
use Symfony\Component\HttpFoundation\Response;

/**
 * @tags Profils
 */
class ProfileCoverController extends Controller
{
    public function __construct(private ProfileCovers $covers) {}

    /**
     * Set the profile's cover image.
     *
     * Multipart field `cover`. Shown at the top of its page instead of its cover of the catalog
     * (`cover_url` of the profile and of its page); replaces the previous image. The app sends the
     * 3:1 banner it framed; the API keeps the central 3:1 part anyway, 1,500 × 500 pixels at most,
     * re-encoded without the metadata of the file (location…).
     */
    public function store(ProfileCoverRequest $request, Profile $profile): ProfileResource
    {
        Gate::authorize('update', $profile);

        $this->covers->store($profile, $request->file('cover'));

        return new ProfileResource($profile);
    }

    /**
     * Remove the profile's cover image.
     *
     * Its cover of the catalog (`cover`) is shown again.
     */
    public function destroy(Profile $profile): ProfileResource
    {
        Gate::authorize('update', $profile);

        $this->covers->remove($profile);

        return new ProfileResource($profile);
    }

    /**
     * A profile cover.
     *
     * Public (images are loaded without a bearer token), at the address `cover_url` gives; cached
     * for a year: a new image has a new address.
     *
     * @unauthenticated
     */
    public function show(string $name): Response
    {
        return $this->covers->response($name);
    }
}
