<?php

namespace App\Support;

use App\Models\PushSubscription;
use Illuminate\Support\Facades\Log;
use Minishlink\WebPush\Subscription;
use Minishlink\WebPush\WebPush;

/**
 * Envoie une notification web à un appareil abonné, signée de nos clés VAPID.
 */
class PushSender
{
    private ?WebPush $webPush = null;

    public function enabled(): bool
    {
        return filled(config('services.webpush.public_key')) && filled(config('services.webpush.private_key'));
    }

    /**
     * @param  array{title: string, body?: string|null, tag?: string, url?: string}  $payload
     * @return bool faux quand l'abonnement n'existe plus (appareil désabonné) : il faut l'oublier
     */
    public function send(PushSubscription $subscription, array $payload): bool
    {
        $report = $this->client()->sendOneNotification(
            Subscription::create([
                'endpoint' => $subscription->endpoint,
                'publicKey' => $subscription->public_key,
                'authToken' => $subscription->auth_token,
                'contentEncoding' => 'aes128gcm',
            ]),
            json_encode($payload, JSON_UNESCAPED_UNICODE),
            // Urgence haute et courte durée de vie : une alerte de repos en retard ne sert plus à rien.
            ['TTL' => 60, 'urgency' => 'high', 'topic' => 'seance'],
        );

        // Un refus du service de notifications (Apple, Google…) se note : sans lui, on ne saurait pas pourquoi rien n'arrive.
        if (! $report->isSuccess()) {
            Log::warning('Notification refusée', [
                'service' => parse_url($subscription->endpoint, PHP_URL_HOST),
                'status' => $report->getResponse()?->getStatusCode(),
                'reason' => $report->getReason(),
            ]);
        }

        return ! $report->isSubscriptionExpired();
    }

    private function client(): WebPush
    {
        return $this->webPush ??= new WebPush([
            'VAPID' => [
                'subject' => config('services.webpush.subject'),
                'publicKey' => config('services.webpush.public_key'),
                'privateKey' => config('services.webpush.private_key'),
            ],
        ]);
    }
}
