<?php

namespace App\Http\Middleware;

use App\Enums\AudioMode;
use App\Enums\CountdownSound;
use App\Support\ExerciseCatalog;
use App\Support\MachineSettings;
use App\Support\MuscleZones;
use Illuminate\Http\Request;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    protected $rootView = 'app';

    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    /**
     * @return array<string, mixed>
     */
    public function share(Request $request): array
    {
        $user = $request->user();

        return [
            ...parent::share($request),
            'auth' => [
                'user' => $user === null ? null : [
                    'name' => $user->name,
                    'first_name' => $user->first_name,
                    'email' => $user->email,
                    'initials' => $user->initials,
                    'sound' => $user->sound,
                    'prep_seconds' => $user->prep_seconds,
                    'countdown_seconds' => $user->countdown_seconds,
                    'volume' => $user->volume,
                    'countdown_sound' => $user->countdown_sound->value,
                    'audio_mode' => $user->audio_mode->value,
                    'custom_sound_url' => $user->custom_sound_url,
                    'custom_sound_name' => $user->custom_sound_name,
                ],
            ],
            'flash' => [
                'success' => fn (): ?string => $request->session()->get('success'),
                'error' => fn (): ?string => $request->session()->get('error'),
            ],
            'registration_open' => config('sport.registration_open'),
            'seconds_per_rep' => config('sport.seconds_per_rep'),
            // Clé publique des notifications web ; absente, l'appli ne propose pas les alertes.
            'push_public_key' => fn (): ?string => $request->user() === null ? null : config('services.webpush.public_key'),
            'audio_modes' => fn (): array => array_map(
                fn (AudioMode $mode): array => ['value' => $mode->value, 'label' => $mode->label(), 'description' => $mode->description()],
                AudioMode::cases(),
            ),
            // Vitesse, inclinaison, niveau : ce que règle chaque machine de cardio.
            'machine_settings' => fn (): ?array => $request->user() === null ? null : MachineSettings::forClient(),
            // Les zones de chaque grand muscle (pectoraux haut, bas…), pour la couverture d'une séance.
            'muscle_zones' => fn (): ?array => $request->user() === null ? null : MuscleZones::forClient(),
            // Les sons du compte à rebours, pour les réglages.
            'countdown_sounds' => fn (): array => array_map(
                fn (CountdownSound $sound): array => ['value' => $sound->value, 'label' => $sound->label()],
                CountdownSound::cases(),
            ),
            // Libellés de la silhouette musculaire, partagés par tous les écrans qui l'affichent.
            'muscles' => fn (): array => ExerciseCatalog::muscles(),
        ];
    }
}
