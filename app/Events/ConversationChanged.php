<?php

namespace App\Events;

use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;

/**
 * A conversation changed (docs/messagerie.md, docs/reverb.md): told to the apps that show it, on its
 * private channel `messages.{id}`, as `message.sent`, `message.deleted`, `message.read` (someone
 * read the messages: read receipts) or `message.updated` (request accepted, group changed). The apps
 * then read the conversation again from the API: the event only says when.
 */
class ConversationChanged implements ShouldBroadcastNow
{
    public function __construct(
        public readonly int $conversationId,
        public readonly string $change,
        public readonly ?int $messageId = null,
        public readonly ?int $senderProfileId = null,
        public readonly string $body = '',
    ) {}

    /** @return list<PrivateChannel> */
    public function broadcastOn(): array
    {
        return [new PrivateChannel('messages.'.$this->conversationId)];
    }

    public function broadcastAs(): string
    {
        return 'message.'.$this->change;
    }

    /** @return array<string, int|string|null> */
    public function broadcastWith(): array
    {
        return [
            'conversation_id' => $this->conversationId,
            'message_id' => $this->messageId,
            'sender_profile_id' => $this->senderProfileId,
            // The text of a new message (the first apps refreshed on `message.sent` only).
            'body' => $this->body,
        ];
    }
}
