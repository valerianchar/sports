<?php

namespace App\Actions;

use App\Models\User;
use App\Models\Workout;
use App\Models\WorkoutItem;
use Illuminate\Support\Facades\DB;

/**
 * Copie une séance — la sienne (dupliquer pour en faire une variante) ou celle
 * qu'un autre a partagée — avec tous ses exercices et leurs réglages.
 */
final class CopyWorkout
{
    /** Ce qui se copie d'un exercice : tout sauf ce qui le rattache à sa séance. */
    private const COPIED = ['position', 'exercise', 'mode', 'per_side', 'value', 'weight', 'set_weights', 'drops', 'drop_on', 'speed', 'incline', 'level', 'sets', 'rest_sets', 'rest_after', 'superset'];

    public function handle(Workout $source, User $owner, string $name): Workout
    {
        return DB::transaction(function () use ($source, $owner, $name): Workout {
            $copy = $owner->workouts()->create(['name' => mb_substr($name, 0, 80)]);

            $copy->items()->createMany($source->items->map(
                fn (WorkoutItem $item): array => array_intersect_key($item->getAttributes(), array_flip(self::COPIED)),
            )->all());

            return $copy;
        });
    }
}
