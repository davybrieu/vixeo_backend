<?php

namespace App\Services;

use App\Models\Profile;
use App\Models\ProfileItem;
use App\Models\ProfileSearch;
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
            'profiles' => $user->profiles()->with(['items', 'searches'])->get()->map(fn (Profile $p) => [
                'name' => $p->name,
                'avatar' => $p->avatar,
                'is_kid' => $p->is_kid,
                'max_age_rating' => $p->max_age_rating,
                'allow_unrated' => $p->allow_unrated,
                'poster_format' => $p->poster_format->value,
                'audio_language' => $p->audio_language,
                'subtitle_language' => $p->subtitle_language,
                'skip_intro' => $p->skip_intro,
                'skip_recap' => $p->skip_recap,
                'skip_credits' => $p->skip_credits,
                'skip_preview' => $p->skip_preview,
                'recent_searches' => $p->searches->sortByDesc('updated_at')->map(fn (ProfileSearch $s) => [
                    'query' => $s->query,
                    'searched_at' => $s->updated_at?->toIso8601String(),
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
            ])->values()->all(),
            'devices' => $user->tokens()->get()->map(fn (PersonalAccessToken $t) => [
                'name' => $t->name,
                'signed_in_at' => $t->created_at?->toIso8601String(),
                'last_used_at' => $t->last_used_at?->toIso8601String(),
            ])->values()->all(),
        ];
    }
}
