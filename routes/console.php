<?php

use App\Services\Tv\ChannelLogos;
use App\Services\Tv\Epg;
use App\Services\Tv\LiveTv;
use App\Services\Tv\LogoLibrary;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

// Deletes expired TV sign-in codes (DeviceLogin::prunable).
Schedule::command('model:prune')->daily();
// Deletes API tokens expired for more than a day (config/sanctum.php: 180 days).
Schedule::command('sanctum:prune-expired --hours=24')->daily();
// Live TV programme guide and logo library (docs/tv-direct.md): downloaded outside requests.
Schedule::command('tv:epg')->everySixHours()->withoutOverlapping(60)->runInBackground()
    ->when(fn () => app(Epg::class)->configured() || app(LogoLibrary::class)->configured());

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Why don't the channel logos show? Counts the channels with a logo from the provider, then tries
// to relay a few of them like GET /tv/logo/{hash} does (docs/tv-direct.md). Nothing is stored.
Artisan::command('tv:logos {--limit=5 : logos to try}', function () {
    $live = app(LiveTv::class);
    $logos = app(ChannelLogos::class);

    try {
        $channels = $live->channels();
    } catch (Throwable $e) {
        $this->error('Chaînes indisponibles : '.$e->getMessage());

        return 1;
    }

    $withLogo = array_values(array_filter($channels, fn (array $c) => $c['logo'] !== null));
    $this->info(count($channels).' chaînes, '.count($withLogo).' avec un logo.');
    if ($withLogo === []) {
        $this->warn('Ni la bibliothèque de logos, ni le guide, ni le fournisseur ne donnent de logo : rien à afficher.');

        return 0;
    }

    foreach (array_slice($withLogo, 0, (int) $this->option('limit')) as $channel) {
        $hash = basename((string) parse_url($channel['logo'], PHP_URL_PATH));
        $result = $logos->inspect($hash);
        $this->line(sprintf(
            '%s : %s',
            $channel['name'],
            $result['image'] === null
                ? sprintf('REFUSÉ, %s (HTTP %s, type %s, %d octets)', $result['reason'], $result['status'] ?? '-', $result['content_type'] ?? '-', $result['bytes'])
                : 'OK, '.$result['image']['content_type'].', '.$result['bytes'].' octets',
        ));
        // The logo's own address (server-side only: never sent to the apps).
        $this->line('    adresse d\'origine : '.($result['url'] ?? '?'));
    }
    $this->line('Adresse du premier logo dans l\'API : '.$withLogo[0]['logo']);

    return 0;
})->purpose('Diagnose the live TV channel logos');

// Downloads the programme guide (TV_EPG_URLS) and the logo library (TV_LOGOS_REPOSITORY) now:
// scheduled every 6 hours, and the first time after a deploy. See docs/tv-direct.md.
Artisan::command('tv:epg', function () {
    @set_time_limit(0);
    $epg = app(Epg::class);
    $library = app(LogoLibrary::class);

    if (! $epg->configured()) {
        $this->warn('Aucun guide des programmes (TV_EPG_URLS vide).');
    }
    foreach ($epg->refresh() as $source) {
        $this->line($source['error'] === null
            ? sprintf('%s : %d chaînes, %d programmes', $source['url'], $source['channels'], $source['programmes'])
            : sprintf('%s : ÉCHEC, %s', $source['url'], $source['error']));
    }

    if (! $library->configured()) {
        $this->warn('Aucune bibliothèque de logos (TV_LOGOS_REPOSITORY vide).');
    } else {
        $logos = $library->refresh();
        $this->line($logos['error'] === null
            ? 'Bibliothèque de logos : '.$logos['logos'].' logos'
            : 'Bibliothèque de logos : ÉCHEC, '.$logos['error']);
    }

    return 0;
})->purpose('Download the live TV programme guide and the logo library');
