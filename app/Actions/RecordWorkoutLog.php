<?php

namespace App\Actions;

use App\Models\User;
use App\Models\Workout;
use App\Models\WorkoutLog;

/**
 * Inscrit une séance terminée dans l'historique. L'identifiant fabriqué par le
 * téléphone rend l'opération rejouable : le lecteur renvoie le journal tant
 * qu'il n'a pas eu de réponse, sans jamais compter la séance deux fois.
 */
final class RecordWorkoutLog
{
    /**
     * @param  array{client_id: string, duration_seconds: int, sets_done: int, exercises_done: int, finished_at: string}  $data
     */
    public function handle(User $user, Workout $workout, array $data): WorkoutLog
    {
        return $user->workoutLogs()->firstOrCreate(
            ['client_id' => $data['client_id']],
            [
                'workout_id' => $workout->id,
                'name' => $workout->name,
                'duration_seconds' => $data['duration_seconds'],
                'sets_done' => $data['sets_done'],
                'exercises_done' => $data['exercises_done'],
                'finished_at' => $data['finished_at'],
            ],
        );
    }
}
