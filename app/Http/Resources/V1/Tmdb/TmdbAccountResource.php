<?php

namespace App\Http\Resources\V1\Tmdb;

use App\Models\TmdbLink;
use App\Services\Tmdb\TmdbFormat as F;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * TMDb account of a profile; every field is null when none is linked.
 *
 * @property TmdbLink|null $resource
 */
class TmdbAccountResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $link = $this->resource;

        return [
            /** A TMDb account is linked to this profile. */
            'linked' => $link !== null,
            /** TMDb account id. */
            'account_id' => $link === null ? null : (int) $link->account_id,
            'username' => $link === null ? null : (string) $link->username,
            /** Name shown on TMDb (may be null). */
            'name' => $link?->name === null ? null : (string) $link->name,
            /** TMDb avatar, w185. */
            'avatar_url' => F::image($link?->avatar_path, 'w185'),
            /** ISO 8601. */
            'linked_at' => $link === null ? null : (string) $link->linked_at->toIso8601String(),
        ];
    }
}
