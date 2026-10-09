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
            // Un côté puis l'autre : toujours par côté ; en alternant : au choix, par côté sans précision ; symétrique : sans objet.
            [true, false, true, null],
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
        $this->assertTrue(WorkoutDefaults::for('rowing-haltere-un-bras')['per_side']);
        $this->assertNull(WorkoutDefaults::for('developpe-couche')['per_side']);
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
}
