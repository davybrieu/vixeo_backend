<?php

namespace App\Console\Commands;

use App\Services\Push\EpisodePush;
use App\Services\Push\PushDevices;
use Illuminate\Console\Command;

/**
 * New-episode push notifications (docs/notifications.md): scheduled every 15 minutes
 * (routes/console.php), and to run by hand, for everyone or for one account.
 */
class PushEpisodes extends Command
{
    protected $signature = 'vixeo:push-episodes
        {--user= : ID ou adresse e-mail d\'un compte (tous les comptes sinon)}
        {--dry-run : afficher les notifications sans les envoyer}';

    protected $description = 'Send the new-episode push notifications of the « Ma liste » TV shows';

    public function handle(EpisodePush $push, PushDevices $devices): int
    {
        $dryRun = (bool) $this->option('dry-run');
        if (! $dryRun && ! $devices->available()) {
            $this->error('Notifications non configurées : ni FCM_CREDENTIALS ni Reverb (docs/notifications.md).');

            return self::FAILURE;
        }

        $user = null;
        if (($value = $this->option('user')) !== null) {
            $user = SendPush::findUser((string) $value);
            if ($user === null) {
                $this->error("Aucun compte « {$value} ».");

                return self::FAILURE;
            }
        }

        $report = $push->run($user, $dryRun);
        foreach ($report['lines'] as $line) {
            $this->line($line);
        }
        $this->info(sprintf(
            '%d profil(s), %d notification(s)%s : %d envoi(s) réussi(s), %d échec(s), %d appareil(s) oublié(s), %d en attente.',
            $report['profiles'],
            $report['notifications'],
            $dryRun ? ' (simulation, rien n\'est envoyé)' : '',
            $report['sent'],
            $report['failed'],
            $report['removed'],
            $report['waiting'],
        ));

        return self::SUCCESS;
    }
}
