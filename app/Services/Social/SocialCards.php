<?php

namespace App\Services\Social;

use App\Http\Resources\V1\Tmdb\RatedItemResource;
use App\Models\ListLike;
use App\Models\Profile;
use App\Models\ProfileList;
use App\Models\ReviewLike;
use App\Models\SocialComment;
use App\Models\TmdbRating;
use App\Services\Tmdb\TmdbFormat as F;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Support\Collection;

/**
 * The same shapes everywhere the social network shows something (feed, title page, member page,
 * review and list pages, messages): a profile, a review, a list, a comment. Each batch costs a
 * fixed number of queries.
 */
class SocialCards
{
    /**
     * A profile in a list or on a card: who it is.
     *
     * @return array{id: int, username: string, name: string, avatar: string|null, photo_url: string|null, bio: string|null, is_private: bool}
     */
    public static function identity(Profile $profile): array
    {
        return [
            'id' => (int) $profile->id,
            'username' => (string) $profile->username,
            'name' => (string) $profile->name,
            'avatar' => $profile->avatar === null ? null : (string) $profile->avatar,
            /** Its own photo, shown instead of the avatar; null = the avatar. */
            'photo_url' => $profile->photoUrl(),
            'bio' => $profile->bio === null ? null : (string) $profile->bio,
            /** Private account: what it publishes is seen by its accepted followers only. */
            'is_private' => (bool) $profile->is_private,
        ];
    }

    /**
     * Reviews with their author, likes and comments, as [viewer] sees them.
     *
     * @param  Collection<int, TmdbRating>  $reviews
     * @return list<array<string, mixed>>
     */
    public function reviews(Collection $reviews, ?Profile $viewer): array
    {
        if ($reviews->isEmpty()) {
            return [];
        }
        $reviews = new EloquentCollection($reviews->values()->all());
        $reviews->loadMissing('profile')->loadCount([
            'likes',
            'comments' => fn ($q) => $q->whereNull('hidden_at'),
        ]);
        $liked = $viewer === null ? [] : ReviewLike::query()->where('profile_id', $viewer->id)
            ->whereIn('tmdb_rating_id', $reviews->modelKeys())->pluck('tmdb_rating_id')->map(intval(...))->all();
        $request = request();

        return $reviews->map(fn (TmdbRating $review) => [
            ...(new RatedItemResource($review))->toArray($request),
            'author' => self::identity($review->profile),
            /** Hidden behind « Afficher » until the reader asks. */
            'contains_spoilers' => (bool) $review->contains_spoilers,
            'liked' => in_array((int) $review->id, $liked, true),
            'comments_count' => (int) ($review->comments_count ?? 0),
            'is_mine' => $viewer !== null && (int) $review->profile_id === (int) $viewer->id,
        ])->values()->all();
    }

    /**
     * Lists with their author, cover (the first 4 posters), likes and comments.
     *
     * @param  Collection<int, ProfileList>  $lists
     * @return list<array<string, mixed>>
     */
    public function lists(Collection $lists, ?Profile $viewer): array
    {
        if ($lists->isEmpty()) {
            return [];
        }
        $lists = new EloquentCollection($lists->values()->all());
        $lists->loadMissing(['profile', 'items' => fn ($q) => $q->where('position', '<', 4)])->loadCount([
            'likes',
            'comments' => fn ($q) => $q->whereNull('hidden_at'),
        ]);
        $liked = $viewer === null ? [] : ListLike::query()->where('profile_id', $viewer->id)
            ->whereIn('profile_list_id', $lists->modelKeys())->pluck('profile_list_id')->map(intval(...))->all();

        return $lists->map(fn (ProfileList $list) => [
            'id' => (int) $list->id,
            'author' => self::identity($list->profile),
            'title' => (string) $list->title,
            'description' => $list->description === null ? null : (string) $list->description,
            'items_count' => (int) $list->items_count,
            /**
             * Posters of the first 4 titles, w342.
             *
             * @var list<string>
             */
            'cover' => $list->items->take(4)->map(fn ($item) => F::image($item->poster_path, 'w342'))->filter()->values()->all(),
            'likes_count' => (int) ($list->likes_count ?? 0),
            'liked' => in_array((int) $list->id, $liked, true),
            'comments_count' => (int) ($list->comments_count ?? 0),
            'is_mine' => $viewer !== null && (int) $list->profile_id === (int) $viewer->id,
            'created_at' => (string) $list->created_at?->toIso8601String(),
            'updated_at' => (string) $list->updated_at?->toIso8601String(),
        ])->values()->all();
    }

    /**
     * A comment with its author.
     *
     * @return array{id: int, author: array<string, mixed>, body: string, contains_spoilers: bool, created_at: string, is_mine: bool, can_delete: bool}
     */
    public static function comment(SocialComment $comment, ?Profile $viewer, bool $ownsSubject): array
    {
        $mine = $viewer !== null && (int) $comment->profile_id === (int) $viewer->id;

        return [
            'id' => (int) $comment->id,
            'author' => self::identity($comment->profile),
            'body' => (string) $comment->body,
            'contains_spoilers' => (bool) $comment->contains_spoilers,
            'created_at' => (string) $comment->created_at?->toIso8601String(),
            'is_mine' => $mine,
            /** Its author, or the author of the review or list. */
            'can_delete' => $mine || $ownsSubject,
        ];
    }
}
