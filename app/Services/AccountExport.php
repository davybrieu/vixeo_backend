<?php

namespace App\Services;

use App\Models\DiaryEntry;
use App\Models\DirectMessage;
use App\Models\EpisodeNotification;
use App\Models\ListLike;
use App\Models\MessageBlock;
use App\Models\Profile;
use App\Models\ProfileItem;
use App\Models\ProfileList;
use App\Models\ProfileMute;
use App\Models\ProfileSearch;
use App\Models\PushDevice;
use App\Models\Report;
use App\Models\ReviewLike;
use App\Models\SocialAccount;
use App\Models\SocialComment;
use App\Models\SocialNotification;
use App\Models\TitleArtwork;
use App\Models\TmdbRating;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\PersonalAccessToken;

/**
 * Everything Vixeo stores about an account, as one JSON document (GDPR right of access and
 * portability). Password hashes are left out: they are secrets, not personal data
 * the user can use.
 */
class AccountExport
{
    /** @return array<string, mixed> */
    public function build(User $user): array
    {
        return [
            'exported_at' => now()->toIso8601String(),
            'account' => [
                'name' => $user->name,
                'email' => $user->email,
                'created_at' => $user->created_at?->toIso8601String(),
                'has_password' => $user->hasPassword(),
                // « Continuer avec Google / Apple ».
                'linked_accounts' => SocialAccount::query()->where('user_id', $user->id)->orderBy('id')->get()->map(fn (SocialAccount $account) => [
                    'provider' => $account->provider,
                    'email' => $account->email,
                    'linked_at' => $account->created_at?->toIso8601String(),
                ])->all(),
            ],
            'profiles' => $user->profiles()->with(['items', 'searches', 'tmdbRatings', 'episodeNotifications'])->get()->map(fn (Profile $p) => [
                'name' => $p->name,
                'username' => $p->username,
                'bio' => $p->bio,
                'is_private' => (bool) $p->is_private,
                'social_links' => $p->social_links ?? [],
                // The poster, backdrop and logo it chose for titles (TitleArtworks).
                'title_images' => TitleArtwork::query()->where('profile_id', $p->id)->orderBy('id')->get()
                    ->map(fn (TitleArtwork $a) => [
                        'item_id' => "{$a->media_type}:{$a->tmdb_id}",
                        'poster' => $a->poster_path,
                        'backdrop' => $a->backdrop_path,
                        'logo' => $a->logo_path,
                    ])->all(),
                // Members followed (or asked to follow), followers, likes given, notifications (docs/social.md).
                'following' => $this->follows('follower_profile_id', 'followed_profile_id', $p),
                'followers' => $this->follows('followed_profile_id', 'follower_profile_id', $p),
                'review_likes' => ReviewLike::query()->where('profile_id', $p->id)->with('rating.profile')->get()
                    ->map(fn (ReviewLike $like) => [
                        'review_of' => $like->rating->profile->username,
                        'title' => $like->rating->title,
                        'liked_at' => $like->created_at?->toIso8601String(),
                    ])->values()->all(),
                'notifications' => SocialNotification::query()->where('profile_id', $p->id)->with('actor')
                    ->latest('created_at')->get()->map(fn (SocialNotification $n) => [
                        'type' => $n->type,
                        'from' => $n->actor->username,
                        'at' => $n->created_at?->toIso8601String(),
                        'read' => $n->read_at !== null,
                    ])->values()->all(),
                // Messages the profile sent (docs/messagerie.md), and the members it blocked.
                'messages' => $this->messages($p),
                'message_blocks' => MessageBlock::query()->where('blocker_profile_id', $p->id)->with('blocked')->get()
                    ->map(fn (MessageBlock $block) => $block->blocked?->username)->filter()->values()->all(),
                // The social network (docs/social.md): lists, comments, diary, muted members, reports.
                'lists' => ProfileList::query()->where('profile_id', $p->id)->with('items')->get()->map(fn (ProfileList $l) => [
                    'title' => $l->title,
                    'description' => $l->description,
                    'created_at' => $l->created_at?->toIso8601String(),
                    'titles' => $l->items->map(fn ($i) => ['item_id' => $i->itemId(), 'title' => $i->title])->values()->all(),
                ])->values()->all(),
                'list_likes' => ListLike::query()->where('profile_id', $p->id)->with('list')->get()
                    ->map(fn (ListLike $like) => ['list' => $like->list?->title, 'liked_at' => $like->created_at?->toIso8601String()])->values()->all(),
                'comments' => SocialComment::query()->where('profile_id', $p->id)->with(['review', 'list'])->get()->map(fn (SocialComment $c) => [
                    'on' => $c->review !== null ? "Avis sur {$c->review->title}" : 'Liste « '.($c->list?->title ?? '').' »',
                    'body' => $c->body,
                    'contains_spoilers' => (bool) $c->contains_spoilers,
                    'created_at' => $c->created_at?->toIso8601String(),
                ])->values()->all(),
                'diary' => DiaryEntry::query()->where('profile_id', $p->id)->orderBy('watched_at')->get()->map(fn (DiaryEntry $e) => [
                    'item_id' => "{$e->media_type}:{$e->tmdb_id}",
                    'title' => $e->title,
                    'season_number' => $e->season_number,
                    'episode_number' => $e->episode_number,
                    'watched_at' => $e->watched_at->toIso8601String(),
                    'rewatch' => (bool) $e->rewatch,
                ])->values()->all(),
                'muted' => ProfileMute::query()->where('profile_id', $p->id)->pluck('muted_profile_id')
                    ->map(fn ($id) => Profile::query()->find($id)?->username)->filter()->values()->all(),
                'reports' => Report::query()->where('reporter_profile_id', $p->id)->get()->map(fn (Report $r) => [
                    'type' => $r->subject_type,
                    'reason' => $r->reason,
                    'details' => $r->details,
                    'status' => $r->status,
                    'decision' => $r->decision,
                    'created_at' => $r->created_at?->toIso8601String(),
                ])->values()->all(),
                'rate_after_watching' => (bool) $p->rate_after_watching,
                'avatar' => $p->avatar,
                'photo_url' => $p->photoUrl(),
                'cover' => $p->cover,
                'cover_url' => $p->coverUrl(),
                'audio_language' => $p->audio_language,
                'subtitle_language' => $p->subtitle_language,
                'no_spoiler' => $p->no_spoiler,
                'skip_intro' => $p->skip_intro,
                'skip_recap' => $p->skip_recap,
                'skip_credits' => $p->skip_credits,
                'skip_preview' => $p->skip_preview,
                'recent_searches' => $p->searches->sortByDesc('updated_at')->map(fn (ProfileSearch $s) => [
                    'query' => $s->query,
                    'searched_at' => $s->updated_at?->toIso8601String(),
                ])->values()->all(),
                // The profile's reviews (« Mes avis »): rating and comment.
                'tmdb_ratings' => $p->tmdbRatings->sortByDesc('rated_at')->map(fn (TmdbRating $r) => [
                    'title' => $r->title,
                    'type' => $r->type->value,
                    'season_number' => $r->season_number,
                    'episode_number' => $r->episode_number,
                    'value' => $r->value,
                    'comment' => $r->comment,
                    'rated_at' => $r->rated_at?->toIso8601String(),
                ])->values()->all(),
                // New episodes already announced by a notification.
                'episode_notifications' => $p->episodeNotifications->sortByDesc('sent_at')->map(fn (EpisodeNotification $n) => [
                    'show_id' => $n->show_id,
                    'season_number' => $n->season_number,
                    'episode_number' => $n->episode_number,
                    'sent_at' => $n->sent_at?->toIso8601String(),
                ])->values()->all(),
                'created_at' => $p->created_at?->toIso8601String(),
                'library' => $p->items->map(fn (ProfileItem $i) => [
                    'title' => $i->title,
                    'type' => $i->type,
                    'source' => $i->source,
                    'item_id' => $i->item_id,
                    'season_number' => $i->season_number,
                    'episode_number' => $i->episode_number,
                    'is_favorite' => $i->is_favorite,
                    'favorited_at' => $i->favorited_at?->toIso8601String(),
                    'position_ms' => $i->position_ms,
                    'duration_ms' => $i->duration_ms,
                    'last_engaged_at' => $i->last_engaged_at?->toIso8601String(),
                    'is_watched' => $i->is_watched,
                    'watched_at' => $i->watched_at?->toIso8601String(),
                    'is_liked' => $i->is_liked,
                    'liked_at' => $i->liked_at?->toIso8601String(),
                ])->values()->all(),
            ])->values()->all(),
            // Apps registered for push notifications (their Firebase token is left out).
            'notification_devices' => $user->pushDevices()->with('profile')->get()->map(fn (PushDevice $d) => [
                'platform' => $d->platform,
                'target' => $d->target,
                'profile' => $d->profile?->name,
                'new_episodes' => $d->new_episodes,
                'social' => (bool) $d->social,
                'registered_at' => $d->created_at?->toIso8601String(),
                'last_seen_at' => $d->last_seen_at?->toIso8601String(),
            ])->values()->all(),
            'devices' => $user->tokens()->get()->map(fn (PersonalAccessToken $t) => [
                'name' => $t->name,
                'signed_in_at' => $t->created_at?->toIso8601String(),
                'last_used_at' => $t->last_used_at?->toIso8601String(),
            ])->values()->all(),
        ];
    }

    /**
     * The messages [profile] sent and did not delete, oldest first, with their conversation and
     * the names of their files (the files themselves stay on the server).
     *
     * @return list<array<string, mixed>>
     */
    private function messages(Profile $profile): array
    {
        return DirectMessage::query()
            ->where('sender_profile_id', $profile->id)
            ->whereNull('deleted_at')
            ->with(['conversation', 'attachments'])
            ->orderBy('id')
            ->get()
            ->map(fn (DirectMessage $message) => [
                'conversation_id' => $message->conversation_id,
                'conversation' => $message->conversation->isGroup() ? $message->conversation->name : null,
                'body' => $message->body,
                'attachments' => $message->attachments->pluck('original_name')->all(),
                'sent_at' => $message->created_at?->toIso8601String(),
            ])->values()->all();
    }

    /**
     * The members [profile] follows (`$from` = follower) or that follow it, with their status.
     *
     * @return list<array{username: string, accepted: bool, since: string}>
     */
    private function follows(string $from, string $other, Profile $profile): array
    {
        return DB::table('profile_follows')->join('profiles', 'profiles.id', '=', "profile_follows.{$other}")
            ->where("profile_follows.{$from}", $profile->id)->orderBy('profile_follows.created_at')
            ->get(['profiles.username', 'profile_follows.accepted_at', 'profile_follows.created_at'])
            ->map(fn (object $row) => [
                'username' => (string) $row->username,
                'accepted' => $row->accepted_at !== null,
                'since' => (string) $row->created_at,
            ])->values()->all();
    }
}
