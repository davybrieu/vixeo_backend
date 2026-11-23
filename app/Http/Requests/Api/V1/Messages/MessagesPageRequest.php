<?php

namespace App\Http\Requests\Api\V1\Messages;

use Illuminate\Foundation\Http\FormRequest;

class MessagesPageRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            /** Id of the oldest message shown: the page of messages before it (to scroll up). */
            'before' => ['sometimes', 'nullable', 'integer', 'min:1'],
        ];
    }

    public function before(): ?int
    {
        $before = $this->validated('before');

        return $before === null ? null : (int) $before;
    }
}
