<?php

use App\Http\Middleware\ResolveProfile;
use App\Services\Messaging\Conversations;
use App\Services\Push\PushDevices;
use Illuminate\Support\Facades\Broadcast;

// A conversation's real-time channel (docs/reverb.md): its active members, from the profile of the
// `X-Profile-Id` header of the authorization request (POST /api/broadcasting/auth).
Broadcast::channel('messages.{conversation}', function ($user, int $conversation): bool {
    $profile = ResolveProfile::profile(request());

    return Conversations::allowed($profile)
        && (int) $profile->user_id === (int) $user->getAuthIdentifier()
        && app(Conversations::class)->isMember($profile, $conversation);
});

// The notifications of an app without Firebase (Windows, docs/notifications.md): only that app, signed
// in with the API token of the push registration.
Broadcast::channel('push-devices.{device}', fn ($user, int $device): bool => app(PushDevices::class)->belongsTo($user, $device));
