<?php

namespace App\Http\Resources\V1\Playback;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * A subtitle file found on OpenSubtitles. Get its text with GET /catalog/subtitles/{file_id}.
 *
 * @property array{file_id: int, language: string, release: string|null, hearing_impaired: bool, ai_translated: bool, machine_translated: bool, downloads: int} $resource
 */
class SubtitleResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $r = $this->resource;

        return [
            'file_id' => (int) $r['file_id'],
            /** "fr", "en", "pt-BR"… */
            'language' => (string) $r['language'],
            /** Release the timing was made for (e.g. "Dune.Part.Two.2024.1080p.WEB"), if known. */
            'release' => $r['release'] === null ? null : (string) $r['release'],
            /** Subtitles for the deaf and hard of hearing (sounds described). */
            'hearing_impaired' => (bool) $r['hearing_impaired'],
            /** Translated by a machine or an AI rather than by a person. */
            'ai_translated' => (bool) ($r['ai_translated'] || $r['machine_translated']),
            'downloads' => (int) $r['downloads'],
        ];
    }
}
