<?php

namespace App\Services\Tv;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Channel logos from a public GitHub repository of TV logos (`TV_LOGOS_REPOSITORY`, by default
 * `tv-logo/tv-logos`): transparent PNGs named after the channel and its country
 * (`countries/france/tf1-fr.png`, `canal-plus-fr.png`).
 *
 * The listed folders (`TV_LOGOS_DIRECTORIES`, in order of preference) are read with GitHub's API
 * once a day, and each file is matched to the channels by name ({@see ChannelName}). Only the map
 * name → image address is cached: the image itself is relayed by `GET /tv/logo/{hash}` like every
 * other logo ({@see ChannelLogos}), never stored, and the apps never see GitHub's address.
 */
class LogoLibrary
{
    private const CACHE_KEY = 'tv:logo-library';

    /** @var array<string, string>|null */
    private ?array $map = null;

    public function configured(): bool
    {
        return filled(config('services.tv.logos_repository')) && $this->directories() !== [];
    }

    /**
     * The library's logo of a channel, or null. `$fallback`: the logo of a shorter name instead
     * ({@see ChannelName::fallbackKeys}, "RMC SPORT LIVE 16" → rmc-sport-fr.png).
     */
    public function logo(string $name, bool $fallback = false): ?string
    {
        $map = $this->map();
        foreach ($fallback ? ChannelName::fallbackKeys($name) : [ChannelName::key($name)] as $key) {
            if ($key !== '' && isset($map[$key])) {
                return $map[$key];
            }
        }

        // Final pass: tolerate a minor typo only if one logo filename is a clear, unique match.
        $matched = ChannelName::bestMatchKey($name, array_keys($map));

        return $matched === null ? null : $map[$matched];
    }

    /**
     * Reads the folders again (`php artisan tv:epg`).
     *
     * @return array{logos: int, error: string|null}
     */
    public function refresh(): array
    {
        if (! $this->configured()) {
            return ['logos' => 0, 'error' => null];
        }

        try {
            $map = $this->fetch();
        } catch (Throwable $e) {
            Log::warning('TV logo library unavailable', ['error' => $e->getMessage()]);
            // Tried again in an hour, the previous map kept meanwhile.
            Cache::add(self::CACHE_KEY, [], 3600);

            return ['logos' => 0, 'error' => $e->getMessage()];
        }
        Cache::put(self::CACHE_KEY, $map, (int) config('services.tv.logos_ttl', 86400));
        $this->map = $map;

        return ['logos' => count($map), 'error' => null];
    }

    /** @return array<string, string> */
    private function map(): array
    {
        if (! $this->configured()) {
            return [];
        }
        if ($this->map !== null) {
            return $this->map;
        }

        $cached = Cache::get(self::CACHE_KEY);
        if (! is_array($cached)) {
            // A few small requests (one per folder), once a day: one request at a time does it.
            $cached = Cache::lock(self::CACHE_KEY.':lock', 30)->get(function () {
                $this->refresh();

                return Cache::get(self::CACHE_KEY, []);
            }) ?: [];
        }

        return $this->map = is_array($cached) ? $cached : [];
    }

    /** @return list<string> */
    private function directories(): array
    {
        return array_values(array_filter(array_map(
            fn ($dir) => trim((string) $dir, " /\t"),
            (array) config('services.tv.logos_directories', [])
        )));
    }

    /** @return array<string, string> */
    private function fetch(): array
    {
        $repository = trim((string) config('services.tv.logos_repository'), '/');
        $map = [];

        foreach ($this->directories() as $directory) {
            $path = implode('/', array_map('rawurlencode', explode('/', $directory)));
            $files = Http::acceptJson()->connectTimeout(5)->timeout(15)
                ->get("https://api.github.com/repos/{$repository}/contents/{$path}")
                ->throw()
                ->json();

            foreach (is_array($files) ? $files : [] as $file) {
                $name = is_array($file) ? (string) ($file['name'] ?? '') : '';
                $url = is_array($file) ? (string) ($file['download_url'] ?? '') : '';
                if (($file['type'] ?? null) !== 'file' || ! str_ends_with(strtolower($name), '.png') || ! str_starts_with($url, 'https://')) {
                    continue;
                }
                // "tf1-fr.png" → "tf1": the country suffix doesn't name the channel.
                $base = preg_replace('/-[a-z]{2,3}$/', '', substr($name, 0, -4)) ?? '';
                $key = ChannelName::key($base);
                if ($key !== '') {
                    // The first folder wins (France before international).
                    $map[$key] ??= $url;
                }
            }
        }

        return $map;
    }
}
