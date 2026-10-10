<?php

namespace App\Queries;

use App\Models\BodyWeight;
use App\Models\SetLog;
use App\Models\User;
use App\Models\WorkoutLog;
use App\Support\Energy;
use App\Support\ExerciseCatalog;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Contracts\Pagination\Paginator;
use Illuminate\Support\Collection;

/**
 * Le journal des séances : celles réellement faites, de la plus récente à la
 * plus ancienne, et le détail de chacune, série par série.
 *
 * Les séances interrompues y figurent — c'est un journal, pas un palmarès —
 * mais marquées : elles n'ont ni calories ni records, comme partout ailleurs
 * dans les statistiques. Dates et semaines à l'heure de Paris, semaines du
 * lundi au dimanche.
 */
final class SessionJournal
{
    private const TIMEZONE = 'Europe/Paris';

    public const PER_PAGE = 20;

    /** @var Collection<int, BodyWeight>|null */
    private ?Collection $weights = null;

    private CarbonImmutable $now;

    public function __construct(private readonly User $user)
    {
        $this->now = CarbonImmutable::now(self::TIMEZONE);
    }

    /**
     * Une page du journal : chaque séance en bref.
     *
     * @return Paginator<int, array<string, mixed>>
     */
    public function page(): Paginator
    {
        return $this->user->workoutLogs()
            ->with(['sets' => fn ($query) => $query
                ->select(['id', 'workout_log_id', 'exercise', 'position', 'drop', 'seconds', 'volume', 'performed_at'])
                ->orderBy('performed_at')
                ->orderBy('position')])
            ->orderByDesc('finished_at')
            ->orderByDesc('id')
            ->simplePaginate(self::PER_PAGE)
            ->through(fn (WorkoutLog $log): array => $this->summary($log));
    }

    /**
     * Une séance en détail : ses exercices dans l'ordre où ils ont été faits,
     * chacun avec ses séries, ses paliers de drop et son meilleur 1RM.
     *
     * @return array<string, mixed>
     */
    public function detail(WorkoutLog $log): array
    {
        $sets = $log->sets()
            ->orderBy('performed_at')
            ->orderBy('position')
            ->orderBy('set_number')
            ->orderBy('id')
            ->get();

        $log->setRelation('sets', $sets);
        $records = $this->records($log, $sets);

        $exercises = $sets->groupBy('exercise')->map(function (Collection $sets, string $slug) use ($records): array {
            $exercise = ExerciseCatalog::find($slug);
            $main = $sets->whereNull('drop');

            return [
                'slug' => $slug,
                'name' => $exercise['name'] ?? $slug,
                'images' => $exercise === null ? [] : ExerciseCatalog::images($slug),
                'known' => $exercise !== null,
                'best_e1rm' => $sets->max('e1rm'),
                'volume' => round($sets->sum('volume'), 1),
                'record' => $records[$slug] ?? null,
                'sets' => $main->map(fn (SetLog $set): array => [
                    ...$this->presentSet($set),
                    'target' => $set->target_reps,
                    // Les paliers d'une série dégressive : même exercice, même place, même numéro.
                    'drops' => $sets
                        ->where('position', $set->position)
                        ->where('set_number', $set->set_number)
                        ->whereNotNull('drop')
                        ->sortBy('drop')
                        ->map($this->presentSet(...))
                        ->values()
                        ->all(),
                ])->values()->all(),
            ];
        })->values()->all();

        return [
            ...$this->summary($log),
            'exercises' => $exercises,
            'planned_sets' => $log->planned_sets,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function summary(WorkoutLog $log): array
    {
        $at = $log->finished_at->toImmutable()->setTimezone(self::TIMEZONE)->locale('fr');
        $week = $at->startOfWeek(CarbonInterface::MONDAY);
        $sets = $log->sets;

        return [
            'id' => $log->id,
            'name' => $log->name,
            'completed' => $log->completed,
            'finished_at' => $log->finished_at->toIso8601String(),
            'day_label' => $this->dayLabel($at),
            'time' => $at->format('H:i'),
            'week_start' => $week->toDateString(),
            'week_label' => 'Semaine du '.$week->translatedFormat($week->year === $this->now->year ? 'j F' : 'j F Y'),
            'minutes' => (int) round($log->duration_seconds / 60),
            // Les anciens journaux n'ont pas le détail des séries : on s'en tient au compte du téléphone.
            'sets' => $sets->isEmpty() ? $log->sets_done : $sets->whereNull('drop')->count(),
            'tonnage' => round($sets->sum('volume')),
            'kcal' => $log->completed ? $this->kcal($log, $sets) : null,
            'rpe' => $log->rpe,
            'exercises' => $sets->pluck('exercise')->unique()
                ->map(fn (string $slug): string => ExerciseCatalog::find($slug)['name'] ?? $slug)
                ->values()->all(),
        ];
    }

    /** « Aujourd'hui », « Hier », sinon « Lundi 6 octobre » (avec l'année si ce n'est pas la courante). */
    private function dayLabel(CarbonImmutable $at): string
    {
        $today = $this->now->startOfDay();

        return match (true) {
            $at->isSameDay($today) => 'Aujourd’hui',
            $at->isSameDay($today->subDay()) => 'Hier',
            default => ucfirst($at->translatedFormat($at->year === $this->now->year ? 'l j F' : 'l j F Y')),
        };
    }

    /**
     * @return array{reps: int|null, weight: float|null, seconds: int|null, per_side: bool}
     */
    private function presentSet(SetLog $set): array
    {
        return [
            'reps' => $set->reps,
            'weight' => $set->weight,
            'seconds' => $set->seconds,
            // Le journal ne garde pas le « par côté » : il se lit dans le volume, compté double.
            'per_side' => $set->weight > 0 && $set->reps > 0 && $set->volume > $set->weight * $set->reps * 1.5,
        ];
    }

    /**
     * Calories estimées, au poids de la dernière pesée avant la séance — à
     * défaut la première, à défaut 75 kg —, comme dans les statistiques.
     *
     * @param  Collection<int, SetLog>  $sets
     */
    private function kcal(WorkoutLog $log, Collection $sets): int
    {
        $this->weights ??= $this->user->bodyWeights()->orderBy('measured_on')->get(['kg', 'measured_on']);
        $before = $this->weights->filter(fn (BodyWeight $w): bool => $w->measured_on->lte($log->finished_at))->last();
        $kg = (float) (($before ?? $this->weights->first())?->kg ?? Energy::DEFAULT_WEIGHT);

        return Energy::kcal(
            $log->duration_seconds,
            $sets->map(fn (SetLog $s): array => ['exercise' => $s->exercise, 'seconds' => $s->seconds]),
            $kg,
        );
    }

    /**
     * Les exercices où cette séance a battu un record — charge maximale
     * d'abord, sinon 1RM estimé — face aux séances complètes d'avant. Un
     * premier passage n'en est pas un ; une séance interrompue n'en bat aucun.
     *
     * @param  Collection<int, SetLog>  $sets
     * @return array<string, string> 'weight' ou 'e1rm', par exercice
     */
    private function records(WorkoutLog $log, Collection $sets): array
    {
        if (! $log->completed || $sets->isEmpty()) {
            return [];
        }

        $before = SetLog::query()
            ->whereIn('exercise', $sets->pluck('exercise')->unique()->all())
            ->whereIn('workout_log_id', WorkoutLog::query()->select('id')
                ->where('user_id', $log->user_id)
                ->where('completed', true)
                ->where('id', '!=', $log->id)
                ->where('finished_at', '<', $log->finished_at))
            ->groupBy('exercise')
            ->selectRaw('exercise, max(weight) as weight, max(e1rm) as e1rm')
            ->get()
            ->keyBy('exercise');

        $records = [];

        foreach ($sets->groupBy('exercise') as $slug => $mine) {
            $previous = $before->get($slug);

            if ($previous === null) {
                continue;
            }

            if ($previous->weight !== null && $mine->max('weight') > (float) $previous->weight) {
                $records[$slug] = 'weight';
            } elseif ($previous->e1rm !== null && $mine->max('e1rm') > (float) $previous->e1rm) {
                $records[$slug] = 'e1rm';
            }
        }

        return $records;
    }
}
