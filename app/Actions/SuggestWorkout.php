<?php

namespace App\Actions;

use App\Enums\EquipmentKind;
use App\Enums\ExerciseMode;
use App\Enums\Muscle;
use App\Enums\MuscleGroup;
use App\Enums\WorkoutGoal;
use App\Support\ExerciseCatalog;
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
 */
final class SuggestWorkout
{
    /** Groupes jamais pris pour le corps de séance : ils ont leurs options. */
    private const EXCLUDED_GROUPS = [MuscleGroup::Cardio, MuscleGroup::Mobilite, MuscleGroup::Fonctionnel];

    private const WARMUP_EXERCISES = ['velo', 'rameur', 'elliptique', 'marche-inclinee'];

    private const MAX_SETS = 5;

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
        'souleve-de-terre', 'extensions-lombaires', 'low-row-iso-lateral', 'developpe-epaules-machine',
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
        'turkish-get-up', 'windmill-kettlebell', 'dips-anneaux', 'planche-commando',
    ];

    /** Accessoires d'appoint, peu indiqués pour la charge principale d'une séance en salle. */
    private const ACCESSORY_EQUIPMENT = ['trx', 'bands', 'swiss-ball', 'rings', 'mat'];

    private Randomizer $random;

    /**
     * @param  list<Muscle>  $muscles
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
    ): array {
        $this->random = new Randomizer(new Mt19937($variant));
        $targets = array_map(fn (Muscle $muscle): string => $muscle->value, $muscles);
        $budget = $minutes * 60;

        $opening = $warmup ? [$this->warmup()] : [];
        $closing = $stretch ? $this->stretches($targets) : [];
        $reserved = WorkoutEstimate::seconds([...$opening, ...$closing]);

        $main = $this->pick($targets, $budget - $reserved, $goal, $equipment);
        $main = $this->fill($main, $budget - $reserved, $goal);
        $items = [...$opening, ...$this->order($main), ...$closing];

        return [
            'name' => $this->name($targets, $minutes),
            'items' => array_map(fn (array $item): array => array_diff_key($item, ['_score' => true]), $items),
            'seconds' => WorkoutEstimate::seconds($items),
        ];
    }

    /**
     * @param  list<string>  $targets
     * @return list<array<string, mixed>>
     */
    private function pick(array $targets, int $budget, WorkoutGoal $goal, ?EquipmentKind $equipment): array
    {
        $pool = $this->pool($targets, $equipment);
        $coverage = array_fill_keys($targets, 0.0);
        // Muscles visés qui ont déjà un exercice où ils sont principaux.
        $served = [];
        $chosen = [];

        // Mieux vaut quatre séries de six bons exercices que trois de dix : environ
        // un exercice par tranche de sept minutes, et jamais moins que de muscles visés.
        $limit = max(2, min(10, max(count($targets), (int) round($budget / 420))));

        while ($pool->isNotEmpty() && count($chosen) < $limit) {
            $best = $pool
                ->map(fn (array $exercise): array => [$exercise, $this->score($exercise, $targets, $coverage, $chosen, $equipment)])
                ->filter(fn (array $pair): bool => $pair[1] > 0)
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
            while ($uncovered && WorkoutEstimate::seconds($candidate) > $budget * 1.05) {
                $index = collect($candidate)->keys()->sortByDesc(fn (int $i): int => $candidate[$i]['sets'])->first();

                if ($candidate[$index]['sets'] <= 2) {
                    break;
                }

                $candidate[$index]['sets']--;
            }

            // Au moins deux exercices, même pour une séance éclair.
            if (count($chosen) >= 2 && WorkoutEstimate::seconds($candidate) > $budget * 1.05) {
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
        $usable = ExerciseCatalog::all()
            ->reject(fn (array $exercise): bool => in_array(MuscleGroup::from($exercise['group']), self::EXCLUDED_GROUPS, true))
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

        if ($gain <= 0) {
            return 0;
        }

        // Un exercice qui fait surtout travailler ailleurs n'est pas le bienvenu.
        $gain -= 0.35 * count(array_diff($exercise['primary'], $targets));

        if (in_array($exercise['slug'], self::STAPLES, true)) {
            $gain += 0.45;
        }

        if (in_array($exercise['slug'], self::ADVANCED, true)) {
            $gain -= 0.7;
        }

        // Au poids du corps, les accessoires sont justement ce qu'on cherche.
        if ($equipment !== EquipmentKind::Bodyweight && in_array($exercise['equipment'], self::ACCESSORY_EQUIPMENT, true)) {
            $gain -= 0.5;
        }

        foreach ($chosen as $item) {
            $other = ExerciseCatalog::find($item['exercise']);

            // Deux variantes du même geste (mêmes muscles principaux) : une suffit,
            // a fortiori sur le même matériel.
            if ($other['primary'] == $exercise['primary']) {
                $gain -= $other['equipment'] === $exercise['equipment'] ? 1.5 : 0.8;
            } elseif ($other['equipment'] === $exercise['equipment']) {
                $gain -= 0.25;
            }
        }

        return $gain + $this->random->getFloat(0, 0.35);
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

        return ['exercise' => $exercise['slug'], 'mode' => $mode->value, ...$goal->prescription($mode)];
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
        if ($items === []) {
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
     * @return array<string, mixed>
     */
    private function warmup(): array
    {
        $slug = self::WARMUP_EXERCISES[$this->random->getInt(0, count(self::WARMUP_EXERCISES) - 1)];

        return ['exercise' => $slug, 'mode' => 'time', 'value' => 300, 'sets' => 1, 'rest_sets' => 0, 'rest_after' => 60];
    }

    /**
     * Deux étirements des muscles travaillés, tenus 30 secondes, deux fois.
     *
     * @param  list<string>  $targets
     * @return list<array<string, mixed>>
     */
    private function stretches(array $targets): array
    {
        return ExerciseCatalog::all()
            ->filter(fn (array $exercise): bool => $exercise['group'] === MuscleGroup::Mobilite->value
                && str_starts_with($exercise['slug'], 'etirement-')
                && array_intersect($exercise['primary'], $targets) !== [])
            ->sortByDesc(fn (array $exercise): float => count(array_intersect($exercise['primary'], $targets)) + $this->random->getFloat(0, 0.5))
            ->take(2)
            ->map(fn (array $exercise): array => ['exercise' => $exercise['slug'], 'mode' => 'time', 'value' => 30, 'sets' => 2, 'rest_sets' => 10, 'rest_after' => 15])
            ->values()
            ->all();
    }

    /**
     * « Dos · Bras — 45 min » : les groupes de la bibliothèque, dans l'ordre des
     * muscles choisis.
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

        return "{$title} — {$minutes} min";
    }
}
