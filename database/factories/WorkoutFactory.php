<?php

namespace Database\Factories;

use App\Models\User;
use App\Models\Workout;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Workout>
 */
class WorkoutFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'name' => fake()->randomElement(['Push', 'Pull', 'Jambes', 'Full body', 'HIIT']),
        ];
    }

    /**
     * @param  list<string>  $exercises  slugs du catalogue
     */
    public function withItems(array $exercises = ['developpe-couche', 'gainage-planche']): static
    {
        return $this->afterCreating(function (Workout $workout) use ($exercises): void {
            foreach ($exercises as $position => $slug) {
                $timed = $slug === 'gainage-planche';

                $workout->items()->create([
                    'position' => $position,
                    'exercise' => $slug,
                    'mode' => $timed ? 'time' : 'reps',
                    'value' => $timed ? 30 : 10,
                    'sets' => 3,
                    'rest_sets' => 60,
                    'rest_after' => 90,
                ]);
            }
        });
    }
}
