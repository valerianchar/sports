<?php

namespace App\Http\Controllers;

use App\Models\Workout;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

/**
 * Les réglages d'une machine de cardio changés en pleine séance — le tapis
 * monté à 10 km/h — deviennent ceux de l'exercice pour la prochaine fois,
 * comme la charge. JSON, appelé par le lecteur sans quitter l'écran.
 */
class WorkoutItemSettingsController extends Controller
{
    public function update(Request $request, Workout $workout): JsonResponse
    {
        Gate::authorize('update', $workout);

        $data = $request->validate([
            'position' => ['required', 'integer', 'min:0'],
            'speed' => ['sometimes', 'nullable', 'numeric', 'min:0.5', 'max:25'],
            'incline' => ['sometimes', 'nullable', 'numeric', 'min:-3', 'max:30'],
            'level' => ['sometimes', 'nullable', 'integer', 'min:1', 'max:30'],
        ]);

        $item = $workout->items()->where('position', $data['position'])->firstOrFail();
        $item->update(array_intersect_key($data, array_flip(['speed', 'incline', 'level'])));

        return response()->json($item->only(['speed', 'incline', 'level']));
    }
}
