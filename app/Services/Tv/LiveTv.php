<?php

namespace App\Services\Tv;

use App\Services\Vavoo\VavooClient;
use App\Services\Xtream\XtreamClient;
use RuntimeException;

/**
 * Live TV behind one provider-neutral facade.
 *
 * The app never knows which provider serves the channels: it calls `/tv/categories`,
 * `/tv/channels`, `/tv/channels/{id}/stream` and always gets the same shape. This service picks
 * the configured provider (Vavoo first, then a personal Xtream panel), normalises both into the
 * same arrays and turns provider failures into neutral messages, so the provider name never
 * reaches the app. Mirrors, signatures and credentials never leave the server.
 */
class LiveTv
{
    public function __construct(
        private VavooClient $vavoo,
        private XtreamClient $xtream,
    ) {}

    /** Whether at least one provider is configured. */
    public function configured(): bool
    {
        return $this->vavoo->configured() || $this->xtream->configured();
    }

    /**
     * @return list<array{id: string, name: string, parent_id: string|null}>
     */
    public function categories(): array
    {
        return $this->run(function (): array {
            if (! $this->usingVavoo()) {
                return $this->xtream->liveCategories();
            }

            return array_map(
                fn (array $c) => [
                    'id' => (string) $c['id'],
                    'name' => (string) $c['name'],
                    'parent_id' => null,
                ],
                $this->vavoo->categories()
            );
        });
    }

    /**
     * @return list<array{id: string, name: string, category_id: string, logo: string|null, number: int|null, epg_channel_id: string|null}>
     */
    public function channels(): array
    {
        return $this->run(function (): array {
            if (! $this->usingVavoo()) {
                return $this->xtream->liveChannels();
            }

            return array_map(
                fn (array $c) => [
                    'id' => (string) $c['id'],
                    'name' => (string) $c['name'],
                    'category_id' => (string) $c['category_id'],
                    'logo' => $c['logo'] === null ? null : (string) $c['logo'],
                    'number' => null,
                    'epg_channel_id' => null,
                ],
                $this->vavoo->channels()
            );
        });
    }

    /**
     * Resolve a channel into playable streams.
     *
     * Several candidate URLs may come back for one channel (a signed provider answers with one
     * URL per mirror, and any of them may be down). They are kept in order so the app can fall
     * back to the next one when a stream refuses to play, instead of failing on the first.
     *
     * @return array{urls: list<string>, format: string, headers: array<string, string>}
     */
    public function stream(string $channelId): array
    {
        return $this->run(function () use ($channelId): array {
            if (! $this->usingVavoo()) {
                return $this->xtream->liveStream($channelId);
            }

            return [
                'urls' => $this->vavoo->streams($channelId),
                'format' => 'hls',
                'headers' => $this->vavoo->streamHeaders(),
            ];
        });
    }

    private function usingVavoo(): bool
    {
        return $this->vavoo->configured();
    }

    /**
     * Runs a provider call, mapping its errors to neutral messages.
     *
     * The provider name must never appear in an API message the app displays.
     */
    private function run(callable $call): array
    {
        try {
            return $call();
        } catch (RuntimeException $e) {
            match ($e->getMessage()) {
                __('Vavoo is not configured.'), __('Live TV is not configured.') => throw new RuntimeException(__('Live TV is not configured.')),
                __('Unable to reach the live TV server.') => throw new RuntimeException(__('Unable to reach the live TV server.')),
                __('Channel not found.') => throw new RuntimeException(__('Channel not found.')),
                default => throw new RuntimeException(__('Live TV is temporarily unavailable.')),
            };
        }
    }
}
