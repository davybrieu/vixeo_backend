<?php

namespace App\Policies;

use App\Models\Profile;
use App\Models\User;
use Illuminate\Auth\Access\Response;

class ProfilePolicy
{
    public function view(User $user, Profile $profile): bool
    {
        return $profile->id === $user->id;
    }

    public function create(User $user): Response
    {
        return $user->fresh()->username === null
            ? Response::allow()
            : Response::deny(__('An account can have only one profile.'));
    }

    public function update(User $user, Profile $profile): bool
    {
        return $profile->user_id === $user->id;
    }
}
