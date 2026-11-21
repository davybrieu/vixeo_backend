<?php

namespace App\Services\Social;

use App\Models\Profile;
use App\Models\SocialNotification;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

/**
 * Who follows whom (docs/social.md). A public account is followed at once; a private one receives
 * a request (`accepted_at` null) it accepts or declines, like Twitter. Lists and reviews of a
 * private account are only seen by its accepted followers ([canView]).
 */
class Follows
{
    public const NONE = 'none';

    public const REQUESTED = 'requested';

    public const FOLLOWING = 'following';

    public function __construct(private SocialNotifications $notifications) {}

    /** Where [follower] stands with [target]: none, requested or following. */
    public function status(?Profile $follower, Profile $target): string
    {
        if ($follower === null || $follower->id === $target->id) {
            return self::NONE;
        }
        $row = $this->row($follower, $target);

        return $row === null ? self::NONE : ($row->accepted_at === null ? self::REQUESTED : self::FOLLOWING);
    }

    /** Whether [viewer] sees the lists and reviews of [profile]: public, itself, or an accepted follower. */
    public function canView(?Profile $viewer, Profile $profile): bool
    {
        return ! $profile->is_private || $viewer?->id === $profile->id || $this->status($viewer, $profile) === self::FOLLOWING;
    }

    /** [follower] follows [target]: at once, or a request to a private account. Returns the status. */
    public function follow(Profile $follower, Profile $target): string
    {
        $current = $this->status($follower, $target);
        if ($current !== self::NONE) {
            return $current;
        }
        $accepted = ! $target->is_private;
        DB::table('profile_follows')->insertOrIgnore([
            'follower_profile_id' => $follower->id,
            'followed_profile_id' => $target->id,
            'accepted_at' => $accepted ? now() : null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $this->notifications->notify($target, $follower, $accepted ? SocialNotification::FOLLOW : SocialNotification::FOLLOW_REQUEST);

        return $accepted ? self::FOLLOWING : self::REQUESTED;
    }

    /** [follower] stops following [target], or withdraws its request. */
    public function unfollow(Profile $follower, Profile $target): void
    {
        $this->query($follower, $target)->delete();
        $this->notifications->forget($target, $follower, [SocialNotification::FOLLOW, SocialNotification::FOLLOW_REQUEST]);
    }

    /** [profile] accepts [requester]'s request; false without one. */
    public function accept(Profile $profile, Profile $requester): bool
    {
        $accepted = $this->query($requester, $profile)->whereNull('accepted_at')
            ->update(['accepted_at' => now(), 'updated_at' => now()]) > 0;
        if ($accepted) {
            // The request becomes a follower in the profile's notifications (already seen).
            $this->notifications->forget($profile, $requester, [SocialNotification::FOLLOW_REQUEST]);
            $this->notifications->notify($profile, $requester, SocialNotification::FOLLOW, push: false);
            $this->notifications->notify($requester, $profile, SocialNotification::FOLLOW_ACCEPTED);
        }

        return $accepted;
    }

    /** [profile] declines [requester]'s request. */
    public function decline(Profile $profile, Profile $requester): void
    {
        $this->query($requester, $profile)->whereNull('accepted_at')->delete();
        $this->notifications->forget($profile, $requester, [SocialNotification::FOLLOW_REQUEST]);
    }

    /** [profile] became public: its waiting requests are accepted. */
    public function acceptAll(Profile $profile): void
    {
        $requesters = Profile::query()->whereIn('id', DB::table('profile_follows')
            ->where('followed_profile_id', $profile->id)->whereNull('accepted_at')->select('follower_profile_id'))->get();
        foreach ($requesters as $requester) {
            $this->accept($profile, $requester);
        }
    }

    /**
     * The requests waiting for [profile], the most recent first.
     *
     * @return list<array{profile: Profile, requested_at: string}>
     */
    public function requests(Profile $profile): array
    {
        $rows = DB::table('profile_follows')->where('followed_profile_id', $profile->id)
            ->whereNull('accepted_at')->orderByDesc('created_at')->orderByDesc('id')->limit(200)->get();
        $profiles = Profile::query()->whereIn('id', $rows->pluck('follower_profile_id'))->get()->keyBy('id');

        return $rows->filter(fn (object $row) => $profiles->has($row->follower_profile_id))
            ->map(fn (object $row) => [
                'profile' => $profiles[$row->follower_profile_id],
                'requested_at' => (string) $row->created_at,
            ])->values()->all();
    }

    private function row(Profile $follower, Profile $target): ?object
    {
        return $this->query($follower, $target)->first();
    }

    private function query(Profile $follower, Profile $target): Builder
    {
        return DB::table('profile_follows')->where('follower_profile_id', $follower->id)->where('followed_profile_id', $target->id);
    }
}
