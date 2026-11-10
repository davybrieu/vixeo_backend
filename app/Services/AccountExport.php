<?php

namespace App\Services;

use App\Models\Profile;
use App\Models\ProfileItem;
use App\Models\ProfileSearch;
use App\Models\ProfileStream;
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
            'profiles' => $user->profiles()->with(['items', 'streams', 'searches', 'tmdbLink', 'tmdbRatings'])->get()->map(fn (Profile $p) => [
                'name' => $p->name,
                'avatar' => $p->avatar,
                'is_kid' => $p->is_kid,
                'max_age_rating' => $p->max_age_rating,
                'allow_unrated' => $p->allow_unrated,
                'poster_format' => $p->poster_format->value,
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
                // The ratings themselves are on TMDb, not at Vixeo.
                'tmdb_account' => $p->tmdbLink === null ? null : [
                    'account_id' => $p->tmdbLink->account_id,
                    'username' => $p->tmdbLink->username,
                    'linked_at' => $p->tmdbLink->linked_at?->toIso8601String(),
                ],
                // Copy of the TMDb ratings, with the comments written in Vixeo.
                'tmdb_ratings' => $p->tmdbRatings->sortByDesc('rated_at')->map(fn (TmdbRating $r) => [
                    'title' => $r->title,
                    'type' => $r->type->value,
                    'season_number' => $r->season_number,
                    'episode_number' => $r->episode_number,
                    'value' => $r->value,
                    'comment' => $r->comment,
                    'rated_at' => $r->rated_at?->toIso8601String(),
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
            'devices' => $user->tokens()->get()->map(fn (PersonalAccessToken $t) => [
                'name' => $t->name,
                'signed_in_at' => $t->created_at?->toIso8601String(),
                'last_used_at' => $t->last_used_at?->toIso8601String(),
            ])->values()->all(),
        ];
    }
}
