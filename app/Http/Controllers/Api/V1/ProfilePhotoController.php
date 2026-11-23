<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\ProfilePhotoRequest;
use App\Http\Resources\V1\ProfileResource;
use App\Models\Profile;
use App\Services\ProfilePhotos;
use Illuminate\Support\Facades\Gate;
use Symfony\Component\HttpFoundation\Response;

/**
 * @tags Profils
 */
class ProfilePhotoController extends Controller
{
    public function __construct(private ProfilePhotos $photos) {}

    /**
     * Set the profile's photo.
     *
     * Multipart field `photo`. Shown instead of its avatar (`photo_url` of the profile, of its
     * cards in the social network and the messaging); replaces the previous photo. The app sends
     * the square it framed; the API keeps the central square anyway, 512 pixels, re-encoded
     * without the metadata of the file (location…).
     */
    public function store(ProfilePhotoRequest $request, Profile $profile): ProfileResource
    {
        Gate::authorize('update', $profile);

        $this->photos->store($profile, $request->file('photo'));

        return new ProfileResource($profile);
    }

    /**
     * Remove the profile's photo.
     *
     * Its avatar is shown again.
     */
    public function destroy(Profile $profile): ProfileResource
    {
        Gate::authorize('update', $profile);

        $this->photos->remove($profile);

        return new ProfileResource($profile);
    }

    /**
     * A profile photo.
     *
     * Public (images are loaded without a bearer token), at the address `photo_url` gives; cached
     * for a year: a new photo has a new address.
     *
     * @unauthenticated
     */
    public function show(string $name): Response
    {
        return $this->photos->response($name);
    }
}
