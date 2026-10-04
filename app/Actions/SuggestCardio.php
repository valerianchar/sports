<?php

namespace App\Actions;

use App\Enums\CardioStyle;
use App\Enums\EquipmentKind;
use App\Support\Stretches;
use App\Support\WorkoutEstimate;
use Random\Engine\Mt19937;
use Random\Randomizer;

/**
 * L'assistant, côté cardio : une séance en blocs — un échauffement doux, du
 * continu à allure régulière ou du fractionné (efforts courts, récupération
 * entre deux), puis un retour au calme. Le fractionné s'écrit avec ce que le
 * lecteur sait déjà faire : une série par effort, le repos entre séries pour
 * la récupération. Ses blocs servent aussi de finisher aux séances de perte
 * de poids.
 */
final class SuggestCardio
{
    /** Pour s'échauffer et revenir au calme : des machines où l'on règle une allure tranquille. */
    private const GENTLE_MACHINES = ['velo', 'elliptique', 'marche-inclinee', 'rameur', 'velo-semi-allonge'];

    private const STEADY_MACHINES = [
        'course', 'tapis-curve', 'marche-inclinee', 'velo', 'velo-semi-allonge', 'velo-de-biking', 'elliptique',
        'stepper', 'escalier', 'rameur', 'skierg',
    ];

    private const INTERVAL_MACHINES = ['air-bike', 'rameur', 'skierg', 'sprints-fractionnes', 'velo-de-biking', 'escalier', 'tapis-curve', 'elliptique'];

    private const GENTLE_BODYWEIGHT = ['jumping-jacks', 'talons-fesses', 'shadow-boxing'];

    private const STEADY_BODYWEIGHT = ['corde-a-sauter', 'shadow-boxing', 'jumping-jacks', 'montees-de-genoux'];

    private const INTERVAL_BODYWEIGHT = ['burpees', 'montees-de-genoux', 'jumping-jacks', 'corde-a-sauter', 'talons-fesses', 'shadow-boxing', 'bear-crawl', 'burpee-broad-jump'];

    /** Sans consigne de matériel, le fractionné s'offre aussi la corde et les burpees. */
    private const INTERVAL_ANY = [...self::INTERVAL_MACHINES, 'corde-a-sauter', 'burpees'];

    /**
     * Les formats de fractionné : effort, récupération, en secondes. Le Tabata
     * pour les blocs courts, les efforts d'une ou deux minutes pour les longs.
     */
    private const PROTOCOLS = [[20, 10], [30, 30], [40, 20], [60, 60], [120, 60]];

    /** Pause entre deux blocs, le temps de changer de machine. */
    private const BETWEEN_BLOCKS = 90;

    /** Des burpees, huit séries au plus : au-delà, on ne les fait plus proprement. */
    private const MAX_REP_SETS = 8;

    /** Les muscles qu'étirent les retours au calme : le cardio, c'est d'abord les jambes. */
    private const STRETCHED = ['quadriceps', 'hamstring', 'calves', 'gluteal'];

    private Randomizer $random;

    /**
     * @return array{name: string, items: list<array<string, mixed>>, seconds: int}
     */
    public function handle(int $minutes, CardioStyle $style, ?EquipmentKind $equipment = null, bool $stretch = false, int $variant = 0): array
    {
        $this->random = new Randomizer(new Mt19937($variant));
        $bodyweight = $equipment === EquipmentKind::Bodyweight;
        $budget = $minutes * 60;
        $ease = $minutes <= 20 ? 180 : 300;

        $warmup = $this->easy($bodyweight ? self::GENTLE_BODYWEIGHT : self::GENTLE_MACHINES, $ease);
        // Au poids du corps, pas de machine où trottiner : les étirements font le retour au calme.
        $cooldown = $bodyweight ? [] : [$this->easy(array_diff(self::GENTLE_MACHINES, [$warmup['exercise']]), $ease)];
        $closing = $stretch ? Stretches::pick(self::STRETCHED, $this->random) : [];

        $middle = $budget - WorkoutEstimate::seconds([$warmup, ...$cooldown, ...$closing]) - self::BETWEEN_BLOCKS * (1 + count($cooldown));
        $used = [$warmup['exercise'], ...array_column($cooldown, 'exercise')];

        $blocks = match ($style) {
            CardioStyle::Steady => $this->steady($middle, $equipment, $used),
            CardioStyle::Intervals => $this->intervals($middle, $equipment, $used),
            CardioStyle::Mixed => $this->mixed($middle, $equipment, $used),
        };

        $items = [$warmup, ...$blocks, ...$cooldown, ...$closing];
        // Les blocs s'ajustent d'abord ; l'échauffement et le retour au calme, s'il le faut.
        $blockIndexes = range(1, count($blocks));
        $easyIndexes = $cooldown === [] ? [0] : [count($blocks) + 1, 0];
        $items = $this->fit($items, $budget, array_reverse($blockIndexes), $easyIndexes);

        return [
            'name' => 'Cardio '.mb_strtolower($style->label())." — {$minutes} min",
            'items' => $items,
            'seconds' => WorkoutEstimate::seconds($items),
        ];
    }

    /**
     * Un bloc de fractionné d'à peu près `seconds`, pour finir une séance de
     * perte de poids : le hasard de la séance le choisit.
     *
     * @param  list<string>  $exclude
     * @return array<string, mixed>
     */
    public function finisher(int $seconds, ?EquipmentKind $equipment, Randomizer $random, array $exclude = []): array
    {
        $this->random = $random;
        $protocol = match (true) {
            $seconds < 360 => [20, 10],
            $seconds < 600 => [40, 20],
            default => [30, 30],
        };

        return $this->interval($this->choose($this->intervalPool($equipment), $exclude), $protocol, $seconds);
    }

    /**
     * @param  list<string>  $used
     * @return list<array<string, mixed>>
     */
    private function steady(int $seconds, ?EquipmentKind $equipment, array &$used): array
    {
        $count = match (true) {
            $seconds <= 1200 => 1,
            $seconds <= 2400 => 2,
            default => 3,
        };
        $length = intdiv($seconds - ($count - 1) * self::BETWEEN_BLOCKS, $count);
        $blocks = [];

        for ($i = 0; $i < $count; $i++) {
            $slug = $this->choose($equipment === EquipmentKind::Bodyweight ? self::STEADY_BODYWEIGHT : self::STEADY_MACHINES, $used);
            $used[] = $slug;
            $blocks[] = $this->continuous($slug, $length, $equipment === EquipmentKind::Bodyweight);
        }

        return $blocks;
    }

    /**
     * @param  list<string>  $used
     * @return list<array<string, mixed>>
     */
    private function intervals(int $seconds, ?EquipmentKind $equipment, array &$used): array
    {
        // Un bloc toutes les dix minutes environ, six au plus : au-delà, les blocs s'allongent.
        $count = max(1, min(6, (int) round($seconds / 600)));
        $length = intdiv($seconds - ($count - 1) * self::BETWEEN_BLOCKS, $count);
        // Les longs blocs prennent les longs efforts ; les courts, le Tabata ou le 40/20.
        $protocols = array_values(array_filter(self::PROTOCOLS, fn (array $p): bool => $length >= 600 ? $p[0] >= 30 : $p[0] <= 40));
        $protocols = $this->random->shuffleArray($protocols);
        $blocks = [];

        for ($i = 0; $i < $count; $i++) {
            $slug = $this->choose($this->intervalPool($equipment), $used);
            $used[] = $slug;
            $blocks[] = $this->interval($slug, $protocols[$i % count($protocols)], $length);
        }

        return $blocks;
    }

    /**
     * Un bloc continu d'un peu moins de la moitié du temps, puis du fractionné.
     *
     * @param  list<string>  $used
     * @return list<array<string, mixed>>
     */
    private function mixed(int $seconds, ?EquipmentKind $equipment, array &$used): array
    {
        if ($seconds < 900) {
            return $this->intervals($seconds, $equipment, $used);
        }

        $steady = (int) round($seconds * 0.45 / 60) * 60;

        return [
            ...$this->steady($steady, $equipment, $used),
            ...$this->intervals($seconds - $steady - self::BETWEEN_BLOCKS, $equipment, $used),
        ];
    }

    /**
     * @return list<string>
     */
    private function intervalPool(?EquipmentKind $equipment): array
    {
        return match ($equipment) {
            EquipmentKind::Bodyweight => self::INTERVAL_BODYWEIGHT,
            null => self::INTERVAL_ANY,
            default => self::INTERVAL_MACHINES,
        };
    }

    /**
     * Un exercice du lot, pas encore pris si possible.
     *
     * @param  list<string>  $pool
     * @param  list<string>  $exclude
     */
    private function choose(array $pool, array $exclude): string
    {
        $fresh = array_values(array_diff($pool, $exclude));
        $candidates = $fresh === [] ? array_values($pool) : $fresh;

        return $candidates[$this->random->getInt(0, count($candidates) - 1)];
    }

    /**
     * @param  list<string>  $pool
     * @return array<string, mixed>
     */
    private function easy(array $pool, int $seconds): array
    {
        return ['exercise' => $this->choose(array_values($pool), []), 'mode' => 'time', 'value' => $seconds, 'sets' => 1, 'rest_sets' => 0, 'rest_after' => self::BETWEEN_BLOCKS];
    }

    /**
     * Un bloc à allure régulière : d'une traite sur machine, en rounds de
     * trois minutes au poids du corps — personne ne saute à la corde vingt
     * minutes sans s'arrêter.
     *
     * @return array<string, mixed>
     */
    private function continuous(string $slug, int $seconds, bool $rounds): array
    {
        if ($rounds) {
            $sets = max(2, (int) round(($seconds + 60) / 240));

            return ['exercise' => $slug, 'mode' => 'time', 'value' => 180, 'sets' => $sets, 'rest_sets' => 60, 'rest_after' => self::BETWEEN_BLOCKS];
        }

        return ['exercise' => $slug, 'mode' => 'time', 'value' => max(300, (int) round($seconds / 60) * 60), 'sets' => 1, 'rest_sets' => 0, 'rest_after' => self::BETWEEN_BLOCKS];
    }

    /**
     * Un bloc de fractionné : autant d'efforts que le temps en loge, entre
     * quatre et douze. Un exercice compté en répétitions (burpees) prend huit
     * répétitions par effort, huit fois au plus.
     *
     * @param  array{int, int}  $protocol
     * @return array<string, mixed>
     */
    private function interval(string $slug, array $protocol, int $seconds): array
    {
        [$work, $rest] = $protocol;
        $reps = in_array($slug, ['burpees', 'burpee-broad-jump'], true);

        if ($reps) {
            $work = (int) round(8 * (float) config('sport.seconds_per_rep'));
            $rest = max(40, $rest);
        }

        $sets = max(4, min($reps ? self::MAX_REP_SETS : 12, (int) round(($seconds + $rest) / ($work + $rest))));

        return [
            'exercise' => $slug,
            'mode' => $reps ? 'reps' : 'time',
            'value' => $reps ? 8 : $work,
            'sets' => $sets,
            'rest_sets' => $rest,
            'rest_after' => self::BETWEEN_BLOCKS,
        ];
    }

    /**
     * Ajuste les blocs pour tomber sur le temps demandé : le continu s'allonge
     * ou raccourcit à la minute, le fractionné gagne ou perd des efforts.
     *
     * @param  list<array<string, mixed>>  $items
     * @param  list<int>  $blocks  les blocs, retouchés à tour de rôle
     * @param  list<int>  $easy  l'échauffement et le retour au calme, en dernier recours
     * @return list<array<string, mixed>>
     */
    private function fit(array $items, int $budget, array $blocks, array $easy): array
    {
        for ($round = 0; $round < 60; $round++) {
            $gap = $budget - WorkoutEstimate::seconds($items);

            if (abs($gap) <= max(60, $budget * 0.04)) {
                break;
            }

            $better = null;

            // Un pas qui éloigne du but ne compte pas : on essaie le suivant.
            foreach ([...$blocks, ...$easy] as $i) {
                $candidate = $items;

                if ($candidate[$i]['sets'] === 1) {
                    $candidate[$i]['value'] = max(180, $candidate[$i]['value'] + ($gap > 0 ? 60 : -60));
                } else {
                    $most = $candidate[$i]['mode'] === 'reps' ? self::MAX_REP_SETS : 16;
                    $candidate[$i]['sets'] = max(3, min($most, $candidate[$i]['sets'] + ($gap > 0 ? 1 : -1)));
                }

                if (abs($budget - WorkoutEstimate::seconds($candidate)) < abs($gap)) {
                    $better = $candidate;

                    break;
                }
            }

            if ($better === null) {
                break;
            }

            $items = $better;
            // Chacun son tour : le bloc qu'on vient de toucher passe en fin de file.
            $blocks = [...array_slice($blocks, 1), $blocks[0]];
        }

        return $items;
    }
}
