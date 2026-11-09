<?php

namespace App\Http\Requests\Api\V1;

use App\Enums\MediaType;
use Illuminate\Foundation\Http\FormRequest;

class CatalogTitlesRequest extends FormRequest
{
    /** Titles asked at once, at most. */
    public const MAX = 100;

    public function rules(): array
    {
        return [
            /** Titles, comma-separated: `movie:550,tv:1399` (100 at most). */
            'ids' => ['required', 'string', 'max:2000', 'regex:/^(movie|tv):\d{1,9}(,(movie|tv):\d{1,9}){0,'.(self::MAX - 1).'}$/'],
        ];
    }

    /** @return list<array{MediaType, int}> */
    public function titles(): array
    {
        $titles = [];
        foreach (array_unique(explode(',', (string) $this->validated('ids'))) as $key) {
            [$type, $id] = explode(':', $key);
            $titles[] = [MediaType::from($type), (int) $id];
        }

        return $titles;
    }
}
