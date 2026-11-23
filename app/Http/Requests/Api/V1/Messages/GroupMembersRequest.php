<?php

namespace App\Http\Requests\Api\V1\Messages;

use App\Models\Conversation;
use Illuminate\Foundation\Http\FormRequest;

class GroupMembersRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            /** Usernames of the members to add (with or without @). */
            'usernames' => ['required', 'array', 'min:1', 'max:'.(Conversation::MAX_GROUP_MEMBERS - 1)],
            'usernames.*' => ['required', 'string', 'max:40', 'distinct:ignore_case'],
        ];
    }

    /** @return list<string> */
    public function usernames(): array
    {
        return array_values(array_map('strval', $this->validated('usernames')));
    }
}
