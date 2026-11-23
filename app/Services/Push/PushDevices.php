<?php

namespace App\Services\Push;

use App\Models\Profile;
use App\Models\PushDevice;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\PersonalAccessToken;

/**
 * The apps that receive push notifications (docs/notifications.md): registered by the app
 * itself once signed in, one per API token (signing out removes it), with the profile in use and
 * whether it wants the new episodes. Sends a message to some of them, by Firebase Cloud Messaging
 * or, for the apps without Firebase (Windows), by the real-time channel, and forgets those FCM no
 * longer knows.
 */
class PushDevices
{
    public function __construct(private Fcm $fcm, private RealtimePush $realtime) {}

    /** At least one way to send is configured (FCM or Reverb). */
    public function available(): bool
    {
        return $this->fcm->configured() || $this->realtime->configured();
    }

    /** Notifications can be sent to the apps with this PushDevice target. */
    public function supports(string $target): bool
    {
        return $target === PushDevice::REALTIME ? $this->realtime->configured() : $this->fcm->configured();
    }

    /**
     * Registers (or updates) the app signed in with [$access]. [$registration]: its Firebase
     * installation ID or registration token, null for the real-time channel ([$target]).
     */
    public function register(
        User $user,
        PersonalAccessToken $access,
        ?Profile $profile,
        string $platform,
        string $target,
        ?string $registration,
        bool $newEpisodes,
        int $utcOffset,
        ?bool $social = null,
    ): PushDevice {
        return DB::transaction(function () use ($user, $access, $profile, $platform, $target, $registration, $newEpisodes, $utcOffset, $social) {
            $hash = $registration === null ? null : PushDevice::hashToken($registration);
            if ($hash !== null) {
                // The same app signed in again (new API token): its old registration goes.
                PushDevice::where('token_hash', $hash)->where('personal_access_token_id', '!=', $access->getKey())->delete();
            }

            $device = PushDevice::firstOrNew(['personal_access_token_id' => $access->getKey()]);
            $wanted = $device->exists && $device->new_episodes && $device->profile_id === $profile?->id;

            $device->fill([
                'user_id' => $user->id,
                'profile_id' => $profile?->id,
                'token' => $registration,
                'token_hash' => $hash,
                'target' => $target,
                'platform' => $platform,
                'new_episodes' => $newEpisodes,
                // Not sent (older apps): unchanged, on for a new registration.
                'social' => $social ?? ($device->exists ? $device->social : true),
                'utc_offset' => $utcOffset,
                'last_seen_at' => now(),
            ]);
            // Turned on (or another profile): the episodes already out are not announced.
            if (! $newEpisodes) {
                $device->new_episodes_since = null;
            } elseif (! $wanted || $device->new_episodes_since === null) {
                $device->new_episodes_since = now();
            }
            $device->save();

            return $device;
        });
    }

    public function forget(PersonalAccessToken $access): void
    {
        PushDevice::where('personal_access_token_id', $access->getKey())->delete();
    }

    public function current(PersonalAccessToken $access): ?PushDevice
    {
        return PushDevice::where('personal_access_token_id', $access->getKey())->first();
    }

    /**
     * Sends [$message] to [$devices]; those FCM no longer knows are deleted.
     *
     * @param  Collection<int, PushDevice>  $devices
     * @return array{sent: int, failed: int, removed: int}
     */
    public function send(Collection $devices, PushMessage $message): array
    {
        $report = ['sent' => 0, 'failed' => 0, 'removed' => 0];
        if ($devices->isEmpty()) {
            return $report;
        }

        [$realtime, $firebase] = $devices->partition(fn (PushDevice $device) => $device->target === PushDevice::REALTIME);
        $results = $this->realtime->send($realtime, $message);
        if ($firebase->isNotEmpty()) {
            $byRegistration = $this->fcm->configured()
                ? $this->fcm->send($firebase->mapWithKeys(fn (PushDevice $d) => [(string) $d->token => $d->fcmField()])->all(), $message)
                : [];
            foreach ($firebase as $device) {
                $results[$device->id] = $byRegistration[(string) $device->token] ?? PushResult::Failed;
            }
        }

        $invalid = [];
        foreach ($devices as $device) {
            match ($results[$device->id] ?? PushResult::Failed) {
                PushResult::Sent => $report['sent']++,
                PushResult::Failed => $report['failed']++,
                PushResult::Invalid => $invalid[] = $device->id,
            };
        }
        if ($invalid !== []) {
            $report['removed'] = PushDevice::whereKey($invalid)->delete();
        }

        return $report;
    }

    /** [$device] is the push registration of the app signed in as [$user] (its real-time channel). */
    public function belongsTo(User $user, int $device): bool
    {
        $access = $user->currentAccessToken();

        return $access instanceof PersonalAccessToken
            && PushDevice::whereKey($device)->where('personal_access_token_id', $access->getKey())->exists();
    }
}
