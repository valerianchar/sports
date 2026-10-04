<?php

namespace App\Support;

use App\Enums\MuscleGroup;

/**
 * Les calories dépensées pendant une séance, estimées en MET (équivalents
 * métaboliques, valeurs arrondies du Compendium of Physical Activities) :
 * kcal = MET × poids (kg) × durée (h).
 *
 * Toute la séance compte comme de la musculation — repos compris, d'où un MET
 * modéré —, et chaque effort de cardio ou de fonctionnel ajoute la différence
 * avec sa propre intensité. Une estimation, honnête à 20 % près : de quoi
 * suivre une tendance, pas compter au gramme.
 */
final class Energy
{
    /** Le poids de référence tant qu'aucune pesée n'est notée. */
    public const DEFAULT_WEIGHT = 75.0;

    /** Musculation en séance complète, repos compris. */
    private const BASE_MET = 4.0;

    private const FUNCTIONAL_MET = 7.0;

    private const CARDIO_MET = 7.0;

    /** @var array<string, float> */
    private const MET = [
        'course' => 9.8, 'marche-inclinee' => 6.0, 'sprints-fractionnes' => 11.0, 'tapis-curve' => 10.0,
        'velo' => 6.8, 'velo-semi-allonge' => 5.5, 'velo-de-biking' => 8.5, 'air-bike' => 10.0,
        'elliptique' => 5.0, 'stepper' => 8.8, 'escalier' => 9.0, 'rameur' => 7.0, 'skierg' => 8.0,
        'ergometre-a-bras' => 4.5, 'corde-a-sauter' => 11.0, 'burpees' => 8.0, 'jumping-jacks' => 7.7,
        'montees-de-genoux' => 8.0, 'talons-fesses' => 7.0, 'shadow-boxing' => 5.5,
    ];

    /**
     * @param  iterable<array{exercise: string, seconds: int|null}>  $efforts  les séries faites
     */
    public static function kcal(int $durationSeconds, iterable $efforts, float $kg): int
    {
        $extra = 0.0;
        $intense = 0;

        foreach ($efforts as $effort) {
            $met = self::met($effort['exercise']);
            $seconds = (int) ($effort['seconds'] ?? 0);

            if ($met !== null && $seconds > 0) {
                $extra += ($met - self::BASE_MET) * $seconds;
                $intense += $seconds;
            }
        }

        // Des efforts plus longs que la séance elle-même : un chrono resté ouvert, on s'en tient à la séance.
        if ($intense > $durationSeconds && $intense > 0) {
            $extra *= $durationSeconds / $intense;
        }

        return (int) round($kg * (self::BASE_MET * $durationSeconds + $extra) / 3600);
    }

    /** Un exercice de cardio : ses secondes d'effort comptent comme du cardio. */
    public static function isCardio(string $slug): bool
    {
        return ExerciseCatalog::group($slug) === MuscleGroup::Cardio;
    }

    /**
     * L'intensité d'un effort de cardio ou de fonctionnel ; rien pour la musculation.
     */
    private static function met(string $slug): ?float
    {
        return match (ExerciseCatalog::group($slug)) {
            MuscleGroup::Cardio => self::MET[$slug] ?? self::CARDIO_MET,
            MuscleGroup::Fonctionnel => self::FUNCTIONAL_MET,
            default => null,
        };
    }
}
