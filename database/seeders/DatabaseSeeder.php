<?php

namespace Database\Seeders;

use App\Actions\CreateStarterWorkouts;
use App\Models\User;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Un profil de démonstration avec les trois séances d'exemple.
     */
    public function run(CreateStarterWorkouts $createStarterWorkouts): void
    {
        $user = User::query()->firstOrCreate(
            ['email' => 'demo@seance.test'],
            ['name' => 'Marie Demo', 'password' => 'password'],
        );

        if ($user->workouts()->doesntExist()) {
            $createStarterWorkouts->handle($user);
        }
    }
}
