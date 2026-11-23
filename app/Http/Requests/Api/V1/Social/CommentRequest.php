<?php

namespace App\Http\Requests\Api\V1\Social;

use App\Models\SocialComment;
use Illuminate\Foundation\Http\FormRequest;

class CommentRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            /** The comment (spaces around it removed). */
            'body' => ['required', 'string', 'max:'.SocialComment::MAX_BODY],
            /** It reveals the story: hidden behind « Afficher » for the others. */
            'contains_spoilers' => ['sometimes', 'boolean'],
        ];
    }

    protected function prepareForValidation(): void
    {
        if (is_string($this->input('body'))) {
            $this->merge(['body' => trim($this->input('body'))]);
        }
    }

    public function body(): string
    {
        return (string) $this->validated('body');
    }
}
