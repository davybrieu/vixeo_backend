<?php

namespace App\Http\Requests\Api\V1;

use App\Services\ProfileSync;
use Illuminate\Foundation\Http\FormRequest;

class SyncPullRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            /** `cursor` returned by the previous pull. 0 (default) = full download. */
            'cursor' => ['sometimes', 'integer', 'min:0'],
            /** Max items per call (default 500, max 1000). Repeat while `has_more` is true. */
            'limit' => ['sometimes', 'integer', 'min:1', 'max:'.ProfileSync::MAX_PULL],
        ];
    }
}
