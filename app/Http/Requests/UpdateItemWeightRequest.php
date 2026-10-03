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
        ];
    }
}
