<?php

namespace App\Http\Controllers;

use App\Models\WorkoutLog;
use App\Queries\SessionJournal;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Le journal des séances faites : la liste, le détail série par série, et
 * l'effacement d'une séance enregistrée par erreur.
 */
class JournalController extends Controller
{
    public function index(Request $request): Response
    {
        return Inertia::render('Journal/Index', [
            // Vingt par vingt, la suite chargée au défilement.
            'sessions' => Inertia::scroll(fn () => (new SessionJournal($request->user()))->page()),
        ]);
    }

    public function show(Request $request, int $log): Response
    {
        $workoutLog = $this->find($request, $log);

        return Inertia::render('Journal/Show', [
            'session' => (new SessionJournal($request->user()))->detail($workoutLog),
        ]);
    }

    /**
     * Efface une séance et toutes ses séries : elle disparaît aussi des
     * statistiques et des records.
     */
    public function destroy(Request $request, int $log): RedirectResponse
    {
        $workoutLog = $this->find($request, $log);

        DB::transaction(function () use ($workoutLog): void {
            $workoutLog->sets()->delete();
            $workoutLog->delete();
        });

        return redirect()->route('journal.index')->with('success', 'Séance retirée du journal.');
    }

    /** Seulement parmi les séances de l'utilisateur : celle d'un autre n'existe pas pour lui. */
    private function find(Request $request, int $log): WorkoutLog
    {
        return $request->user()->workoutLogs()->findOrFail($log);
    }
}
