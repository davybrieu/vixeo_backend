<?php

namespace App\Services\Playback;

use App\Models\DeadStream;
use Illuminate\Support\Facades\Cache;

/**
 * Cache of streams reported as dead, shared by every account and kept 24 h (like ONYX's
 * IptvDeadServersCache). Lets the sources panel drop a broken stream right away instead of making
 * each viewer wait for the failure. See docs/lecture.md.
 */
class DeadStreams
{
    /** Lifetime of a report, in seconds (24 h). */
    public const TTL = 86400;

    private const KEY = 'dead_streams';

    /**
     * Dead stream keys ("<source>\0<stream_id>") still within their TTL.
     *
     * @return list<string>
     */
    public function active(): array
    {
        return Cache::remember(self::KEY, self::TTL, function () {
            $cutoff = now()->subSeconds(self::TTL);

            return DeadStream::where('dead_at', '>=', $cutoff)
                ->get(['source', 'stream_id'])
                ->map(fn (DeadStream $s) => self::key($s->source, $s->stream_id))
                ->all();
        });
    }

    public function isDead(string $source, string $streamId): bool
    {
        return in_array(self::key($source, $streamId), $this->active(), true);
    }

    public function markDead(string $source, string $streamId): void
    {
        DeadStream::updateOrCreate(
            ['source' => $source, 'stream_id' => $streamId],
            ['dead_at' => now()],
        );

        Cache::forget(self::KEY);
    }

    public function revive(string $source, string $streamId): void
    {
        DeadStream::where('source', $source)->where('stream_id', $streamId)->delete();

        Cache::forget(self::KEY);
    }

    /**
     * Drop dead streams from a list. Each item must expose `provider` (or `source`) and `url`
     * (or `stream_id`); anything else is kept as is.
     *
     * @template T of array<string, mixed>
     *
     * @param  list<T>  $sources
     * @return list<T>
     */
    public function filter(array $sources): array
    {
        $dead = array_flip($this->active());

        return array_values(array_filter($sources, function (array $source) use ($dead) {
            $provider = (string) ($source['provider'] ?? $source['source'] ?? '');
            $streamId = (string) ($source['url'] ?? $source['stream_id'] ?? '');

            return $provider === '' || $streamId === '' || ! isset($dead[self::key($provider, $streamId)]);
        }));
    }

    private static function key(string $source, string $streamId): string
    {
        return $source."\0".$streamId;
    }
}
