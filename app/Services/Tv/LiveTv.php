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
     * @return list<array{id: string, name: string, category_id: string, logo: string|null, number: int|null}>
     */
    public function channels(): array
    {
        return $this->providerChannels();
    }

    /**
     * One channel by id (its page in the app).
     *
     * @return array{id: string, name: string, category_id: string, logo: string|null, number: int|null}
     */
    public function channel(string $channelId): array
    {
        foreach ($this->channels() as $channel) {
            if ($channel['id'] === $channelId) {
                return $channel;
            }
        }

        throw new RuntimeException(__('Channel not found.'));
    }

    /**
     * @return list<array{id: string, name: string, category_id: string, logo: string|null, number: int|null}>
     */
    private function providerChannels(): array
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
                ],
                $this->vavoo->channels()
            );
        });
    }

    /**
     * @return array{url: string, format: string, headers: array<string, string>}
     */
    public function stream(string $channelId): array
    {
        return $this->run(function () use ($channelId): array {
            if (! $this->usingVavoo()) {
                return $this->xtream->liveStream($channelId);
            }

            return [
                'url' => $this->vavoo->streams($channelId)[0],
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
