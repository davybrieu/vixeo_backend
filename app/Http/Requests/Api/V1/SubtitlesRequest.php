<?php

namespace App\Http\Requests\Api\V1;

use App\Http\Middleware\ResolveProfile;
use App\Support\Languages;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class SubtitlesRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            /** Comma-separated languages, preferred first ("fr,en"). Default: the profile's subtitle language, else French. */
            'languages' => ['sometimes', 'string', 'max:100'],
        ];
    }

    public function after(): array
    {
        return [function (Validator $validator) {
            if ($this->has('languages') && array_diff($this->languages(), Languages::SUBTITLES) !== []) {
                $validator->errors()->add('languages', __('validation.in', ['attribute' => __('validation.attributes.languages')]));
            }
        }];
    }

    /** @return list<string> */
    public function languages(): array
    {
        if ($this->filled('languages')) {
            return array_values(array_filter(array_map(trim(...), explode(',', (string) $this->input('languages')))));
        }

        return [ResolveProfile::profile($this)?->subtitle_language ?? 'fr'];
    }
}
