<?php

namespace App\Http\Resources\V1\Messages;

use App\Models\Conversation;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * A request the active profile received: who sent it and its first message. Its requester and
 * first message must be loaded (Conversations::requests).
 *
 * @mixin Conversation
 */
class MessageRequestResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $first = $this->firstMessage;

        return [
            'conversation_id' => (int) $this->id,
            'sender' => $this->requester === null ? null : new ChatProfileResource($this->requester),
            /** Its first message, or null before any. */
            'preview' => $first === null || $first->isDeleted() ? null : (string) $first->body,
        ];
    }
}
