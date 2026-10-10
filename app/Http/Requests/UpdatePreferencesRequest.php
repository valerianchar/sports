<?php

namespace App\Http\Requests;

use App\Enums\AudioMode;
use App\Enums\CountdownSound;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdatePreferencesRequest extends FormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'sound' => ['required', 'boolean'],
            'prep_seconds' => ['required', 'integer', 'min:0', 'max:15'],
            // Bips des dernières secondes d'un repos ou d'une série chronométrée.
            'countdown_seconds' => ['required', 'integer', Rule::in([0, 3, 5, 10])],
            'volume' => ['required', 'integer', 'min:0', 'max:100'],
            'audio_mode' => ['sometimes', Rule::enum(AudioMode::class)],
            'warmup_sets' => ['sometimes', 'boolean'],
            'countdown_sound' => ['required', Rule::enum(CountdownSound::class), function (string $attribute, mixed $value, \Closure $fail): void {
                if ($value === CountdownSound::Perso->value && $this->user()->custom_sound_path === null) {
                    $fail('Envoie d’abord un fichier pour utiliser « Mon son ».');
                }
            }],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'prep_seconds.max' => 'Le compte à rebours de départ dure au plus 15 secondes.',
        ];
    }
}
