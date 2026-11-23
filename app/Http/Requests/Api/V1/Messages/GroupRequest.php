<?php

namespace App\Http\Requests\Api\V1\Messages;

use App\Models\Conversation;
use Illuminate\Foundation\Http\FormRequest;

/** A new group: its name and its members (besides the profile creating it, its admin). */
class GroupRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            /** Name of the group. */
            'name' => ['required', 'string', 'min:2', 'max:80'],
            /** Usernames of the members (with or without @). */
            'usernames' => ['required', 'array', 'min:1', 'max:'.(Conversation::MAX_GROUP_MEMBERS - 1)],
            'usernames.*' => ['required', 'string', 'max:40', 'distinct:ignore_case'],
        ];
    }

    public function name(): string
    {
        return trim((string) $this->validated('name'));
    }

    /** @return list<string> */
    public function usernames(): array
    {
        return array_values(array_map('strval', $this->validated('usernames')));
    }
}
