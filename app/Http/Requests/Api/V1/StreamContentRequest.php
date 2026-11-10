<?php

namespace App\Http\Requests\Api\V1;

use Illuminate\Foundation\Http\FormRequest;

/** Content a stream belongs to (source + item_id, the profile item convention). */
class StreamContentRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            /** Source of the content, same as the profile item ("tmdb", "fast:pluto"…). */
            'source' => ['required', 'string', 'max:100', 'regex:/^[A-Za-z0-9._:\-]+$/'],
            /** Id of the content inside the source ("movie:550", "episode:1399:1:3"…). */
            'item_id' => ['required', 'string', 'max:191'],
        ];
    }
}
