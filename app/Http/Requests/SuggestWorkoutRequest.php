<?php

namespace App\Http\Requests;

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
            'muscles' => ['required', 'array', 'min:1', 'max:17'],
            'muscles.*' => ['distinct', Rule::enum(Muscle::class)],
            'minutes' => ['required', 'integer', 'min:10', 'max:150'],
            'goal' => ['required', Rule::enum(WorkoutGoal::class)],
            'equipment' => ['nullable', Rule::enum(EquipmentKind::class)->only([EquipmentKind::Machine, EquipmentKind::Free, EquipmentKind::Bodyweight])],
            'warmup' => ['boolean'],
            'stretch' => ['boolean'],
            'variant' => ['integer', 'min:0', 'max:1000000'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'muscles.required' => 'Choisis au moins un muscle à travailler.',
            'muscles.min' => 'Choisis au moins un muscle à travailler.',
            'minutes.min' => 'Une séance dure au moins 10 minutes.',
            'minutes.max' => 'Une séance dure au plus 2 h 30.',
        ];
    }

    /**
     * @return list<Muscle>
     */
    public function muscles(): array
    {
        return array_map(fn (string $muscle): Muscle => Muscle::from($muscle), $this->validated('muscles'));
    }
}
