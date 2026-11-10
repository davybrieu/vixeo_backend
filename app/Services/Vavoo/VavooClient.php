<?php

namespace App\Services\Vavoo;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\RequestException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * Vavoo live TV through its "MediaHubMX" API, ported from the ONYX app (com.streamflixreborn
 * .streamflix.providers.VavooProvider). The API is signed: an "addonSig" obtained from a ping
 * endpoint authorises the catalog and resolve calls. Mirrors, signature and versions never leave
 * the server; apps only receive the resolved HLS URL and the User-Agent to send. See docs/tv-direct.md.
 */
class VavooClient
{
    /** @return list<string> */
    private function baseUrls(): array
    {
        return array_values(config('services.vavoo.base_urls', []));
    }

    /** @return list<string> */
    private function pingUrls(): array
    {
        return array_values(config('services.vavoo.ping_urls', []));
    }

    public function configured(): bool
    {
        return $this->baseUrls() !== [] && $this->pingUrls() !== [];
    }

    /**
     * MediaHubMX signature, cached for its lifetime (ONYX refreshes it every 5 min).
     */
    public function signature(): string
    {
        $this->ensureConfigured();

        return (string) Cache::remember('vavoo:signature', (int) config('services.vavoo.ttl.signature'), function () {
            foreach ($this->pingUrls() as $url) {
                try {
                    $response = $this->client()->post($url, $this->pingBody());
                    if ($response->successful()) {
                        $signature = (string) $response->json('addonSig', '');
                        if ($signature !== '') {
                            return $signature;
                        }
                    }
                } catch (ConnectionException|RequestException) {
                    // Try the next mirror.
                }
            }

            throw new RuntimeException(__('Vavoo is temporarily unavailable.'));
        });
    }

    /**
     * All live channels of the configured country, cached.
     *
     * @return list<array{id: string, name: string, category_id: string, logo: string|null, url: string}>
     */
    public function channels(): array
    {
        $this->ensureConfigured();

        $country = (string) config('services.vavoo.country', 'France');

        return Cache::remember('vavoo:channels:'.md5($country), (int) config('services.vavoo.ttl.catalog'), function () use ($country) {
            $headers = $this->catalogHeaders($this->signature());
            $channels = [];
            $seen = [];

            foreach ($this->baseUrls() as $base) {
                try {
                    $page = $this->loadCatalog(rtrim($base, '/').'/mediahubmx-catalog.json', $headers, $country);
                } catch (ConnectionException|RequestException|RuntimeException) {
                    continue;
                }

                foreach ($page as $channel) {
                    if (! isset($seen[$channel['id']])) {
                        $seen[$channel['id']] = true;
                        $channels[] = $channel;
                    }
                }

                if ($channels !== []) {
                    break;
                }
            }

            if ($channels === []) {
                throw new RuntimeException(__('Vavoo is temporarily unavailable.'));
            }

            usort($channels, fn (array $a, array $b) => strcasecmp($a['name'], $b['name']));

            return $channels;
        });
    }

    /**
     * Live channels, optionally filtered by category.
     *
     * @return list<array{id: string, name: string, category_id: string, logo: string|null, url: string}>
     */
    public function channelsByCategory(?string $categoryId = null): array
    {
        $channels = $this->channels();

        if ($categoryId !== null && $categoryId !== '') {
            $channels = array_values(array_filter(
                $channels,
                fn (array $c) => $c['category_id'] === $categoryId
            ));
        }

        return $channels;
    }

    /**
     * @return list<array{id: string, name: string}>
     */
    public function categories(): array
    {
        $ids = [];
        foreach ($this->channels() as $channel) {
            $ids[$channel['category_id']] = true;
        }

        $ids = array_keys($ids);
        sort($ids);

        return array_map(fn (string $name) => ['id' => $name, 'name' => $name], $ids);
    }

    /**
     * Resolve a channel into one or more playable HLS URLs, asking every mirror in parallel and
     * keeping the first distinct answers (ONYX keeps 3).
     *
     * @return list<string>
     */
    public function streams(string $channelId): array
    {
        $this->ensureConfigured();

        $channel = $this->findChannel($channelId);
        if ($channel === null) {
            throw new RuntimeException(__('Channel not found.'));
        }

        $max = max(1, (int) config('services.vavoo.max_streams', 3));
        $headers = $this->catalogHeaders($this->signature());
        $body = [
            'language' => 'fr',
            'region' => 'US',
            'url' => $channel['url'],
            'clientVersion' => (string) config('services.vavoo.resolve_client_version', '3.0.2'),
        ];

        $responses = Http::pool(fn ($pool) => array_map(
            fn (string $base) => $pool->as($base)
                ->withHeaders($headers)
                ->timeout((int) config('services.vavoo.timeout', 20))
                ->post(rtrim($base, '/').'/mediahubmx-resolve.json', $body),
            $this->baseUrls()
        ));

        $streams = [];
        foreach ($responses as $response) {
            if (! $response instanceof Response || $response->failed()) {
                continue;
            }

            $url = $this->extractStreamUrl($response->json());
            if ($url !== null && ! in_array($url, $streams, true)) {
                $streams[] = $url;
            }

            if (count($streams) >= $max) {
                break;
            }
        }

        if ($streams === []) {
            throw new RuntimeException(__('Vavoo is temporarily unavailable.'));
        }

        return $streams;
    }

    /**
     * Headers the player must send to play a Vavoo stream.
     *
     * @return array<string, string>
     */
    public function streamHeaders(): array
    {
        return ['User-Agent' => (string) config('services.vavoo.user_agent', 'MediaHubMX/2')];
    }

    public function forgetCache(): void
    {
        Cache::forget('vavoo:signature');
        Cache::forget('vavoo:channels:'.md5((string) config('services.vavoo.country', 'France')));
    }

    /**
     * @return array{id: string, name: string, category_id: string, logo: string|null, url: string}|null
     */
    private function findChannel(string $channelId): ?array
    {
        foreach ($this->channels() as $channel) {
            if ($channel['id'] === $channelId) {
                return $channel;
            }
        }

        // The cache may be stale: refresh once.
        $this->forgetCache();

        foreach ($this->channels() as $channel) {
            if ($channel['id'] === $channelId) {
                return $channel;
            }
        }

        return null;
    }

    /**
     * Walk the paginated catalog of one mirror.
     *
     * @param  array<string, string>  $headers
     * @return list<array{id: string, name: string, category_id: string, logo: string|null, url: string}>
     */
    private function loadCatalog(string $url, array $headers, string $country): array
    {
        $channels = [];
        $cursor = 0;

        for ($page = 0; $page < 50; $page++) {
            $response = $this->client()
                ->withHeaders($headers)
                ->post($url, [
                    'language' => 'de',
                    'region' => 'AT',
                    'catalogId' => 'iptv',
                    'id' => 'iptv',
                    'adult' => false,
                    'search' => '',
                    'sort' => 'name',
                    'filter' => ['group' => $country],
                    'cursor' => $cursor,
                    'clientVersion' => (string) config('services.vavoo.catalog_client_version', '3.1.0'),
                ]);

            if ($response->failed()) {
                throw new RuntimeException(__('Vavoo is temporarily unavailable.'));
            }

            $items = $response->json('items');
            if (! is_array($items)) {
                break;
            }

            foreach ($items as $item) {
                if (! is_array($item) || ($item['type'] ?? null) !== 'iptv') {
                    continue;
                }

                $channel = $this->channel($item);
                if ($channel !== null) {
                    $channels[] = $channel;
                }
            }

            $next = $response->json('nextCursor');
            if (! is_int($next) && ! (is_string($next) && ctype_digit($next))) {
                break;
            }

            $cursor = (int) $next;
            if ($cursor < 0) {
                break;
            }
        }

        return $channels;
    }

    /**
     * @param  array<string, mixed>  $item
     * @return array{id: string, name: string, category_id: string, logo: string|null, url: string}|null
     */
    private function channel(array $item): ?array
    {
        $url = (string) ($item['url'] ?? '');
        $name = (string) ($item['name'] ?? '');
        if ($url === '' || $name === '') {
            return null;
        }

        $ids = is_array($item['ids'] ?? null) ? $item['ids'] : [];
        $id = (string) ($ids['id'] ?? $item['id'] ?? $url);
        $group = (string) ($item['group'] ?? '');
        $logo = (string) ($item['logo'] ?? '');

        return [
            'id' => $id,
            'name' => $name,
            'category_id' => $this->category($name, $group),
            'logo' => $logo !== '' ? $logo : null,
            'url' => $url,
        ];
    }

    /**
     * ONYX sorts channels into French categories from their name (VavooProvider.guessCategoryByName).
     */
    private function category(string $name, string $group): string
    {
        $haystack = mb_strtolower($name.' '.$group);

        foreach (self::CATEGORIES as $category => $keywords) {
            foreach ($keywords as $keyword) {
                if (str_contains($haystack, $keyword)) {
                    return $category;
                }
            }
        }

        return 'Généralistes';
    }

    /**
     * @return array<string, string>
     */
    private function catalogHeaders(string $signature): array
    {
        return [
            'mediahubmx-signature' => $signature,
            'user-agent' => (string) config('services.vavoo.user_agent', 'MediaHubMX/2'),
            'Accept-Language' => 'fr',
        ];
    }

    private function client(): PendingRequest
    {
        return Http::acceptJson()
            ->withUserAgent((string) config('services.vavoo.user_agent', 'MediaHubMX/2'))
            ->timeout((int) config('services.vavoo.timeout', 20));
    }

    /**
     * @return array<string, mixed>
     */
    private function pingBody(): array
    {
        $now = (int) (microtime(true) * 1000);

        return [
            'token' => '',
            'reason' => 'app-focus',
            'locale' => 'fr',
            'theme' => 'dark',
            'metadata' => [
                'device' => ['type' => 'phone', 'uniqueId' => 'vixeo-server'],
                'os' => ['name' => 'android', 'version' => '10', 'abis' => ['arm64-v8a'], 'host' => 'android'],
                'app' => ['platform' => 'android'],
                'version' => ['package' => 'net.vypn.app', 'binary' => '1.4.1', 'js' => '1.4.1'],
            ],
            'appFocusTime' => 0,
            'playerActive' => false,
            'playDuration' => 0,
            'devMode' => false,
            'hasAddon' => true,
            'castConnected' => false,
            'package' => 'net.vypn.app',
            'version' => '1.4.1',
            'process' => 'app',
            'firstAppStart' => $now,
            'lastAppStart' => $now,
            'ipLocation' => null,
            'adblockEnabled' => true,
            'migrationApplied' => false,
            'migrationTargetInstalled' => false,
            'proxy' => ['supported' => ['ss'], 'engine' => 'Mu', 'ssVersion' => '2022', 'enabled' => false, 'autoServer' => true, 'id' => ''],
            'iap' => ['supported' => false, 'error' => ''],
        ];
    }

    /**
     * The resolve endpoint answers `{url}` or `[{url}]`.
     */
    private function extractStreamUrl(mixed $json): ?string
    {
        if (is_string($json)) {
            return $json !== '' ? $json : null;
        }

        if (! is_array($json)) {
            return null;
        }

        if (isset($json[0]) && is_array($json[0])) {
            $url = $json[0]['url'] ?? null;

            return is_string($url) && $url !== '' ? $url : null;
        }

        $url = $json['url'] ?? $json['streamUrl'] ?? null;

        return is_string($url) && $url !== '' ? $url : null;
    }

    private function ensureConfigured(): void
    {
        if (! $this->configured()) {
            throw new RuntimeException(__('Vavoo is not configured.'));
        }
    }

    /** French channel categories, keywords taken from ONYX's guessCategoryByName. */
    private const CATEGORIES = [
        'Sport' => ['sport', 'foot', 'rugby', 'equidia', 'eurosport', 'beinsport', 'dazn', 'rmc sport'],
        'Cinéma' => ['cine', 'cinéma', 'ocs', 'paramount', 'warner', 'tcm', 'polar'],
        'Info' => ['news', 'info', 'bfm', 'lci', 'france 24', 'cnews', 'euronews'],
        'Musique' => ['music', 'musique', 'mtv', 'mcm', 'nrj', 'trace', 'mezzo'],
        'Documentaire' => ['nat geo', 'national geographic', 'discovery', 'planete', 'planète', 'ushuaia', 'histoire', 'rmc découverte'],
        'Enfants' => ['kids', 'disney', 'nickel', 'gulli', 'tiji', 'boomerang', 'canal j', 'piwi'],
        'Séries' => ['série', 'series', 'syfy', '13ème', '13eme', 'comédie', 'comedy'],
    ];
}
