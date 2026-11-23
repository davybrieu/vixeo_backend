<?php

namespace App\Http\Resources\V1\Messages;

use App\Models\DirectMessage;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * A message of a conversation. Its attachments and reads must be loaded.
 *
 * @mixin DirectMessage
 */
class MessageResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $deleted = $this->isDeleted();

        return [
            'id' => (int) $this->id,
            'sender_profile_id' => (int) $this->sender_profile_id,
            /** The text; empty for an attachment alone. A deleted message says so. */
            'body' => $deleted ? __('This message was deleted.') : (string) $this->body,
            /** Deleted by its sender, for everybody. */
            'deleted' => $deleted,
            /**
             * Profiles that read it (read receipts).
             *
             * @var list<int>
             */
            'read_by' => $this->reads->pluck('profile_id')->map(fn ($id) => (int) $id)->values()->all(),
            /** ISO 8601, with the time zone. */
            'created_at' => (string) $this->created_at?->toIso8601String(),
            'attachments' => AttachmentResource::collection($this->attachments),
            /**
             * A title, a review or a list sent with it: `type` (`title`, `review`, `list`) and what
             * the card shows, as when it was sent.
             *
             * @var array<string, mixed>|null
             */
            'shared' => $deleted || ! is_array($this->shared) ? null : $this->shared,
        ];
    }
}
