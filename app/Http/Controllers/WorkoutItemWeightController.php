<?php

namespace App\Http\Controllers;

use App\Http\Requests\UpdateItemWeightRequest;
use App\Models\Workout;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Gate;

/**
 * La charge changée en pleine séance — on a pris 2,5 kg de plus à la troisième
 * série — devient celle de l'exercice : la prochaine fois, le lecteur
 * l'affiche d'emblée. Selon l'exercice, c'est la charge fixe, celle d'une
 * série (dégressif) ou celle d'un palier de drop set. JSON, appelé par le
 * lecteur sans quitter l'écran.
 */
class WorkoutItemWeightController extends Controller
{
    public function update(UpdateItemWeightRequest $request, Workout $workout): JsonResponse
    {
        Gate::authorize('update', $workout);

        $item = $workout->items()->where('position', $request->integer('position'))->firstOrFail();
        $weight = $request->validated('weight') === null ? null : round((float) $request->validated('weight'), 2);

        if ($request->filled('drop')) {
            $drops = $item->drops ?? [];
            abort_unless(isset($drops[$request->integer('drop')]), 404);
            $drops[$request->integer('drop')]['weight'] = $weight;
            $item->drops = $drops;
        } elseif ($request->filled('set') && $item->set_weights !== null) {
            $weights = $item->set_weights;
            abort_unless(array_key_exists($request->integer('set'), $weights), 404);
            $weights[$request->integer('set')] = $weight;
            $item->set_weights = $weights;
        } else {
            $item->weight = $weight;
        }

        $item->save();

        return response()->json(['weight' => $weight]);
    }
}
