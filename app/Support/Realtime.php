<?php

namespace App\Support;

use Illuminate\Broadcasting\Broadcasters\PusherBroadcaster;
use Illuminate\Support\Facades\Broadcast;
use RuntimeException;

/**
 * Laravel Reverb, the WebSocket server of the apps (docs/reverb.md): the messages of a conversation
 * and the notifications of the apps without Firebase (Windows, docs/notifications.md).
 */
final class Realtime
{
    /** Reverb is the broadcaster, with its application key: without it nothing is broadcast. */
    public static function configured(): bool
    {
        return config('broadcasting.default') === 'reverb' && filled(config('broadcasting.connections.reverb.key'));
    }

    /**
     * The channels someone listens to right now, among those starting with [$prefix] (Reverb's
     * HTTP API, as Pusher's: `GET /apps/{id}/channels`).
     *
     * @return list<string>
     */
    public static function occupiedChannels(string $prefix): array
    {
        $broadcaster = Broadcast::connection('reverb');
        if (! $broadcaster instanceof PusherBroadcaster) {
            throw new RuntimeException('Reverb is not the broadcaster.');
        }

        $channels = $broadcaster->getPusher()->getChannels(['filter_by_prefix' => $prefix])->channels;

        return array_values(array_map('strval', array_keys((array) $channels)));
    }
}
