<?php

namespace App\Http\Controllers;

use App\Actions\CopyWorkout;
use App\Http\Resources\WorkoutResource;
use App\Models\Workout;
use App\Notifications\WorkoutShared;
use App\Support\ExerciseCatalog;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\Mailer\Exception\TransportExceptionInterface;

/**
 * Dupliquer une séance, et la partager : un lien qu'un autre membre ouvre
 * pour l'ajouter d'un toucher à ses propres séances. Le lien ne montre que
 * le contenu de la séance — ni son historique ni son auteur au-delà du prénom.
 */
class WorkoutShareController extends Controller
{
    public function duplicate(Request $request, Workout $workout, CopyWorkout $copyWorkout): RedirectResponse
    {
        Gate::authorize('view', $workout);

        $copy = $copyWorkout->handle($workout->load('items'), $request->user(), "{$workout->name} (copie)");

        return redirect()->route('workouts.edit', $copy)->with('success', 'Séance dupliquée : renomme-la et retouche-la.');
    }

    /**
     * Le lien de partage, créé au premier partage puis réutilisé. JSON.
     */
    public function link(Request $request, Workout $workout): JsonResponse
    {
        Gate::authorize('update', $workout);

        $workout->share_token ??= Str::random(24);
        $workout->save();

        return response()->json(['url' => route('shares.show', $workout->share_token)]);
    }

    /**
     * Envoyer la séance par mail : le destinataire reçoit son contenu et le
     * lien pour l'ajouter à ses séances. JSON.
     */
    public function mail(Request $request, Workout $workout): JsonResponse
    {
        Gate::authorize('update', $workout);

        $data = $request->validate(['email' => ['required', 'email:rfc', 'max:255']], [
            'email.required' => 'Indique une adresse mail.',
            'email.email' => 'Cette adresse mail ne semble pas valide.',
        ]);

        $workout->share_token ??= Str::random(24);
        $workout->save();

        try {
            Notification::route('mail', $data['email'])->notify(new WorkoutShared($workout->load('items'), $request->user()));
        } catch (TransportExceptionInterface $exception) {
            // Adresse inconnue ou refusée par le serveur de mail : on le dit, sans planter.
            report($exception);

            return response()->json(['message' => 'Le serveur de mail refuse cette adresse.', 'errors' => ['email' => ['Le serveur de mail refuse cette adresse.']]], 422);
        }

        return response()->json(['sent' => true]);
    }

    public function show(Request $request, string $token): Response
    {
        $workout = Workout::query()->where('share_token', $token)->with(['items', 'user'])->firstOrFail();

        return Inertia::render('Workouts/Shared', [
            'workout' => new WorkoutResource($workout),
            'author' => $workout->user->first_name,
            'mine' => $workout->user_id === $request->user()->id,
            'exercises' => ExerciseCatalog::forClient($workout->items->pluck('exercise')),
            'token' => $token,
        ]);
    }

    public function import(Request $request, string $token, CopyWorkout $copyWorkout): RedirectResponse
    {
        $workout = Workout::query()->where('share_token', $token)->with('items')->firstOrFail();
        $copy = $copyWorkout->handle($workout, $request->user(), $workout->name);

        return redirect()->route('workouts.list')->with('success', "« {$copy->name} » est dans tes séances.");
    }
}
