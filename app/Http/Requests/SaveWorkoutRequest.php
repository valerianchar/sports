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
            // Dégressif / pyramide : une charge par série.
            'items.*.set_weights' => ['nullable', 'array', 'max:20'],
            'items.*.set_weights.*' => ['nullable', 'numeric', 'min:0', 'max:999'],
            // Drop set : jusqu'à quatre paliers enchaînés sans repos.
            'items.*.drops' => ['nullable', 'array', 'max:4'],
            'items.*.drops.*.reps' => ['required', 'integer', 'min:1', 'max:100'],
            'items.*.drops.*.weight' => ['nullable', 'numeric', 'min:0', 'max:999'],
            'items.*.drop_on' => ['nullable', 'in:last,all'],
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
            'items.*.set_weights.*.numeric' => 'La charge s’écrit en kilos, par exemple 62,5.',
            'items.*.drops.max' => 'Un drop set compte au plus quatre paliers.',
            'items.*.drops.*.reps.required' => 'Chaque palier du drop set a son nombre de répétitions.',
            'items.*.rest_sets.max' => 'Le repos ne dépasse pas 15 minutes.',
            'items.*.rest_after.max' => 'Le repos ne dépasse pas 15 minutes.',
        ];
    }

    public function workoutName(): string
    {
        return trim((string) $this->input('name')) ?: 'Séance sans nom';
    }

    /**
     * @return list<array{exercise: string, mode: string, value: int, weight: float|null, set_weights: list<float|null>|null, drops: list<array{reps: int, weight: float|null}>|null, drop_on: string|null, sets: int, rest_sets: int, rest_after: int}>
     */
    public function items(): array
    {
        return array_values(array_map(fn (array $item): array => [
            'exercise' => $item['exercise'],
            'mode' => $item['mode'],
            'value' => (int) $item['value'],
            'weight' => self::kilos($item['weight'] ?? null),
            'set_weights' => $this->setWeights($item),
            'drops' => $this->drops($item),
            'drop_on' => $this->drops($item) === null ? null : ($item['drop_on'] ?? 'last'),
            'sets' => (int) $item['sets'],
            'rest_sets' => (int) $item['rest_sets'],
            'rest_after' => (int) $item['rest_after'],
        ], $this->validated('items')));
    }

    private static function kilos(mixed $value): ?float
    {
        return $value === null || $value === '' ? null : round((float) $value, 2);
    }

    /**
     * Une charge par série, ajustée au nombre de séries : une série ajoutée
     * reprend la charge de la précédente. Les charges ne portent que sur un
     * exercice compté en répétitions.
     *
     * @param  array<string, mixed>  $item
     * @return list<float|null>|null
     */
    private function setWeights(array $item): ?array
    {
        if (empty($item['set_weights']) || $item['mode'] !== 'reps') {
            return null;
        }

        $weights = array_map(self::kilos(...), array_values($item['set_weights']));
        $sets = (int) $item['sets'];

        while (count($weights) < $sets) {
            $weights[] = end($weights);
        }

        return array_slice($weights, 0, $sets);
    }

    /**
     * @param  array<string, mixed>  $item
     * @return list<array{reps: int, weight: float|null}>|null
     */
    private function drops(array $item): ?array
    {
        if (empty($item['drops']) || $item['mode'] !== 'reps') {
            return null;
        }

        return array_map(fn (array $drop): array => [
            'reps' => (int) $drop['reps'],
            'weight' => self::kilos($drop['weight'] ?? null),
        ], array_values($item['drops']));
    }
}
