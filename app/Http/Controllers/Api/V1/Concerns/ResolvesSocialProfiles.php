<?php

namespace App\Http\Controllers\Api\V1\Concerns;

use App\Http\Middleware\ResolveProfile;
use App\Models\Profile;
use App\Services\Social\Audience;
use Illuminate\Http\Request;

/**
 * Who asks, and whose content, for the social network (docs/social.md): kid profiles have no
 * social features, a suspended profile can no longer publish, and a member is an adult profile
 * with a username.
 */
trait ResolvesSocialProfiles
{
    /** The active profile, maybe none (public pages); never a kid profile. */
    protected function viewer(Request $request): ?Profile
    {
        $viewer = ResolveProfile::profile($request);
        abort_if($viewer?->is_kid, 403, __('Kid profiles cannot use the social features.'));

        return $viewer;
    }

    /** The active profile, which publishes, likes or comments: an adult with a username, not suspended. */
    protected function author(Request $request): Profile
    {
        $profile = ResolveProfile::profile($request);
        abort_unless($profile instanceof Profile, 422, __('Choose a profile.'));
        abort_if($profile->is_kid, 403, __('Kid profiles cannot use the social features.'));
        abort_if($profile->suspended_at !== null, 403, __('This profile is suspended.'));
        abort_if($profile->username === null, 403, __('Choose a username to use the social features.'));

        return $profile;
    }

    /** A member, by its username (404 for a kid or suspended profile). */
    protected function member(string $username): Profile
    {
        return Profile::query()->where('username', mb_strtolower(ltrim($username, '@')))
            ->where('is_kid', false)->whereNull('suspended_at')->firstOrFail();
    }

    /** A member whose content the active profile sees (403: private, 404: blocked). */
    protected function visibleMember(Request $request, string $username): Profile
    {
        $viewer = $this->viewer($request);
        $member = $this->member($username);
        $audience = app(Audience::class);
        abort_if($audience->isBlocked($viewer, $member), 404);
        abort_unless($audience->canSee($viewer, $member), 403, __('This account is private.'));

        return $member;
    }

    /** What [author] published, seen by the active profile (403: private, 404: blocked or hidden). */
    protected function assertVisible(Request $request, Profile $author, bool $hidden = false): ?Profile
    {
        $viewer = $this->viewer($request);
        $audience = app(Audience::class);
        abort_if($hidden || ! $author->isSocial() || $audience->isBlocked($viewer, $author), 404);
        abort_unless($audience->canSee($viewer, $author), 403, __('This account is private.'));

        return $viewer;
    }
}
