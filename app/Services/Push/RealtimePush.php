<?php

namespace App\Services\Push;

use App\Events\PushNotified;
use App\Models\PushDevice;
use App\Support\Realtime;
use Illuminate\Support\Collection;
use Throwable;

/**
 * The notifications of the apps without Firebase (Windows, docs/notifications.md): a Reverb event on
 * the private channel of the app's registration (`push-devices.{id}`), shown by the app while it
 * runs. Sent only to the apps listening right now (Reverb lists the occupied channels); the others
 * get "failed", so the new episodes are announced at a later run, once the app is open.
 */
class RealtimePush
{
    /** Channel of a registration, followed by its id (authorized in routes/channels.php). */
    public const CHANNEL = 'push-devices.';

    public function configured(): bool
    {
        return Realtime::configured();
    }

    /**
     * Sends [$message] to [$devices]. The answer, by registration id: sent, or failed (app closed,
     * Reverb down or not configured).
     *
     * @param  Collection<int, PushDevice>  $devices
     * @return array<int, PushResult>
     */
    public function send(Collection $devices, PushMessage $message): array
    {
        /** @var list<int> $ids */
        $ids = $devices->map(fn (PushDevice $device) => (int) $device->id)->values()->all();
        if ($ids === []) {
            return [];
        }
        if (! $this->configured()) {
            return array_fill_keys($ids, PushResult::Failed);
        }

        try {
            $listening = array_values(array_intersect($ids, $this->listening()));
            if ($listening !== []) {
                event(new PushNotified($listening, $message));
            }
        } catch (Throwable $exception) {
            report($exception);

            return array_fill_keys($ids, PushResult::Failed);
        }

        $results = [];
        foreach ($ids as $id) {
            $results[$id] = in_array($id, $listening, true) ? PushResult::Sent : PushResult::Failed;
        }

        return $results;
    }

    /**
     * The registrations whose app listens to its channel right now.
     *
     * @return list<int>
     */
    public function listening(): array
    {
        $prefix = 'private-'.self::CHANNEL;
        $ids = [];
        foreach (Realtime::occupiedChannels($prefix) as $channel) {
            $id = substr($channel, strlen($prefix));
            if (ctype_digit($id)) {
                $ids[] = (int) $id;
            }
        }

        return $ids;
    }
}
