<?php

namespace App\Http\Requests;

use App\Enums\EquipmentKind;
use App\Enums\ExerciseMode;
use App\Enums\Muscle;
use App\Support\ExerciseCatalog;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CompleteWorkoutRequest extends FormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            // La séance telle qu'elle est dans l'éditeur, enregistrée ou non.
            'items' => ['present', 'array', 'max:'.config('sport.max_items')],
            'items.*.exercise' => ['required', 'string', Rule::in(ExerciseCatalog::slugs())],
            'items.*.mode' => ['required', Rule::enum(ExerciseMode::class)],
            'items.*.value' => ['required', 'integer', 'min:1', 'max:3600'],
            'items.*.sets' => ['required', 'integer', 'min:1', 'max:20'],
            'items.*.rest_sets' => ['required', 'integer', 'min:0', 'max:900'],
            'items.*.rest_after' => ['required', 'integer', 'min:0', 'max:900'],
            'minutes' => ['required', 'integer', 'min:5', 'max:90'],
            'muscles' => ['array', 'max:17'],
            'muscles.*' => ['distinct', Rule::enum(Muscle::class)],
            'equipment' => ['nullable', Rule::enum(EquipmentKind::class)->only([EquipmentKind::Machine, EquipmentKind::Free, EquipmentKind::Bodyweight])],
            'variant' => ['integer', 'min:0', 'max:1000000'],
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
