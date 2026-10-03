<?php

namespace App\Http\Controllers;

use App\Actions\SuggestWorkout;
use App\Enums\EquipmentKind;
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

    public function suggest(SuggestWorkoutRequest $request, SuggestWorkout $suggestWorkout): Response
    {
        $proposal = $suggestWorkout->handle(
            muscles: $request->muscles(),
            minutes: $request->integer('minutes'),
            goal: WorkoutGoal::from($request->validated('goal')),
            equipment: EquipmentKind::tryFrom((string) $request->validated('equipment')),
            warmup: $request->boolean('warmup'),
            stretch: $request->boolean('stretch'),
            variant: $request->integer('variant'),
        );

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
            'exercises' => $proposal === null ? [] : ExerciseCatalog::forClient(array_column($proposal['items'], 'exercise')),
            'input' => [
                'muscles' => array_values(array_filter((array) ($input['muscles'] ?? []), fn ($muscle): bool => Muscle::tryFrom((string) $muscle) !== null)),
                'minutes' => (int) ($input['minutes'] ?? 45),
                'goal' => WorkoutGoal::tryFrom((string) ($input['goal'] ?? ''))?->value ?? WorkoutGoal::Hypertrophy->value,
                'equipment' => EquipmentKind::tryFrom((string) ($input['equipment'] ?? ''))?->value,
                'warmup' => filter_var($input['warmup'] ?? false, FILTER_VALIDATE_BOOL),
                'stretch' => filter_var($input['stretch'] ?? false, FILTER_VALIDATE_BOOL),
                'variant' => (int) ($input['variant'] ?? 0),
            ],
            'goals' => array_map(fn (WorkoutGoal $goal): array => [
                'value' => $goal->value,
                'label' => $goal->label(),
                'description' => $goal->description(),
            ], WorkoutGoal::cases()),
            'equipments' => array_map(fn (EquipmentKind $kind): array => [
                'value' => $kind->value,
                'label' => $kind->label(),
            ], [EquipmentKind::Machine, EquipmentKind::Free, EquipmentKind::Bodyweight]),
        ]);
    }
}
