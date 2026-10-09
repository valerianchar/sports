<?php

namespace App\Support;

/**
 * Durée estimée d'une séance, en secondes — le même calcul que l'éditeur
 * (resources/js/workout.js, estimate) : les répétitions au tempo réglé, les
 * séries chronométrées à leur durée, les repos entre séries et entre
 * exercices, sauf après le dernier ; les paliers de drop set au même tempo.
 * Un exercice compté par côté dure deux fois sa valeur : droite, puis gauche.
 */
final class WorkoutEstimate
{
    /**
     * @param  list<array{mode: string, value: int, sets: int, rest_sets: int, rest_after: int, drops?: list<array{reps: int}>|null, drop_on?: string|null}>  $items
     */
    public static function seconds(array $items): int
    {
        $tempo = (float) config('sport.seconds_per_rep');
        $total = 0.0;
        $last = count($items) - 1;

        foreach (array_values($items) as $index => $item) {
            $effort = ($item['mode'] === 'reps' ? $item['value'] * $tempo : $item['value']) * (empty($item['per_side']) ? 1 : 2);
            $total += $item['sets'] * $effort + ($item['sets'] - 1) * $item['rest_sets'];

            // Les paliers d'un drop set s'enchaînent sans repos, sur la dernière série ou sur chacune.
            if (! empty($item['drops'])) {
                $dropReps = array_sum(array_column($item['drops'], 'reps'));
                $total += $dropReps * $tempo * (($item['drop_on'] ?? 'last') === 'all' ? $item['sets'] : 1);
            }

            if ($index < $last) {
                $total += $item['rest_after'];
            }
        }

        return (int) round($total);
    }
}
