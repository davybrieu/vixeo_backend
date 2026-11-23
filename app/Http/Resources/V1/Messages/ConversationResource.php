<?php

namespace App\Http\Resources\V1\Messages;

use App\Http\Middleware\ResolveProfile;
use App\Models\Conversation;
use App\Models\ConversationMember;
use App\Services\Messaging\Conversations;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * A conversation in the list of the active profile (« Messages »): its active members and their
 * profiles, its last message and `unread_count` must be loaded (Conversations::inbox).
 *
 * @mixin Conversation
 */
class ConversationResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $me = ResolveProfile::profile($request);
        $others = $this->activeMembers->reject(fn (ConversationMember $member) => $member->profile_id === $me?->id)->values();
        $direct = $others->first();
        $last = $this->lastMessage;

        return [
            'id' => (int) $this->id,
            /** `direct` (two profiles) or `group`. */
            'type' => (string) $this->type,
            /** Name of a group; null for a direct conversation. */
            'name' => $this->name === null ? null : (string) $this->name,
            /** The members besides the active profile. */
            'participants' => ChatMemberResource::collection($others),
            /** The other profile of a direct conversation. */
            'participant' => ! $this->isGroup() && $direct !== null ? new ChatMemberResource($direct) : null,
            'last_message' => $last === null ? null : [
                'id' => (int) $last->id,
                'body' => $last->isDeleted() ? __('Message deleted')
                    : ((string) $last->body !== '' ? (string) $last->body : Conversations::sharedPreview(is_array($last->shared) ? $last->shared : null)),
                'sender_profile_id' => (int) $last->sender_profile_id,
                /** ISO 8601, with the time zone. */
                'created_at' => (string) $last->created_at?->toIso8601String(),
            ],
            /** Messages of the others the active profile has not read. */
            'unread_count' => (int) ($this->unread_count ?? 0),
            /** `pending` (a request waiting for an answer) or `accepted`. */
            'status' => (string) $this->status,
            /** A request the active profile received (listed by GET /messages/requests). */
            'is_request' => $me !== null && $this->isRequestFor($me),
            /** Last message, or creation of the conversation (ISO 8601). */
            'updated_at' => (string) ($this->last_message_at ?? $this->created_at)?->toIso8601String(),
        ];
    }
}
