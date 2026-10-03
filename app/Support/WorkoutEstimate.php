<?php

namespace App\Support;

/**
 * Durée estimée d'une séance, en secondes — le même calcul que l'éditeur
 * (resources/js/workout.js, estimate) : les répétitions au tempo réglé, les
 * séries chronométrées à leur durée, les repos entre séries et entre
 * exercices, sauf après le dernier.
 */
final class WorkoutEstimate
{
    /**
     * @param  list<array{mode: string, value: int, sets: int, rest_sets: int, rest_after: int}>  $items
     */
    public static function seconds(array $items): int
    {
        $tempo = (float) config('sport.seconds_per_rep');
        $total = 0.0;
        $last = count($items) - 1;

        foreach (array_values($items) as $index => $item) {
            $effort = $item['mode'] === 'reps' ? $item['value'] * $tempo : $item['value'];
            $total += $item['sets'] * $effort + ($item['sets'] - 1) * $item['rest_sets'];

            if ($index < $last) {
                $total += $item['rest_after'];
            }
        }

        return (int) round($total);
    }
}
