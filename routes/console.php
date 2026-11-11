<?php

use App\Services\Tv\ChannelLogos;
use App\Services\Tv\ChannelName;
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

    // Verify the actual provider catalogue against the refreshed guide and logo sources.
    try {
        $matches = app(LiveTv::class)->matchReport();
        $this->info(sprintf(
            'Correspondance catalogue : %d chaînes, %d avec un logo, %d avec un programme.',
            $matches['total'],
            $matches['with_logo'],
            $matches['with_programme'],
        ));

        foreach (['Sans logo' => $matches['without_logo'], 'Sans programme' => $matches['without_programme']] as $title => $names) {
            if ($names === []) {
                continue;
            }
            $this->line($title.' ('.count($names).' chaînes, 10 premières) :');
            foreach (array_slice($names, 0, 10) as $name) {
                $this->line('  '.$name.'  ['.ChannelName::key($name).']');
            }
        }
    } catch (Throwable $e) {
        $this->warn('Vérification du catalogue impossible : '.$e->getMessage());
    }

    return 0;
})->purpose('Download and verify the live TV programme guide and logo library');

// Which channels have no logo or no programme? Counts them and lists their names with the key used
// to match them (docs/tv-direct.md), to see which names to fix. Reads the cache only.
Artisan::command('tv:match {--limit=50 : names listed per group}', function () {
    $live = app(LiveTv::class);

    try {
        $channels = $live->channels();
    } catch (Throwable $e) {
        $this->error('Chaînes indisponibles : '.$e->getMessage());

        return 1;
    }

    $noLogo = [];
    $noGuide = [];
    foreach ($channels as $channel) {
        if ($channel['logo'] === null) {
            $noLogo[] = $channel['name'];
        }
        if ($live->programmes($channel['id']) === []) {
            $noGuide[] = $channel['name'];
        }
    }

    $total = count($channels);
    $this->info(sprintf('%d chaînes : %d avec un logo, %d avec un programme.', $total, $total - count($noLogo), $total - count($noGuide)));
    foreach (['Sans logo' => $noLogo, 'Sans programme' => $noGuide] as $title => $names) {
        $names = array_values(array_unique($names));
        if ($names === []) {
            continue;
        }
        $this->line($title.' ('.count($names).' noms) :');
        foreach (array_slice($names, 0, (int) $this->option('limit')) as $name) {
            $this->line('  '.$name.'  ['.ChannelName::key($name).']');
        }
    }

    return 0;
})->purpose('List the live TV channels without a logo or a programme');
