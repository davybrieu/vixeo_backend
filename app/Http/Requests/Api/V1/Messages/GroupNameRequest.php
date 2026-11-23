<?php

namespace App\Http\Requests\Api\V1\Messages;

use Illuminate\Foundation\Http\FormRequest;

class GroupNameRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            /** New name of the group. */
            'name' => ['required', 'string', 'min:2', 'max:80'],
        ];
    }

    public function name(): string
    {
        return trim((string) $this->validated('name'));
    }
}
