<?php

namespace App\Actions;

use App\Models\User;
use App\Support\WorkoutDefaults;

/**
 * Les trois séances d'exemple de la maquette, offertes à l'inscription : on
 * découvre le lecteur tout de suite, et on les modifie ou les supprime ensuite.
 */
final class CreateStarterWorkouts
{
    public function __construct(private readonly SaveWorkout $saveWorkout) {}

    public function handle(User $user): void
    {
        $item = fn (string $slug, array $overrides = []): array => [...WorkoutDefaults::for($slug), ...$overrides];

        $this->saveWorkout->handle($user, null, 'Push — haut du corps', [
            $item('developpe-couche', ['sets' => 4, 'value' => 8, 'rest_sets' => 90]),
            $item('developpe-incline-halteres'),
            $item('butterfly', ['value' => 12]),
            $item('developpe-epaules-machine'),
            $item('elevations-laterales', ['value' => 15, 'rest_sets' => 45]),
            $item('extension-triceps-poulie', ['value' => 12]),
        ]);

        $this->saveWorkout->handle($user, null, 'Jambes', [
            $item('squat', ['sets' => 4, 'value' => 6, 'rest_sets' => 120, 'rest_after' => 120]),
            $item('presse-a-cuisses', ['value' => 12]),
            $item('leg-extension', ['value' => 15]),
            $item('leg-curl', ['value' => 12]),
            $item('hip-thrust'),
            $item('mollets-debout', ['sets' => 4, 'value' => 15, 'rest_sets' => 45]),
        ]);

        $this->saveWorkout->handle($user, null, 'HIIT 20 minutes', [
            $item('velo', ['value' => 240, 'rest_after' => 30]),
            $item('burpees', ['sets' => 4, 'value' => 12, 'rest_sets' => 20, 'rest_after' => 30]),
            $item('kettlebell-swing', ['sets' => 4, 'value' => 15, 'rest_sets' => 20, 'rest_after' => 30]),
            $item('mountain-climbers', ['sets' => 4, 'value' => 30, 'rest_sets' => 20, 'rest_after' => 30]),
            $item('gainage-planche', ['sets' => 3, 'value' => 45, 'rest_sets' => 20]),
        ]);
    }
}
