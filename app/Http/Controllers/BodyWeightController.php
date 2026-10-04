<?php

namespace App\Http\Controllers;

use App\Models\BodyWeight;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * Le poids de corps, noté de temps en temps : une pesée par jour au plus, la
 * dernière saisie du jour l'emporte. Il donne la force relative des grands
 * mouvements (développé couché à 1,1 fois son poids…), les calories des
 * séances, et la distance au poids visé.
 */
class BodyWeightController extends Controller
{
    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'kg' => ['required', 'numeric', 'min:25', 'max:300'],
            'measured_on' => ['nullable', 'date', 'before_or_equal:today'],
        ], [
            'kg.required' => 'Indique ton poids.',
            'kg.min' => 'Ton poids, en kilos : au moins 25.',
            'kg.max' => 'Ton poids, en kilos : au plus 300.',
        ]);

        // Le jour de la pesée, à l'heure de Paris : peser à 0 h 30 compte pour ce jour-là.
        $day = $data['measured_on'] ?? now('Europe/Paris')->toDateString();
        $kg = round((float) $data['kg'], 2);
        $existing = $request->user()->bodyWeights()->whereDate('measured_on', $day)->first();

        if ($existing) {
            $existing->update(['kg' => $kg]);
        } else {
            $request->user()->bodyWeights()->create(['measured_on' => $day, 'kg' => $kg]);
        }

        return back()->with('success', 'Pesée enregistrée.');
    }

    /**
     * Le poids visé : vide, on l'oublie.
     */
    public function target(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'kg' => ['nullable', 'numeric', 'min:25', 'max:300'],
        ], [
            'kg.min' => 'Le poids visé, en kilos : au moins 25.',
            'kg.max' => 'Le poids visé, en kilos : au plus 300.',
        ]);

        $request->user()->update(['target_weight' => isset($data['kg']) ? round((float) $data['kg'], 1) : null]);

        return back()->with('success', isset($data['kg']) ? 'Objectif enregistré.' : 'Objectif retiré.');
    }

    public function destroy(Request $request, BodyWeight $bodyWeight): RedirectResponse
    {
        abort_unless($bodyWeight->user_id === $request->user()->id, 403);

        $bodyWeight->delete();

        return back()->with('success', 'Pesée supprimée.');
    }
}
