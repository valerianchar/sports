<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Les alertes d'une séance en cours. Quand l'appli passe en arrière-plan, le
 * lecteur confie au serveur les fins de repos à venir ; il les reprend dès
 * qu'on revient. Les délais sont relatifs : l'horloge du téléphone peut
 * différer de celle du serveur. JSON.
 */
class PushAlertController extends Controller
{
    public function store(Request $request, string $session): JsonResponse
    {
        $data = $request->validate([
            'alerts' => ['present', 'array', 'max:40'],
            'alerts.*.in' => ['required', 'numeric', 'min:0', 'max:7200'],
            'alerts.*.title' => ['required', 'string', 'max:120'],
            'alerts.*.body' => ['nullable', 'string', 'max:240'],
        ]);

        $alerts = $request->user()->pushAlerts();
        $alerts->where('session', $session)->whereNull('sent_at')->delete();

        $now = now();
        $request->user()->pushAlerts()->createMany(array_map(fn (array $alert): array => [
            'session' => $session,
            'send_at' => $now->copy()->addMilliseconds((int) round($alert['in'] * 1000)),
            'title' => $alert['title'],
            'body' => $alert['body'] ?? null,
        ], $data['alerts']));

        return response()->json(['scheduled' => count($data['alerts'])]);
    }

    /**
     * L'appli est revenue à l'écran : plus aucune alerte n'a lieu d'arriver,
     * quelle que soit la séance — y compris une séance abandonnée sans
     * repasser par le lecteur.
     */
    public function destroyAll(Request $request): JsonResponse
    {
        $request->user()->pushAlerts()->whereNull('sent_at')->delete();

        return response()->json(['scheduled' => 0]);
    }

    public function destroy(Request $request, string $session): JsonResponse
    {
        $request->user()->pushAlerts()->where('session', $session)->whereNull('sent_at')->delete();

        return response()->json(['scheduled' => 0]);
    }
}
