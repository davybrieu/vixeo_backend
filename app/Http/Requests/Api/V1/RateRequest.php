<?php

namespace App\Http\Requests\Api\V1;

use App\Models\TmdbRating;
use Illuminate\Foundation\Http\FormRequest;

class RateRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            /** Rating from 0.5 to 10, by half points (TMDb scale). */
            'value' => ['required', 'numeric', 'min:0.5', 'max:10', 'multiple_of:0.5'],
            /** Optional comment, kept by Vixeo only (TMDb's API takes no written review). Null or empty removes it; left out, it is kept. */
            'comment' => ['sometimes', 'nullable', 'string', 'max:'.TmdbRating::MAX_COMMENT],
        ];
    }

    protected function prepareForValidation(): void
    {
        if (is_string($this->input('comment'))) {
            $comment = trim($this->input('comment'));
            $this->merge(['comment' => $comment === '' ? null : $comment]);
        }
    }

    public function value(): float
    {
        return (float) $this->validated('value');
    }

    /** The comment was sent (null: remove it). */
    public function hasComment(): bool
    {
        return array_key_exists('comment', $this->validated());
    }

    public function comment(): ?string
    {
        $comment = $this->validated('comment');

        return is_string($comment) ? $comment : null;
    }
}
