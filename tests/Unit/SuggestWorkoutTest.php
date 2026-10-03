<?php

namespace Tests\Unit;

use App\Actions\SuggestWorkout;
use App\Enums\EquipmentKind;
use App\Enums\Muscle;
use App\Enums\MuscleGroup;
use App\Enums\WorkoutGoal;
use App\Support\ExerciseCatalog;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class SuggestWorkoutTest extends TestCase
{
    /**
     * @param  list<Muscle>  $muscles
     * @return array{name: string, items: list<array<string, mixed>>, seconds: int}
     */
    private function suggest(array $muscles, int $minutes = 45, WorkoutGoal $goal = WorkoutGoal::Hypertrophy, ?EquipmentKind $equipment = null, bool $warmup = false, bool $stretch = false, int $variant = 0, array $settings = []): array
    {
        return app(SuggestWorkout::class)->handle($muscles, $minutes, $goal, $equipment, $warmup, $stretch, $variant, $settings);
    }

    /**
     * @return list<string>
     */
    private function primaries(array $proposal): array
    {
        return array_merge(...array_map(fn (array $item): array => ExerciseCatalog::find($item['exercise'])['primary'], $proposal['items']));
    }

    /**
     * @return iterable<string, array{list<Muscle>, int}>
     */
    public static function requests(): iterable
    {
        yield 'pectoraux et triceps, 30 min' => [[Muscle::Chest, Muscle::Triceps], 30];
        yield 'dos et biceps, 45 min' => [[Muscle::UpperBack, Muscle::Biceps, Muscle::RearDeltoids], 45];
        yield 'jambes, 60 min' => [[Muscle::Quadriceps, Muscle::Hamstring, Muscle::Gluteal, Muscle::Calves], 60];
        yield 'corps entier, 90 min' => [[Muscle::Chest, Muscle::UpperBack, Muscle::FrontDeltoids, Muscle::Quadriceps, Muscle::Hamstring, Muscle::Abs], 90];
        yield 'abdos, 20 min' => [[Muscle::Abs, Muscle::Obliques], 20];
    }

    /**
     * @param  list<Muscle>  $muscles
     */
    #[DataProvider('requests')]
    public function test_the_session_fits_the_time_and_works_every_muscle_asked(array $muscles, int $minutes): void
    {
        foreach ([0, 1, 2] as $variant) {
            $proposal = $this->suggest($muscles, $minutes, variant: $variant);

            $this->assertEqualsWithDelta($minutes * 60, $proposal['seconds'], $minutes * 60 * 0.15, "Variante {$variant}");

            foreach ($muscles as $muscle) {
                $this->assertContains($muscle->value, $this->primaries($proposal), "{$muscle->label()} oublié (variante {$variant})");
            }

            $slugs = array_column($proposal['items'], 'exercise');
            $this->assertSame($slugs, array_values(array_unique($slugs)), 'Un exercice en double');
        }
    }

    public function test_the_goal_sets_reps_and_rest(): void
    {
        $strength = $this->suggest([Muscle::Chest], 40, WorkoutGoal::Strength);
        $endurance = $this->suggest([Muscle::Chest], 40, WorkoutGoal::Endurance);

        $reps = fn (array $proposal): array => array_unique(array_column(array_filter($proposal['items'], fn ($i) => $i['mode'] === 'reps'), 'value'));

        $this->assertSame([5], array_values($reps($strength)));
        $this->assertSame([15], array_values($reps($endurance)));
        $this->assertGreaterThan($endurance['items'][0]['rest_sets'], $strength['items'][0]['rest_sets']);
    }

    public function test_the_equipment_choice_is_respected(): void
    {
        foreach ([EquipmentKind::Machine, EquipmentKind::Free, EquipmentKind::Bodyweight] as $kind) {
            $proposal = $this->suggest([Muscle::Chest, Muscle::UpperBack, Muscle::Quadriceps], 45, equipment: $kind);

            foreach ($proposal['items'] as $item) {
                $this->assertSame($kind, ExerciseCatalog::equipment($item['exercise'])->kind(), "{$item['exercise']} hors « {$kind->label()} »");
            }
        }
    }

    public function test_a_muscle_without_machine_falls_back_on_bodyweight(): void
    {
        $proposal = $this->suggest([Muscle::Chest, Muscle::Obliques], 30, equipment: EquipmentKind::Machine);

        $this->assertContains(Muscle::Obliques->value, $this->primaries($proposal));
    }

    public function test_warmup_comes_first_and_stretches_last(): void
    {
        $proposal = $this->suggest([Muscle::Quadriceps, Muscle::Hamstring], 50, warmup: true, stretch: true);
        $first = ExerciseCatalog::find($proposal['items'][0]['exercise']);
        $last = ExerciseCatalog::find(end($proposal['items'])['exercise']);

        $this->assertSame(MuscleGroup::Cardio->value, $first['group']);
        $this->assertSame(300, $proposal['items'][0]['value']);
        $this->assertSame(MuscleGroup::Mobilite->value, $last['group']);
        $this->assertEqualsWithDelta(50 * 60, $proposal['seconds'], 50 * 60 * 0.15);
    }

    public function test_core_work_closes_the_session(): void
    {
        $proposal = $this->suggest([Muscle::Abs, Muscle::Quadriceps, Muscle::Chest], 60);
        $groups = array_map(fn (array $item): string => ExerciseCatalog::find($item['exercise'])['group'], $proposal['items']);
        $firstCore = array_search(MuscleGroup::Abdos->value, $groups, true);

        $this->assertNotFalse($firstCore);
        $this->assertSame([MuscleGroup::Abdos->value], array_values(array_unique(array_slice($groups, $firstCore))));
    }

    public function test_a_variant_is_reproducible_and_another_one_differs(): void
    {
        $muscles = [Muscle::Chest, Muscle::Triceps, Muscle::FrontDeltoids];

        $this->assertSame($this->suggest($muscles, variant: 7), $this->suggest($muscles, variant: 7));

        $variants = array_map(fn (int $v): array => array_column($this->suggest($muscles, variant: $v)['items'], 'exercise'), range(1, 6));
        $this->assertGreaterThan(1, count(array_unique(array_map('serialize', $variants))));
    }

    public function test_gym_classics_come_before_advanced_moves(): void
    {
        $advanced = ['handstand-push-up', 'pistol-squat', 'nordic-curl', 'l-sit', 'dragon-flag', 'turkish-get-up', 'dips-anneaux'];

        foreach (self::requests() as [$muscles, $minutes]) {
            foreach (range(0, 9) as $variant) {
                $slugs = array_column($this->suggest($muscles, $minutes, variant: $variant)['items'], 'exercise');

                $this->assertSame([], array_values(array_intersect($slugs, $advanced)));
            }
        }
    }

    public function test_alternatives_work_the_same_muscles(): void
    {
        $alternatives = app(SuggestWorkout::class)->alternatives('developpe-couche', exclude: ['pompes']);

        $this->assertNotEmpty($alternatives);
        $this->assertNotContains('developpe-couche', $alternatives);
        $this->assertNotContains('pompes', $alternatives);

        foreach ($alternatives as $slug) {
            $this->assertContains('chest', ExerciseCatalog::find($slug)['primary'], $slug);
        }

        // Les classiques d'abord : le haut de la liste n'est pas une variante exotique.
        $this->assertContains($alternatives[0], ['developpe-incline-halteres', 'developpe-couche-smith', 'chest-press', 'presse-pectorale-convergente', 'developpe-incline-machine', 'dips-pectoraux', 'ecarte-halteres']);
    }

    public function test_alternatives_keep_the_equipment_choice(): void
    {
        foreach (app(SuggestWorkout::class)->alternatives('leg-extension', EquipmentKind::Machine) as $slug) {
            $this->assertSame(EquipmentKind::Machine, ExerciseCatalog::equipment($slug)->kind(), $slug);
        }
    }

    public function test_a_warmup_is_replaced_by_cardio_and_a_stretch_by_a_stretch(): void
    {
        $suggest = app(SuggestWorkout::class);

        foreach ($suggest->alternatives('velo') as $slug) {
            $this->assertSame('cardio', ExerciseCatalog::find($slug)['group'], $slug);
        }

        $stretch = ExerciseCatalog::all()->first(fn (array $e): bool => str_starts_with($e['slug'], 'etirement-'));

        foreach ($suggest->alternatives($stretch['slug']) as $slug) {
            $this->assertSame('mobilite', ExerciseCatalog::find($slug)['group'], $slug);
        }
    }

    public function test_chosen_reps_and_rests_replace_the_goal_defaults(): void
    {
        $proposal = app(SuggestWorkout::class)->handle([Muscle::Chest, Muscle::Abs], 45, WorkoutGoal::Hypertrophy, settings: ['reps' => 12, 'rest_sets' => 45, 'rest_after' => 120]);

        foreach ($proposal['items'] as $item) {
            $this->assertSame(45, $item['rest_sets']);
            $this->assertSame(120, $item['rest_after']);

            if ($item['mode'] === 'reps') {
                $this->assertSame(12, $item['value']);
            } else {
                // Un gainage garde la durée de l'objectif.
                $this->assertSame(40, $item['value']);
            }
        }

        $this->assertEqualsWithDelta(45 * 60, $proposal['seconds'], 45 * 60 * 0.15);
    }

    public function test_shorter_rests_fit_more_exercises_in_the_same_time(): void
    {
        $muscles = [Muscle::Chest, Muscle::UpperBack, Muscle::Quadriceps, Muscle::Hamstring];
        $count = fn (int $rest): int => count($this->suggest($muscles, 60, settings: ['rest_sets' => $rest, 'rest_after' => $rest])['items']);

        $this->assertGreaterThan($count(150), $count(30));

        foreach ([30, 90, 150] as $rest) {
            $proposal = $this->suggest($muscles, 60, settings: ['rest_sets' => $rest, 'rest_after' => $rest]);
            $this->assertEqualsWithDelta(60 * 60, $proposal['seconds'], 60 * 60 * 0.15, "Repos {$rest} s");
        }
    }

    public function test_fixed_sets_stay_put_and_the_exercise_count_adapts(): void
    {
        $muscles = [Muscle::Chest, Muscle::UpperBack, Muscle::Quadriceps];

        foreach ([2, 4, 6] as $sets) {
            $proposal = $this->suggest($muscles, 60, settings: ['sets' => $sets]);

            $this->assertSame([$sets], array_values(array_unique(array_column($proposal['items'], 'sets'))), "{$sets} séries");
            $this->assertEqualsWithDelta(60 * 60, $proposal['seconds'], 60 * 60 * 0.2, "{$sets} séries");
        }

        $this->assertGreaterThan(
            count($this->suggest($muscles, 60, settings: ['sets' => 6])['items']),
            count($this->suggest($muscles, 60, settings: ['sets' => 2])['items']),
        );
    }

    public function test_the_name_tells_what_and_how_long(): void
    {
        $this->assertSame('Dos · Bras — 45 min', $this->suggest([Muscle::UpperBack, Muscle::Biceps])['name']);
    }
}
