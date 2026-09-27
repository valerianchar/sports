<?php

namespace App\Policies;

use App\Models\User;
use App\Models\Workout;

/**
 * Une séance n'appartient qu'à celui qui l'a composée : personne d'autre ne la
 * voit, ne la lance ni ne la modifie.
 */
class WorkoutPolicy
{
    public function view(User $user, Workout $workout): bool
    {
        return $workout->user_id === $user->id;
    }

    public function update(User $user, Workout $workout): bool
    {
        return $workout->user_id === $user->id;
    }

    public function delete(User $user, Workout $workout): bool
    {
        return $workout->user_id === $user->id;
    }
}
