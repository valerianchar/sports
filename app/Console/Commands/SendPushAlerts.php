<?php

namespace App\Console\Commands;

use App\Models\PushAlert;
use App\Support\PushSender;
use Illuminate\Console\Command;
use Illuminate\Database\QueryException;
use Throwable;

/**
 * Envoie, à la seconde près, les alertes de séance arrivées à échéance — fin
 * d'un repos, reprise de l'effort — aux téléphones de leur propriétaire.
 * Tourne en continu dans son propre conteneur ; `--une-fois` fait un seul tour.
 */
class SendPushAlerts extends Command
{
    protected $signature = 'alertes:envoyer {--une-fois : un seul passage, puis s\'arrête}';

    protected $description = 'Envoie les alertes de séance arrivées à échéance';

    public function handle(PushSender $sender): int
    {
        if (! $sender->enabled()) {
            $this->warn('Clés VAPID absentes : aucune alerte ne partira.');

            if ($this->option('une-fois')) {
                return self::SUCCESS;
            }
        }

        do {
            try {
                if ($sender->enabled()) {
                    $this->sendDue($sender);
                }
            } catch (QueryException $exception) {
                // Base pas encore prête (premier démarrage, migration en cours) : on réessaie.
                $this->warn($exception->getMessage());
                sleep(3);
            }

            if (! $this->option('une-fois')) {
                usleep(500_000);
            }
        } while (! $this->option('une-fois'));

        return self::SUCCESS;
    }

    private function sendDue(PushSender $sender): void
    {
        $due = PushAlert::query()
            ->with('user.pushSubscriptions')
            ->whereNull('sent_at')
            ->where('send_at', '<=', now())
            ->orderBy('send_at')
            ->limit(50)
            ->get();

        foreach ($due as $alert) {
            // Marquée d'abord : une alerte ne part jamais deux fois, même si l'envoi échoue.
            $alert->update(['sent_at' => now()]);

            // Plus d'une minute de retard (conteneur arrêté) : elle ne veut plus rien dire.
            if ($alert->send_at->lt(now()->subMinute())) {
                continue;
            }

            foreach ($alert->user->pushSubscriptions as $subscription) {
                try {
                    if (! $sender->send($subscription, ['title' => $alert->title, 'body' => $alert->body, 'tag' => "seance-{$alert->session}"])) {
                        $subscription->delete();
                    }
                } catch (Throwable $exception) {
                    report($exception);
                }
            }
        }

        // Le ménage : les alertes passées depuis un jour.
        PushAlert::query()->where('send_at', '<', now()->subDay())->delete();
    }
}
