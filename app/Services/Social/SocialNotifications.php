<?php

namespace App\Services\Social;

use App\Models\Profile;
use App\Models\ProfileList;
use App\Models\PushDevice;
use App\Models\SocialComment;
use App\Models\SocialNotification;
use App\Models\TmdbRating;
use App\Services\Push\PushDevices;
use App\Services\Push\PushMessage;
use Illuminate\Support\Str;

/**
 * The social notifications of a profile (the app's « Notifications » page, docs/social.md): one
 * row per thing another profile did, the latest one kept (following twice, liking again: the same
 * notification, unread again). Pushed to the profile's apps that keep the social activity on
 * (`push_devices.social`), after the response.
 */
class SocialNotifications
{
    public function __construct(private PushDevices $devices) {}

    /**
     * [actor] did [type] for [recipient] (never for itself): about a review, a list or with a
     * comment. A like replaces the previous one; every comment is a notification of its own.
     */
    public function notify(
        Profile $recipient,
        Profile $actor,
        string $type,
        ?TmdbRating $rating = null,
        bool $push = true,
        ?ProfileList $list = null,
        ?SocialComment $comment = null,
    ): void {
        if ($recipient->id === $actor->id) {
            return;
        }
        if ($comment === null) {
            $this->forget($recipient, $actor, [$type], $rating, $list);
        }
        $notification = SocialNotification::create([
            'profile_id' => $recipient->id,
            'actor_profile_id' => $actor->id,
            'type' => $type,
            'tmdb_rating_id' => $rating?->id,
            'profile_list_id' => $list?->id,
            'social_comment_id' => $comment?->id,
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
    public function forget(Profile $recipient, Profile $actor, array $types, ?TmdbRating $rating = null, ?ProfileList $list = null): void
    {
        SocialNotification::query()
            ->where('profile_id', $recipient->id)
            ->where('actor_profile_id', $actor->id)
            ->whereIn('type', $types)
            ->when($rating !== null, fn ($query) => $query->where('tmdb_rating_id', $rating->id))
            ->when($list !== null, fn ($query) => $query->where('profile_list_id', $list->id))
            ->delete();
    }

    /**
     * A decision of the moderation (docs/moderation.md), told to the author of the content or to
     * who reported it: « Votre avis sur Dune a été masqué : … ».
     */
    public function moderation(Profile $profile, string $text): void
    {
        $notification = SocialNotification::create([
            'profile_id' => $profile->id,
            'actor_profile_id' => $profile->id,
            'type' => SocialNotification::MODERATION,
            'data' => ['text' => $text],
            'created_at' => now(),
        ]);
        $id = $notification->id;
        dispatch(fn () => app(self::class)->push($id))->afterResponse();
    }

    /** Sends a notification to its profile's apps (if it still exists). */
    public function push(int $id): void
    {
        $notification = SocialNotification::with(['actor', 'rating', 'list', 'comment'])->find($id);
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
            SocialNotification::REVIEW_COMMENT => ['Nouveau commentaire', sprintf('%s a commenté votre avis sur %s : « %s »', $who, $notification->rating?->title ?? 'un titre', self::excerpt($notification->comment?->body)), '/notifications'],
            SocialNotification::LIST_LIKE => ['J’aime', sprintf('%s aime votre liste « %s ».', $who, $notification->list?->title ?? ''), '/notifications'],
            SocialNotification::LIST_COMMENT => ['Nouveau commentaire', sprintf('%s a commenté votre liste « %s » : « %s »', $who, $notification->list?->title ?? '', self::excerpt($notification->comment?->body)), '/notifications'],
            SocialNotification::MODERATION => ['Modération', (string) ($notification->data['text'] ?? ''), '/notifications'],
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

    /** The start of a comment, for a notification. */
    public static function excerpt(?string $text, int $length = 80): string
    {
        return Str::limit(trim(preg_replace('/\s+/u', ' ', (string) $text) ?? ''), $length, '…');
    }
}
