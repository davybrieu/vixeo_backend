<?php

namespace App\Services\Tv;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;
use XMLReader;

/**
 * Programme guide (EPG) of the live channels, read from an XMLTV file (the standard format of TV
 * guides), configured on the server (`TV_EPG_URL`, gzip accepted). Provider-neutral: whatever
 * serves the streams, the guide comes from this one file.
 *
 * The file is downloaded and parsed at most every `TV_EPG_TTL` seconds, then cached as a compact
 * index: programmes of the next hours, and each channel's icon (used when the provider has no
 * logo). A channel is matched by its guide id (`epg_channel_id`, from a panel) or by its name,
 * normalised ("TF1 HD" and the guide's "TF1.fr" / "TF1" are the same channel).
 *
 * Without a file, or when it can't be read, the guide is simply empty: live TV keeps working.
 */
class Epg
{
    private const CACHE_KEY = 'tv:epg:index';

    /** Hours of programmes kept from now (and the one that is on). */
    private const HORIZON_HOURS = 36;

    /** Words that don't name the channel ("TF1 HD" = "TF1"). */
    private const NOISE = ['hd', 'fhd', 'uhd', '4k', 'sd', 'hevc', 'h265', 'h264', 'backup', 'fr', 'tv'];

    public function configured(): bool
    {
        return filled(config('services.tv.epg_url'));
    }

    /**
     * Programmes of a channel, from the one on now, in order.
     *
     * @return list<array{title: string, subtitle: string|null, description: string|null, category: string|null, image: string|null, start: string, end: string}>
     */
    public function programmes(?string $epgChannelId, string $name, int $limit = 30): array
    {
        $index = $this->index();
        $key = $this->find($index, $epgChannelId, $name);
        if ($key === null) {
            return [];
        }

        $now = time();
        $list = array_values(array_filter($index['programmes'][$key] ?? [], fn (array $p) => $p['end'] > $now));

        return array_map(fn (array $p) => [
            'title' => $p['title'],
            'subtitle' => $p['subtitle'],
            'description' => $p['description'],
            'category' => $p['category'],
            'image' => $p['image'],
            'start' => gmdate('Y-m-d\TH:i:s\Z', $p['start']),
            'end' => gmdate('Y-m-d\TH:i:s\Z', $p['end']),
        ], array_slice($list, 0, $limit));
    }

    /** The guide's icon of a channel (a provider without logos), or null. */
    public function icon(?string $epgChannelId, string $name): ?string
    {
        if (! $this->configured()) {
            return null;
        }
        $index = $this->index();
        $key = $this->find($index, $epgChannelId, $name);

        return $key === null ? null : ($index['icons'][$key] ?? null);
    }

    /** "TF1 HD", "TF1.fr", "FR | TF1" → "tf1"; "France 2" → "france2". */
    public static function normalize(string $name): string
    {
        $name = mb_strtolower($name);
        // A guide id: "TF1.fr" (country suffix).
        $name = preg_replace('/\.[a-z]{2}$/', '', $name) ?? $name;
        // A panel prefix: "FR | TF1", "FR: TF1".
        $name = preg_replace('/^[a-z]{2,3}\s*[|:]\s*/', '', $name) ?? $name;
        $name = strtr($name, ['é' => 'e', 'è' => 'e', 'ê' => 'e', 'ë' => 'e', 'à' => 'a', 'â' => 'a', 'î' => 'i', 'ï' => 'i', 'ô' => 'o', 'ö' => 'o', 'ù' => 'u', 'û' => 'u', 'ü' => 'u', 'ç' => 'c', '+' => 'plus']);
        $words = preg_split('/[^a-z0-9]+/', $name, -1, PREG_SPLIT_NO_EMPTY) ?: [];
        $kept = array_filter($words, fn (string $w) => ! in_array($w, self::NOISE, true));

        return implode('', $kept === [] ? $words : $kept);
    }

    /**
     * @param  array{keys: array<string, string>, programmes: array<string, list<array<string, mixed>>>, icons: array<string, string>}  $index
     */
    private function find(array $index, ?string $epgChannelId, string $name): ?string
    {
        foreach (array_filter([$epgChannelId, $name]) as $candidate) {
            $key = $index['keys'][$candidate] ?? $index['keys'][self::normalize($candidate)] ?? null;
            if ($key !== null) {
                return $key;
            }
        }

        return null;
    }

    /**
     * @return array{keys: array<string, string>, programmes: array<string, list<array<string, mixed>>>, icons: array<string, string>}
     */
    private function index(): array
    {
        $empty = ['keys' => [], 'programmes' => [], 'icons' => []];
        if (! $this->configured()) {
            return $empty;
        }

        $cached = Cache::get(self::CACHE_KEY);
        if (is_array($cached)) {
            return $cached;
        }

        // One download at a time; a failure is cached briefly, so a broken file is not fetched on
        // every request.
        return Cache::lock(self::CACHE_KEY.':lock', 120)->block(60, function () use ($empty) {
            $cached = Cache::get(self::CACHE_KEY);
            if (is_array($cached)) {
                return $cached;
            }
            try {
                $index = $this->parse($this->download());
                Cache::put(self::CACHE_KEY, $index, (int) config('services.tv.epg_ttl', 21600));

                return $index;
            } catch (Throwable $e) {
                Log::warning('EPG unavailable', ['error' => $e->getMessage()]);
                Cache::put(self::CACHE_KEY, $empty, 600);

                return $empty;
            }
        });
    }

    private function download(): string
    {
        $response = Http::timeout((int) config('services.tv.epg_timeout', 60))
            ->withHeaders(['Accept-Encoding' => 'gzip'])
            ->get((string) config('services.tv.epg_url'))
            ->throw();
        $body = $response->body();

        // A .gz file (served as is, not as a transfer encoding).
        return str_starts_with($body, "\x1f\x8b") ? (string) gzdecode($body) : $body;
    }

    /**
     * Streams the XMLTV document (files of several MB): channels, then programmes in the horizon.
     *
     * @return array{keys: array<string, string>, programmes: array<string, list<array<string, mixed>>>, icons: array<string, string>}
     */
    private function parse(string $xml): array
    {
        $keys = [];
        $icons = [];
        $programmes = [];
        $now = time();
        $until = $now + self::HORIZON_HOURS * 3600;

        $reader = XMLReader::XML($xml, null, LIBXML_NONET | LIBXML_COMPACT);
        if ($reader === false) {
            throw new \RuntimeException('Invalid XMLTV document.');
        }

        while ($reader->read()) {
            if ($reader->nodeType !== XMLReader::ELEMENT) {
                continue;
            }

            if ($reader->name === 'channel') {
                $id = (string) $reader->getAttribute('id');
                $node = simplexml_load_string($reader->readOuterXml());
                if ($id === '' || $node === false) {
                    continue;
                }
                $keys[$id] = $id;
                $keys[self::normalize($id)] ??= $id;
                foreach ($node->{'display-name'} as $displayName) {
                    $keys[self::normalize((string) $displayName)] ??= $id;
                }
                $icon = (string) ($node->icon['src'] ?? '');
                if (str_starts_with($icon, 'https://') || str_starts_with($icon, 'http://')) {
                    $icons[$id] = $icon;
                }
            } elseif ($reader->name === 'programme') {
                $start = self::time((string) $reader->getAttribute('start'));
                $end = self::time((string) $reader->getAttribute('stop'));
                $channel = (string) $reader->getAttribute('channel');
                if ($start === null || $end === null || $end <= $now || $start > $until || $channel === '') {
                    continue;
                }
                $node = simplexml_load_string($reader->readOuterXml());
                if ($node === false) {
                    continue;
                }
                $image = (string) ($node->icon['src'] ?? '');
                $programmes[$channel][] = [
                    'title' => self::text($node->title) ?? '',
                    'subtitle' => self::text($node->{'sub-title'}),
                    'description' => self::text($node->desc),
                    'category' => self::text($node->category),
                    'image' => str_starts_with($image, 'https://') ? $image : null,
                    'start' => $start,
                    'end' => $end,
                ];
            }
        }
        $reader->close();

        foreach ($programmes as &$list) {
            usort($list, fn (array $a, array $b) => $a['start'] <=> $b['start']);
        }

        return ['keys' => $keys, 'programmes' => $programmes, 'icons' => $icons];
    }

    /** XMLTV time: "20261110203000 +0100". */
    private static function time(string $value): ?int
    {
        $date = \DateTimeImmutable::createFromFormat('YmdHis O', trim($value))
            ?: \DateTimeImmutable::createFromFormat('YmdHis', substr(trim($value), 0, 14), new \DateTimeZone('UTC'));

        return $date === false ? null : $date->getTimestamp();
    }

    private static function text(mixed $node): ?string
    {
        $value = trim((string) ($node ?? ''));

        return $value === '' ? null : $value;
    }
}
