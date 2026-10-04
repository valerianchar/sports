<?php

namespace App\Enums;

/**
 * L'objectif d'une séance proposée par l'assistant, et les réglages qui vont
 * avec : peu de répétitions lourdes et de longs repos pour la force, la
 * fourchette classique 8–12 pour le volume, des séries longues et des repos
 * courts pour l'endurance. La perte de poids enchaîne des mouvements complets
 * presque sans pause et finit par du fractionné ; le cardio se passe des
 * muscles et des séries, il se compose en blocs sur les machines.
 */
enum WorkoutGoal: string
{
    case Strength = 'force';
    case Hypertrophy = 'volume';
    case Endurance = 'endurance';
    case WeightLoss = 'perte-de-poids';
    case Cardio = 'cardio';

    public function label(): string
    {
        return match ($this) {
            self::Strength => 'Force',
            self::Hypertrophy => 'Volume',
            self::Endurance => 'Endurance',
            self::WeightLoss => 'Perte de poids',
            self::Cardio => 'Cardio',
        };
    }

    public function description(): string
    {
        return match ($this) {
            self::Strength => '5 reps lourdes, longs repos',
            self::Hypertrophy => '10 reps, prise de muscle',
            self::Endurance => '15 reps, repos courts',
            self::WeightLoss => 'Circuit sans temps mort, finisher cardio',
            self::Cardio => 'Machines ou poids du corps, continu ou fractionné',
        };
    }

    /** Musculation au sens strict : on y choisit des muscles et un objectif de charge. */
    public function isStrengthTraining(): bool
    {
        return in_array($this, [self::Strength, self::Hypertrophy, self::Endurance], true);
    }

    /**
     * Réglages d'un exercice pour cet objectif : répétitions (ou secondes pour
     * un exercice chronométré), séries, repos entre séries et après l'exercice.
     *
     * @return array{value: int, sets: int, rest_sets: int, rest_after: int}
     */
    public function prescription(ExerciseMode $mode): array
    {
        return match ($this) {
            self::Strength => ['value' => $mode === ExerciseMode::Time ? 30 : 5, 'sets' => 4, 'rest_sets' => 150, 'rest_after' => 120],
            self::Hypertrophy => ['value' => $mode === ExerciseMode::Time ? 40 : 10, 'sets' => 3, 'rest_sets' => 75, 'rest_after' => 90],
            self::Endurance => ['value' => $mode === ExerciseMode::Time ? 45 : 15, 'sets' => 3, 'rest_sets' => 40, 'rest_after' => 60],
            self::WeightLoss => ['value' => $mode === ExerciseMode::Time ? 40 : 15, 'sets' => 3, 'rest_sets' => 30, 'rest_after' => 45],
            self::Cardio => ['value' => $mode === ExerciseMode::Time ? 300 : 15, 'sets' => 1, 'rest_sets' => 0, 'rest_after' => 60],
        };
    }
}
