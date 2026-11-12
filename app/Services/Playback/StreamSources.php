<?php

namespace App\Services\Playback;

use App\Models\MissingExtractor;
use App\Services\Tmdb\TmdbClient;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class StreamSources
{
    /**
     * Domaines supportés par les extracteurs du scraper Node.
     * Portés depuis l'appli ONYX (com.streamflixreborn.streamflix.extractors).
     */
    private const SUPPORTED_DOMAINS = [
        // VOE
        'voe.sx', 'jessicachoosemake.com', 'jamesbornmain.com', 'jilliandescribecompany.com',
        'mikaylaarealike.com', 'christopheruntilpoint.com', 'walterprettytheir.com',
        'crystaltreatmenteast.com', 'lauradaydo.com', 'lancewhosedifficult.com',
        'dianaavoidthey.com', 'jefferycontrolmodel.com', 'sandratableother.com',
        'marissasharecareer.com', 'ralphysuccessfull.org', 'charlestoughrace.com',
        'timmaybealready.com', 'maryspecialwatch.com', 'rebeccacostthousand.com',
        'bryantenunder.com',
        // Streamwish
        'streamwish.com', 'streamwish.to', 'streamwish.site', 'streamwish.fun', 'embedwish.com',
        'awish.pro', 'awish.top', 'dwish.pro', 'kisswish.com', 'hlswish.com', 'swishsrv.com',
        'playerwish.com', 'swiftplayers.com', 'uqloads.xyz', 'wishembed.pro', 'strmwis.xyz',
        'vidmoviesb.xyz', 'cilootv.store', 'tuktukcinema.store', 'doodporn.xyz', 'volvovideo.top',
        'wishfast.top', 'sfastwish.com', 'playembed.online', 'flaswish.com', 'obeywish.com',
        'cdnwish.com', 'javsw.me', 'cinemathek.online', 'mohahhda.site', 'ma2d.store',
        'dancima.shop', 'swhoi.com', 'jodwish.com', 'swdyu.com', 'strwish.com', 'asnwish.com',
        'wishonly.site', 'katomen.store', 'iplayerhls.com', 'hlsflast.com', 'ghbrisk.com',
        'cybervynx.com', 'dhcplay.com', 'gradehgplus.com', 'ultpreplayer.com', 'hglink.to',
        'haxloppd.com', 'hgcloud.to', 'vidwish.live', 'hgplaycdn.com', 'hanerix.com',
        // Filemoon
        'filemoon.org', 'filemoon.sx', 'filemoon.to', 'filemoon.in', 'filemoon.nl',
        'bysejikuar.com', '1day1movie.website', 'moflix-stream.link', 'bysezoxexe.com',
        'bysebuho.com', 'bysezejataos.com', 'bysekoze.com', 'bysesayeveum.com', 'lukefirst.lol',
        'weneverbeenfree.com', 'gn1r5n.org', 'mfw09.org', 'kaydendown.lol',
        // VidHide
        'dhtpre.com', 'peytonepre.com', 'vidhideplus.com', 'dingtezuni.com', 'minochinos.com',
        'moflix-stream.click', 'filelions.to', 'filelions.com', 'streamhide.to', 'streamhide.com',
        'morencius.com', 'mivalyo.com', 'dinisglows.com',
        // Streamruby
        'streamruby.com', 'stmruby.com', 'rubystm.com', 'rubyvid.com', 'moflix-stream.fans',
        // Supervideo
        'supervideo.cc', 'supervideo.tv',
        // Vidara
        'vidara.to', 'viewdara.com', 'vidara.so', 'ano.cx', 'bufsao.com', 'vidaraa.cc',
        'vidaraa.com', 'vidvara.site', 'vidar.cc', 'gaqghe.com', 'ykfrtza.com', 'h308sa.com',
        // Vidsonic
        'vidsonic.net',
        // Rpmvid
        'rpmvid.com', 'cubeembed.rpmvid.com', 'bummi.upns.xyz', 'loadm.cam', 'anibum.playerp2p.online',
        'pelisplus.upns.pro', 'pelisplus.rpmstream.live', 'pelisplus.strp2p.com', 'flemmix.upns.pro',
        'moflix.rpmplay.xyz', 'moflix.upns.xyz', 'flix2day.xyz', 'primevid.click',
        'totocoutouno.rpmlive.online', 'dismoiceline.uns.bio', 'doremifasol.ezplayer.me',
        'marcus.p2pstream.vip', 'animeav1.uns.bio', 'serix.upns.live', 'barbapapa.p2pstream.online',
        'coflix.upn.one', 'flemmix.farm', 'flemmix.rpmlive.online', 'flemmix.upns.xyz',
        'flemmix.rpmstream.live', 'flemmix.strp2p.com', 'flemmix.ezplayer.me', 'flemmix.uns.bio',
        'flemmix.upns.live', 'flemmix.upn.one', 'flemmix.p2pstream.vip', 'flemmix.prof',
        // Vidzy
        'vidzy.org', 'vidzy.live', 'vidzy.cc', 'vidzy.to',
        // Uqload
        'uqload.is', 'uqload.cx', 'uqload.co', 'uqload.net', 'uqload.bz', 'uqload.io',
        'uqload.com', 'uqload.ws', 'uqload.vc', 'uqload.to',
        // Streamtape
        'streamtape.com', 'streamtape.net', 'streamtape.to', 'streamtape.xyz', 'streamta.site',
        'strtape.cloud', 'stape.fun', 'shavetape.cash', 'advtpe.com', 'watchadsontape.com',
        // MixDrop
        'mixdrop.co', 'mixdrop.bz', 'mixdrop.ag', 'mixdrop.ch', 'mixdrop.to', 'mixdrop.cv',
        'mxdrop.to', 'mixdrop.club', 'm1xdrop.net', 'mixdrop.is', 'mixdrop.gl', 'mixdrop.sx',
        'mxdrop.sx', 'mixdrop.ps', 'miixdrop.net', 'miiixdrop.net', 'mxdrop.top', 'mxdrop.click',
        'mixdrop.si',
        // DoodStream
        'dood.la', 'dood.li', 'dood.so', 'dood.to', 'dood.watch', 'dood.yt', 'dood.re', 'dood.wf',
        'dood.ws', 'dood.pm', 'doodstream.com', 'doodstream.me', 'doods.pro', 'dsvplay.com',
        'myvidplay.com', 'playmogo.com', 'do7go.com', 'd000d.com', 'doply.net', 'ds2video.com',
        'vvide0.com', 'vide0.net',
        // Netu / HQQ
        'waaw1.tv', 'netu.tv', 'hqq.tv', 'waaw.tv', 'hqcloud.to', 'wsaw.to', 'netu.ac', 'hqq.ac',
        'waaw.ac', 'waaw.to', 'younetu.org',
        // Vidoza
        'vidoza.net', 'videzz.net',
        // SendVid
        'sendvid.com',
    ];

    /** Racines des miroirs Rpmvid qui tournent souvent. */
    private const SUPPORTED_HOST_PATTERNS = ['flemmix.', '.upns.', '.rpmlive.', '.rpmstream.', '.p2pstream.', '.ezplayer.'];

    public function __construct(private TmdbClient $tmdb, private DeadStreams $dead) {}

    /**
     * Providers the sources panel offers, in display order.
     *
     * @return list<array{id: string, name: string}>
     */
    public function providers(): array
    {
        $fallback = config('services.scraper.providers', []);
        $base = $this->scraperBase();

        if ($base !== '') {
            try {
                $response = Http::baseUrl($base)
                    ->acceptJson()
                    ->timeout(3)
                    ->get('providers');

                $providers = $response->successful() ? $response->json('providers') : null;
                if (is_array($providers)) {
                    return array_values(array_filter(array_map(
                        fn ($provider) => is_array($provider) && isset($provider['id'], $provider['name'])
                            ? ['id' => (string) $provider['id'], 'name' => (string) $provider['name']]
                            : null,
                        $providers
                    )));
                }
            } catch (\\Throwable $e) {
                Log::warning('Unable to retrieve scraper providers; using configured fallback', [
                    'error' => $e->getMessage(),
                ]);
            }
        }

        return array_values(array_map(fn (array $provider) => [
            'id' => (string) $provider['id'],
            'name' => (string) $provider['name'],
        ], is_array($fallback) ? $fallback : []));
    }

    public function movie(int $tmdbId, bool $extract = false, ?string $provider = null): array
    {
        $meta = $this->tmdb->get("movie/{$tmdbId}");

        return $this->fetch([
            'type' => 'movie',
            'tmdb_id' => $tmdbId,
            'title' => $meta['title'] ?? '',
            'original_title' => $meta['original_title'] ?? ($meta['title'] ?? ''),
            'year' => isset($meta['release_date']) ? (int) substr($meta['release_date'], 0, 4) : null,
            'extract' => $extract,
            'provider' => $provider,
        ]);
    }

    public function episode(int $tmdbId, int $season, int $episode, bool $extract = false, ?string $provider = null): array
    {
        $meta = $this->tmdb->get("tv/{$tmdbId}");

        return $this->fetch([
            'type' => 'tv',
            'tmdb_id' => $tmdbId,
            'title' => $meta['name'] ?? '',
            'original_title' => $meta['original_name'] ?? ($meta['name'] ?? ''),
            'year' => isset($meta['first_air_date']) ? (int) substr($meta['first_air_date'], 0, 4) : null,
            'season' => $season,
            'episode' => $episode,
            'extract' => $extract,
            'provider' => $provider,
        ]);
    }

    /**
     * Resolves a player page URL into a playable stream (m3u8 / mp4) through the scraper's
     * extractors. Returns null when no extractor knows the host or extraction fails.
     *
     * @return array{stream: string, stream_type: string|null, extractor: string|null, headers: array<string, string>, subtitles: list<mixed>}|null
     */
    public function resolve(string $url): ?array
    {
        $base = $this->scraperBase();
        if ($base === '') {
            Log::warning('STREAM scraper not configured (SCRAPER_URL)');

            return null;
        }

        try {
            $response = Http::baseUrl($base)
                ->acceptJson()
                ->timeout((int) config('services.scraper.timeout', 45))
                ->post('extract', ['url' => $url]);
        } catch (\Throwable $e) {
            Log::warning('Scraper unreachable', ['error' => $e->getMessage()]);

            throw new PlaybackServiceUnavailable('Le service de sources est temporairement indisponible.', $e);
        }

        if ($response->failed()) {
            Log::warning('Scraper failed', ['status' => $response->status()]);

            throw new PlaybackServiceUnavailable('Le service de sources est temporairement indisponible.');
        }

        $stream = $response->json('url');
        if (! is_string($stream) || $stream === '') {
            return null;
        }

        $headers = $response->json('headers');
        $subtitles = $response->json('subtitles');

        return [
            'stream' => $stream,
            'stream_type' => $response->json('type') ?: null,
            'extractor' => $response->json('extractor') ?: null,
            'headers' => is_array($headers) ? $headers : [],
            'subtitles' => is_array($subtitles) ? array_values($subtitles) : [],
        ];
    }

    private function fetch(array $payload): array
    {
        $base = $this->scraperBase();
        if ($base === '') {
            Log::warning('STREAM scraper not configured (SCRAPER_URL)');

            return [];
        }

        try {
            $response = Http::baseUrl($base)
                ->acceptJson()
                ->timeout((int) config('services.scraper.timeout', 45))
                ->post('sources', $payload);
        } catch (\Throwable $e) {
            Log::warning('Scraper unreachable', ['error' => $e->getMessage()]);

            throw new PlaybackServiceUnavailable('Le service de sources est temporairement indisponible.', $e);
        }

        if ($response->failed()) {
            Log::warning('Scraper failed', ['status' => $response->status()]);

            throw new PlaybackServiceUnavailable('Le service de sources est temporairement indisponible.');
        }

        $sources = $response->json('sources') ?? [];
        $sources = is_array($sources) ? array_values($sources) : [];

        // A single provider was asked for: its failure is the caller's failure, so the
        // panel can tell "provider down" from "provider has no link for this title".
        $provider = $payload['provider'] ?? null;
        if ($provider !== null) {
            $status = collect($response->json('providers') ?? [])->firstWhere('id', $provider);
            if (is_array($status) && ($status['status'] ?? null) === 'error') {
                Log::warning('Provider failed', ['provider' => $provider, 'error' => $status['error'] ?? null]);

                throw new PlaybackServiceUnavailable('Ce fournisseur est momentanément indisponible.');
            }
        }

        $this->recordMissingExtractors($sources, $payload);

        // Only hand the app links we can actually resolve into a stream: a link
        // with no extractor would leave the player with nothing to play.
        $sources = array_values(array_filter(
            $sources,
            fn (array $source) => $this->hasExtractor((string) ($source['url'] ?? '')),
        ));

        // Streams the players reported as dead (24 h) are dropped up front, so nobody
        // else has to wait for the same failure. See docs/lecture.md.
        return $this->dead->filter($sources);
    }

    private function scraperBase(): string
    {
        return rtrim((string) config('services.scraper.base_url'), '/');
    }

    private function recordMissingExtractors(array $sources, array $payload): void
    {
        $now = now();
        $userId = Auth::id();

        foreach ($sources as $source) {
            $url = $source['url'] ?? '';
            if ($url === '' || $this->hasExtractor($url)) {
                continue;
            }

            $host = parse_url($url, PHP_URL_HOST) ?: '';

            $existing = MissingExtractor::where('url', $url)->first();

            if ($existing) {
                $existing->increment('occurrences');
                $existing->update([
                    'last_seen_at' => $now,
                    'user_id' => $userId ?? $existing->user_id,
                ]);
            } else {
                MissingExtractor::create([
                    'url' => $url,
                    'host' => $host,
                    'provider' => $source['provider'] ?? null,
                    'name' => $source['name'] ?? null,
                    'type' => $payload['type'] ?? null,
                    'tmdb_id' => $payload['tmdb_id'] ?? null,
                    'title' => $payload['title'] ?? null,
                    'season' => $payload['season'] ?? null,
                    'episode' => $payload['episode'] ?? null,
                    'user_id' => $userId,
                    'occurrences' => 1,
                    'first_seen_at' => $now,
                    'last_seen_at' => $now,
                ]);
            }
        }
    }

    private function hasExtractor(string $url): bool
    {
        $host = parse_url($url, PHP_URL_HOST);
        if (! $host) {
            return false;
        }
        $host = strtolower(preg_replace('/^www\./', '', $host));

        foreach (self::SUPPORTED_DOMAINS as $domain) {
            if ($host === $domain || str_ends_with($host, '.'.$domain)) {
                return true;
            }
        }

        foreach (self::SUPPORTED_HOST_PATTERNS as $pattern) {
            if (str_contains($host, $pattern)) {
                return true;
            }
        }

        return false;
    }
}
