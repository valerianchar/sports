<?php

namespace App\Http\Controllers;

use App\Http\Requests\UpdateItemWeightRequest;
use App\Models\Workout;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Gate;

/**
 * La charge changée en pleine séance — on a pris 2,5 kg de plus à la troisième
 * série — devient celle de l'exercice : la prochaine fois, le lecteur
 * l'affiche d'emblée. JSON, appelé par le lecteur sans quitter l'écran.
 */
class WorkoutItemWeightController extends Controller
{
    public function update(UpdateItemWeightRequest $request, Workout $workout): JsonResponse
    {
        Gate::authorize('update', $workout);

        $item = $workout->items()->where('position', $request->integer('position'))->firstOrFail();
        $weight = $request->validated('weight');
        $item->update(['weight' => $weight === null ? null : round((float) $weight, 2)]);

        return response()->json(['weight' => $item->weight]);
    }
}
