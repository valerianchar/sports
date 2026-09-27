<?php

namespace App\Support;

use App\Enums\ExerciseMode;
use App\Enums\MuscleGroup;

/**
 * Réglages d'un exercice qu'on vient d'ajouter à une séance — les mêmes que ceux
 * de l'éditeur (resources/js/workout.js), qui les applique sans aller-retour.
 */
final class WorkoutDefaults
{
    /**
     * @return array{exercise: string, mode: string, value: int, sets: int, rest_sets: int, rest_after: int}
     */
    public static function for(string $slug): array
    {
        $mode = ExerciseCatalog::mode($slug);

        $values = match (true) {
            // Le cardio se fait d'une traite : 5 minutes, une seule série.
            $mode === ExerciseMode::Time && ExerciseCatalog::group($slug) === MuscleGroup::Cardio => [300, 1, 0, 60],
            $mode === ExerciseMode::Time => [30, 3, 30, 60],
            default => [10, 3, 60, 90],
        };

        return [
            'exercise' => $slug,
            'mode' => $mode->value,
            'value' => $values[0],
            'sets' => $values[1],
            'rest_sets' => $values[2],
            'rest_after' => $values[3],
        ];
    }
}
