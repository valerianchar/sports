<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreBodyPhotoRequest;
use App\Models\BodyPhoto;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Les photos de progression : des photos intimes. Elles vivent sur le disque
 * privé, sous photos/{user_id}/, jamais sur le disque public, et ne sont
 * servies qu'à leur propriétaire — comme le son personnel du compte à rebours.
 */
class BodyPhotoController extends Controller
{
    public function store(StoreBodyPhotoRequest $request): RedirectResponse
    {
        $user = $request->user();
        $file = $request->file('photo');
        $data = $request->validated();

        $path = $file->storeAs("photos/{$user->id}", Str::random(24).'.'.($file->guessExtension() ?: 'jpg'), 'local');

        abort_if($path === false, 500, 'La photo n’a pas pu être enregistrée.');

        $user->bodyPhotos()->create([
            'taken_on' => $data['taken_on'] ?? now('Europe/Paris')->toDateString(),
            'path' => $path,
            'pose' => $data['pose'],
            'note' => isset($data['note']) ? (Str::squish($data['note']) ?: null) : null,
        ]);

        return back()->with('success', 'Photo ajoutée.');
    }

    public function show(Request $request, BodyPhoto $photo): StreamedResponse
    {
        abort_unless($photo->user_id === $request->user()->id, 403);
        abort_unless(Storage::disk('local')->exists($photo->path), 404);

        // Une photo ne change jamais à adresse égale : le navigateur la garde,
        // mais pour ce compte seulement — aucun cache partagé.
        return Storage::disk('local')->response($photo->path, null, [
            'Cache-Control' => 'private, max-age=31536000, immutable',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    public function destroy(Request $request, BodyPhoto $photo): RedirectResponse
    {
        abort_unless($photo->user_id === $request->user()->id, 403);

        Storage::disk('local')->delete($photo->path);
        $photo->delete();

        return back()->with('success', 'Photo supprimée.');
    }
}
