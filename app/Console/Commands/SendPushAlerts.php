<?php

namespace App\Console\Commands;

use App\Models\PushAlert;
use App\Models\WorkoutSchedule;
use App\Support\PushSender;
use App\Support\WorkoutEstimate;
use Carbon\CarbonImmutable;
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
                    $this->sendReminders($sender);
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

    /**
     * Les rappels du programme de la semaine : à l'heure prévue (heure de
     * Paris), une notification par séance du jour — une seule, même si le
     * conteneur a redémarré dans la minute ; jusqu'à dix minutes de retard
     * rattrapées, au-delà le rappel ne sert plus.
     */
    private function sendReminders(PushSender $sender): void
    {
        $now = CarbonImmutable::now('Europe/Paris');

        $due = WorkoutSchedule::query()
            ->with(['workout.items', 'user.pushSubscriptions'])
            ->where('weekday', $now->dayOfWeekIso)
            ->where('remind', true)
            ->whereNotNull('workout_id')
            ->where('time', '<=', $now->format('H:i'))
            ->where('time', '>=', $now->subMinutes(10)->format('H:i'))
            ->where(fn ($query) => $query->whereNull('reminded_on')->orWhereDate('reminded_on', '<', $now->toDateString()))
            ->get();

        foreach ($due as $schedule) {
            $schedule->update(['reminded_on' => $now->toDateString()]);
            $minutes = (int) round(WorkoutEstimate::seconds($schedule->workout->items->map(fn ($item): array => ['mode' => $item->mode->value, ...$item->only(['value', 'sets', 'rest_sets', 'rest_after', 'per_side', 'drops', 'drop_on', 'superset'])])->all()) / 60);

            foreach ($schedule->user->pushSubscriptions as $subscription) {
                try {
                    $payload = ['title' => "C'est l'heure : {$schedule->workout->name}", 'body' => "Ta séance du jour t'attend · {$schedule->workout->items->count()} exercices · ~{$minutes} min", 'tag' => 'seance-rappel', 'url' => '/'];

                    if (! $sender->send($subscription, $payload)) {
                        $subscription->delete();
                    }
                } catch (Throwable $exception) {
                    report($exception);
                }
            }
        }
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
