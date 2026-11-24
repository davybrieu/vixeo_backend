<?php

namespace App\Services\Social;

use App\Models\Profile;
use App\Models\ProfileList;
use App\Models\SocialComment;
use App\Models\SocialNotification;
use App\Models\TmdbRating;
use Illuminate\Database\Eloquent\Builder;

/**
 * Comments on a review or a list (docs/social.md): where the conversation happens, since there are
 * no posts of text alone. The author of the review or list is notified, and may delete any of them.
 * Comments of blocked, suspended or hidden profiles are left out.
 */
class Comments
{
    public const PAGE = 20;

    public function __construct(private Audience $audience, private SocialNotifications $notifications) {}

    /**
     * The latest comments of [subject], oldest first; `before` (a comment id) for older ones.
     *
     * @return array{data: list<array<string, mixed>>, has_more: bool}
     */
    public function page(TmdbRating|ProfileList $subject, ?Profile $viewer, ?int $before): array
    {
        $rows = $this->audience->constrain($this->query($subject), $viewer)
            ->with('profile')
            ->when($before !== null, fn (Builder $q) => $q->where('id', '<', $before))
            ->orderByDesc('id')->limit(self::PAGE + 1)->get();
        $owns = $viewer !== null && (int) $subject->profile_id === (int) $viewer->id;

        return [
            'data' => $rows->take(self::PAGE)->reverse()->values()
                ->map(fn (SocialComment $c) => SocialCards::comment($c, $viewer, $owns))->all(),
            'has_more' => $rows->count() > self::PAGE,
        ];
    }

    public function count(TmdbRating|ProfileList $subject): int
    {
        return $this->query($subject)->count();
    }

    public function add(Profile $author, TmdbRating|ProfileList $subject, string $body, bool $spoilers): SocialComment
    {
        $review = $subject instanceof TmdbRating;
        $comment = SocialComment::query()->create([
            'profile_id' => $author->id,
            'tmdb_rating_id' => $review ? $subject->id : null,
            'profile_list_id' => $review ? null : $subject->id,
            'body' => $body,
            'contains_spoilers' => $spoilers,
        ]);
        $comment->setRelation('profile', $author);
        $this->notifications->notify(
            $subject->profile,
            $author,
            $review ? SocialNotification::REVIEW_COMMENT : SocialNotification::LIST_COMMENT,
            rating: $review ? $subject : null,
            list: $review ? null : $subject,
            comment: $comment,
        );

        return $comment;
    }

    /** Its author, or the author of the review or list (403 otherwise). */
    public function delete(Profile $profile, SocialComment $comment): void
    {
        $subject = $comment->review ?? $comment->list;
        $allowed = (int) $comment->profile_id === (int) $profile->id
            || ($subject !== null && (int) $subject->profile_id === (int) $profile->id);
        abort_unless($allowed, 403, __('You cannot delete this comment.'));
        $comment->delete();
    }

    /** @return Builder<SocialComment> */
    private function query(TmdbRating|ProfileList $subject): Builder
    {
        return SocialComment::query()
            ->where($subject instanceof TmdbRating ? 'tmdb_rating_id' : 'profile_list_id', $subject->id)
            ->whereNull('hidden_at');
    }
}
