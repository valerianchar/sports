<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Workout;
use App\Support\ExerciseCatalog;
use App\Support\WorkoutDefaults;
use App\Support\WorkoutEstimate;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Les côtés : un exercice d'un côté puis de l'autre compte par côté, un
 * exercice en alternance par côté ou au total, un exercice symétrique sans.
 */
class SidesTest extends TestCase
{
    use RefreshDatabase;

    private function item(string $slug, array $overrides = []): array
    {
        return ['exercise' => $slug, 'mode' => ExerciseCatalog::mode($slug)->value, 'value' => 10, 'sets' => 3, 'rest_sets' => 60, 'rest_after' => 90, ...$overrides];
    }

    public function test_the_workout_remembers_how_sides_count(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->post('/seances', ['name' => 'Côtés', 'items' => [
            $this->item('rowing-haltere-un-bras', ['per_side' => false]),
            $this->item('curl-halteres-alterne', ['per_side' => false]),
            $this->item('curl-halteres-alterne'),
            $this->item('developpe-couche', ['per_side' => true]),
        ]])->assertRedirect();

        $this->assertSame(
            // Gauche / droite : une option de l'exercice, désactivée sauf si on l'active.
            [null, null, null, true],
            $user->workouts()->sole()->items()->orderBy('position')->pluck('per_side')->all(),
        );
    }

    public function test_a_per_side_exercise_lasts_twice_as_long(): void
    {
        $both = ['mode' => 'reps', 'value' => 10, 'sets' => 3, 'rest_sets' => 60, 'rest_after' => 90];

        $this->assertSame(
            WorkoutEstimate::seconds([$both]) + 3 * 10 * (int) config('sport.seconds_per_rep'),
            WorkoutEstimate::seconds([[...$both, 'per_side' => true]]),
        );
        // Désactivé par défaut, même pour un exercice qui se fait d'un côté.
        $this->assertArrayNotHasKey('per_side', WorkoutDefaults::for('rowing-haltere-un-bras'));
    }

    public function test_per_side_sets_lift_twice_the_volume(): void
    {
        $user = User::factory()->create();
        $workout = Workout::factory()->for($user)->withItems(['rowing-haltere-un-bras'])->create();
        $at = now()->toIso8601String();

        $this->actingAs($user)->postJson("/seances/{$workout->id}/journal", [
            'client_id' => (string) Str::uuid(),
            'duration_seconds' => 600,
            'sets_done' => 1,
            'exercises_done' => 1,
            'planned_sets' => 1,
            'finished_at' => $at,
            'sets' => [['exercise' => 'rowing-haltere-un-bras', 'position' => 0, 'set' => 1, 'reps' => 10, 'weight' => 30, 'per_side' => true, 'at' => $at]],
        ])->assertCreated();

        $this->assertSame(600.0, $user->setLogs()->sole()->volume);
    }

    public function test_the_catalogue_tells_the_player_how_sides_work(): void
    {
        $this->assertSame('each', collect(ExerciseCatalog::forClient(['rowing-haltere-un-bras']))->first()['sides']);
    }

    public function test_a_weight_changed_during_the_session_only_changes_its_set(): void
    {
        $user = User::factory()->create();
        $workout = Workout::factory()->for($user)->withItems(['developpe-couche'])->create();
        $workout->items()->first()->update(['weight' => 60, 'sets' => 4]);

        $this->actingAs($user)->patchJson("/seances/{$workout->id}/charge", ['position' => 0, 'set' => 1, 'weight' => 62.5])->assertOk();

        $this->assertSame([60.0, 62.5, 60.0, 60.0], array_map('floatval', $workout->items()->first()->set_weights));
    }

    public function test_warm_up_sets_are_chosen_per_exercise_and_off_by_default(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->post('/seances', ['name' => 'Squat', 'items' => [
            $this->item('squat', ['warmup' => true, 'weight' => 80]),
            $this->item('leg-extension', ['weight' => 40]),
        ]])->assertRedirect();

        $this->assertSame([true, false], $user->workouts()->sole()->items()->orderBy('position')->pluck('warmup')->all());
    }
}
