<?php

use App\Services\Tv\ChannelLogos;
use App\Services\Tv\LiveTv;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

// Deletes expired TV sign-in codes (DeviceLogin::prunable).
Schedule::command('model:prune')->daily();
// Deletes API tokens expired for more than a day (config/sanctum.php: 180 days).
Schedule::command('sanctum:prune-expired --hours=24')->daily();

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
    $this->info(count($channels).' chaînes, '.count($withLogo).' avec un logo du fournisseur.');
    if ($withLogo === []) {
        $this->warn('Le fournisseur ne donne aucun logo : rien à afficher.');

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
        // The provider's own address (server-side only: never sent to the apps).
        $this->line('    adresse du fournisseur : '.($result['url'] ?? '?'));
    }
    $this->line('Adresse du premier logo dans l\'API : '.$withLogo[0]['logo']);

    return 0;
})->purpose('Diagnose the live TV channel logos');
