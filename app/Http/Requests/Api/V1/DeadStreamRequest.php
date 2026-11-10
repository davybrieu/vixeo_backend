<?php

namespace App\Http\Requests\Api\V1;

use Illuminate\Foundation\Http\FormRequest;

class DeadStreamRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            /** Provider of the stream ("frenchstream", "wiflix"…). */
            'source' => ['required', 'string', 'max:100', 'regex:/^[A-Za-z0-9._:\-]+$/'],
            /** Stream the app knows it by (usually the player page URL from the sources list). */
            'stream_id' => ['required', 'string', 'max:191'],
        ];
    }
}
