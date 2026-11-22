<?php

use App\Http\Middleware\ResolveProfile;
use Illuminate\Support\Facades\Broadcast;
use Illuminate\Support\Facades\DB;

Broadcast::channel('messages.{conversation}', function ($user, int $conversation): bool {
    $profile = ResolveProfile::profile(request());

    return $profile !== null
        && ! $profile->is_kid
        && $profile->username !== null
        && (int) $profile->user_id === (int) $user->getAuthIdentifier()
        && DB::table('direct_conversation_members')
            ->where('conversation_id', $conversation)
            ->where('profile_id', $profile->id)
            ->whereNull('left_at')
            ->exists();
});
