<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateItemWeightRequest extends FormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'position' => ['required', 'integer', 'min:0'],
            'weight' => ['nullable', 'numeric', 'min:0', 'max:999'],
            // La série (dès 0) dont on change la charge, pour un exercice en dégressif.
            'set' => ['nullable', 'integer', 'min:0', 'max:19'],
            // Le palier (dès 0) d'un drop set.
            'drop' => ['nullable', 'integer', 'min:0', 'max:3'],
        ];
    }
}
