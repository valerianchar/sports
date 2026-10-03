<?php

namespace App\Actions;

use App\Models\User;
use App\Models\Workout;
use Illuminate\Support\Facades\DB;

/**
 * Enregistre une séance d'un bloc : son nom et la liste complète de ses
 * exercices, dans l'ordre. L'éditeur envoie toujours l'état entier — réordonner,
 * retirer, régler se résument donc à remplacer les lignes.
 */
final class SaveWorkout
{
    /**
     * @param  list<array{exercise: string, mode: string, value: int, weight?: float|null, sets: int, rest_sets: int, rest_after: int}>  $items
     */
    public function handle(User $user, ?Workout $workout, string $name, array $items): Workout
    {
        return DB::transaction(function () use ($user, $workout, $name, $items): Workout {
            $workout ??= $user->workouts()->make();
            $workout->name = $name;
            $workout->save();

            $workout->items()->delete();
            $workout->items()->createMany(array_map(
                fn (array $item, int $position): array => [...$item, 'position' => $position],
                $items,
                array_keys($items),
            ));

            return $workout;
        });
    }
}
