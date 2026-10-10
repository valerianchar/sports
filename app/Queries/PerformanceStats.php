<?php

namespace App\Queries;

use App\Enums\BodyMeasure;
use App\Enums\Muscle;
use App\Enums\PhotoPose;
use App\Models\BodyMeasurement;
use App\Models\BodyPhoto;
use App\Models\BodyWeight;
use App\Models\SetLog;
use App\Models\User;
use App\Models\WorkoutLog;
use App\Support\Energy;
use App\Support\ExerciseCatalog;
use App\Support\Strength;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

/**
 * Les indicateurs de performance d'un utilisateur, calculés à partir de ses
 * séances et de chacune de ses séries — les séances complètes seulement : une
 * séance abandonnée en route ne dit rien de ses performances.
 *
 * Tout est lu en une fois (une année glissante), puis agrégé en mémoire : un
 * pratiquant assidu cumule quelques milliers de séries par an, rien qui
 * justifie une requête par indicateur. Les semaines vont du lundi au dimanche,
 * à l'heure de Paris.
 */
final class PerformanceStats
{
    private const TIMEZONE = 'Europe/Paris';

    /** Les muscles qu'on surveille pour « à travailler » et les équilibres. */
    private const MAJOR_MUSCLES = ['chest', 'upper-back', 'front-deltoids', 'rear-deltoids', 'biceps', 'triceps', 'abs', 'gluteal', 'quadriceps', 'hamstring', 'calves'];

    private const PUSH = ['chest', 'front-deltoids', 'triceps'];

    private const PULL = ['upper-back', 'rear-deltoids', 'trapezius', 'biceps'];

    private const UPPER = ['chest', 'front-deltoids', 'rear-deltoids', 'upper-back', 'trapezius', 'biceps', 'triceps', 'forearm'];

    private const LOWER = ['gluteal', 'quadriceps', 'hamstring', 'adductors', 'calves', 'tibialis'];

    /** Les grands mouvements dont on suit la force relative au poids de corps. */
    private const BIG_LIFTS = ['developpe-couche', 'squat', 'souleve-de-terre', 'developpe-militaire', 'tractions'];

    /** Repères de volume hebdomadaire par muscle pour progresser (séries). */
    public const WEEKLY_SETS_TARGET = [10, 20];

    /** @var Collection<int, WorkoutLog>|null */
    private ?Collection $logs = null;

    /** @var Collection<int, SetLog>|null */
    private ?Collection $sets = null;

    /** @var Collection<int, BodyWeight>|null */
    private ?Collection $weights = null;

    /** @var array<int, array{kcal: int, cardio: int}>|null calories et secondes de cardio, par séance */
    private ?array $energy = null;

    private CarbonImmutable $now;

    public function __construct(private readonly User $user)
    {
        $this->now = CarbonImmutable::now(self::TIMEZONE);
    }

    // ------------------------------------------------------------------ accueil

    /**
     * Les indicateurs de l'accueil : la semaine en cours et ce qui mérite
     * l'attention.
     *
     * @return array<string, mixed>
     */
    public function home(): array
    {
        $thisWeek = $this->weekStart($this->now);
        $lastWeek = $thisWeek->subWeek();
        $weeks = $this->weeks(8);
        $current = $weeks[count($weeks) - 1];
        $previous = $weeks[count($weeks) - 2];
        $records = $this->recordEvents();
        $recent = array_values(array_filter($records, fn (array $r): bool => $r['at'] >= $this->now->subDays(30)));

        return [
            'has_data' => $this->logs()->isNotEmpty(),
            'week' => [
                'sessions' => $current['sessions'],
                'minutes' => $current['minutes'],
                'tonnage' => $current['tonnage'],
                'sets' => $current['sets'],
                // Comparée à la semaine dernière au même moment : une semaine entamée
                // ne se mesure pas à une semaine complète.
                'tonnage_delta' => $this->delta($current['tonnage'], $this->tonnageBetween($lastWeek, $this->now->subWeek())),
                'sessions_last_week' => $previous['sessions'],
                'cardio_minutes' => $current['cardio_minutes'],
                'kcal' => $current['kcal'],
                'kcal_last_week' => $previous['kcal'],
            ],
            'body' => $this->bodySummary(),
            'streak' => $this->streak(),
            'records_30d' => count($recent),
            'latest_record' => $recent === [] ? null : $this->presentRecord(end($recent)),
            'tonnage_weeks' => array_map(fn (array $w): array => ['label' => $w['label'], 'value' => $w['tonnage']], $weeks),
            'cardio_weeks' => array_map(fn (array $w): array => ['label' => $w['label'], 'value' => $w['cardio_minutes']], $weeks),
            'neglected' => $this->neglected(),
            'total_sessions' => $this->logs()->count(),
            'week_start' => $thisWeek->toDateString(),
            'last_week_start' => $lastWeek->toDateString(),
        ];
    }

    // ------------------------------------------------------------------ vue d'ensemble

    /**
     * @return array<string, mixed>
     */
    public function overview(): array
    {
        $logs = $this->logs();
        $sets = $this->sets();
        $rated = $logs->whereNotNull('rpe');

        return [
            'weeks' => $this->weeks(12),
            'calendar' => $this->calendar(84),
            'streak' => $this->streak(),
            'totals' => [
                'sessions' => $logs->count(),
                'minutes' => (int) round($logs->sum('duration_seconds') / 60),
                'tonnage' => round($sets->sum('volume')),
                'sets' => $sets->whereNull('drop')->count(),
                'reps' => (int) $sets->sum('reps'),
                'records' => count($this->recordEvents()),
                'average_minutes' => $logs->isEmpty() ? 0 : (int) round($logs->avg('duration_seconds') / 60),
                'average_rpe' => $rated->isEmpty() ? null : round($rated->avg('rpe'), 1),
                'cardio_minutes' => (int) round(array_sum(array_column($this->energy(), 'cardio')) / 60),
                'kcal' => array_sum(array_column($this->energy(), 'kcal')),
            ],
            'records' => array_map($this->presentRecord(...), array_slice(array_reverse($this->recordEvents()), 0, 8)),
        ];
    }

    // ------------------------------------------------------------------ exercices

    /**
     * Chaque exercice pratiqué : la dernière fois, le meilleur 1RM, la tendance,
     * la charge conseillée. Les plus récents d'abord.
     *
     * @return list<array<string, mixed>>
     */
    public function exercises(): array
    {
        $byExercise = $this->sets()->groupBy('exercise');
        $recentFrom = $this->now->subDays(28);
        $olderFrom = $this->now->subDays(56);

        return $byExercise
            ->map(function (Collection $sets, string $slug) use ($recentFrom, $olderFrom): ?array {
                $exercise = ExerciseCatalog::find($slug);

                if ($exercise === null) {
                    return null;
                }

                $last = $this->lastSession($sets);
                $recent = $sets->filter(fn (SetLog $s): bool => $s->performed_at >= $recentFrom)->max('e1rm');
                $older = $sets->filter(fn (SetLog $s): bool => $s->performed_at < $recentFrom && $s->performed_at >= $olderFrom)->max('e1rm');
                $top = $last->sortByDesc('weight')->first();

                return [
                    'slug' => $slug,
                    'name' => $exercise['name'],
                    'image' => ExerciseCatalog::images($slug)[0],
                    'group' => $exercise['group'],
                    'sessions' => $sets->pluck('workout_log_id')->unique()->count(),
                    'last_at' => $sets->max('performed_at')->toIso8601String(),
                    'last_ago' => $sets->max('performed_at')->diffForHumans(),
                    'last_top' => $top === null ? null : ['weight' => $top->weight, 'reps' => $top->reps, 'seconds' => $top->seconds],
                    'best_e1rm' => $sets->max('e1rm'),
                    'best_weight' => $sets->max('weight'),
                    'trend' => $this->delta($recent, $older),
                    'next' => $this->nextFrom($last),
                ];
            })
            ->filter()
            ->sortByDesc('last_at')
            ->values()
            ->all();
    }

    /**
     * L'historique d'un exercice : séance par séance, et ses records.
     *
     * @return array<string, mixed>
     */
    public function exercise(string $slug): array
    {
        $sets = $this->user->setLogs()
            ->whereIn('workout_log_id', $this->completedLogIds())
            ->where('exercise', $slug)
            ->orderBy('performed_at')
            ->get();

        $sessions = $sets->groupBy('workout_log_id')->map(function (Collection $session): array {
            $main = $session->whereNull('drop');

            return [
                'date' => $session->first()->performed_at->toIso8601String(),
                'label' => $session->first()->performed_at->setTimezone(self::TIMEZONE)->translatedFormat('j M'),
                'top_weight' => $session->max('weight'),
                'best_e1rm' => $session->max('e1rm'),
                'volume' => round($session->sum('volume'), 1),
                'reps' => (int) $session->sum('reps'),
                'sets' => $main->map(fn (SetLog $s): array => [
                    'weight' => $s->weight,
                    'reps' => $s->reps,
                    'target' => $s->target_reps,
                    'seconds' => $s->seconds,
                    'drops' => $session->where('set_number', $s->set_number)->whereNotNull('drop')
                        ->map(fn (SetLog $d): array => ['weight' => $d->weight, 'reps' => $d->reps])->values()->all(),
                ])->values()->all(),
            ];
        })->values();

        $at = fn (?SetLog $set): ?string => $set?->performed_at->diffForHumans();
        $heaviest = $sets->sortByDesc('weight')->first();
        $strongest = $sets->whereNotNull('e1rm')->sortByDesc('e1rm')->first();
        $mostReps = $sets->sortByDesc('reps')->first();
        $biggest = $sessions->sortByDesc('volume')->first();

        return [
            'sessions' => $sessions->reverse()->values()->all(),
            'chart' => $sessions->map(fn (array $s): array => [
                'label' => $s['label'],
                'e1rm' => $s['best_e1rm'],
                'weight' => $s['top_weight'],
                'volume' => $s['volume'],
            ])->take(-30)->values()->all(),
            'records' => [
                'weight' => $heaviest?->weight ? ['value' => $heaviest->weight, 'reps' => $heaviest->reps, 'ago' => $at($heaviest)] : null,
                'e1rm' => $strongest ? ['value' => $strongest->e1rm, 'weight' => $strongest->weight, 'reps' => $strongest->reps, 'ago' => $at($strongest)] : null,
                'reps' => $mostReps?->reps ? ['value' => $mostReps->reps, 'weight' => $mostReps->weight, 'ago' => $at($mostReps)] : null,
                'volume' => $biggest && $biggest['volume'] > 0 ? ['value' => $biggest['volume'], 'label' => $biggest['label']] : null,
            ],
            'next' => $this->nextFrom($this->lastSession($sets)),
            'total' => [
                'sessions' => $sessions->count(),
                'sets' => $sets->whereNull('drop')->count(),
                'reps' => (int) $sets->sum('reps'),
                'volume' => round($sets->sum('volume')),
            ],
        ];
    }

    /**
     * Pour le lecteur : la dernière fois sur chaque exercice de la séance, et la
     * charge conseillée cette fois-ci.
     *
     * @param  list<string>  $slugs
     * @return array<string, array{sets: list<array{weight: float|null, reps: int|null}>, ago: string, next: array{weight: float, trend: string}|null}>
     */
    public function lastTimes(array $slugs): array
    {
        $history = [];

        foreach ($this->sets()->whereIn('exercise', $slugs)->groupBy('exercise') as $slug => $sets) {
            $last = $this->lastSession($sets);

            $history[$slug] = [
                'sets' => $last->map(fn (SetLog $s): array => ['weight' => $s->weight, 'reps' => $s->reps])->values()->all(),
                'ago' => $last->first()->performed_at->diffForHumans(),
                'next' => $this->nextFrom($last),
            ];
        }

        return $history;
    }

    // ------------------------------------------------------------------ muscles

    /**
     * @return array<string, mixed>
     */
    public function muscles(): array
    {
        $week = $this->muscleSets($this->now->subDays(7));
        $month = $this->muscleSets($this->now->subDays(28));

        $rows = collect(Muscle::cases())
            ->reject(fn (Muscle $m): bool => in_array($m, [Muscle::Neck, Muscle::Tibialis], true))
            ->map(fn (Muscle $m): array => [
                'muscle' => $m->value,
                'label' => $m->label(),
                'week' => round($week[$m->value] ?? 0, 1),
                'weekly_average' => round(($month[$m->value] ?? 0) / 4, 1),
                'strength' => $this->muscleStrengthTrend($m->value),
            ])
            ->sortByDesc('week')
            ->values()
            ->all();

        $sum = fn (array $sets, array $muscles): float => array_sum(array_intersect_key($sets, array_flip($muscles)));

        return [
            'rows' => $rows,
            'week' => $week,
            'month' => $month,
            'target' => self::WEEKLY_SETS_TARGET,
            'balances' => [
                $this->balance('Poussée / tirage', 'Poussée', 'Tirage', $sum($month, self::PUSH), $sum($month, self::PULL)),
                $this->balance('Haut / bas du corps', 'Haut', 'Bas', $sum($month, self::UPPER), $sum($month, self::LOWER)),
                $this->balance('Quadriceps / ischios', 'Quadriceps', 'Ischios', $month['quadriceps'] ?? 0, $month['hamstring'] ?? 0),
            ],
        ];
    }

    // ------------------------------------------------------------------ corps

    /**
     * @return array<string, mixed>
     */
    public function body(): array
    {
        $weights = $this->weights();
        $latest = $weights->last();

        $lifts = collect(self::BIG_LIFTS)->map(function (string $slug) use ($latest): ?array {
            $best = $this->sets()->where('exercise', $slug)->max('e1rm');

            if (! $best) {
                return null;
            }

            return [
                'slug' => $slug,
                'name' => ExerciseCatalog::find($slug)['name'],
                'e1rm' => round($best, 1),
                'ratio' => $latest ? round($best / $latest->kg, 2) : null,
            ];
        })->filter()->values()->all();

        return [
            'entries' => $weights->reverse()->take(30)->map(fn (BodyWeight $w): array => [
                'id' => $w->id,
                'kg' => $w->kg,
                'date' => $w->measured_on->toDateString(),
                'label' => $w->measured_on->translatedFormat('j M Y'),
            ])->values()->all(),
            'chart' => $weights->take(-30)->map(fn (BodyWeight $w): array => [
                'label' => $w->measured_on->translatedFormat('j M'),
                'value' => $w->kg,
            ])->values()->all(),
            ...$this->bodySummary(),
            'lifts' => $lifts,
            'measurements' => $this->measurements(),
            'photos' => $this->photos(),
            'poses' => array_map(fn (PhotoPose $pose): array => ['value' => $pose->value, 'label' => $pose->label()], PhotoPose::cases()),
        ];
    }

    /**
     * Les mensurations : pour chaque mesure, la dernière valeur, l'écart sur
     * 30 jours et depuis la première, et sa courbe ; puis les dernières
     * saisies, jour par jour.
     *
     * @return array{kinds: list<array<string, mixed>>, entries: list<array<string, mixed>>}
     */
    private function measurements(): array
    {
        $rows = $this->user->bodyMeasurements()->orderBy('measured_on')->get();
        $monthAgo = $this->now->subDays(30);

        $kinds = array_map(function (BodyMeasure $measure) use ($rows, $monthAgo): array {
            $column = $measure->value;
            $points = $rows->filter(fn (BodyMeasurement $m): bool => $m->{$column} !== null)->values();
            $latest = $points->last();
            $first = $points->first();
            // Comme pour le poids : la dernière mesure prise il y a au moins 30 jours.
            $reference = $points->filter(fn (BodyMeasurement $m): bool => $m->measured_on->lte($monthAgo))->last();

            return [
                'key' => $column,
                'label' => $measure->label(),
                'hint' => $measure->hint(),
                'latest' => $latest?->{$column},
                'latest_label' => $latest?->measured_on->translatedFormat('j M'),
                'change_30d' => $latest && $reference && $reference->isNot($latest) ? round($latest->{$column} - $reference->{$column}, 1) : null,
                'change_total' => $points->count() > 1 ? round($latest->{$column} - $first->{$column}, 1) : null,
                'first_label' => $points->count() > 1 ? $first->measured_on->translatedFormat($first->measured_on->year === $this->now->year ? 'j M' : 'j M Y') : null,
                'chart' => $points->take(-30)->map(fn (BodyMeasurement $m): array => [
                    'label' => $m->measured_on->translatedFormat('j M'),
                    'value' => $m->{$column},
                ])->values()->all(),
            ];
        }, BodyMeasure::cases());

        return [
            'kinds' => $kinds,
            'entries' => $rows->reverse()->take(30)->map(fn (BodyMeasurement $m): array => [
                'id' => $m->id,
                'date' => $m->measured_on->toDateString(),
                'label' => $m->measured_on->translatedFormat('j M Y'),
                'values' => collect(BodyMeasure::columns())
                    ->filter(fn (string $column): bool => $m->{$column} !== null)
                    ->mapWithKeys(fn (string $column): array => [$column => $m->{$column}])
                    ->all(),
            ])->values()->all(),
        ];
    }

    /**
     * Les photos de progression, les plus récentes d'abord : leur adresse
     * seulement (le fichier est servi à part, au seul propriétaire), et le
     * poids du moment s'il y a une pesée à une semaine près.
     *
     * @return list<array<string, mixed>>
     */
    private function photos(): array
    {
        return $this->user->bodyPhotos()
            ->orderByDesc('taken_on')
            ->orderByDesc('id')
            ->get()
            ->map(fn (BodyPhoto $photo): array => [
                'id' => $photo->id,
                'url' => route('body-photos.show', $photo, false),
                'pose' => $photo->pose->value,
                'date' => $photo->taken_on->toDateString(),
                'label' => $photo->taken_on->translatedFormat('j M Y'),
                'note' => $photo->note,
                'kg' => $this->weights()
                    ->filter(fn (BodyWeight $w): bool => abs($w->measured_on->diffInDays($photo->taken_on)) <= 7)
                    ->sortBy(fn (BodyWeight $w): float => abs($w->measured_on->diffInDays($photo->taken_on)))
                    ->first()?->kg,
            ])
            ->values()
            ->all();
    }

    /**
     * Le poids en bref : la dernière pesée, l'évolution sur 30 jours, et ce
     * qui reste jusqu'au poids visé.
     *
     * @return array{latest: float|null, change_30d: float|null, target: float|null, to_go: float|null}
     */
    private function bodySummary(): array
    {
        $weights = $this->weights();
        $latest = $weights->last();
        $monthAgo = $weights->filter(fn (BodyWeight $w): bool => $w->measured_on->lte($this->now->subDays(30)))->last();
        $target = $this->user->target_weight;

        return [
            'latest' => $latest?->kg,
            'change_30d' => $latest && $monthAgo ? round($latest->kg - $monthAgo->kg, 1) : null,
            'target' => $target,
            'to_go' => $latest && $target ? round($latest->kg - $target, 1) : null,
        ];
    }

    // ------------------------------------------------------------------ outils

    /** @return Collection<int, BodyWeight> */
    private function weights(): Collection
    {
        return $this->weights ??= $this->user->bodyWeights()->orderBy('measured_on')->get();
    }

    /**
     * Calories estimées et secondes de cardio de chaque séance complète. Le
     * poids est celui de la dernière pesée avant la séance — ou de la première
     * après, ou 75 kg tant qu'on ne s'est jamais pesé.
     *
     * @return array<int, array{kcal: int, cardio: int}>
     */
    private function energy(): array
    {
        if ($this->energy !== null) {
            return $this->energy;
        }

        $sets = $this->sets()->groupBy('workout_log_id');
        $this->energy = [];

        foreach ($this->logs() as $log) {
            $efforts = $sets->get($log->id, collect())
                ->map(fn (SetLog $s): array => ['exercise' => $s->exercise, 'seconds' => $s->seconds]);

            $this->energy[$log->id] = [
                'kcal' => Energy::kcal($log->duration_seconds, $efforts, $this->weightOn($log->finished_at->toImmutable())),
                'cardio' => (int) $efforts->filter(fn (array $e): bool => Energy::isCardio($e['exercise']))->sum('seconds'),
            ];
        }

        return $this->energy;
    }

    private function weightOn(CarbonImmutable $day): float
    {
        $weights = $this->weights();
        $before = $weights->filter(fn (BodyWeight $w): bool => $w->measured_on->lte($day))->last();

        return (float) (($before ?? $weights->first())?->kg ?? Energy::DEFAULT_WEIGHT);
    }

    /** @return Collection<int, WorkoutLog> */
    private function logs(): Collection
    {
        return $this->logs ??= $this->user->workoutLogs()
            ->where('completed', true)
            ->where('finished_at', '>=', $this->now->subYear())
            ->orderBy('finished_at')
            ->get();
    }

    /** @return Collection<int, SetLog> */
    private function sets(): Collection
    {
        return $this->sets ??= $this->user->setLogs()
            ->whereIn('workout_log_id', $this->completedLogIds())
            ->where('performed_at', '>=', $this->now->subYear())
            ->orderBy('performed_at')
            ->get();
    }

    /** Sous-requête des séances complètes de l'utilisateur. */
    private function completedLogIds(): Builder
    {
        return WorkoutLog::query()->select('id')->where('user_id', $this->user->id)->where('completed', true);
    }

    private function weekStart(CarbonImmutable $date): CarbonImmutable
    {
        return $date->setTimezone(self::TIMEZONE)->startOfWeek(CarbonImmutable::MONDAY);
    }

    /**
     * Les `count` dernières semaines, la courante comprise, de la plus ancienne
     * à la plus récente.
     *
     * @return list<array<string, mixed>>
     */
    private function weeks(int $count): array
    {
        $current = $this->weekStart($this->now);
        $logs = $this->logs()->groupBy(fn (WorkoutLog $l): string => $this->weekStart($l->finished_at->toImmutable())->toDateString());
        $sets = $this->sets()->groupBy(fn (SetLog $s): string => $this->weekStart($s->performed_at->toImmutable())->toDateString());

        $weeks = [];

        for ($i = $count - 1; $i >= 0; $i--) {
            $start = $current->subWeeks($i);
            $key = $start->toDateString();
            $weekLogs = $logs->get($key, collect());
            $weekSets = $sets->get($key, collect());
            $rated = $weekLogs->whereNotNull('rpe');
            $minutes = (int) round($weekLogs->sum('duration_seconds') / 60);
            $energy = array_intersect_key($this->energy(), array_flip($weekLogs->pluck('id')->all()));

            $weeks[] = [
                'start' => $key,
                'label' => $start->translatedFormat('j M'),
                'sessions' => $weekLogs->count(),
                'minutes' => $minutes,
                'tonnage' => round($weekSets->sum('volume')),
                'sets' => $weekSets->whereNull('drop')->count(),
                'rpe' => $rated->isEmpty() ? null : round($rated->avg('rpe'), 1),
                // Charge d'entraînement : difficulté ressentie × minutes (méthode Foster).
                'load' => (int) round($rated->sum(fn (WorkoutLog $l): float => $l->rpe * $l->duration_seconds / 60)),
                'cardio_minutes' => (int) round(array_sum(array_column($energy, 'cardio')) / 60),
                'kcal' => array_sum(array_column($energy, 'kcal')),
            ];
        }

        return $weeks;
    }

    /**
     * Semaines d'affilée avec au moins une séance, jusqu'à la semaine courante
     * — qui ne casse pas la série tant qu'elle n'est pas finie.
     */
    private function streak(): int
    {
        $weeks = $this->logs()
            ->map(fn (WorkoutLog $l): string => $this->weekStart($l->finished_at->toImmutable())->toDateString())
            ->unique()
            ->flip();

        $week = $this->weekStart($this->now);

        if (! $weeks->has($week->toDateString())) {
            $week = $week->subWeek();
        }

        $streak = 0;

        while ($weeks->has($week->toDateString())) {
            $streak++;
            $week = $week->subWeek();
        }

        return $streak;
    }

    /**
     * Séances par jour sur les `days` derniers jours, pour le calendrier.
     *
     * @return list<array{date: string, count: int, minutes: int}>
     */
    private function calendar(int $days): array
    {
        $byDay = $this->logs()->groupBy(fn (WorkoutLog $l): string => $l->finished_at->toImmutable()->setTimezone(self::TIMEZONE)->toDateString());
        $start = $this->weekStart($this->now)->subDays($days - 7);
        $calendar = [];

        for ($day = $start; $day <= $this->now; $day = $day->addDay()) {
            $logs = $byDay->get($day->toDateString(), collect());
            $calendar[] = ['date' => $day->toDateString(), 'count' => $logs->count(), 'minutes' => (int) round($logs->sum('duration_seconds') / 60)];
        }

        return $calendar;
    }

    /**
     * Les records battus au fil du temps — charge maximale, sinon meilleur 1RM —
     * en rejouant l'historique dans l'ordre : un par exercice et par séance au
     * plus. Un premier passage n'en est pas un.
     *
     * @return list<array{exercise: string, kind: string, value: float, previous: float, at: CarbonImmutable}>
     */
    private function recordEvents(): array
    {
        $best = [];
        $events = [];

        foreach ($this->sets()->groupBy('workout_log_id') as $session) {
            $at = $session->first()->performed_at->toImmutable();

            foreach ($session->groupBy('exercise') as $slug => $sets) {
                $beaten = null;

                foreach (['weight' => $sets->max('weight'), 'e1rm' => $sets->max('e1rm')] as $kind => $value) {
                    if (! $value) {
                        continue;
                    }

                    $previous = $best[$slug][$kind] ?? null;

                    // Une séance, un exercice : un seul record, la charge d'abord.
                    if ($previous !== null && $value > $previous && $beaten === null) {
                        $beaten = ['exercise' => $slug, 'kind' => $kind, 'value' => (float) $value, 'previous' => (float) $previous, 'at' => $at];
                    }

                    $best[$slug][$kind] = max($previous ?? 0, $value);
                }

                if ($beaten !== null) {
                    $events[] = $beaten;
                }
            }
        }

        return $events;
    }

    /**
     * @param  array{exercise: string, kind: string, value: float, previous: float, at: CarbonImmutable}  $record
     * @return array<string, mixed>
     */
    private function presentRecord(array $record): array
    {
        return [
            'exercise' => $record['exercise'],
            'name' => ExerciseCatalog::find($record['exercise'])['name'] ?? $record['exercise'],
            'kind' => $record['kind'],
            'value' => round($record['value'], 1),
            'previous' => round($record['previous'], 1),
            'ago' => $record['at']->diffForHumans(),
        ];
    }

    /**
     * Les séries principales (hors paliers de drop) de la dernière séance d'un
     * exercice.
     *
     * @param  Collection<int, SetLog>  $sets
     * @return Collection<int, SetLog>
     */
    private function lastSession(Collection $sets): Collection
    {
        $last = $sets->sortBy('performed_at')->last();

        return $last === null
            ? collect()
            : $sets->where('workout_log_id', $last->workout_log_id)->whereNull('drop')->sortBy('set_number')->values();
    }

    /**
     * @param  Collection<int, SetLog>  $last
     * @return array{weight: float, trend: string}|null
     */
    private function nextFrom(Collection $last): ?array
    {
        return Strength::nextWeight($last->map(fn (SetLog $s): array => [
            'weight' => $s->weight,
            'reps' => $s->reps,
            'target_reps' => $s->target_reps,
        ])->all());
    }

    /**
     * Séries par muscle depuis une date : une pleine pour un muscle principal,
     * une demie pour un secondaire. Les paliers de drop prolongent une série,
     * ils n'en ajoutent pas.
     *
     * @return array<string, float>
     */
    private function muscleSets(CarbonImmutable $since): array
    {
        $counts = [];

        foreach ($this->sets()->whereNull('drop')->filter(fn (SetLog $s): bool => $s->performed_at >= $since) as $set) {
            $exercise = ExerciseCatalog::find($set->exercise);

            if ($exercise === null) {
                continue;
            }

            foreach ($exercise['primary'] as $muscle) {
                $counts[$muscle] = ($counts[$muscle] ?? 0) + 1;
            }

            foreach ($exercise['secondary'] as $muscle) {
                $counts[$muscle] = ($counts[$muscle] ?? 0) + 0.5;
            }
        }

        arsort($counts);

        return $counts;
    }

    /**
     * Évolution de la force d'un muscle : la somme des meilleurs 1RM des
     * exercices qui le travaillent en principal, sur les 4 dernières semaines,
     * comparée aux 4 précédentes — sur les seuls exercices pratiqués dans les
     * deux périodes, pour comparer ce qui est comparable.
     */
    private function muscleStrengthTrend(string $muscle): ?float
    {
        $recentFrom = $this->now->subDays(28);
        $olderFrom = $this->now->subDays(56);
        $recent = [];
        $older = [];

        foreach ($this->sets()->whereNotNull('e1rm') as $set) {
            $exercise = ExerciseCatalog::find($set->exercise);

            if ($exercise === null || ! in_array($muscle, $exercise['primary'], true) || $set->performed_at < $olderFrom) {
                continue;
            }

            if ($set->performed_at >= $recentFrom) {
                $recent[$set->exercise] = max($recent[$set->exercise] ?? 0, $set->e1rm);
            } else {
                $older[$set->exercise] = max($older[$set->exercise] ?? 0, $set->e1rm);
            }
        }

        $common = array_intersect_key($recent, $older);

        return $common === [] ? null : $this->delta(array_sum($common), array_sum(array_intersect_key($older, $common)));
    }

    /**
     * @return array{title: string, left: string, right: string, left_sets: float, right_sets: float, ratio: float|null}
     */
    private function balance(string $title, string $left, string $right, float $leftSets, float $rightSets): array
    {
        return [
            'title' => $title,
            'left' => $left,
            'right' => $right,
            'left_sets' => round($leftSets, 1),
            'right_sets' => round($rightSets, 1),
            'ratio' => $rightSets > 0 ? round($leftSets / $rightSets, 2) : null,
        ];
    }

    /**
     * Muscles majeurs sans aucune série principale depuis 14 jours, du plus
     * anciennement travaillé au plus récent.
     *
     * @return list<array{muscle: string, label: string, ago: string|null}>
     */
    private function neglected(): array
    {
        if ($this->sets()->isEmpty()) {
            return [];
        }

        $lastWorked = [];

        foreach ($this->sets()->whereNull('drop') as $set) {
            foreach (ExerciseCatalog::find($set->exercise)['primary'] ?? [] as $muscle) {
                $lastWorked[$muscle] = $set->performed_at;
            }
        }

        $since = $this->now->subDays(14);

        return collect(self::MAJOR_MUSCLES)
            ->filter(fn (string $m): bool => ! isset($lastWorked[$m]) || $lastWorked[$m] < $since)
            ->sortBy(fn (string $m): int => isset($lastWorked[$m]) ? $lastWorked[$m]->timestamp : 0)
            ->take(4)
            ->map(fn (string $m): array => [
                'muscle' => $m,
                'label' => Muscle::from($m)->label(),
                'ago' => isset($lastWorked[$m]) ? $lastWorked[$m]->diffForHumans() : null,
            ])
            ->values()
            ->all();
    }

    private function tonnageBetween(CarbonImmutable $from, CarbonImmutable $to): float
    {
        return round($this->sets()
            ->filter(fn (SetLog $s): bool => $s->performed_at >= $from && $s->performed_at <= $to)
            ->sum('volume'));
    }

    /** Évolution en pourcentage, ou rien si la base de comparaison manque. */
    private function delta(float|int|null $now, float|int|null $before): ?float
    {
        if (! $before || $now === null) {
            return null;
        }

        return round(($now - $before) / $before * 100, 1);
    }
}
