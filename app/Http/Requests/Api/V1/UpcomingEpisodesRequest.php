<?php

namespace App\Http\Requests\Api\V1;

use Illuminate\Foundation\Http\FormRequest;

class UpcomingEpisodesRequest extends FormRequest
{
    /** TV shows asked at once, at most. */
    public const MAX = 100;

    public function rules(): array
    {
        return [
            /** TMDb ids of TV shows, comma-separated: `1399,66732` (100 at most). */
            'ids' => ['required', 'string', 'max:1000', 'regex:/^\d{1,9}(,\d{1,9}){0,'.(self::MAX - 1).'}$/'],
        ];
    }

    /** @return list<int> */
    public function showIds(): array
    {
        return array_values(array_unique(array_map('intval', explode(',', (string) $this->validated('ids')))));
    }
}
