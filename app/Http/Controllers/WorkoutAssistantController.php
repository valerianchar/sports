<?php

namespace App\Http\Controllers;

use App\Actions\SuggestCardio;
use App\Actions\SuggestWorkout;
use App\Enums\CardioStyle;
use App\Enums\EquipmentKind;
use App\Enums\ExerciseMode;
use App\Enums\Muscle;
use App\Enums\WorkoutGoal;
use App\Http\Requests\SuggestWorkoutRequest;
use App\Support\ExerciseCatalog;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * L'assistant de création : on dit ce qu'on veut travailler et combien de
 * temps on a, il propose une séance qu'on lance, enregistre ou retouche.
 */
class WorkoutAssistantController extends Controller
{
    public function create(Request $request): Response
    {
        return $this->page(null, $request->query());
    }

    public function suggest(SuggestWorkoutRequest $request, SuggestWorkout $suggestWorkout, SuggestCardio $suggestCardio): Response
    {
        $goal = WorkoutGoal::from($request->validated('goal'));
        $equipment = EquipmentKind::tryFrom((string) $request->validated('equipment'));

        $proposal = $goal === WorkoutGoal::Cardio
            ? $suggestCardio->handle(
                minutes: $request->integer('minutes'),
                style: CardioStyle::tryFrom((string) $request->validated('style')) ?? CardioStyle::Mixed,
                equipment: $equipment,
                stretch: $request->boolean('stretch'),
                variant: $request->integer('variant'),
            )
            : $suggestWorkout->handle(
                muscles: $request->muscles(),
                minutes: $request->integer('minutes'),
                goal: $goal,
                equipment: $equipment,
                warmup: $request->boolean('warmup'),
                stretch: $request->boolean('stretch'),
                variant: $request->integer('variant'),
                settings: $request->settings(),
            );

        $inSession = array_column($proposal['items'], 'exercise');

        // Pour chaque exercice proposé, ses remplaçants classés : le bouton « changer »
        // les fait défiler sans aller-retour, la feuille de choix les liste.
        $proposal['alternatives'] = collect($inSession)
            ->mapWithKeys(fn (string $slug): array => [$slug => $suggestWorkout->alternatives($slug, $equipment, $inSession)])
            ->all();

        $proposal['prescriptions'] = [
            'reps' => $suggestWorkout->prescribe($goal, ExerciseMode::Reps, array_filter($request->settings(), fn ($v): bool => $v !== null)),
            'time' => $suggestWorkout->prescribe($goal, ExerciseMode::Time, array_filter($request->settings(), fn ($v): bool => $v !== null)),
        ];

        return $this->page($proposal, $request->validated());
    }

    /**
     * @param  array{name: string, items: list<array<string, mixed>>, seconds: int}|null  $proposal
     * @param  array<string, mixed>  $input
     */
    private function page(?array $proposal, array $input): Response
    {
        return Inertia::render('Workouts/Assistant', [
            'proposal' => $proposal,
            'exercises' => $proposal === null ? [] : ExerciseCatalog::forClient([
                ...array_column($proposal['items'], 'exercise'),
                ...array_merge(...array_values($proposal['alternatives'] ?? [[]])),
            ]),
            // Toute la bibliothèque, chargée seulement quand on veut choisir soi-même un remplaçant.
            'library' => Inertia::optional(fn (): array => ExerciseCatalog::forClient()),
            'groups' => ExerciseCatalog::groups(),
            'input' => [
                'muscles' => array_values(array_filter((array) ($input['muscles'] ?? []), fn ($muscle): bool => Muscle::tryFrom((string) $muscle) !== null)),
                'minutes' => (int) ($input['minutes'] ?? 45),
                'goal' => WorkoutGoal::tryFrom((string) ($input['goal'] ?? ''))?->value ?? WorkoutGoal::Hypertrophy->value,
                'equipment' => EquipmentKind::tryFrom((string) ($input['equipment'] ?? ''))?->value,
                'style' => CardioStyle::tryFrom((string) ($input['style'] ?? ''))?->value ?? CardioStyle::Mixed->value,
                'warmup' => filter_var($input['warmup'] ?? false, FILTER_VALIDATE_BOOL),
                'stretch' => filter_var($input['stretch'] ?? false, FILTER_VALIDATE_BOOL),
                'variant' => (int) ($input['variant'] ?? 0),
                'reps' => isset($input['reps']) ? (int) $input['reps'] : null,
                'sets' => isset($input['sets']) ? (int) $input['sets'] : null,
                'rest_sets' => isset($input['rest_sets']) ? (int) $input['rest_sets'] : null,
                'rest_after' => isset($input['rest_after']) ? (int) $input['rest_after'] : null,
            ],
            'goals' => array_map(fn (WorkoutGoal $goal): array => [
                'value' => $goal->value,
                'label' => $goal->label(),
                'description' => $goal->description(),
                'strength' => $goal->isStrengthTraining(),
                // Les réglages par défaut de l'objectif, que le formulaire préremplit.
                'prescription' => $goal->prescription(ExerciseMode::Reps),
            ], WorkoutGoal::cases()),
            'styles' => array_map(fn (CardioStyle $style): array => [
                'value' => $style->value,
                'label' => $style->label(),
                'description' => $style->description(),
            ], CardioStyle::cases()),
            'equipments' => array_map(fn (EquipmentKind $kind): array => [
                'value' => $kind->value,
                'label' => $kind->label(),
            ], [EquipmentKind::Machine, EquipmentKind::Free, EquipmentKind::Bodyweight]),
        ]);
    }
}
