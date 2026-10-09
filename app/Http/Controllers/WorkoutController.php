<?php

namespace App\Http\Controllers;

use App\Actions\SaveWorkout;
use App\Actions\SuggestWorkout;
use App\Enums\EquipmentKind;
use App\Http\Requests\SaveWorkoutRequest;
use App\Http\Resources\WorkoutResource;
use App\Models\Workout;
use App\Queries\PerformanceStats;
use App\Support\ExerciseCatalog;
use Illuminate\Database\Eloquent\Collection;
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
    /**
     * « Aujourd'hui » : reprendre une séance, en composer une, refaire une
     * séance récente, et la semaine en un coup d'œil.
     */
    public function index(Request $request): Response
    {
        $workouts = $this->workouts($request)
            // Les plus récemment faites d'abord ; les jamais faites, les plus récentes.
            ->sortByDesc(fn (Workout $workout): string => $workout->latestLog?->finished_at?->toIso8601String() ?? '0'.$workout->created_at->toIso8601String())
            ->values();

        return Inertia::render('Home', [
            'workouts' => WorkoutResource::collection($workouts->take(3)),
            'workoutsCount' => $workouts->count(),
            // Une séance laissée en cours sur ce téléphone retrouve son nom.
            'workoutNames' => $workouts->mapWithKeys(fn (Workout $workout): array => [$workout->id => $workout->name]),
            'exercises' => ExerciseCatalog::forClient($workouts->take(3)->flatMap->items->pluck('exercise')),
            'kpis' => (new PerformanceStats($request->user()))->home(),
        ]);
    }

    /**
     * Toutes les séances, pour les lancer, les modifier ou les supprimer.
     */
    public function list(Request $request): Response
    {
        $workouts = $this->workouts($request);

        return Inertia::render('Workouts/Index', [
            'workouts' => WorkoutResource::collection($workouts),
            // Les séances ne portent que des slugs : groupes et durées se déduisent du catalogue.
            'exercises' => ExerciseCatalog::forClient($workouts->flatMap->items->pluck('exercise')),
        ]);
    }

    /** @return Collection<int, Workout> */
    private function workouts(Request $request): Collection
    {
        return $request->user()->workouts()->with(['items', 'latestLog'])->oldest()->get();
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

        return redirect()->route('workouts.list')->with('success', "« {$workout->name} » supprimée.");
    }

    /**
     * Le lecteur : tout se joue ensuite dans le navigateur, minuteurs compris.
     * Une séance vide n'a rien à lancer — on la renvoie à l'éditeur.
     */
    public function play(Request $request, Workout $workout, SuggestWorkout $suggestWorkout): Response|RedirectResponse
    {
        Gate::authorize('view', $workout);

        $workout->load('items');

        if ($workout->items->isEmpty()) {
            return redirect()->route('workouts.edit', $workout)->with('error', 'Ajoute un exercice avant de lancer la séance.');
        }

        $slugs = $workout->items->pluck('exercise')->all();
        // Les variantes de chaque exercice, prêtes hors réseau : la machine est prise, on en change sur place.
        $alternatives = collect($slugs)->unique()
            ->mapWithKeys(fn (string $slug): array => [$slug => $suggestWorkout->alternatives($slug, exclude: $slugs)])
            ->all();

        return Inertia::render('Workouts/Play', [
            'workout' => new WorkoutResource($workout),
            'exercises' => ExerciseCatalog::forClient([...$slugs, ...array_merge([], ...array_values($alternatives))]),
            'alternatives' => $alternatives,
            // La dernière fois sur chaque exercice, et la charge conseillée cette fois-ci.
            'history' => (new PerformanceStats($request->user()))->lastTimes($workout->items->pluck('exercise')->unique()->values()->all()),
            'preferences' => [
                'sound' => $request->user()->sound,
                'prep_seconds' => $request->user()->prep_seconds,
                'countdown_seconds' => $request->user()->countdown_seconds,
                'volume' => $request->user()->volume,
                'countdown_sound' => $request->user()->countdown_sound->value,
                'custom_sound_url' => $request->user()->custom_sound_url,
                'audio_mode' => $request->user()->audio_mode->value,
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
            // Le matériel qu'on peut demander à l'assistant pour compléter la séance.
            'equipments' => array_map(fn (EquipmentKind $kind): array => [
                'value' => $kind->value,
                'label' => $kind->label(),
            ], [EquipmentKind::Machine, EquipmentKind::Free, EquipmentKind::Bodyweight]),
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

        return redirect()->route('workouts.list')->with('success', $message);
    }
}
