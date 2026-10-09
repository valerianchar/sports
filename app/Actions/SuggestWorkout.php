<?php

namespace App\Actions;

use App\Enums\EquipmentKind;
use App\Enums\ExerciseMode;
use App\Enums\Muscle;
use App\Enums\MuscleGroup;
use App\Enums\WorkoutGoal;
use App\Support\ExerciseCatalog;
use App\Support\MachineSettings;
use App\Support\MuscleZones;
use App\Support\Stretches;
use App\Support\WorkoutEstimate;
use Illuminate\Support\Collection;
use Random\Engine\Mt19937;
use Random\Randomizer;

/**
 * L'assistant : compose une séance à partir des muscles qu'on veut travailler,
 * du temps dont on dispose, d'un objectif et du matériel qu'on veut utiliser.
 *
 * Le choix est glouton : à chaque tour, l'exercice qui sert le mieux les
 * muscles visés encore peu travaillés l'emporte — un muscle déjà couvert pèse
 * moins, si bien que la séance se répartit d'elle-même. On s'arrête quand la
 * séance remplit le temps demandé, puis on ajuste le nombre de séries pour
 * coller au chrono. Une petite part de hasard, réglée par `variant`, donne une
 * autre proposition à chaque fois qu'on la demande — et la même pour la même
 * variante.
 *
 * Pour perdre du poids, la séance prend aussi les mouvements fonctionnels,
 * préfère ceux qui mettent beaucoup de muscles en jeu, et garde la fin pour un
 * bloc de fractionné (voir SuggestCardio).
 */
final class SuggestWorkout
{
    /** Groupes jamais pris pour le corps de séance : ils ont leurs options. */
    private const EXCLUDED_GROUPS = [MuscleGroup::Cardio, MuscleGroup::Mobilite, MuscleGroup::Fonctionnel];

    private const WARMUP_EXERCISES = ['velo', 'rameur', 'elliptique', 'marche-inclinee'];

    private const MAX_SETS = 5;

    /** Sans muscles choisis, une séance de perte de poids fait travailler tout le corps. */
    public const FULL_BODY = ['chest', 'upper-back', 'front-deltoids', 'quadriceps', 'hamstring', 'gluteal', 'abs'];

    /**
     * Les classiques d'une salle : la base de la première version du catalogue
     * et les grandes machines. Une séance proposée doit d'abord ressembler à
     * ce qu'un coach écrirait ; les variantes rares viennent en complément.
     */
    private const STAPLES = [
        'developpe-couche', 'developpe-incline-halteres', 'developpe-couche-smith', 'chest-press', 'butterfly',
        'ecarte-vis-a-vis', 'ecarte-halteres', 'dips-pectoraux', 'pompes', 'presse-pectorale-convergente',
        'developpe-incline-machine', 'tirage-vertical-prise-large', 'tirage-vertical-prise-serree', 'rowing-assis',
        't-bar-row', 'rowing-barre', 'rowing-haltere-un-bras', 'tractions', 'tractions-assistees', 'pull-over-poulie',
        'souleve-de-terre', 'extensions-lombaires', 'low-row-iso-lateral', 'presse-a-mollets', 'developpe-epaules-machine',
        'developpe-militaire', 'developpe-arnold', 'elevations-laterales', 'elevations-laterales-poulie',
        'oiseau-inverse', 'face-pull', 'elevations-frontales', 'shrugs', 'curl-barre', 'curl-halteres', 'curl-marteau',
        'curl-poulie', 'curl-pupitre', 'extension-triceps-poulie', 'barre-au-front', 'dips-triceps',
        'extension-nuque-haltere', 'kickback-triceps', 'presse-a-cuisses', 'hack-squat', 'squat', 'squat-smith',
        'leg-extension', 'leg-curl', 'leg-curl-assis', 'fentes-marchees', 'squat-bulgare', 'goblet-squat',
        'souleve-de-terre-roumain', 'mollets-debout', 'mollets-assis', 'adducteurs', 'hip-thrust', 'hip-thrust-barre',
        'kickback-machine', 'abducteurs', 'pont-fessier', 'kickback-poulie', 'crunch', 'crunch-poulie-haute',
        'releves-de-jambes-suspendu', 'releves-de-genoux', 'gainage-planche', 'gainage-lateral', 'russian-twist',
        'roue-abdominale', 'pallof-press', 'crunch-machine', 'rotation-du-buste-machine',
    ];

    /** Les grands groupes : leurs exercices ouvrent la séance, avant l'isolation. */
    private const BIG_MUSCLES = ['chest', 'upper-back', 'quadriceps', 'hamstring', 'gluteal'];

    /** Avancés ou très techniques : à proposer seulement en dernier recours. */
    private const ADVANCED = [
        'handstand-push-up', 'pistol-squat', 'sissy-squat', 'nordic-curl', 'glute-ham-raise', 'l-sit', 'dragon-flag',
        'turkish-get-up', 'windmill-kettlebell', 'dips-anneaux', 'planche-commando', 'reverse-hyper',
    ];

    /** Accessoires d'appoint, peu indiqués pour la charge principale d'une séance en salle. */
    private const ACCESSORY_EQUIPMENT = ['trx', 'bands', 'swiss-ball', 'rings', 'mat'];

    private Randomizer $random;

    /** @var list<string> les zones des muscles visés (pectoraux haut, bas…) que la séance devrait toucher */
    private array $targetZones = [];

    private WorkoutGoal $goal = WorkoutGoal::Hypertrophy;

    public function __construct(private readonly SuggestCardio $cardio) {}

    /** @var array{reps?: int, sets?: int, rest_sets?: int, rest_after?: int} réglages choisis à la place de ceux de l'objectif */
    private array $settings = [];

    /**
     * @param  list<Muscle>  $muscles
     * @param  array{reps?: int|null, rest_sets?: int|null, rest_after?: int|null}  $settings  répétitions et repos
     *                                                                                         voulus, à la place de ceux de l'objectif
     * @return array{name: string, items: list<array<string, mixed>>, seconds: int}
     */
    public function handle(
        array $muscles,
        int $minutes,
        WorkoutGoal $goal,
        ?EquipmentKind $equipment = null,
        bool $warmup = false,
        bool $stretch = false,
        int $variant = 0,
        array $settings = [],
    ): array {
        $this->random = new Randomizer(new Mt19937($variant));
        $this->goal = $goal;
        $this->settings = array_filter($settings, fn ($value): bool => $value !== null);
        $targets = array_map(fn (Muscle $muscle): string => $muscle->value, $muscles);
        $chosenMuscles = $targets !== [];
        $targets = $chosenMuscles || $goal !== WorkoutGoal::WeightLoss ? $targets : self::FULL_BODY;
        $budget = $minutes * 60;

        $opening = $warmup ? [$this->warmup()] : [];
        $closing = $stretch ? Stretches::pick($targets, $this->random) : [];
        $finisher = $goal === WorkoutGoal::WeightLoss ? [$this->cardio->finisher($this->finisherSeconds($minutes), $equipment, $this->random, array_column($opening, 'exercise'))] : [];
        $reserved = WorkoutEstimate::seconds([...$opening, ...$finisher, ...$closing]) + ($finisher === [] ? 0 : $finisher[0]['rest_after']);

        $main = $this->pick($targets, $budget - $reserved, $goal, $equipment);
        $main = $this->fill($main, $budget - $reserved, $goal);
        $items = [...$opening, ...$this->order($main), ...$finisher, ...$closing];

        return [
            'name' => $this->name($chosenMuscles ? $targets : [], $minutes),
            'items' => array_map(fn (array $item): array => array_diff_key($item, ['_score' => true]), $items),
            'seconds' => WorkoutEstimate::seconds($items),
        ];
    }

    /** Un classique de salle, que l'assistant propose en premier. */
    public static function isStaple(string $slug): bool
    {
        return in_array($slug, self::STAPLES, true);
    }

    /** Avancé ou très technique : en dernier recours. */
    public static function isAdvanced(string $slug): bool
    {
        return in_array($slug, self::ADVANCED, true);
    }

    /**
     * Complète une séance commencée à la main : de nouveaux exercices pour
     * `minutes` de plus, qui travaillent les muscles demandés — à défaut ceux
     * que la séance travaille déjà — en tenant compte de ce qu'elle contient
     * (muscles déjà servis, variantes déjà présentes). Les réglages suivent
     * ceux de la séance : mêmes répétitions, séries et repos.
     *
     * @param  list<array<string, mixed>>  $existing  les exercices déjà dans la séance
     * @param  list<Muscle>  $muscles
     * @return array{items: list<array<string, mixed>>, muscles: list<string>, seconds: int}
     */
    public function complete(array $existing, array $muscles, int $minutes, ?EquipmentKind $equipment = null, int $variant = 0): array
    {
        $this->random = new Randomizer(new Mt19937($variant));
        $strength = array_values(array_filter(
            $existing,
            fn (array $item): bool => ExerciseCatalog::find($item['exercise']) !== null
                && ! in_array(ExerciseCatalog::group($item['exercise']), self::EXCLUDED_GROUPS, true),
        ));
        [$this->goal, $this->settings] = $this->inferred($strength);

        $targets = array_map(fn (Muscle $muscle): string => $muscle->value, $muscles);

        if ($targets === []) {
            $worked = array_merge(...array_map(fn (array $item): array => ExerciseCatalog::find($item['exercise'])['primary'], $strength));
            $targets = $worked === [] ? self::FULL_BODY : array_values(array_unique($worked));
        }

        $budget = $minutes * 60;
        $items = $this->order($this->fill($this->pick($targets, $budget, $this->goal, $equipment, $strength), $budget, $this->goal));

        return [
            'items' => $items,
            'muscles' => $targets,
            'seconds' => WorkoutEstimate::seconds($items),
        ];
    }

    /**
     * L'objectif et les réglages d'une séance d'après ses exercices : les
     * répétitions les plus fréquentes disent la force (6 et moins), l'endurance
     * (13 et plus) ou le volume.
     *
     * @param  list<array<string, mixed>>  $items
     * @return array{WorkoutGoal, array{reps?: int, sets?: int, rest_sets?: int, rest_after?: int}}
     */
    private function inferred(array $items): array
    {
        $repsItems = array_values(array_filter($items, fn (array $item): bool => $item['mode'] === ExerciseMode::Reps->value));

        if ($repsItems === []) {
            return [WorkoutGoal::Hypertrophy, []];
        }

        $common = function (string $key) use ($repsItems): int {
            $counts = array_count_values(array_map(fn (array $item): int => (int) $item[$key], $repsItems));
            arsort($counts);

            return (int) array_key_first($counts);
        };

        $reps = $common('value');
        $goal = match (true) {
            $reps <= 6 => WorkoutGoal::Strength,
            $reps >= 13 => WorkoutGoal::Endurance,
            default => WorkoutGoal::Hypertrophy,
        };

        return [$goal, ['reps' => $reps, 'sets' => $common('sets'), 'rest_sets' => $common('rest_sets'), 'rest_after' => $common('rest_after')]];
    }

    /**
     * Les remplaçants d'un exercice, du meilleur au moins bon : ceux qui
     * travaillent les mêmes muscles principaux, avec le matériel choisi, les
     * classiques en premier. Un échauffement se remplace par un autre cardio,
     * un étirement par un autre étirement.
     *
     * @param  list<string>  $exclude  exercices déjà dans la séance
     * @return list<string>
     */
    public function alternatives(string $slug, ?EquipmentKind $equipment = null, array $exclude = [], int $limit = 8): array
    {
        $original = ExerciseCatalog::find($slug);
        $group = MuscleGroup::from($original['group']);
        // Un cardio se remplace par n'importe quel autre, pourvu qu'il se mesure pareil
        // (un effort de 30 s ne devient pas 30 burpees) ; ses machines comptent comme machines.
        $cardio = $group === MuscleGroup::Cardio;
        $equipment = $cardio && $equipment !== EquipmentKind::Bodyweight && $equipment !== null ? EquipmentKind::Conditioning : $equipment;
        $sameFamily = fn (array $exercise): bool => in_array($group, self::EXCLUDED_GROUPS, true)
            ? $exercise['group'] === $group->value
            : ! in_array(MuscleGroup::from($exercise['group']), self::EXCLUDED_GROUPS, true);

        $candidates = ExerciseCatalog::all()
            ->except([$slug, ...$exclude])
            ->filter($sameFamily)
            ->filter(fn (array $exercise): bool => $cardio
                ? $exercise['mode'] === $original['mode']
                : array_intersect($exercise['primary'], $original['primary']) !== []);

        if ($equipment !== null) {
            $kept = $candidates->filter(fn (array $exercise): bool => ExerciseCatalog::equipment($exercise['slug'])->kind() === $equipment);
            // Trop peu de choix dans ce matériel : on ouvre plutôt que de laisser l'exercice sans remplaçant.
            $candidates = $kept->count() >= 3 ? $kept : $candidates;
        }

        return $candidates
            ->sortByDesc(fn (array $exercise): float => $this->likeness($exercise, $original, $equipment))
            ->keys()
            ->take($limit)
            ->values()
            ->all();
    }

    /**
     * À quel point un exercice peut en remplacer un autre.
     */
    private function likeness(array $exercise, array $original, ?EquipmentKind $equipment): float
    {
        $score = 2 * count(array_intersect($exercise['primary'], $original['primary'])) / count($original['primary'])
            - 0.5 * count(array_diff($exercise['primary'], $original['primary']))
            + 0.3 * count(array_intersect($exercise['secondary'], $original['secondary']))
            // Des muscles secondaires en plus trahissent un autre geste : une presse ne remplace pas un écarté.
            - 0.3 * count(array_diff($exercise['secondary'], $original['secondary'], $original['primary']));

        if (in_array($exercise['slug'], self::STAPLES, true)) {
            $score += 0.4;
        }

        if (in_array($exercise['slug'], self::ADVANCED, true)) {
            $score -= 0.7;
        }

        if ($equipment !== EquipmentKind::Bodyweight && in_array($exercise['equipment'], self::ACCESSORY_EQUIPMENT, true)) {
            $score -= 0.5;
        }

        // Une autre machine pour une machine, une barre pour une barre : on garde l'esprit de la séance.
        if (ExerciseCatalog::equipment($exercise['slug'])->kind() === ExerciseCatalog::equipment($original['slug'])->kind()) {
            $score += 0.2;
        }

        return $score;
    }

    /**
     * @param  list<string>  $targets
     * @param  list<array<string, mixed>>  $existing  exercices déjà dans la séance, qu'on complète
     * @return list<array<string, mixed>>
     */
    private function pick(array $targets, int $budget, WorkoutGoal $goal, ?EquipmentKind $equipment, array $existing = []): array
    {
        $pool = $this->pool($targets, $equipment)->except(array_column($existing, 'exercise'));
        $this->targetZones = MuscleZones::zonesOfMuscles($targets);
        $coverage = array_fill_keys($targets, 0.0);
        // Muscles visés qui ont déjà un exercice où ils sont principaux.
        $served = [];
        $chosen = [];

        // Une séance qu'on complète : ses exercices comptent déjà.
        foreach ($existing as $item) {
            $exercise = ExerciseCatalog::find($item['exercise']);

            foreach ($exercise['primary'] as $muscle) {
                if (isset($coverage[$muscle])) {
                    $coverage[$muscle] += $item['sets'];
                    $served[] = $muscle;
                }
            }

            foreach ($exercise['secondary'] as $muscle) {
                if (isset($coverage[$muscle])) {
                    $coverage[$muscle] += $item['sets'] / 2;
                }
            }
        }

        // Mieux vaut quatre séries de six bons exercices que trois de dix : autant
        // d'exercices que le temps en loge avec les répétitions et les repos choisis
        // — moins de repos, plus d'exercices —, jamais moins que de muscles visés.
        $one = $this->prescribe($goal, ExerciseMode::Reps);
        $perExercise = $one['sets'] * $one['value'] * (float) config('sport.seconds_per_rep')
            + ($one['sets'] - 1) * $one['rest_sets'] + $one['rest_after'];
        // Séries fixées : seul le nombre d'exercices peut remplir le temps, d'où un plafond plus haut.
        $ceiling = isset($this->settings['sets']) ? 16 : 12;
        $limit = $existing === []
            ? max(2, min($ceiling, max(count($targets), (int) round($budget / max(60, $perExercise)))))
            // Compléter : autant que le temps ajouté en loge, au moins un.
            : max(1, min($ceiling, (int) round($budget / max(60, $perExercise))));
        $minimum = $existing === [] ? 2 : 1;

        while ($pool->isNotEmpty() && count($chosen) < $limit) {
            // Plus que les places qu'il faut pour les muscles qui attendent encore : on les sert d'abord.
            $waiting = array_diff($targets, $served);
            $candidates = count($waiting) >= $limit - count($chosen)
                ? $pool->filter(fn (array $exercise): bool => array_intersect($exercise['primary'], $waiting) !== [])
                : $pool;
            $candidates = $candidates->isEmpty() ? $pool : $candidates;

            $best = $candidates
                ->map(fn (array $exercise): array => [$exercise, $this->score($exercise, $targets, $coverage, [...$existing, ...$chosen], $equipment)])
                // Séries fixées : seul le nombre d'exercices remplit le temps, on accepte
                // alors les variantes moins bien classées plutôt qu'une séance trop courte.
                ->filter(fn (array $pair): bool => isset($this->settings['sets']) ? $pair[1] > -INF : $pair[1] > 0)
                ->sortByDesc(fn (array $pair): float => $pair[1])
                ->first();

            if ($best === null) {
                break;
            }

            [$exercise] = $best;
            $item = $this->item($exercise, $goal);
            $candidate = [...$chosen, $item];
            $uncovered = array_diff($targets, $served) !== [];

            // Plus de temps pour tout : tant qu'un muscle visé attend son exercice,
            // on rabote les séries (jamais sous deux) plutôt que de l'oublier.
            while ($uncovered && ! isset($this->settings['sets']) && WorkoutEstimate::seconds($candidate) > $budget * 1.05) {
                $index = collect($candidate)->keys()->sortByDesc(fn (int $i): int => $candidate[$i]['sets'])->first();

                if ($candidate[$index]['sets'] <= 2) {
                    break;
                }

                $candidate[$index]['sets']--;
            }

            // Au moins deux exercices, même pour une séance éclair (un pour compléter).
            if (count($chosen) >= $minimum && WorkoutEstimate::seconds($candidate) > $budget * 1.05) {
                break;
            }

            $chosen = $candidate;
            $item = end($chosen);
            $pool = $pool->except($exercise['slug']);

            foreach ($exercise['primary'] as $muscle) {
                if (isset($coverage[$muscle])) {
                    $coverage[$muscle] += $item['sets'];
                    $served[] = $muscle;
                }
            }

            foreach ($exercise['secondary'] as $muscle) {
                if (isset($coverage[$muscle])) {
                    $coverage[$muscle] += $item['sets'] / 2;
                }
            }
        }

        return $chosen;
    }

    /**
     * Les exercices utilisables : hors cardio et mobilité, avec le matériel
     * choisi — à ceci près qu'un muscle visé sans aucun exercice dans ce
     * matériel (les abdos « aux machines », par exemple) reçoit les exercices au
     * poids du corps, plutôt que d'être oublié.
     *
     * @param  list<string>  $targets
     * @return Collection<string, array<string, mixed>>
     */
    private function pool(array $targets, ?EquipmentKind $equipment): Collection
    {
        // Pour perdre du poids, les mouvements fonctionnels (thruster, wall ball…) sont les bienvenus.
        $excluded = $this->goal === WorkoutGoal::WeightLoss ? [MuscleGroup::Cardio, MuscleGroup::Mobilite] : self::EXCLUDED_GROUPS;
        $usable = ExerciseCatalog::all()
            ->reject(fn (array $exercise): bool => in_array(MuscleGroup::from($exercise['group']), $excluded, true))
            ->filter(fn (array $exercise): bool => array_intersect($exercise['primary'], $targets) !== []);

        if ($equipment === null) {
            return $usable;
        }

        $kind = fn (array $exercise): EquipmentKind => ExerciseCatalog::equipment($exercise['slug'])->kind();
        $pool = $usable->filter(fn (array $exercise): bool => $kind($exercise) === $equipment);

        foreach ($targets as $muscle) {
            $served = $pool->contains(fn (array $exercise): bool => in_array($muscle, $exercise['primary'], true));

            if (! $served) {
                $pool = $pool->merge($usable->filter(fn (array $exercise): bool => in_array($muscle, $exercise['primary'], true)
                    && $kind($exercise) === EquipmentKind::Bodyweight));
            }
        }

        return $pool;
    }

    /**
     * @param  list<string>  $targets
     * @param  array<string, float>  $coverage  séries déjà données à chaque muscle visé
     * @param  list<array<string, mixed>>  $chosen
     */
    private function score(array $exercise, array $targets, array $coverage, array $chosen, ?EquipmentKind $equipment): float
    {
        $gain = 0.0;

        foreach ($exercise['primary'] as $muscle) {
            if (in_array($muscle, $targets, true)) {
                // Un muscle visé qui n'a encore aucun exercice à lui passe devant.
                $gain += 1 / (1 + $coverage[$muscle] / 3) + ($this->served($muscle, $chosen) ? 0 : 0.5);
            }
        }

        foreach ($exercise['secondary'] as $muscle) {
            if (in_array($muscle, $targets, true)) {
                $gain += 0.3 / (1 + $coverage[$muscle] / 3);
            }
        }

        // Hors des muscles visés : jamais candidat.
        if ($gain <= 0) {
            return -INF;
        }

        // Un exercice qui fait surtout travailler ailleurs n'est pas le bienvenu.
        $gain -= 0.35 * count(array_diff($exercise['primary'], $targets));

        if (in_array($exercise['slug'], self::STAPLES, true)) {
            $gain += 0.45;
        }

        if (in_array($exercise['slug'], self::ADVANCED, true)) {
            $gain -= 0.7;
        }

        // Pour perdre du poids, un mouvement qui met tout le corps en jeu dépense davantage.
        if ($this->goal === WorkoutGoal::WeightLoss) {
            $gain += 0.04 * min(6, $this->regions($exercise)) + ($exercise['group'] === MuscleGroup::Fonctionnel->value ? 0.15 : 0);
        }

        // Au poids du corps, les accessoires sont justement ce qu'on cherche.
        if ($equipment !== EquipmentKind::Bodyweight && in_array($exercise['equipment'], self::ACCESSORY_EQUIPMENT, true)) {
            $gain -= 0.5;
        }

        // Une zone du muscle encore jamais touchée (le haut des pectoraux après un
        // développé couché) : un angle de plus vaut mieux qu'une variante du même.
        $covered = array_merge(...array_map(fn (array $item): array => ExerciseCatalog::find($item['exercise'])['zones'] ?? [], $chosen));
        $fresh = array_diff(array_intersect($exercise['zones'] ?? [], $this->targetZones), $covered);
        $gain += 0.3 * min(2, count($fresh));

        $twin = 0.0;
        $twins = 0;

        foreach ($chosen as $item) {
            $other = ExerciseCatalog::find($item['exercise']);

            // Deux variantes du même geste (mêmes muscles principaux) : on s'en
            // méfie, a fortiori sur le même matériel. Avec des repos courts, la
            // séance a de la place pour deux ou trois exercices d'un même muscle,
            // pourvu qu'ils diffèrent — la méfiance grandit doucement avec leur nombre.
            if ($other['primary'] == $exercise['primary']) {
                $twin = max($twin, $other['equipment'] === $exercise['equipment'] ? 1.5 : 0.8);
                $twins++;
            } elseif ($other['equipment'] === $exercise['equipment']) {
                $gain -= 0.25;
            }
        }

        $gain -= $twin + 0.4 * max(0, $twins - 1);

        return $gain + $this->random->getFloat(0, 0.35);
    }

    /**
     * Les réglages d'un exercice : ceux de l'objectif, remplacés par ceux qu'on
     * a choisis — les répétitions ne concernent que les exercices comptés en
     * répétitions, un gainage garde sa durée.
     *
     * @param  array{reps?: int, sets?: int, rest_sets?: int, rest_after?: int}|null  $settings
     * @return array{value: int, sets: int, rest_sets: int, rest_after: int}
     */
    public function prescribe(WorkoutGoal $goal, ExerciseMode $mode, ?array $settings = null): array
    {
        $settings ??= $this->settings;
        $prescription = $goal->prescription($mode);

        if ($mode === ExerciseMode::Reps && isset($settings['reps'])) {
            $prescription['value'] = $settings['reps'];
        }

        return [
            ...$prescription,
            ...array_intersect_key($settings, ['sets' => true, 'rest_sets' => true, 'rest_after' => true]),
        ];
    }

    /**
     * @param  list<array<string, mixed>>  $chosen
     */
    private function served(string $muscle, array $chosen): bool
    {
        foreach ($chosen as $item) {
            if (in_array($muscle, ExerciseCatalog::find($item['exercise'])['primary'], true)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Nombre de régions du corps qu'un exercice met en jeu — les deux faisceaux
     * de l'épaule n'en font qu'une : une élévation latérale reste de l'isolation.
     */
    private function regions(array $exercise): int
    {
        $muscles = [...$exercise['primary'], ...$exercise['secondary']];

        return count(array_unique(array_map(fn (string $muscle): string => str_ends_with($muscle, 'deltoids') ? 'deltoids' : $muscle, $muscles)));
    }

    /**
     * @return array<string, mixed>
     */
    private function item(array $exercise, WorkoutGoal $goal): array
    {
        $mode = ExerciseMode::from($exercise['mode']);

        return [
            'exercise' => $exercise['slug'],
            'mode' => $mode->value,
            'per_side' => ($exercise['sides'] ?? 'both') === 'both' ? null : true,
            ...$this->prescribe($goal, $mode),
        ];
    }

    /**
     * Rapproche la séance du temps demandé en ajoutant des séries, à tour de
     * rôle en commençant par le haut de la liste, ou en en retirant à la fin.
     *
     * @param  list<array<string, mixed>>  $items
     * @return list<array<string, mixed>>
     */
    private function fill(array $items, int $budget, WorkoutGoal $goal): array
    {
        // Séries fixées : on ne touche à rien, le nombre d'exercices a déjà fait le travail.
        if ($items === [] || isset($this->settings['sets'])) {
            return $items;
        }

        $grown = true;

        while ($grown && WorkoutEstimate::seconds($items) < $budget * 0.92) {
            $grown = false;

            foreach ($items as $index => $item) {
                if ($item['sets'] >= self::MAX_SETS) {
                    continue;
                }

                $items[$index]['sets']++;

                if (WorkoutEstimate::seconds($items) > $budget * 1.05) {
                    $items[$index]['sets']--;

                    continue;
                }

                $grown = true;

                if (WorkoutEstimate::seconds($items) >= $budget * 0.92) {
                    break;
                }
            }
        }

        for ($index = count($items) - 1; $index >= 0 && WorkoutEstimate::seconds($items) > $budget * 1.1; $index--) {
            $items[$index]['sets'] = max(2, $items[$index]['sets'] - 1);
        }

        return $items;
    }

    /**
     * Les grands groupes d'abord, tant qu'on est frais — et parmi eux les
     * classiques, puis la barre et les haltères avant les machines ; épaules et
     * bras ensuite ; les abdos et le gainage en dernier, pour ne pas fatiguer la
     * ceinture avant un squat.
     *
     * @param  list<array<string, mixed>>  $items
     * @return list<array<string, mixed>>
     */
    private function order(array $items): array
    {
        $kinds = [EquipmentKind::Free->value => 0, EquipmentKind::Machine->value => 1, EquipmentKind::Bodyweight->value => 2];

        $rank = function (array $item) use ($kinds): array {
            $exercise = ExerciseCatalog::find($item['exercise']);
            $core = $exercise['group'] === MuscleGroup::Abdos->value;
            $regions = $this->regions($exercise);
            $kind = $kinds[ExerciseCatalog::equipment($exercise['slug'])->kind()->value] ?? 3;

            $staple = in_array($exercise['slug'], self::STAPLES, true);
            $big = array_intersect($exercise['primary'], self::BIG_MUSCLES) !== [];

            return [$core, ! $big, ! $staple, $kind, -$regions];
        };

        usort($items, fn (array $a, array $b): int => $rank($a) <=> $rank($b));

        return $items;
    }

    /**
     * La part du finisher : un Tabata pour une séance éclair, dix minutes au plus.
     */
    private function finisherSeconds(int $minutes): int
    {
        return match (true) {
            $minutes < 25 => 240,
            $minutes < 40 => 480,
            default => 600,
        };
    }

    /**
     * @return array<string, mixed>
     */
    private function warmup(): array
    {
        $slug = self::WARMUP_EXERCISES[$this->random->getInt(0, count(self::WARMUP_EXERCISES) - 1)];

        return ['exercise' => $slug, 'mode' => 'time', 'value' => 300, 'sets' => 1, 'rest_sets' => 0, 'rest_after' => 60, ...MachineSettings::defaults($slug, 'easy')];
    }

    /**
     * « Dos · Bras — 45 min » : les groupes de la bibliothèque, dans l'ordre des
     * muscles choisis ; « Perte de poids · Full body — 45 min » sans muscles.
     *
     * @param  list<string>  $targets
     */
    private function name(array $targets, int $minutes): string
    {
        $groups = array_values(array_unique(array_map(fn (string $muscle): string => match ($muscle) {
            'chest' => MuscleGroup::Pectoraux->label(),
            'front-deltoids', 'rear-deltoids' => MuscleGroup::Epaules->label(),
            'upper-back', 'trapezius', 'lower-back' => MuscleGroup::Dos->label(),
            'biceps', 'triceps', 'forearm' => MuscleGroup::Bras->label(),
            'abs', 'obliques' => MuscleGroup::Abdos->label(),
            'gluteal' => MuscleGroup::Fessiers->label(),
            default => MuscleGroup::Jambes->label(),
        }, $targets)));

        $title = count($groups) > 3 ? implode(' · ', array_slice($groups, 0, 3)).'…' : implode(' · ', $groups);

        if ($this->goal === WorkoutGoal::WeightLoss) {
            $title = 'Perte de poids · '.($groups === [] ? 'Full body' : $title);
        }

        return "{$title} — {$minutes} min";
    }
}
