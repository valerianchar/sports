<?php

namespace App\Http\Controllers;

use App\Actions\RecordWorkoutLog;
use App\Http\Requests\StoreWorkoutLogRequest;
use App\Models\Workout;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

/**
 * Reçoit le journal d'une séance terminée — en JSON, hors Inertia : le lecteur
 * peut le rejouer plus tard si la salle n'avait pas de réseau à la fin.
 */
class WorkoutLogController extends Controller
{
    public function store(StoreWorkoutLogRequest $request, Workout $workout, RecordWorkoutLog $recordWorkoutLog): JsonResponse
    {
        Gate::authorize('view', $workout);

        [$log, $records] = $recordWorkoutLog->handle($request->user(), $workout, $request->validated());

        return response()->json(['id' => $log->id, 'records' => $records], $log->wasRecentlyCreated ? 201 : 200);
    }

    /**
     * La difficulté ressentie, notée sur l'écran de fin de séance.
     */
    public function feeling(Request $request, string $clientId): JsonResponse
    {
        $data = $request->validate(['rpe' => ['required', 'integer', 'min:1', 'max:10']]);

        $log = $request->user()->workoutLogs()->where('client_id', $clientId)->firstOrFail();
        $log->update(['rpe' => $data['rpe']]);

        return response()->json(['rpe' => $log->rpe]);
    }
}
