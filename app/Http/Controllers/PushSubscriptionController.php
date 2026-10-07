<?php

namespace App\Http\Controllers;

use App\Support\PushSender;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * L'abonnement d'un téléphone aux notifications web — l'alerte de fin de
 * repos qui sonne même quand l'appli n'est plus à l'écran. JSON.
 */
class PushSubscriptionController extends Controller
{
    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'endpoint' => ['required', 'url:https', 'max:2000'],
            'keys.p256dh' => ['required', 'string', 'max:255'],
            'keys.auth' => ['required', 'string', 'max:255'],
        ]);

        $request->user()->pushSubscriptions()->updateOrCreate(
            ['endpoint_hash' => hash('sha256', $data['endpoint'])],
            ['endpoint' => $data['endpoint'], 'public_key' => $data['keys']['p256dh'], 'auth_token' => $data['keys']['auth']],
        );

        return response()->json(['subscribed' => true], 201);
    }

    public function destroy(Request $request): JsonResponse
    {
        $data = $request->validate(['endpoint' => ['required', 'string', 'max:2000']]);

        $request->user()->pushSubscriptions()->where('endpoint_hash', hash('sha256', $data['endpoint']))->delete();

        return response()->json(['subscribed' => false]);
    }

    /**
     * Une notification d'essai sur tous les téléphones abonnés.
     */
    public function test(Request $request, PushSender $sender): JsonResponse
    {
        abort_unless($sender->enabled(), 503, 'Notifications non configurées sur le serveur.');

        $sent = 0;

        foreach ($request->user()->pushSubscriptions as $subscription) {
            if ($sender->send($subscription, ['title' => 'Séance', 'body' => 'Les alertes de fin de repos arriveront comme ça.', 'tag' => 'seance-test'])) {
                $sent++;
            } else {
                $subscription->delete();
            }
        }

        return response()->json(['sent' => $sent]);
    }
}
