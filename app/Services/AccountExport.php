<?php

namespace App\Services;

use App\Enums\RatingSource;
use App\Models\Addon;
use App\Models\EpisodeNotification;
use App\Models\Profile;
use App\Models\ProfileItem;
use App\Models\ProfileSearch;
use App\Models\ProfileStream;
use App\Models\PushDevice;
use App\Models\TmdbRating;
use App\Models\User;
use Laravel\Sanctum\PersonalAccessToken;

/**
 * Everything Vixeo stores about an account, as one JSON document (GDPR right of access and
 * portability). Hashes (password, PINs) are left out: they are secrets, not personal data
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
                'parental_control' => $user->hasParentalControl(),
            ],
            'profiles' => $user->profiles()->with(['items', 'streams', 'searches', 'tmdbRatings', 'episodeNotifications'])->get()->map(fn (Profile $p) => [
                'name' => $p->name,
                'avatar' => $p->avatar,
                'is_kid' => $p->is_kid,
                'max_age_rating' => $p->max_age_rating,
                'allow_unrated' => $p->allow_unrated,
                'poster_format' => $p->poster_format->value,
                'rating_source' => ($p->rating_source ?? RatingSource::Imdb)->value,
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
                'has_pin' => $p->pin !== null,
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
                    'is_followed' => $i->is_followed,
                    'followed_at' => $i->followed_at?->toIso8601String(),
                ])->values()->all(),
                // Stream preferences: favorites (❤) and the last stream that worked per content.
                'stream_preferences' => $p->streams->sortByDesc('last_worked_at')->map(fn (ProfileStream $s) => [
                    'source' => $s->source,
                    'item_id' => $s->item_id,
                    'stream_id' => $s->stream_id,
                    'stream_url' => $s->stream_url,
                    'quality' => $s->quality,
                    'language' => $s->language,
                    'is_favorite' => $s->is_favorite,
                    'favorited_at' => $s->favorited_at?->toIso8601String(),
                    'last_worked_at' => $s->last_worked_at?->toIso8601String(),
                ])->values()->all(),
            ])->values()->all(),
            // Apps registered for push notifications (their Firebase token is left out).
            'notification_devices' => $user->pushDevices()->with('profile')->get()->map(fn (PushDevice $d) => [
                'platform' => $d->platform,
                'profile' => $d->profile?->name,
                'new_episodes' => $d->new_episodes,
                'registered_at' => $d->created_at?->toIso8601String(),
                'last_seen_at' => $d->last_seen_at?->toIso8601String(),
            ])->values()->all(),
            // Plug-ins the account installed (docs/plugins.md), with their address: it is the
            // account's own data, even when it holds a personal key.
            'plugins' => $user->addons()->get()->map(fn (Addon $a) => [
                'name' => $a->manifest['name'] ?? null,
                'version' => $a->manifest['version'] ?? null,
                'address' => $a->transport_url,
                'enabled' => $a->enabled,
                'installed_at' => $a->created_at?->toIso8601String(),
            ])->values()->all(),
            'devices' => $user->tokens()->get()->map(fn (PersonalAccessToken $t) => [
                'name' => $t->name,
                'signed_in_at' => $t->created_at?->toIso8601String(),
                'last_used_at' => $t->last_used_at?->toIso8601String(),
            ])->values()->all(),
        ];
    }
}
