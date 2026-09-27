<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

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
            'sets_done' => ['required', 'integer', 'min:0', 'max:1000'],
            'exercises_done' => ['required', 'integer', 'min:0', 'max:100'],
            // Rejoué après une coupure, le journal garde l'heure réelle de la fin.
            'finished_at' => ['required', 'date', 'before_or_equal:+5 minutes', 'after:-30 days'],
        ];
    }
}
