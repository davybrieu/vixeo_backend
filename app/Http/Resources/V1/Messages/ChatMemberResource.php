<?php

namespace App\Http\Resources\V1\Messages;

use App\Models\ConversationMember;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * A member of a conversation: its profile and its role.
 *
 * @mixin ConversationMember
 */
class ChatMemberResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            ...(new ChatProfileResource($this->profile))->toArray($request),
            /** `admin` (runs a group) or `member`. */
            'role' => (string) $this->role,
        ];
    }
}
