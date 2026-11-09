<?php

namespace App\Http\Requests\Api\V1;

use Illuminate\Foundation\Http\FormRequest;

class WorkedStreamRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'source' => ['required', 'string', 'max:100', 'regex:/^[A-Za-z0-9._:\-]+$/'],
            'item_id' => ['required', 'string', 'max:191'],
            'stream_id' => ['required', 'string', 'max:191'],
            /** Resolved URL, so the player can replay it without resolving again. */
            'stream_url' => ['sometimes', 'nullable', 'string', 'max:2048', 'url:http,https'],
            /** "m3u8" or "mp4". */
            'stream_type' => ['sometimes', 'nullable', 'string', 'max:20'],
            'extractor' => ['sometimes', 'nullable', 'string', 'max:50'],
            'quality' => ['sometimes', 'nullable', 'string', 'max:50'],
            'language' => ['sometimes', 'nullable', 'string', 'max:10'],
        ];
    }
}
