<?php

namespace App\Support;

/**
 * Social networks of a profile (developer's request, 21/11/2026): one account per network, kept
 * as its handle (or the address, for the website) and shown as a link on the profile pages.
 */
final class SocialLinks
{
    /**
     * Networks in the order of the profile pages: name, address of an account (`%s` = handle),
     * the hosts of its pages and the shape of a handle.
     *
     * @var array<string, array{label: string, url: string|null, hosts: list<string>, pattern: string}>
     */
    public const NETWORKS = [
        'instagram' => ['label' => 'Instagram', 'url' => 'https://www.instagram.com/%s', 'hosts' => ['instagram.com'], 'pattern' => '/^[A-Za-z0-9._]{1,30}$/'],
        'x' => ['label' => 'X', 'url' => 'https://x.com/%s', 'hosts' => ['x.com', 'twitter.com'], 'pattern' => '/^[A-Za-z0-9_]{1,15}$/'],
        'tiktok' => ['label' => 'TikTok', 'url' => 'https://www.tiktok.com/@%s', 'hosts' => ['tiktok.com'], 'pattern' => '/^[A-Za-z0-9._]{2,24}$/'],
        'youtube' => ['label' => 'YouTube', 'url' => 'https://www.youtube.com/@%s', 'hosts' => ['youtube.com'], 'pattern' => '/^[A-Za-z0-9._-]{3,30}$/'],
        'letterboxd' => ['label' => 'Letterboxd', 'url' => 'https://letterboxd.com/%s/', 'hosts' => ['letterboxd.com'], 'pattern' => '/^[A-Za-z0-9_]{2,15}$/'],
        'threads' => ['label' => 'Threads', 'url' => 'https://www.threads.net/@%s', 'hosts' => ['threads.net', 'threads.com'], 'pattern' => '/^[A-Za-z0-9._]{1,30}$/'],
        'bluesky' => ['label' => 'Bluesky', 'url' => 'https://bsky.app/profile/%s', 'hosts' => ['bsky.app'], 'pattern' => '/^[A-Za-z0-9-]+(\.[A-Za-z0-9-]+)+$/'],
        'website' => ['label' => 'Site web', 'url' => null, 'hosts' => [], 'pattern' => ''],
    ];

    /**
     * What the person typed, as kept: a handle without its « @ » (the address of their page is
     * accepted too), or the address of the website (https added when missing). Null: nothing.
     */
    public static function normalize(string $network, mixed $value): ?string
    {
        if (! is_string($value)) {
            return null;
        }
        $value = trim($value);
        if ($value === '') {
            return null;
        }
        if ($network === 'website') {
            return preg_match('#^https?://#i', $value) === 1 ? $value : 'https://'.$value;
        }

        $hosts = self::NETWORKS[$network]['hosts'] ?? [];
        if (preg_match('#^(https?://)?([^/]+)(/.*)?$#i', $value, $parts) === 1) {
            $host = preg_replace('/^(www\.|m\.|vm\.)/i', '', mb_strtolower($parts[2]));
            if (in_array($host, $hosts, true)) {
                $path = trim($parts[3] ?? '', '/');
                // bsky.app/profile/handle; the others: the first segment of the path.
                $segments = array_values(array_filter(explode('/', $path)));
                $value = $network === 'bluesky' ? ($segments[1] ?? '') : ($segments[0] ?? '');
            }
        }

        return ltrim($value, '@');
    }

    /** Whether [handle] is an account of [network] (an address for the website). */
    public static function valid(string $network, string $handle): bool
    {
        if ($network === 'website') {
            return mb_strlen($handle) <= 200 && filter_var($handle, FILTER_VALIDATE_URL) !== false
                && in_array(mb_strtolower((string) parse_url($handle, PHP_URL_SCHEME)), ['http', 'https'], true);
        }

        return isset(self::NETWORKS[$network]) && preg_match(self::NETWORKS[$network]['pattern'], $handle) === 1;
    }

    /**
     * The kept accounts with [changes] applied (null or empty: removed).
     *
     * @param  array<string, string>|null  $links
     * @param  array<string, string|null>  $changes
     * @return array<string, string>|null
     */
    public static function merge(?array $links, array $changes): ?array
    {
        $merged = $links ?? [];
        foreach ($changes as $network => $handle) {
            if ($handle === null || $handle === '') {
                unset($merged[$network]);
            } else {
                $merged[$network] = $handle;
            }
        }

        return $merged === [] ? null : $merged;
    }

    /**
     * The accounts as the apps and the site show them, in the order of [NETWORKS].
     *
     * @param  array<string, string>|null  $links
     * @return list<array{network: string, label: string, handle: string, url: string}>
     */
    public static function present(?array $links): array
    {
        $shown = [];
        foreach (self::NETWORKS as $network => $info) {
            $handle = $links[$network] ?? null;
            if (! is_string($handle) || $handle === '') {
                continue;
            }
            $shown[] = [
                'network' => $network,
                'label' => $info['label'],
                'handle' => $handle,
                'url' => $info['url'] === null ? $handle : sprintf($info['url'], rawurlencode($handle)),
            ];
        }

        return $shown;
    }
}
