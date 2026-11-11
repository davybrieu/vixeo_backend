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
        private ChannelLogos $logos,
        private LogoLibrary $library,
        private Epg $epg,
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
        // Logo candidates, best first: the logo library (clean, transparent PNGs), the programme
        // guide's icon, then the provider's. All relayed from our own origin (ChannelLogos): the
        // app never sees their address.
        // Then, when none of them knows the full name, the library's and the guide's logo of a
        // shorter name ("RMC SPORT LIVE 16" → RMC Sport).
        $channels = array_map(fn (array $c) => ['name' => ChannelName::clean($c['name']), 'logo' => array_values(array_filter([
            $this->library->logo($c['name']),
            $this->epg->icon($c['epg_channel_id'], $c['name']),
            $c['logo'],
            $this->library->logo($c['name'], fallback: true),
            $this->epg->icon(null, $c['name'], fallback: true),
        ]))] + $c, $this->providerChannels());

        // The guide id is a server-side detail.
        return array_map(function (array $c): array {
            unset($c['epg_channel_id']);

            return $c;
        }, $this->logos->rewrite($channels));
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
     * The channel's programme guide, from the one on now (empty without a guide).
     *
     * @return list<array{title: string, subtitle: string|null, description: string|null, category: string|null, image: string|null, start: string, end: string}>
     */
    public function programmes(string $channelId): array
    {
        foreach ($this->providerChannels() as $channel) {
            if ($channel['id'] === $channelId) {
                return $this->epg->programmes($channel['epg_channel_id'], $channel['name']);
            }
        }

        throw new RuntimeException(__('Channel not found.'));
    }

    /** Whether a programme guide is configured on the server. */
    public function hasGuide(): bool
    {
        return $this->epg->configured();
    }

    /**
     * Matching report used after refreshing the EPG and logo sources.
     *
     * @return array{total: int, with_logo: int, with_programme: int, epg_matches: array{id: int, name: int, approximate: int, unavailable: int}, without_logo: list<string>, without_programme: list<string>}
     */
    public function matchReport(): array
    {
        $providerChannels = $this->providerChannels();
        $channels = $this->channels();
        $logoIds = array_fill_keys(array_column(
            array_filter($channels, fn (array $channel) => $channel['logo'] !== null),
            'id'
        ), true);

        $withoutLogo = [];
        $withoutProgramme = [];
        $epgMatches = ['id' => 0, 'name' => 0, 'approximate' => 0, 'unavailable' => 0];
        foreach ($providerChannels as $channel) {
            if (! isset($logoIds[$channel['id']])) {
                $withoutLogo[] = ChannelName::clean($channel['name']);
            }
            $method = $this->epg->matchMethod($channel['epg_channel_id'], $channel['name']);
            if ($method === null) {
                $epgMatches['unavailable']++;
                $withoutProgramme[] = ChannelName::clean($channel['name']);
            } else {
                $epgMatches[$method]++;
            }
        }

        return [
            'total' => count($providerChannels),
            'with_logo' => count($providerChannels) - count($withoutLogo),
            'with_programme' => count($providerChannels) - count($withoutProgramme),
            'epg_matches' => $epgMatches,
            'without_logo_channels' => count($withoutLogo),
            'without_programme_channels' => count($withoutProgramme),
            'without_logo' => array_values(array_unique($withoutLogo)),
            'without_programme' => array_values(array_unique($withoutProgramme)),
        ];
    }

    /**
     * @return list<array{id: string, name: string, category_id: string, logo: string|null, number: int|null, epg_channel_id: string|null}>
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
                    'epg_channel_id' => null,
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
