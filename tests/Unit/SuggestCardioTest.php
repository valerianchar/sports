<?php

namespace Tests\Unit;

use App\Actions\SuggestCardio;
use App\Actions\SuggestWorkout;
use App\Enums\CardioStyle;
use App\Enums\EquipmentKind;
use App\Enums\Muscle;
use App\Enums\MuscleGroup;
use App\Enums\WorkoutGoal;
use App\Support\ExerciseCatalog;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class SuggestCardioTest extends TestCase
{
    /**
     * @return iterable<string, array{int, CardioStyle, EquipmentKind|null}>
     */
    public static function requests(): iterable
    {
        foreach ([15, 20, 30, 45, 60, 90] as $minutes) {
            foreach (CardioStyle::cases() as $style) {
                foreach ([null, EquipmentKind::Machine, EquipmentKind::Bodyweight] as $equipment) {
                    yield "{$minutes} min, {$style->value}, ".($equipment->value ?? 'tout') => [$minutes, $style, $equipment];
                }
            }
        }
    }

    #[DataProvider('requests')]
    public function test_a_cardio_session_fits_the_time_with_cardio_only(int $minutes, CardioStyle $style, ?EquipmentKind $equipment): void
    {
        foreach ([0, 1, 2] as $variant) {
            $proposal = app(SuggestCardio::class)->handle($minutes, $style, $equipment, variant: $variant);

            $this->assertEqualsWithDelta($minutes * 60, $proposal['seconds'], $minutes * 60 * 0.15, "Variante {$variant}");

            foreach ($proposal['items'] as $item) {
                $exercise = ExerciseCatalog::find($item['exercise']);
                $this->assertNotNull($exercise, $item['exercise']);
                $this->assertContains($exercise['group'], ['cardio', 'fonctionnel']);
                $this->assertSame($exercise['mode'], $item['mode'], $item['exercise']);

                if ($equipment === EquipmentKind::Bodyweight) {
                    // Sans machine : le corps, au plus une corde.
                    $this->assertContains($exercise['equipment'], ['bodyweight', 'jump-rope'], $item['exercise']);
                }

                if ($item['mode'] === 'reps') {
                    $this->assertLessThanOrEqual(8, $item['sets'], 'Trop de burpees');
                }
            }
        }
    }

    public function test_intervals_alternate_effort_and_recovery(): void
    {
        $proposal = app(SuggestCardio::class)->handle(30, CardioStyle::Intervals);
        $blocks = array_slice($proposal['items'], 1, -1);

        $this->assertSame('Cardio fractionné — 30 min', $proposal['name']);
        $this->assertNotEmpty($blocks);

        foreach ($blocks as $block) {
            $this->assertGreaterThanOrEqual(3, $block['sets']);
            $this->assertGreaterThan(0, $block['rest_sets']);
        }
    }

    public function test_steady_cardio_runs_in_long_blocks_between_a_warm_up_and_a_cool_down(): void
    {
        $proposal = app(SuggestCardio::class)->handle(45, CardioStyle::Steady, EquipmentKind::Machine);
        $items = $proposal['items'];

        $this->assertCount(4, $items);
        $this->assertSame(300, $items[0]['value']);
        $this->assertSame(300, end($items)['value']);
        $this->assertNotSame($items[0]['exercise'], end($items)['exercise']);

        foreach (array_slice($items, 1, -1) as $block) {
            $this->assertSame(1, $block['sets']);
            $this->assertGreaterThanOrEqual(600, $block['value']);
        }

        $this->assertCount(4, array_unique(array_column($items, 'exercise')), 'Une machine par bloc');
    }

    public function test_stretches_close_a_cardio_session_on_request(): void
    {
        $items = app(SuggestCardio::class)->handle(40, CardioStyle::Mixed, stretch: true)['items'];

        $this->assertSame(MuscleGroup::Mobilite->value, ExerciseCatalog::find(end($items)['exercise'])['group']);
    }

    public function test_the_same_variant_gives_the_same_session(): void
    {
        $suggest = fn (int $variant): array => app(SuggestCardio::class)->handle(45, CardioStyle::Mixed, variant: $variant);

        $this->assertSame($suggest(4), $suggest(4));
        $this->assertNotSame(array_column($suggest(4)['items'], 'exercise'), array_column($suggest(5)['items'], 'exercise'));
    }

    /**
     * @return iterable<string, array{list<Muscle>, int, EquipmentKind|null}>
     */
    public static function weightLoss(): iterable
    {
        yield 'tout le corps, 45 min' => [[], 45, null];
        yield 'tout le corps, 20 min, sans matériel' => [[], 20, EquipmentKind::Bodyweight];
        yield 'jambes, 35 min, machines' => [[Muscle::Quadriceps, Muscle::Gluteal], 35, EquipmentKind::Machine];
        yield 'haut du corps, 60 min' => [[Muscle::Chest, Muscle::UpperBack, Muscle::FrontDeltoids], 60, null];
    }

    /**
     * @param  list<Muscle>  $muscles
     */
    #[DataProvider('weightLoss')]
    public function test_a_weight_loss_session_is_a_circuit_ending_with_intervals(array $muscles, int $minutes, ?EquipmentKind $equipment): void
    {
        foreach ([0, 1, 2] as $variant) {
            $proposal = app(SuggestWorkout::class)->handle($muscles, $minutes, WorkoutGoal::WeightLoss, $equipment, variant: $variant);
            $items = $proposal['items'];
            $finisher = end($items);

            $this->assertStringStartsWith('Perte de poids · ', $proposal['name']);
            $this->assertEqualsWithDelta($minutes * 60, $proposal['seconds'], $minutes * 60 * 0.15, "Variante {$variant}");
            $this->assertSame('cardio', ExerciseCatalog::find($finisher['exercise'])['group'], 'Le finisher est du cardio');
            $this->assertGreaterThanOrEqual(4, $finisher['sets']);

            $targets = $muscles === [] ? SuggestWorkout::FULL_BODY : array_map(fn (Muscle $m): string => $m->value, $muscles);
            $primaries = array_merge(...array_map(fn (array $item): array => ExerciseCatalog::find($item['exercise'])['primary'], array_slice($items, 0, -1)));

            foreach ($targets as $muscle) {
                $this->assertContains($muscle, $primaries, "{$muscle} oublié (variante {$variant})");
            }

            foreach (array_slice($items, 0, -1) as $item) {
                $this->assertLessThanOrEqual(45, $item['rest_sets'], 'Un circuit ne traîne pas');
            }
        }
    }

    public function test_cardio_is_replaced_by_cardio_measured_the_same_way(): void
    {
        $alternatives = app(SuggestWorkout::class)->alternatives('rameur', EquipmentKind::Machine);

        $this->assertNotEmpty($alternatives);

        foreach ($alternatives as $slug) {
            $this->assertSame('cardio', ExerciseCatalog::find($slug)['group']);
            $this->assertSame('time', ExerciseCatalog::find($slug)['mode']);
            $this->assertSame(EquipmentKind::Conditioning, ExerciseCatalog::equipment($slug)->kind());
        }
    }
}
