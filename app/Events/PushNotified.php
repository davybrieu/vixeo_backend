<?php

namespace App\Events;

use App\Services\Push\PushMessage;
use App\Services\Push\RealtimePush;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;

/**
 * A notification for apps without Firebase (Windows, docs/notifications.md): broadcast as
 * `notification` on the private channel of each app's registration (`push-devices.{id}`), shown by
 * the app itself (title, text, page opened by a click, Android channel's name for the kind).
 */
class PushNotified implements ShouldBroadcastNow
{
    /** @param  list<int>  $devices  ids of the push registrations */
    public function __construct(
        public readonly array $devices,
        public readonly PushMessage $message,
    ) {}

    /** @return list<PrivateChannel> */
    public function broadcastOn(): array
    {
        return array_map(fn (int $id) => new PrivateChannel(RealtimePush::CHANNEL.$id), $this->devices);
    }

    public function broadcastAs(): string
    {
        return 'notification';
    }

    /** @return array<string, string|null> */
    public function broadcastWith(): array
    {
        return $this->message->toRealtime();
    }
}
