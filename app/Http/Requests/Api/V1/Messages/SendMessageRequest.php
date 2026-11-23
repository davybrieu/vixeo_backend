<?php

namespace App\Http\Requests\Api\V1\Messages;

use Illuminate\Foundation\Http\FormRequest;

class SendMessageRequest extends FormRequest
{
    /** Longest message, in characters. */
    public const MAX_LENGTH = 5000;

    public function rules(): array
    {
        return [
            /** The text (spaces around it removed). */
            'body' => ['required', 'string', 'max:'.self::MAX_LENGTH],
        ];
    }

    public function body(): string
    {
        return trim((string) $this->validated('body'));
    }
}
