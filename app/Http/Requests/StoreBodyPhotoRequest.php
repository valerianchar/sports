<?php

namespace App\Http\Requests;

use App\Enums\PhotoPose;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreBodyPhotoRequest extends FormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            // Le type est lu dans le contenu du fichier, pas dans son nom. Les
            // téléphones envoient du JPEG (l'iPhone convertit ses HEIC à l'envoi).
            'photo' => ['required', 'file', 'max:8192', 'mimetypes:image/jpeg,image/png,image/webp'],
            'pose' => ['required', Rule::enum(PhotoPose::class)],
            'taken_on' => ['nullable', 'date', 'before_or_equal:today'],
            'note' => ['nullable', 'string', 'max:140'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'photo.required' => 'Choisis une photo.',
            'photo.uploaded' => 'La photo n’a pas pu être envoyée : 8 Mo au plus.',
            'photo.max' => 'La photo dépasse 8 Mo.',
            'photo.mimetypes' => 'Ce fichier n’est pas une photo lisible (JPEG, PNG ou WebP).',
            'pose.required' => 'Choisis la pose : face, profil ou dos.',
            'pose.enum' => 'Choisis la pose : face, profil ou dos.',
            'taken_on.before_or_equal' => 'La date ne peut pas être dans le futur.',
            'note.max' => 'La note : 140 caractères au plus.',
        ];
    }
}
