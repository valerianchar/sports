<?php

namespace App\Http\Controllers;

use App\Actions\SaveWorkout;
use App\Http\Requests\SaveWorkoutRequest;
use App\Http\Resources\WorkoutResource;
use App\Models\Workout;
use App\Support\ExerciseCatalog;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class WorkoutController extends Controller
{
    /**
     * « Mes séances » : l'écran d'accueil.
     */
    public function index(Request $request): Response
    {
        $workouts = $request->user()->workouts()
            ->with(['items', 'latestLog'])
            ->oldest()
            ->get();

        return Inertia::render('Workouts/Index', [
            'workouts' => WorkoutResource::collection($workouts),
            // Les séances ne portent que des slugs : groupes et durées se déduisent du catalogue.
            'exercises' => ExerciseCatalog::forClient($workouts->flatMap->items->pluck('exercise')),
        ]);
    }

    public function create(): Response
    {
        return $this->editor(null);
    }

    public function store(SaveWorkoutRequest $request, SaveWorkout $saveWorkout): RedirectResponse
    {
        $workout = $saveWorkout->handle($request->user(), null, $request->workoutName(), $request->items());

        return $this->afterSave($request, $workout, 'Séance créée.');
    }

    public function edit(Workout $workout): Response
    {
        Gate::authorize('update', $workout);

        return $this->editor($workout->load('items'));
    }

    public function update(SaveWorkoutRequest $request, Workout $workout, SaveWorkout $saveWorkout): RedirectResponse
    {
        Gate::authorize('update', $workout);

        $saveWorkout->handle($request->user(), $workout, $request->workoutName(), $request->items());

        return $this->afterSave($request, $workout, 'Séance enregistrée.');
    }

    public function destroy(Workout $workout): RedirectResponse
    {
        Gate::authorize('delete', $workout);

        $workout->delete();

        return redirect()->route('workouts.index')->with('success', "« {$workout->name} » supprimée.");
    }

    /**
     * Le lecteur : tout se joue ensuite dans le navigateur, minuteurs compris.
     * Une séance vide n'a rien à lancer — on la renvoie à l'éditeur.
     */
    public function play(Request $request, Workout $workout): Response|RedirectResponse
    {
        Gate::authorize('view', $workout);

        $workout->load('items');

        if ($workout->items->isEmpty()) {
            return redirect()->route('workouts.edit', $workout)->with('error', 'Ajoute un exercice avant de lancer la séance.');
        }

        return Inertia::render('Workouts/Play', [
            'workout' => new WorkoutResource($workout),
            'exercises' => ExerciseCatalog::forClient($workout->items->pluck('exercise')),
            'preferences' => [
                'sound' => $request->user()->sound,
                'prep_seconds' => $request->user()->prep_seconds,
            ],
        ]);
    }

    private function editor(?Workout $workout): Response
    {
        return Inertia::render('Workouts/Edit', [
            'workout' => $workout === null ? null : new WorkoutResource($workout),
            'exercises' => ExerciseCatalog::forClient(),
            'groups' => ExerciseCatalog::groups(),
            'maxItems' => config('sport.max_items'),
        ]);
    }

    private function afterSave(SaveWorkoutRequest $request, Workout $workout, string $message): RedirectResponse
    {
        if ($request->boolean('start') && $workout->items()->exists()) {
            return redirect()->route('workouts.play', $workout);
        }

        if ($request->boolean('edit')) {
            return redirect()->route('workouts.edit', $workout)->with('success', $message.' Ajuste-la à ton goût.');
        }

        return redirect()->route('workouts.index')->with('success', $message);
    }
}
