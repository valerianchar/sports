<?php

namespace App\Enums;

/**
 * Une série se compte en répétitions — on la valide à la main — ou se chronomètre.
 */
enum ExerciseMode: string
{
    case Reps = 'reps';
    case Time = 'time';

    /**
     * Bornes de la valeur d'une série : des répétitions, ou des secondes d'effort.
     *
     * @return array{int, int}
     */
    public function valueRange(): array
    {
        return match ($this) {
            self::Reps => [1, 100],
            self::Time => [5, 3600],
        };
    }
}
