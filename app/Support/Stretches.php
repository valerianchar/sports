<?php

namespace App\Support;

use App\Enums\MuscleGroup;
use Random\Randomizer;

/**
 * Les étirements de fin de séance que l'assistant ajoute sur demande.
 */
final class Stretches
{
    /**
     * Deux étirements des muscles travaillés, tenus 30 secondes, deux fois.
     *
     * @param  list<string>  $targets
     * @return list<array<string, mixed>>
     */
    public static function pick(array $targets, Randomizer $random): array
    {
        return ExerciseCatalog::all()
            ->filter(fn (array $exercise): bool => $exercise['group'] === MuscleGroup::Mobilite->value
                && str_starts_with($exercise['slug'], 'etirement-')
                && array_intersect($exercise['primary'], $targets) !== [])
            ->sortByDesc(fn (array $exercise): float => count(array_intersect($exercise['primary'], $targets)) + $random->getFloat(0, 0.5))
            ->take(2)
            // Un étirement d'un côté puis de l'autre se tient 30 secondes par côté.
            ->map(fn (array $exercise): array => ['exercise' => $exercise['slug'], 'mode' => 'time', 'per_side' => ($exercise['sides'] ?? 'both') === 'each' ? true : null, 'value' => 30, 'sets' => 2, 'rest_sets' => 10, 'rest_after' => 15])
            ->values()
            ->all();
    }
}
