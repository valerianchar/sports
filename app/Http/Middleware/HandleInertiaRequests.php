<?php

namespace App\Http\Middleware;

use App\Support\ExerciseCatalog;
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
                ],
            ],
            'flash' => [
                'success' => fn (): ?string => $request->session()->get('success'),
                'error' => fn (): ?string => $request->session()->get('error'),
            ],
            'registration_open' => config('sport.registration_open'),
            'seconds_per_rep' => config('sport.seconds_per_rep'),
            // Libellés de la silhouette musculaire, partagés par tous les écrans qui l'affichent.
            'muscles' => fn (): array => ExerciseCatalog::muscles(),
        ];
    }
}
