<?php

namespace App\Services\Addons;

use GuzzleHttp\Exception\TransferException;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Pool;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use RuntimeException;
use Throwable;

/**
 * The only way the API calls a plug-in. A plug-in's address is chosen by whoever installs it, so
 * every request is checked first: https only, a public address only (never the server itself, its
 * network or a cloud metadata service), the checked address pinned for the connection (a DNS
 * answer that changes in between is not followed), no redirect, a short timeout and a size limit.
 * Only the host is ever logged: a configured plug-in's address may hold a personal key.
 */
class AddonHttp
{
    public function __construct(private HostResolver $resolver) {}

    /**
     * The JSON object at `$url`. Throws InvalidAddon (French message) when it cannot be had.
     *
     * @return array<mixed>
     */
    public function getJson(string $url): array
    {
        $target = $this->check($url);

        try {
            $response = $this->request(Http::createPendingRequest(), $target)->get($url);
        } catch (ConnectionException|TransferException $e) {
            Log::info('Plug-in unreachable', ['host' => $target['host'], 'error' => class_basename($e)]);

            throw new InvalidAddon(__('This plug-in does not answer.'), previous: $e);
        }

        return $this->decode($response, $target['host']);
    }

    /**
     * Several JSON objects at once; null for those that cannot be had (never throws).
     *
     * @param  array<string, string>  $urls  key => url
     * @return array<string, array<mixed>|null>
     */
    public function getManyJson(array $urls): array
    {
        $targets = [];
        $results = [];
        foreach ($urls as $key => $url) {
            try {
                $targets[$key] = $this->check($url);
            } catch (InvalidAddon) {
                $results[$key] = null;
            }
        }
        if ($targets === []) {
            return $results;
        }

        try {
            $responses = Http::pool(fn (Pool $pool) => array_map(
                fn (string $key) => $this->request($pool->as($key), $targets[$key])->get($urls[$key]),
                array_keys($targets),
            ), concurrency: 8);
        } catch (Throwable) {
            $responses = [];
        }

        foreach (array_keys($targets) as $key) {
            $response = $responses[$key] ?? null;
            try {
                $results[$key] = $response instanceof Response ? $this->decode($response, $targets[$key]['host']) : null;
            } catch (InvalidAddon) {
                $results[$key] = null;
            }
        }

        return $results;
    }

    /**
     * Refuses an address the API must not call, and returns what to pin.
     *
     * @return array{host: string, port: int, ip: string}
     */
    public function check(string $url): array
    {
        $parts = parse_url($url);
        $scheme = strtolower((string) ($parts['scheme'] ?? ''));
        $host = strtolower(trim((string) ($parts['host'] ?? ''), '[]'));
        $allowed = config('services.addons.allow_http') ? ['https', 'http'] : ['https'];

        if (! in_array($scheme, $allowed, true) || $host === '' || isset($parts['user']) || isset($parts['pass'])) {
            throw new InvalidAddon(__('A plug-in address starts with https://.'));
        }

        $port = (int) ($parts['port'] ?? ($scheme === 'https' ? 443 : 80));
        // An address written as an IP is checked as it is, whatever the resolver.
        $ips = filter_var($host, FILTER_VALIDATE_IP) !== false ? [$host] : $this->resolver->resolve($host);
        if ($ips === []) {
            throw new InvalidAddon(__('This plug-in address cannot be found.'));
        }
        foreach ($ips as $ip) {
            if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_GLOBAL_RANGE) === false) {
                Log::warning('Plug-in on a private address refused', ['host' => $host]);

                throw new InvalidAddon(__('This plug-in address is not allowed.'));
            }
        }

        return ['host' => $host, 'port' => $port, 'ip' => $ips[0]];
    }

    /** @param  array{host: string, port: int, ip: string}  $target */
    private function request(PendingRequest $request, array $target): PendingRequest
    {
        $max = (int) config('services.addons.max_response_bytes', 5 * 1024 * 1024);
        $ip = str_contains($target['ip'], ':') ? "[{$target['ip']}]" : $target['ip'];

        return $request
            ->acceptJson()
            ->withUserAgent('Vixeo/1.0 (+https://www.vixeo.app)')
            ->timeout((int) config('services.addons.timeout', 8))
            ->connectTimeout(4)
            ->withoutRedirecting()
            ->withOptions([
                // The address that was checked, and no other.
                'curl' => [CURLOPT_RESOLVE => ["{$target['host']}:{$target['port']}:{$ip}"]],
                'progress' => function (int|float $total, int|float $downloaded) use ($max): void {
                    if ($total > $max || $downloaded > $max) {
                        throw new RuntimeException('Plug-in answer too large');
                    }
                },
            ]);
    }

    /** @return array<mixed> */
    private function decode(Response $response, string $host): array
    {
        if (! $response->successful()) {
            Log::info('Plug-in failed', ['host' => $host, 'status' => $response->status()]);

            throw new InvalidAddon(__('This plug-in does not answer.'));
        }

        $json = json_decode($response->body(), true);
        if (! is_array($json)) {
            throw new InvalidAddon(__('This address is not a plug-in.'));
        }

        return $json;
    }
}
