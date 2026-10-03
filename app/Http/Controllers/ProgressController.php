<?php

namespace App\Http\Controllers;

use App\Queries\PerformanceStats;
use App\Support\ExerciseCatalog;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * « Progrès » : les indicateurs de performance — vue d'ensemble, exercices,
 * muscles, corps — et l'historique détaillé d'un exercice.
 */
class ProgressController extends Controller
{
    public function index(Request $request): Response
    {
        $stats = new PerformanceStats($request->user());

        return Inertia::render('Progress/Index', [
            'overview' => $stats->overview(),
            'exercises' => $stats->exercises(),
            'muscles' => $stats->muscles(),
            'body' => $stats->body(),
            'tab' => in_array($request->query('vue'), ['apercu', 'exercices', 'muscles', 'corps'], true) ? $request->query('vue') : 'apercu',
        ]);
    }

    public function exercise(Request $request, string $exercise): Response
    {
        abort_if(ExerciseCatalog::find($exercise) === null, 404);

        return Inertia::render('Progress/Exercise', [
            'exercise' => ExerciseCatalog::forClient([$exercise])[0],
            'stats' => (new PerformanceStats($request->user()))->exercise($exercise),
        ]);
    }
}
