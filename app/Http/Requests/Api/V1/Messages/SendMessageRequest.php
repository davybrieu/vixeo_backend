<?php

namespace App\Http\Requests\Api\V1\Messages;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SendMessageRequest extends FormRequest
{
    /** Longest message, in characters. */
    public const MAX_LENGTH = 5000;

    public function rules(): array
    {
        return [
            /** The text (spaces around it removed); may be left out with `share`. */
            'body' => ['required_without:share', 'nullable', 'string', 'max:'.self::MAX_LENGTH],
            /** A title, a review or a list sent with the message (« Recommander à un ami »). */
            'share' => ['sometimes', 'array:type,id'],
            /** `title`, `review` or `list`. */
            'share.type' => ['required_with:share', 'string', Rule::in(['title', 'review', 'list'])],
            /** `movie:550` for a title, the `review_id` of a review, the id of a list. */
            'share.id' => ['required_with:share', 'string', 'max:40'],
        ];
    }

    public function body(): string
    {
        return trim((string) $this->validated('body'));
    }

    /** @return array{type: string, id: string}|null */
    public function share(): ?array
    {
        $share = $this->validated('share');

        return is_array($share) ? ['type' => (string) $share['type'], 'id' => (string) $share['id']] : null;
    }
}
