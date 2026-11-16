<?php

namespace App\Services\Push;

/**
 * A notification as the apps show it: a title, a text, and the page of the app a tap opens.
 */
final readonly class PushMessage
{
    /** Android channel of the new episodes (created by the app). */
    public const EPISODES = 'episodes';

    /** Android channel of the other messages (announcements, tests). */
    public const GENERAL = 'general';

    public function __construct(
        public string $title,
        public string $body,
        /** Page of the app, e.g. /titre/tv/1399 (null: the app opens normally). */
        public ?string $path = null,
        public string $channel = self::GENERAL,
        /** A notification with the same tag replaces the previous one on the device. */
        public ?string $tag = null,
        /** Large image (https). */
        public ?string $image = null,
    ) {}

    /**
     * The `message` of FCM's HTTP v1 API, without its token.
     *
     * @return array<string, mixed>
     */
    public function toFcm(): array
    {
        $notification = ['title' => $this->title, 'body' => $this->body];
        if ($this->image !== null) {
            $notification['image'] = $this->image;
        }

        return array_filter([
            'notification' => $notification,
            // Read by the app when the notification is tapped.
            'data' => $this->path === null ? null : ['path' => $this->path],
            'android' => [
                'priority' => 'high',
                'notification' => array_filter([
                    'channel_id' => $this->channel,
                    // White V of the logo, Vixeo green (the app's resources).
                    'icon' => 'ic_notification',
                    'color' => '#2EE58A',
                    'tag' => $this->tag,
                ]),
            ],
            'apns' => ['payload' => ['aps' => array_filter(['sound' => 'default', 'thread-id' => $this->tag])]],
        ], fn ($value) => $value !== null);
    }
}
