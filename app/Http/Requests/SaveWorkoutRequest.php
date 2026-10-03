<?php

namespace App\Http\Requests;

use App\Enums\ExerciseMode;
use App\Support\ExerciseCatalog;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class SaveWorkoutRequest extends FormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'name' => ['nullable', 'string', 'max:80'],
            'items' => ['present', 'array', 'max:'.config('sport.max_items')],
            'items.*.exercise' => ['required', 'string', Rule::in(ExerciseCatalog::slugs())],
            'items.*.mode' => ['required', Rule::enum(ExerciseMode::class)],
            'items.*.value' => ['required', 'integer', 'min:1', 'max:3600'],
            // Charge en kilos ; vide au poids du corps.
            'items.*.weight' => ['nullable', 'numeric', 'min:0', 'max:999'],
            'items.*.sets' => ['required', 'integer', 'min:1', 'max:20'],
            'items.*.rest_sets' => ['required', 'integer', 'min:0', 'max:900'],
            'items.*.rest_after' => ['required', 'integer', 'min:0', 'max:900'],
            // Enregistrer puis lancer aussitôt : « Lancer la séance » depuis l'éditeur.
            'start' => ['boolean'],
            // Enregistrer puis ouvrir l'éditeur : une proposition de l'assistant qu'on veut retoucher.
            'edit' => ['boolean'],
        ];
    }

    /**
     * La valeur d'une série n'a pas les mêmes bornes selon qu'on compte des
     * répétitions ou des secondes.
     *
     * @return list<callable(Validator): void>
     */
    public function after(): array
    {
        return [function (Validator $validator): void {
            foreach ((array) $this->input('items', []) as $index => $item) {
                $mode = ExerciseMode::tryFrom((string) ($item['mode'] ?? ''));

                if ($mode === null || ! is_numeric($item['value'] ?? null)) {
                    continue;
                }

                [$min, $max] = $mode->valueRange();

                if ($item['value'] < $min || $item['value'] > $max) {
                    $validator->errors()->add("items.{$index}.value", $mode === ExerciseMode::Reps
                        ? "Entre {$min} et {$max} répétitions par série."
                        : 'Une série chronométrée dure de 5 secondes à 1 heure.');
                }
            }
        }];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'name.max' => 'Le nom de la séance ne doit pas dépasser 80 caractères.',
            'items.max' => 'Une séance compte au plus '.config('sport.max_items').' exercices.',
            'items.*.exercise.in' => 'Cet exercice n’existe pas dans la bibliothèque.',
            'items.*.sets.max' => 'Au plus 20 séries par exercice.',
            'items.*.weight.numeric' => 'La charge s’écrit en kilos, par exemple 62,5.',
            'items.*.weight.max' => 'Une charge de plus de 999 kg ? Vérifie la saisie.',
            'items.*.rest_sets.max' => 'Le repos ne dépasse pas 15 minutes.',
            'items.*.rest_after.max' => 'Le repos ne dépasse pas 15 minutes.',
        ];
    }

    public function workoutName(): string
    {
        return trim((string) $this->input('name')) ?: 'Séance sans nom';
    }

    /**
     * @return list<array{exercise: string, mode: string, value: int, weight: float|null, sets: int, rest_sets: int, rest_after: int}>
     */
    public function items(): array
    {
        return array_values(array_map(fn (array $item): array => [
            'exercise' => $item['exercise'],
            'mode' => $item['mode'],
            'value' => (int) $item['value'],
            'weight' => isset($item['weight']) && $item['weight'] !== '' ? round((float) $item['weight'], 2) : null,
            'sets' => (int) $item['sets'],
            'rest_sets' => (int) $item['rest_sets'],
            'rest_after' => (int) $item['rest_after'],
        ], $this->validated('items')));
    }
}
