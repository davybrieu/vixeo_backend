<?php

namespace App\Console\Commands;

use App\Enums\AppPlatform;
use App\Models\AppRelease;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;

/**
 * Declares a version of the app once it can be downloaded (docs/mise-a-jour.md).
 *
 *     php artisan vixeo:release android 1.1.0 14 --apk=https://vixeo.app/telechargements/vixeo-1.1.0.apk \
 *         --sha256=… --notes="Nouveautés…"
 *     php artisan vixeo:release ios 1.1.0 14 --required --at="2026-11-10 08:00"
 *
 * Same platform and build again: updates that release (notes, required, date).
 */
class PublishAppRelease extends Command
{
    protected $signature = 'vixeo:release
        {platform : android ou ios}
        {version : version affichée, ex. 1.1.0}
        {build : numéro de build (--build-number), toujours croissant}
        {--required : les versions plus anciennes doivent se mettre à jour}
        {--notes= : nouveautés, en français}
        {--apk= : Android : adresse https de l’APK (l’appli le télécharge et l’installe)}
        {--sha256= : Android : empreinte SHA-256 de l’APK (sha256sum), obligatoire avec --apk}
        {--at= : date de publication (par défaut : maintenant)}';

    protected $description = 'Publie une version de l’appli Android ou iOS (vérification des mises à jour)';

    public function handle(): int
    {
        $platform = AppPlatform::tryFrom((string) $this->argument('platform'));
        if ($platform === null) {
            $this->error('Plateforme inconnue : utilisez android ou ios.');

            return self::INVALID;
        }

        $version = (string) $this->argument('version');
        if (! preg_match('/^\d+\.\d+\.\d+$/', $version)) {
            $this->error('Version invalide : utilisez le format 1.2.3.');

            return self::INVALID;
        }

        $build = filter_var($this->argument('build'), FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
        if ($build === false) {
            $this->error('Numéro de build invalide : un entier positif.');

            return self::INVALID;
        }

        $apk = $this->option('apk') ?: null;
        $sha256 = $this->option('sha256') ? strtolower((string) $this->option('sha256')) : null;
        if ($apk !== null || $sha256 !== null) {
            if ($platform !== AppPlatform::Android) {
                $this->error('--apk et --sha256 ne concernent que Android.');

                return self::INVALID;
            }
            if ($apk === null || $sha256 === null) {
                $this->error('--apk et --sha256 vont ensemble : l’appli vérifie le fichier avant de l’installer.');

                return self::INVALID;
            }
            $secure = str_starts_with($apk, 'https://') || (app()->environment('local', 'testing') && str_starts_with($apk, 'http://'));
            if (! filter_var($apk, FILTER_VALIDATE_URL) || ! $secure) {
                $this->error('Adresse de l’APK invalide : une adresse https:// est obligatoire.');

                return self::INVALID;
            }
            if (! preg_match('/^[0-9a-f]{64}$/', $sha256)) {
                $this->error('Empreinte invalide : 64 caractères hexadécimaux (sha256sum vixeo.apk).');

                return self::INVALID;
            }
        }

        try {
            $publishedAt = $this->option('at') ? Carbon::parse((string) $this->option('at')) : now();
        } catch (\Throwable) {
            $this->error('Date invalide.');

            return self::INVALID;
        }

        $release = AppRelease::updateOrCreate(
            ['platform' => $platform, 'build' => $build],
            [
                'version' => $version,
                'required' => (bool) $this->option('required'),
                'notes' => $this->option('notes') ?: null,
                'download_url' => $apk,
                'sha256' => $sha256,
                'published_at' => $publishedAt,
            ],
        );

        $this->info(sprintf(
            '%s %s (build %d) %s, %s le %s.',
            $platform === AppPlatform::Ios ? 'iOS' : 'Android',
            $release->version,
            $release->build,
            $release->wasRecentlyCreated ? 'publiée' : 'mise à jour',
            $release->required ? 'obligatoire' : 'facultative',
            $release->published_at->format('d/m/Y H:i'),
        ));

        return self::SUCCESS;
    }
}
