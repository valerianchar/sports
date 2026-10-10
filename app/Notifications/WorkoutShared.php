<?php

namespace App\Notifications;

use App\Models\User;
use App\Models\Workout;
use App\Models\WorkoutItem;
use App\Support\ExerciseCatalog;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Une séance partagée par mail : son contenu, et le lien pour l'ajouter à
 * ses propres séances (avec un compte Séance, créé au besoin en chemin).
 */
class WorkoutShared extends Notification
{
    use Queueable;

    public function __construct(public readonly Workout $workout, public readonly User $sender) {}

    /**
     * @return list<string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $message = (new MailMessage)
            ->subject("{$this->sender->first_name} te partage sa séance « {$this->workout->name} »")
            ->greeting('Salut 👋')
            ->line("{$this->sender->first_name} t'envoie sa séance « {$this->workout->name} » :");

        foreach ($this->workout->items as $item) {
            $message->line('— '.$this->describe($item));
        }

        return $message
            ->action('Ajouter à mes séances', route('shares.show', $this->workout->share_token))
            ->line('Pas encore de compte Séance ? Le lien te propose d’en créer un, puis d’ajouter la séance.')
            ->salutation('L’équipe Séance');
    }

    /** « Développé couché — 4 × 8 reps à 60 kg ». */
    private function describe(WorkoutItem $item): string
    {
        $name = ExerciseCatalog::find($item->exercise)['name'] ?? $item->exercise;
        $effort = $item->mode->value === 'reps' ? "{$item->value} reps" : gmdate($item->value >= 3600 ? 'G:i:s' : 'i:s', $item->value);
        $load = $item->weight ? ' à '.rtrim(rtrim(number_format($item->weight, 2, ',', ''), '0'), ',').' kg' : '';

        return "{$name} — {$item->sets} × {$effort}{$load}";
    }
}
