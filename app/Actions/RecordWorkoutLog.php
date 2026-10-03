<?php

namespace App\Actions;

use App\Models\SetLog;
use App\Models\User;
use App\Models\Workout;
use App\Models\WorkoutLog;
use App\Support\ExerciseCatalog;
use App\Support\Strength;
use Illuminate\Support\Facades\DB;

/**
 * Inscrit une séance terminée dans l'historique, avec chacune de ses séries.
 * L'identifiant fabriqué par le téléphone rend l'opération rejouable : le
 * lecteur renvoie le journal tant qu'il n'a pas eu de réponse, sans jamais
 * compter la séance deux fois.
 *
 * Au passage, on repère les records battus — charge maximale, meilleur 1RM
 * estimé — en comparant à tout ce qui précède : le lecteur les annonce en
 * fin de séance.
 */
final class RecordWorkoutLog
{
    /**
     * @param  array{client_id: string, duration_seconds: int, sets_done: int, exercises_done: int, finished_at: string, sets?: list<array<string, mixed>>}  $data
     * @return array{0: WorkoutLog, 1: list<array{exercise: string, name: string, kind: string, value: float, previous: float}>}
     */
    public function handle(User $user, Workout $workout, array $data): array
    {
        return DB::transaction(function () use ($user, $workout, $data): array {
            $log = $user->workoutLogs()->firstOrCreate(
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

            // Rejoué : déjà enregistré, rien à refaire ni à annoncer.
            if (! $log->wasRecentlyCreated || empty($data['sets'])) {
                return [$log, []];
            }

            $sets = array_map(fn (array $set): array => $this->set($set, $log), $data['sets']);
            $records = $this->records($user, $sets, $log);
            $log->sets()->createMany($sets);

            return [$log, $records];
        });
    }

    /**
     * @param  array<string, mixed>  $set
     * @return array<string, mixed>
     */
    private function set(array $set, WorkoutLog $log): array
    {
        $weight = isset($set['weight']) ? round((float) $set['weight'], 2) : null;
        $reps = isset($set['reps']) ? (int) $set['reps'] : null;

        return [
            'user_id' => $log->user_id,
            'exercise' => $set['exercise'],
            'position' => (int) $set['position'],
            'set_number' => (int) $set['set'],
            'drop' => isset($set['drop']) ? (int) $set['drop'] : null,
            'reps' => $reps,
            'target_reps' => isset($set['target_reps']) ? (int) $set['target_reps'] : null,
            'seconds' => isset($set['seconds']) ? (int) $set['seconds'] : null,
            'weight' => $weight,
            'e1rm' => Strength::oneRepMax($weight, $reps),
            'volume' => $weight !== null && $reps !== null ? round($weight * $reps, 2) : 0,
            'performed_at' => $set['at'] ?? $log->finished_at,
        ];
    }

    /**
     * Les records battus par ces séries. Un premier passage sur un exercice
     * n'est pas un record : il n'y a rien à battre.
     *
     * @param  list<array<string, mixed>>  $sets
     * @return list<array{exercise: string, name: string, kind: string, value: float, previous: float}>
     */
    private function records(User $user, array $sets, WorkoutLog $log): array
    {
        $exercises = array_values(array_unique(array_column($sets, 'exercise')));

        $previous = SetLog::query()
            ->where('user_id', $user->id)
            ->whereIn('exercise', $exercises)
            ->where('workout_log_id', '!=', $log->id)
            ->groupBy('exercise')
            ->selectRaw('exercise, max(weight) as weight, max(e1rm) as e1rm')
            ->get()
            ->keyBy('exercise');

        $records = [];

        foreach ($exercises as $slug) {
            $before = $previous->get($slug);

            if ($before === null) {
                continue;
            }

            $mine = array_filter($sets, fn (array $set): bool => $set['exercise'] === $slug);
            $name = ExerciseCatalog::find($slug)['name'] ?? $slug;
            $bestWeight = max([0, ...array_column($mine, 'weight')]);
            $bestE1rm = max([0, ...array_filter(array_column($mine, 'e1rm'))]);

            if ($before->weight !== null && $bestWeight > (float) $before->weight) {
                $records[] = ['exercise' => $slug, 'name' => $name, 'kind' => 'weight', 'value' => (float) $bestWeight, 'previous' => (float) $before->weight];
            }

            if ($before->e1rm !== null && $bestE1rm > (float) $before->e1rm) {
                $records[] = ['exercise' => $slug, 'name' => $name, 'kind' => 'e1rm', 'value' => round((float) $bestE1rm, 1), 'previous' => round((float) $before->e1rm, 1)];
            }
        }

        return $records;
    }
}
