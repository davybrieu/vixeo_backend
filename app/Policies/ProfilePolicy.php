<?php

namespace App\Policies;

use App\Models\Profile;
use App\Models\User;
use Illuminate\Auth\Access\Response;

class ProfilePolicy
{
    public function view(User $user, Profile $profile): bool
    {
        return $profile->user_id === $user->id;
    }

    public function create(User $user): Response
    {
        return $user->profiles()->count() < Profile::MAX_PER_USER
            ? Response::allow()
            : Response::deny(__('An account can have at most :max profiles.', ['max' => Profile::MAX_PER_USER]));
    }

    public function update(User $user, Profile $profile): bool
    {
        return $profile->user_id === $user->id;
    }

    public function delete(User $user, Profile $profile): Response
    {
        if ($profile->user_id !== $user->id) {
            return Response::deny();
        }

        // An account always keeps at least one profile.
        return $user->profiles()->count() > 1
            ? Response::allow()
            : Response::deny(__('The last profile of an account cannot be deleted.'));
    }
}
