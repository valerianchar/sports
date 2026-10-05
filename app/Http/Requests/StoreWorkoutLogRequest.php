<?php

namespace App\Http\Requests;

use App\Support\ExerciseCatalog;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreWorkoutLogRequest extends FormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'client_id' => ['required', 'uuid'],
            // Une séance oubliée en pause toute une nuit reste vraisemblable ; au-delà, non.
            'duration_seconds' => ['required', 'integer', 'min:0', 'max:86400'],
            'sets_done' => ['required', 'integer', 'min:0', 'max:2000'],
            'exercises_done' => ['required', 'integer', 'min:0', 'max:100'],
            // Rejoué après une coupure, le journal garde l'heure réelle de la fin.
            'finished_at' => ['required', 'date', 'before_or_equal:+5 minutes', 'after:-60 days'],
            // Séries prévues (hors paliers de drop) : la séance ne compte que si toutes sont faites.
            'planned_sets' => ['nullable', 'integer', 'min:0', 'max:1200'],
            // Chaque série réellement faite : la matière des statistiques de progression.
            'sets' => ['nullable', 'array', 'max:2000'],
            'sets.*.exercise' => ['required', 'string', Rule::in(ExerciseCatalog::slugs())],
            'sets.*.position' => ['required', 'integer', 'min:0', 'max:100'],
            'sets.*.set' => ['required', 'integer', 'min:1', 'max:20'],
            'sets.*.drop' => ['nullable', 'integer', 'min:0', 'max:3'],
            'sets.*.reps' => ['nullable', 'integer', 'min:0', 'max:500'],
            'sets.*.target_reps' => ['nullable', 'integer', 'min:0', 'max:500'],
            'sets.*.seconds' => ['nullable', 'integer', 'min:0', 'max:7200'],
            'sets.*.weight' => ['nullable', 'numeric', 'min:0', 'max:999'],
            'sets.*.at' => ['nullable', 'date'],
        ];
    }
}
