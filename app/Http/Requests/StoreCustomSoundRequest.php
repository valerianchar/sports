<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreCustomSoundRequest extends FormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            // Un mémo vocal d'iPhone (m4a), un mp3, un wav ou un ogg ; 2 Mo suffisent
            // largement pour quelques secondes de son.
            'sound' => ['required', 'file', 'max:2048', 'mimetypes:audio/mpeg,audio/mp3,audio/mp4,audio/x-m4a,audio/m4a,audio/aac,audio/wav,audio/x-wav,audio/wave,audio/ogg,audio/webm,video/mp4'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'sound.required' => 'Choisis un fichier audio.',
            'sound.max' => 'Le fichier dépasse 2 Mo : garde quelques secondes seulement.',
            'sound.mimetypes' => 'Ce fichier n’est pas un son lisible (mp3, m4a, wav ou ogg).',
        ];
    }
}
