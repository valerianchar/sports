<?php

namespace App\Http\Requests;

use App\Enums\CardioStyle;
use App\Enums\EquipmentKind;
use App\Enums\Muscle;
use App\Enums\WorkoutGoal;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SuggestWorkoutRequest extends FormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            // Le cardio s'en passe ; pour perdre du poids, sans choix, c'est tout le corps.
            'muscles' => ['required_unless:goal,perte-de-poids,cardio', 'array', 'max:17'],
            'muscles.*' => ['distinct', Rule::enum(Muscle::class)],
            'minutes' => ['required', 'integer', 'min:10', 'max:150'],
            'goal' => ['required', Rule::enum(WorkoutGoal::class)],
            'equipment' => ['nullable', Rule::enum(EquipmentKind::class)->only([EquipmentKind::Machine, EquipmentKind::Free, EquipmentKind::Bodyweight])],
            'style' => ['nullable', Rule::enum(CardioStyle::class)],
            'warmup' => ['boolean'],
            'stretch' => ['boolean'],
            'variant' => ['integer', 'min:0', 'max:1000000'],
            // Réglages choisis à la place de ceux de l'objectif.
            'reps' => ['nullable', 'integer', 'min:1', 'max:50'],
            // Séries par exercice ; absent, l'assistant les ajuste pour tenir le temps.
            'sets' => ['nullable', 'integer', 'min:1', 'max:10'],
            'rest_sets' => ['nullable', 'integer', 'min:0', 'max:600'],
            'rest_after' => ['nullable', 'integer', 'min:0', 'max:600'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'muscles.required_unless' => 'Choisis au moins un muscle à travailler.',
            'minutes.min' => 'Une séance dure au moins 10 minutes.',
            'minutes.max' => 'Une séance dure au plus 2 h 30.',
            'reps.max' => 'Au plus 50 répétitions par série.',
            'sets.max' => 'Au plus 10 séries par exercice.',
            'rest_sets.max' => 'Le repos ne dépasse pas 10 minutes.',
            'rest_after.max' => 'Le repos ne dépasse pas 10 minutes.',
        ];
    }

    /**
     * @return array{reps: int|null, sets: int|null, rest_sets: int|null, rest_after: int|null}
     */
    public function settings(): array
    {
        return [
            'reps' => $this->validated('reps') === null ? null : (int) $this->validated('reps'),
            'sets' => $this->validated('sets') === null ? null : (int) $this->validated('sets'),
            'rest_sets' => $this->validated('rest_sets') === null ? null : (int) $this->validated('rest_sets'),
            'rest_after' => $this->validated('rest_after') === null ? null : (int) $this->validated('rest_after'),
        ];
    }

    /**
     * @return list<Muscle>
     */
    public function muscles(): array
    {
        return array_map(fn (string $muscle): Muscle => Muscle::from($muscle), $this->validated('muscles') ?? []);
    }
}
