<?php

namespace App\Http\Controllers;

use App\Actions\RecordWorkoutLog;
use App\Http\Requests\StoreWorkoutLogRequest;
use App\Models\Workout;
use Illuminate\Http\JsonResponse;
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

        $log = $recordWorkoutLog->handle($request->user(), $workout, $request->validated());

        return response()->json(['id' => $log->id], $log->wasRecentlyCreated ? 201 : 200);
    }
}
