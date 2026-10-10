<?php

namespace App\Http\Controllers;

use App\Enums\BodyMeasure;
use App\Models\BodyMeasurement;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * Les mensurations au mètre ruban : une ligne par jour au plus. On peut n'en
 * noter qu'une à la fois — tour de taille ce matin, bras ce soir — : chaque
 * saisie complète la ligne du jour, et la dernière valeur d'une mesure
 * l'emporte.
 */
class BodyMeasurementController extends Controller
{
    public function store(Request $request): RedirectResponse
    {
        $columns = BodyMeasure::columns();
        $rules = ['measured_on' => ['nullable', 'date', 'before_or_equal:today']];
        $messages = [];

        foreach (BodyMeasure::cases() as $measure) {
            $others = implode(',', array_diff($columns, [$measure->value]));
            $rules[$measure->value] = ['nullable', "required_without_all:{$others}", 'numeric', 'min:10', 'max:300'];
            $messages["{$measure->value}.required_without_all"] = 'Indique au moins une mesure.';
            $messages["{$measure->value}.numeric"] = "{$measure->label()} : un nombre de centimètres.";
            $messages["{$measure->value}.min"] = "{$measure->label()}, en centimètres : au moins 10.";
            $messages["{$measure->value}.max"] = "{$measure->label()}, en centimètres : au plus 300.";
        }

        $data = $request->validate($rules, $messages);

        // Le jour de la mesure, à l'heure de Paris, comme pour les pesées.
        $day = $data['measured_on'] ?? now('Europe/Paris')->toDateString();
        $values = collect($columns)
            ->filter(fn (string $column): bool => isset($data[$column]))
            ->mapWithKeys(fn (string $column): array => [$column => round((float) $data[$column], 1)])
            ->all();

        $existing = $request->user()->bodyMeasurements()->whereDate('measured_on', $day)->first();

        if ($existing) {
            $existing->update($values);
        } else {
            $request->user()->bodyMeasurements()->create(['measured_on' => $day, ...$values]);
        }

        return back()->with('success', count($values) > 1 ? 'Mensurations enregistrées.' : 'Mesure enregistrée.');
    }

    public function destroy(Request $request, BodyMeasurement $measurement): RedirectResponse
    {
        abort_unless($measurement->user_id === $request->user()->id, 403);

        $measurement->delete();

        return back()->with('success', 'Mensurations supprimées.');
    }
}
