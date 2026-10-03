<?php

namespace App\Support;

/**
 * Les calculs de force.
 *
 * Le 1RM estimé — la charge qu'on soulèverait une seule fois — suit la
 * formule d'Epley, charge × (1 + reps / 30), fiable jusqu'à une douzaine de
 * répétitions ; au-delà, on n'estime plus : une série de 25 dit l'endurance,
 * pas la force maximale.
 */
final class Strength
{
    public const MAX_REPS_FOR_ESTIMATE = 12;

    public static function oneRepMax(?float $weight, ?int $reps): ?float
    {
        if ($weight === null || $weight <= 0 || $reps === null || $reps < 1 || $reps > self::MAX_REPS_FOR_ESTIMATE) {
            return null;
        }

        return round($reps === 1 ? $weight : $weight * (1 + $reps / 30), 2);
    }

    /** Arrondi au disque le plus proche : 2,5 kg, ou 1 kg sous 10 kg (haltères). */
    public static function plate(float $weight): float
    {
        $step = $weight < 10 ? 1.0 : 2.5;

        return max($step, round($weight / $step) * $step);
    }

    /**
     * La charge à mettre la prochaine fois — la surcharge progressive : toutes
     * les répétitions visées réussies, on monte d'un cran (1 kg aux haltères
     * légers, 2,5 kg, 5 kg au-delà de 100 kg) ; deux répétitions ou plus de
     * manquées sur une série, on redescend de 5 % ; entre les deux, on garde.
     *
     * @param  list<array{weight: float|null, reps: int|null, target_reps: int|null}>  $sets  les séries de la dernière fois
     * @return array{weight: float, trend: string}|null
     */
    public static function nextWeight(array $sets): ?array
    {
        $loaded = array_values(array_filter($sets, fn (array $set): bool => ($set['weight'] ?? 0) > 0 && $set['reps'] !== null));

        if ($loaded === []) {
            return null;
        }

        $top = max(array_column($loaded, 'weight'));
        $missed = 0;
        $allMade = true;

        foreach ($loaded as $set) {
            $target = $set['target_reps'] ?? $set['reps'];
            $missed = max($missed, $target - $set['reps']);
            $allMade = $allMade && $set['reps'] >= $target;
        }

        if ($allMade) {
            $step = $top < 10 ? 1.0 : ($top >= 100 ? 5.0 : 2.5);

            return ['weight' => round($top + $step, 2), 'trend' => 'up'];
        }

        if ($missed >= 2) {
            return ['weight' => self::plate($top * 0.95), 'trend' => 'down'];
        }

        return ['weight' => $top, 'trend' => 'keep'];
    }
}
