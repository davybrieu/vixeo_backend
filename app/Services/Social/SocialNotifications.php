<?php

namespace App\Services\Social;

use App\Models\Profile;
use App\Models\PushDevice;
use App\Models\SocialNotification;
use App\Models\TmdbRating;
use App\Services\Push\PushDevices;
use App\Services\Push\PushMessage;

/**
 * The social notifications of a profile (the app's « Notifications » page, docs/social.md): one
 * row per thing another profile did, the latest one kept (following twice, liking again: the same
 * notification, unread again). Pushed to the profile's apps that keep the social activity on
 * (`push_devices.social`), after the response.
 */
class SocialNotifications
{
    public function __construct(private PushDevices $devices) {}

    /** [actor] did [type] for [recipient] (never for itself). */
    public function notify(Profile $recipient, Profile $actor, string $type, ?TmdbRating $rating = null, bool $push = true): void
    {
        if ($recipient->id === $actor->id) {
            return;
        }
        $this->forget($recipient, $actor, [$type], $rating);
        $notification = SocialNotification::create([
            'profile_id' => $recipient->id,
            'actor_profile_id' => $actor->id,
            'type' => $type,
            'tmdb_rating_id' => $rating?->id,
            'read_at' => $push ? null : now(),
            'created_at' => now(),
        ]);

        if ($push) {
            $id = $notification->id;
            dispatch(fn () => app(self::class)->push($id))->afterResponse();
        }
    }

    /**
     * Removes what [actor] did for [recipient] (unfollowed, request withdrawn, like taken back).
     *
     * @param  list<string>  $types
     */
    public function forget(Profile $recipient, Profile $actor, array $types, ?TmdbRating $rating = null): void
    {
        SocialNotification::query()
            ->where('profile_id', $recipient->id)
            ->where('actor_profile_id', $actor->id)
            ->whereIn('type', $types)
            ->when($rating !== null, fn ($query) => $query->where('tmdb_rating_id', $rating->id))
            ->delete();
    }

    /** Sends a notification to its profile's apps (if it still exists). */
    public function push(int $id): void
    {
        $notification = SocialNotification::with(['actor', 'rating'])->find($id);
        if ($notification === null || ! $this->devices->available()) {
            return;
        }
        $devices = PushDevice::query()->where('profile_id', $notification->profile_id)->where('social', true)->get();
        if ($devices->isNotEmpty()) {
            $this->devices->send($devices, self::message($notification));
        }
    }

    /** What the apps show: « Léa (@lea) aime votre avis sur Fight Club. » */
    public static function message(SocialNotification $notification): PushMessage
    {
        $actor = $notification->actor;
        $who = sprintf('%s (@%s)', $actor->name, $actor->username);
        [$title, $body, $path] = match ($notification->type) {
            SocialNotification::FOLLOW => ['Nouvel abonné', "{$who} vous suit.", "/membre/{$actor->username}"],
            SocialNotification::FOLLOW_REQUEST => ['Demande d’abonnement', "{$who} demande à vous suivre.", '/notifications'],
            SocialNotification::FOLLOW_ACCEPTED => ['Demande acceptée', "{$who} a accepté votre demande d’abonnement.", "/membre/{$actor->username}"],
            default => ['J’aime', sprintf('%s aime votre avis sur %s.', $who, $notification->rating?->title ?? 'un titre'), '/notifications'],
        };

        return new PushMessage(
            title: $title,
            body: $body,
            path: $path,
            channel: PushMessage::SOCIAL,
            tag: "social-{$notification->id}",
        );
    }
}
