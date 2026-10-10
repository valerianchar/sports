<?php

namespace App\Http\Controllers;

use App\Models\WorkoutSchedule;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Le programme de la semaine : quelle séance quel jour, à quelle heure, avec
 * un rappel ; et l'objectif de séances par semaine.
 */
class ScheduleController extends Controller
{
    public function edit(Request $request): Response
    {
        $schedules = $request->user()->schedules()->get()->keyBy('weekday');

        return Inertia::render('Programme/Index', [
            'days' => array_map(fn (int $weekday, string $label): array => [
                'weekday' => $weekday,
                'label' => $label,
                'workout_id' => $schedules->get($weekday)?->workout_id,
                'time' => $schedules->get($weekday)?->time,
                'remind' => (bool) $schedules->get($weekday)?->remind,
            ], array_keys(WorkoutSchedule::DAYS), WorkoutSchedule::DAYS),
            'workouts' => $request->user()->workouts()->orderBy('name')->get(['id', 'name']),
            'weeklyGoal' => $request->user()->weekly_goal,
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $user = $request->user();
        $data = $request->validate([
            'weekly_goal' => ['required', 'integer', 'min:0', 'max:7'],
            'days' => ['required', 'array', 'size:7'],
            'days.*.weekday' => ['required', 'integer', 'between:1,7', 'distinct'],
            'days.*.workout_id' => ['nullable', 'integer', Rule::exists('workouts', 'id')->where('user_id', $user->id)],
            'days.*.time' => ['nullable', 'date_format:H:i'],
            'days.*.remind' => ['boolean'],
        ], [
            'days.*.time.date_format' => 'Une heure comme 18:30.',
        ]);

        $user->update(['weekly_goal' => $data['weekly_goal']]);

        foreach ($data['days'] as $day) {
            $user->schedules()->updateOrCreate(['weekday' => $day['weekday']], [
                'workout_id' => $day['workout_id'] ?? null,
                'time' => $day['time'] ?? null,
                // Un rappel n'a de sens qu'avec une séance et une heure.
                'remind' => ($day['remind'] ?? false) && isset($day['workout_id'], $day['time']),
            ]);
        }

        return back()->with('success', 'Programme enregistré.');
    }
}
