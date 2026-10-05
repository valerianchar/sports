<?php

namespace App\Http\Controllers;

use App\Actions\SuggestWorkout;
use App\Support\ExerciseCatalog;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * Les variantes d'un exercice — mêmes muscles, autre machine — pour changer
 * dans l'éditeur un exercice dont la machine manque ou ne plaît pas. JSON.
 */
class ExerciseEquivalentController extends Controller
{
    public function index(Request $request, string $exercise, SuggestWorkout $suggestWorkout): JsonResponse
    {
        abort_if(ExerciseCatalog::find($exercise) === null, 404);

        $data = $request->validate([
            'exclude' => ['array', 'max:'.config('sport.max_items')],
            'exclude.*' => ['string', Rule::in(ExerciseCatalog::slugs())],
        ]);

        return response()->json([
            'equivalents' => $suggestWorkout->alternatives($exercise, exclude: $data['exclude'] ?? [], limit: 12),
        ]);
    }
}
