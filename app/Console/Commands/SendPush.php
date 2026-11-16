<?php

namespace App\Console\Commands;

use App\Models\PushDevice;
use App\Models\User;
use App\Services\Push\Fcm;
use App\Services\Push\PushDevices;
use App\Services\Push\PushMessage;
use Illuminate\Console\Command;

/**
 * A push notification written by hand (docs/notifications.md), to every app signed in or to
 * the apps of some accounts:
 *
 *     php artisan vixeo:push "Nouvelle version disponible" --all
 *     php artisan vixeo:push "Bonjour" --user=davy@exemple.fr --title="Vixeo" --path=/titre/tv/1399
 */
class SendPush extends Command
{
    protected $signature = 'vixeo:push
        {message? : texte de la notification (demandé s\'il manque)}
        {--title=Vixeo : titre de la notification}
        {--all : tous les appareils inscrits}
        {--user=* : ID ou adresse e-mail d\'un compte (plusieurs possibles)}
        {--path= : page de l\'appli ouverte par un appui, ex. /titre/tv/1399}
        {--force : sans demander de confirmation}';

    protected $description = 'Send a push notification to every app, or to the apps of some accounts';

    public function handle(PushDevices $devices, Fcm $fcm): int
    {
        if (! $fcm->configured()) {
            $this->error('Notifications non configurées : FCM_CREDENTIALS est vide ou illisible (docs/notifications.md).');

            return self::FAILURE;
        }

        $all = (bool) $this->option('all');
        /** @var list<string> $users */
        $users = array_values(array_filter((array) $this->option('user'), fn ($v) => is_string($v) && trim($v) !== ''));
        if ($all === ($users !== [])) {
            $this->error('Choisissez --all ou --user=… (l\'un ou l\'autre).');

            return self::INVALID;
        }

        $path = $this->option('path');
        if ($path !== null && ! str_starts_with((string) $path, '/')) {
            $this->error('--path est une page de l\'appli qui commence par « / », ex. /titre/tv/1399.');

            return self::INVALID;
        }

        $body = trim((string) ($this->argument('message') ?? ''));
        if ($body === '') {
            $body = trim((string) $this->ask('Texte de la notification'));
        }
        $title = trim((string) $this->option('title'));
        if ($body === '' || $title === '') {
            $this->error('Le titre et le texte ne peuvent pas être vides.');

            return self::INVALID;
        }

        $query = PushDevice::query();
        $target = 'tous les comptes';
        if (! $all) {
            $ids = [];
            foreach ($users as $value) {
                $user = self::findUser($value);
                if ($user === null) {
                    $this->error("Aucun compte « {$value} ».");

                    return self::FAILURE;
                }
                $ids[] = $user->id;
            }
            $query->whereIn('user_id', $ids);
            $target = count($ids) === 1 ? '1 compte' : count($ids).' comptes';
        }

        $targets = $query->get();
        if ($targets->isEmpty()) {
            $this->warn("Aucun appareil inscrit aux notifications ({$target}).");

            return self::SUCCESS;
        }

        $this->line("« {$title} » : {$body}");
        if (! $this->option('force') && ! $this->confirm("Envoyer à {$targets->count()} appareil(s) ({$target}) ?", true)) {
            $this->line('Rien n\'est envoyé.');

            return self::SUCCESS;
        }

        $report = $devices->send($targets, new PushMessage(title: $title, body: $body, path: $path === null ? null : (string) $path));
        $this->info(sprintf(
            '%d envoi(s) réussi(s), %d échec(s), %d appareil(s) oublié(s) (appli désinstallée).',
            $report['sent'],
            $report['failed'],
            $report['removed'],
        ));

        return $report['sent'] > 0 || $report['failed'] === 0 ? self::SUCCESS : self::FAILURE;
    }

    /** An account by ID or e-mail address. */
    public static function findUser(string $value): ?User
    {
        $value = trim($value);

        return ctype_digit($value)
            ? User::find((int) $value)
            : User::whereRaw('lower(email) = ?', [mb_strtolower($value)])->first();
    }
}
