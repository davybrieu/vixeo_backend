<?php

namespace App\Services\Tmdb;

use App\Enums\MediaType;
use App\Services\Tmdb\TmdbFormat as F;

/**
 * « Équipe technique » of a title (developer's request, 25/11/2026: apart from the cast): everyone
 * who isn't an actor, once each with all their jobs in French, the most important first
 * (direction or creation, writing, music, image, editing, production…).
 */
final class TitleCrew
{
    /** Jobs in order of importance, with their French name. */
    private const JOBS = [
        // A TV show's creators (`created_by`) first.
        'Creator' => 'Création',
        'Director' => 'Réalisation',
        'Screenplay' => 'Scénario',
        'Writer' => 'Scénario',
        'Teleplay' => 'Scénario',
        'Novel' => 'Roman',
        'Story' => 'Histoire',
        'Characters' => 'Personnages',
        'Original Music Composer' => 'Musique',
        'Music' => 'Musique',
        'Director of Photography' => 'Photographie',
        'Editor' => 'Montage',
        'Producer' => 'Production',
        'Executive Producer' => 'Production exécutive',
        'Co-Producer' => 'Coproduction',
        'Casting' => 'Distribution des rôles',
        'Production Design' => 'Création des décors',
        'Art Direction' => 'Direction artistique',
        'Set Decoration' => 'Décors',
        'Costume Design' => 'Costumes',
        'Makeup Artist' => 'Maquillage',
        'Visual Effects Supervisor' => 'Effets visuels',
        'Sound Designer' => 'Conception sonore',
        'Stunt Coordinator' => 'Cascades',
    ];

    /** The other jobs: their department, in this order. */
    private const DEPARTMENTS = [
        'Directing' => 'Réalisation',
        'Writing' => 'Scénario',
        'Sound' => 'Son',
        'Camera' => 'Image',
        'Editing' => 'Montage',
        'Production' => 'Production',
        'Art' => 'Décors',
        'Costume & Make-Up' => 'Costumes et maquillage',
        'Visual Effects' => 'Effets visuels',
        'Lighting' => 'Lumière',
        'Crew' => 'Équipe',
    ];

    /**
     * @param  array<string, mixed>  $title  TMDb movie (with `credits`) or TV show (with `created_by`, `aggregate_credits`)
     * @return list<array{id: int, name: string, profile_path: string|null, jobs: list<string>}>
     */
    public static function of(array $title, MediaType $type): array
    {
        $members = [];
        $add = function (array $person, string $job, ?string $department, int $weight) use (&$members) {
            if (! isset($person['id'])) {
                return;
            }
            $id = (int) $person['id'];
            [$rank, $label] = self::job($job, $department);
            $members[$id] ??= [
                'id' => $id,
                'name' => (string) F::string($person, 'name'),
                'profile_path' => F::string($person, 'profile_path'),
                'jobs' => [],
                'rank' => PHP_INT_MAX,
                'weight' => 0,
                'order' => count($members),
            ];
            $members[$id]['jobs'][$label] = min($members[$id]['jobs'][$label] ?? PHP_INT_MAX, $rank);
            $members[$id]['rank'] = min($members[$id]['rank'], $rank);
            $members[$id]['weight'] = max($members[$id]['weight'], $weight);
        };

        if ($type === MediaType::Movie) {
            foreach (F::array(F::array($title, 'credits'), 'crew') as $member) {
                if (is_array($member)) {
                    $add($member, (string) ($member['job'] ?? ''), F::string($member, 'department'), 0);
                }
            }
        } else {
            foreach (F::array($title, 'created_by') as $creator) {
                if (is_array($creator)) {
                    $add($creator, 'Creator', 'Writing', PHP_INT_MAX);
                }
            }
            foreach (F::array(F::array($title, 'aggregate_credits'), 'crew') as $member) {
                if (! is_array($member)) {
                    continue;
                }
                foreach (F::array($member, 'jobs') as $job) {
                    if (is_array($job)) {
                        $add($member, (string) ($job['job'] ?? ''), F::string($member, 'department'), (int) ($job['episode_count'] ?? 0));
                    }
                }
            }
        }

        // The most important job first, then (TV show) the most episodes, then TMDb's order.
        usort($members, fn (array $a, array $b) => [$a['rank'], -$a['weight'], $a['order']] <=> [$b['rank'], -$b['weight'], $b['order']]);

        return array_map(function (array $member) {
            asort($member['jobs']);

            return [
                'id' => $member['id'],
                'name' => $member['name'],
                'profile_path' => $member['profile_path'],
                'jobs' => array_keys($member['jobs']),
            ];
        }, array_values($members));
    }

    /** @return array{int, string} its rank and its French name */
    private static function job(string $job, ?string $department): array
    {
        $jobs = array_keys(self::JOBS);
        $index = array_search($job, $jobs, true);
        if ($index !== false) {
            return [$index, self::JOBS[$job]];
        }

        $departments = array_keys(self::DEPARTMENTS);
        $index = array_search((string) $department, $departments, true);

        return $index === false
            ? [count($jobs) + count($departments), $job !== '' ? $job : 'Équipe']
            : [count($jobs) + $index, self::DEPARTMENTS[(string) $department]];
    }
}
