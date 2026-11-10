<?php

namespace App\Http\Requests\Api\V1;

use App\Enums\ItemType;
use App\Services\ProfileSync;
use Closure;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SyncPushRequest extends FormRequest
{
    /** Query parameters that would mean a secret is embedded in an image URL. */
    private const SECRET_PARAMS = ['api_key', 'apikey', 'token', 'access_token', 'password', 'x-emby-token', 'x-plex-token'];

    public function rules(): array
    {
        $image = ['sometimes', 'nullable', 'string', 'max:2048', 'url:http,https', $this->withoutSecrets(...)];

        return [
            /** Changes made on the device since its last push (500 max, applied in order). */
            'changes' => ['required', 'array', 'list', 'min:1', 'max:'.ProfileSync::MAX_PUSH],
            /** Source chosen by the app: "tmdb", "jellyfin:<serverId>", "plex:<serverId>", "fast:pluto"… */
            'changes.*.source' => ['required', 'string', 'max:100', 'regex:/^[A-Za-z0-9._:\-]+$/'],
            /** Item id inside the source, e.g. "movie:550", "episode:1399:1:3". */
            'changes.*.item_id' => ['required', 'string', 'max:191'],
            'changes.*.type' => ['required', Rule::enum(ItemType::class)],
            /** Display title (for an episode: the episode name). */
            'changes.*.title' => ['required', 'string', 'max:300'],
            'changes.*.year' => ['sometimes', 'nullable', 'integer', 'min:1800', 'max:2200'],
            /** Must not contain credentials (api_key, token…). */
            'changes.*.poster_url' => $image,
            'changes.*.backdrop_url' => $image,
            /** For an episode: item_id of its TV show. */
            'changes.*.parent_item_id' => ['sometimes', 'nullable', 'string', 'max:191'],
            'changes.*.season_number' => ['sometimes', 'nullable', 'integer', 'min:0', 'max:100000'],
            'changes.*.episode_number' => ['sometimes', 'nullable', 'integer', 'min:0', 'max:100000'],

            /** "Ma liste": added or removed. At least one of favorite, liked, progress, watched is required. */
            'changes.*.favorite' => ['required_without_all:changes.*.liked,changes.*.progress,changes.*.watched', 'array:value,at'],
            'changes.*.favorite.value' => ['required_with:changes.*.favorite', 'boolean'],
            /** When the change happened on the device (ISO 8601). */
            'changes.*.favorite.at' => ['required_with:changes.*.favorite', 'date'],

            /** "Favoris" (❤): added or removed (movies, TV shows, episodes, live channels). */
            'changes.*.liked' => ['sometimes', 'array:value,at'],
            'changes.*.liked.value' => ['required_with:changes.*.liked', 'boolean'],
            'changes.*.liked.at' => ['required_with:changes.*.liked', 'date'],

            /** Playback progress. */
            'changes.*.progress' => ['sometimes', 'array:position_ms,duration_ms,continue_watching,at'],
            'changes.*.progress.position_ms' => ['required_with:changes.*.progress', 'integer', 'min:0', 'max:1000000000'],
            'changes.*.progress.duration_ms' => ['sometimes', 'nullable', 'integer', 'min:0', 'max:1000000000'],
            /** false = removed from "Continuer à regarder" (the position is kept for resuming). */
            'changes.*.progress.continue_watching' => ['required_with:changes.*.progress', 'boolean'],
            'changes.*.progress.at' => ['required_with:changes.*.progress', 'date'],

            /** Marked as watched or not watched. */
            'changes.*.watched' => ['sometimes', 'array:value,at'],
            'changes.*.watched.value' => ['required_with:changes.*.watched', 'boolean'],
            'changes.*.watched.at' => ['required_with:changes.*.watched', 'date'],
        ];
    }

    private function withoutSecrets(string $attribute, mixed $value, Closure $fail): void
    {
        parse_str((string) parse_url((string) $value, PHP_URL_QUERY), $query);

        if (array_intersect(array_map('strtolower', array_keys($query)), self::SECRET_PARAMS) !== []) {
            $fail(__('The :attribute must not contain credentials.'));
        }
    }

    /** @return list<array<string, mixed>> */
    public function changes(): array
    {
        return array_values($this->validated('changes'));
    }
}
