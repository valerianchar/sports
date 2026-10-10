<?php

namespace App\Queries;

use App\Models\User;
use App\Models\WorkoutLog;
use App\Models\WorkoutSchedule;
use Carbon\CarbonImmutable;

/**
 * La semaine en cours, du lundi au dimanche (heure de Paris) : pour chaque
 * jour, la séance prévue au programme et si une séance y a été menée au bout ;
 * l'objectif de séances de la semaine et où l'on en est.
 */
final class WeekPlan
{
    private const TIMEZONE = 'Europe/Paris';

    public function __construct(private readonly User $user) {}

    /**
     * @return array{days: list<array<string, mixed>>, goal: int, done: int, today: array<string, mixed>|null}
     */
    public function summary(): array
    {
        $now = CarbonImmutable::now(self::TIMEZONE);
        $monday = $now->startOfWeek(CarbonImmutable::MONDAY);
        $schedules = $this->user->schedules()->with('workout:id,name')->get()->keyBy('weekday');

        $doneDays = WorkoutLog::query()
            ->where('user_id', $this->user->id)
            ->where('completed', true)
            ->where('finished_at', '>=', $monday->utc())
            ->pluck('finished_at')
            ->map(fn ($at): string => CarbonImmutable::parse($at)->setTimezone(self::TIMEZONE)->toDateString())
            ->countBy();

        $days = [];

        foreach (WorkoutSchedule::DAYS as $weekday => $label) {
            $date = $monday->addDays($weekday - 1);
            $schedule = $schedules->get($weekday);

            $days[] = [
                'weekday' => $weekday,
                'initial' => mb_substr($label, 0, 1),
                'label' => $label,
                'date' => $date->toDateString(),
                'today' => $date->isSameDay($now),
                'past' => $date->lt($now->startOfDay()),
                'done' => $doneDays->get($date->toDateString(), 0) > 0,
                'planned' => $schedule?->workout?->name,
                'time' => $schedule?->workout ? $schedule->time : null,
            ];
        }

        $today = $schedules->get($now->dayOfWeekIso);

        return [
            'days' => $days,
            'goal' => $this->user->weekly_goal,
            'done' => (int) $doneDays->sum(),
            'today' => $today?->workout ? ['workout_id' => $today->workout_id, 'name' => $today->workout->name, 'time' => $today->time] : null,
        ];
    }
}
