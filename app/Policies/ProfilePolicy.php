<?php

namespace App\Policies;

use App\Models\Profile;
use App\Models\User;

class ProfilePolicy
{
    public function view(User $user, Profile $profile): bool
    {
        return $profile->user_id === $user->id;
    }

    public function create(User $user): bool
    {
        return $user->profiles()->count() < Profile::MAX_PER_USER;
    }

    public function update(User $user, Profile $profile): bool
    {
        return $profile->user_id === $user->id;
    }

    public function delete(User $user, Profile $profile): bool
    {
        // An account always keeps at least one profile.
        return $profile->user_id === $user->id && $user->profiles()->count() > 1;
    }
}
