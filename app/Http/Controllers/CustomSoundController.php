<?php

namespace App\Http\Controllers;

use App\Enums\CountdownSound;
use App\Http\Requests\StoreCustomSoundRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Le son personnel du compte à rebours : un fichier par compte, sur le disque
 * privé, servi seulement à son propriétaire.
 */
class CustomSoundController extends Controller
{
    public function store(StoreCustomSoundRequest $request): RedirectResponse
    {
        $user = $request->user();
        $file = $request->file('sound');
        $previous = $user->custom_sound_path;

        $path = $file->storeAs('sounds', $user->id.'-'.Str::random(12).'.'.($file->guessExtension() ?: 'audio'), 'local');

        $user->update([
            'custom_sound_path' => $path,
            'custom_sound_name' => Str::limit($file->getClientOriginalName(), 120, ''),
            'countdown_sound' => CountdownSound::Perso,
        ]);

        if ($previous !== null) {
            Storage::disk('local')->delete($previous);
        }

        return back()->with('success', 'Ton son est prêt pour le compte à rebours.');
    }

    public function show(Request $request): StreamedResponse
    {
        $path = $request->user()->custom_sound_path;

        abort_if($path === null || ! Storage::disk('local')->exists($path), 404);

        return Storage::disk('local')->response($path, null, [
            'Cache-Control' => 'private, max-age=31536000',
        ]);
    }

    public function destroy(Request $request): RedirectResponse
    {
        $user = $request->user();

        if ($user->custom_sound_path !== null) {
            Storage::disk('local')->delete($user->custom_sound_path);
        }

        $user->update([
            'custom_sound_path' => null,
            'custom_sound_name' => null,
            'countdown_sound' => $user->countdown_sound === CountdownSound::Perso ? CountdownSound::Bip : $user->countdown_sound,
        ]);

        return back()->with('success', 'Son supprimé : retour au bip.');
    }
}
