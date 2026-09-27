<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

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
