<?php

namespace App\Services\Social;

use App\Models\MessageBlock;
use App\Models\Profile;
use App\Models\ProfileMute;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

/**
 * Who sees what (docs/social.md): what a profile publishes (reviews, lists, comments, diary) is seen
 * by everyone when it is public, by its accepted followers when it is private, never by a profile
 * it blocked or that blocked it, and not at all once suspended by the moderation. A blocked profile
 * can neither follow, comment, like nor write. « Masquer » only hides a followed profile from the
 * feed.
 */
class Audience
{
    public function __construct(private Follows $follows) {}

    /**
     * The profiles [viewer] blocked or that blocked it.
     *
     * @return list<int>
     */
    public function blocked(?Profile $viewer): array
    {
        if ($viewer === null) {
            return [];
        }

        return MessageBlock::query()->where('blocker_profile_id', $viewer->id)->pluck('blocked_profile_id')
            ->merge(MessageBlock::query()->where('blocked_profile_id', $viewer->id)->pluck('blocker_profile_id'))
            ->map(fn (mixed $id) => (int) $id)->unique()->values()->all();
    }

    /** One of them blocked the other. */
    public function isBlocked(?Profile $viewer, Profile $other): bool
    {
        return $viewer !== null && MessageBlock::query()
            ->where(fn ($q) => $q->where('blocker_profile_id', $viewer->id)->where('blocked_profile_id', $other->id))
            ->orWhere(fn ($q) => $q->where('blocker_profile_id', $other->id)->where('blocked_profile_id', $viewer->id))
            ->exists();
    }

    /** [viewer] sees what [author] publishes. */
    public function canSee(?Profile $viewer, Profile $author): bool
    {
        return $author->isSocial() && ! $this->isBlocked($viewer, $author) && $this->follows->canView($viewer, $author);
    }

    /**
     * Keeps the rows of [query] whose author ([column]) [viewer] may see.
     *
     * @template TModel of \Illuminate\Database\Eloquent\Model
     *
     * @param  Builder<TModel>  $query
     * @return Builder<TModel>
     */
    public function constrain(Builder $query, ?Profile $viewer, string $column = 'profile_id'): Builder
    {
        $blocked = $this->blocked($viewer);

        return $query->whereIn($column, Profile::query()->select('id')
            ->where('is_kid', false)->whereNotNull('username')->whereNull('suspended_at')
            ->when($blocked !== [], fn ($q) => $q->whereNotIn('id', $blocked))
            ->where(fn ($q) => $q->where('is_private', false)
                ->when($viewer !== null, fn ($q) => $q->orWhere('id', $viewer->id)
                    ->orWhereIn('id', DB::table('profile_follows')->select('followed_profile_id')
                        ->where('follower_profile_id', $viewer->id)->whereNotNull('accepted_at')))));
    }

    /**
     * The profiles [viewer] hid from its feed (« Masquer »).
     *
     * @return list<int>
     */
    public function muted(Profile $viewer): array
    {
        return ProfileMute::query()->where('profile_id', $viewer->id)->pluck('muted_profile_id')
            ->map(fn (mixed $id) => (int) $id)->all();
    }

    public function isMuted(Profile $viewer, Profile $other): bool
    {
        return ProfileMute::query()->where('profile_id', $viewer->id)->where('muted_profile_id', $other->id)->exists();
    }

    /**
     * [blocker] blocks [blocked] everywhere: neither follows the other any more (nor asks to),
     * neither sees, likes, comments nor writes to the other.
     */
    public function block(Profile $blocker, Profile $blocked): void
    {
        abort_if($blocker->id === $blocked->id, 422, __('You cannot block yourself.'));
        MessageBlock::query()->firstOrCreate(['blocker_profile_id' => $blocker->id, 'blocked_profile_id' => $blocked->id]);
        DB::table('profile_follows')
            ->where(fn ($q) => $q->where('follower_profile_id', $blocker->id)->where('followed_profile_id', $blocked->id))
            ->orWhere(fn ($q) => $q->where('follower_profile_id', $blocked->id)->where('followed_profile_id', $blocker->id))
            ->delete();
        ProfileMute::query()->where('profile_id', $blocker->id)->where('muted_profile_id', $blocked->id)->delete();
    }

    public function unblock(Profile $blocker, Profile $blocked): void
    {
        MessageBlock::query()->where('blocker_profile_id', $blocker->id)->where('blocked_profile_id', $blocked->id)->delete();
    }

    public function mute(Profile $profile, Profile $muted): void
    {
        abort_if($profile->id === $muted->id, 422, __('You cannot mute yourself.'));
        ProfileMute::query()->firstOrCreate(['profile_id' => $profile->id, 'muted_profile_id' => $muted->id], ['created_at' => now()]);
    }

    public function unmute(Profile $profile, Profile $muted): void
    {
        ProfileMute::query()->where('profile_id', $profile->id)->where('muted_profile_id', $muted->id)->delete();
    }
}
