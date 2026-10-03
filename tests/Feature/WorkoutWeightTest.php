<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Workout;
use App\Support\WorkoutEstimate;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia;
use Tests\TestCase;

class WorkoutWeightTest extends TestCase
{
    use RefreshDatabase;

    public function test_each_exercise_keeps_its_weight(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->post('/seances', ['name' => 'Force', 'items' => [
            ['exercise' => 'squat', 'mode' => 'reps', 'value' => 5, 'weight' => '102.5', 'sets' => 5, 'rest_sets' => 150, 'rest_after' => 120],
            ['exercise' => 'tractions', 'mode' => 'reps', 'value' => 8, 'weight' => null, 'sets' => 4, 'rest_sets' => 90, 'rest_after' => 90],
        ]])->assertSessionHasNoErrors();

        $items = $user->workouts()->sole()->items;
        $this->assertSame(102.5, $items[0]->weight);
        $this->assertNull($items[1]->weight);
    }

    public function test_the_player_shows_the_weight(): void
    {
        $user = User::factory()->create();
        $workout = Workout::factory()->for($user)->create();
        $workout->items()->create(['position' => 0, 'exercise' => 'developpe-couche', 'mode' => 'reps', 'value' => 8, 'weight' => 60, 'sets' => 4, 'rest_sets' => 120, 'rest_after' => 90]);

        $this->actingAs($user)->get("/seances/{$workout->id}/lancer")
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('workout.items.0.weight', 60)
                ->where('workout.urls.weight', route('workouts.weight.update', $workout)));
    }

    public function test_the_weight_can_change_during_the_session(): void
    {
        $user = User::factory()->create();
        $workout = Workout::factory()->for($user)->withItems(['developpe-couche', 'gainage-planche'])->create();

        $this->actingAs($user)->patchJson("/seances/{$workout->id}/charge", ['position' => 0, 'weight' => 62.5])
            ->assertOk()
            ->assertJson(['weight' => 62.5]);

        $this->assertSame(62.5, $workout->items()->where('position', 0)->sole()->weight);

        $this->actingAs($user)->patchJson("/seances/{$workout->id}/charge", ['position' => 0, 'weight' => null])->assertOk();
        $this->assertNull($workout->items()->where('position', 0)->sole()->weight);
    }

    public function test_nobody_changes_someone_elses_weights(): void
    {
        $workout = Workout::factory()->withItems()->create();

        $this->actingAs(User::factory()->create())
            ->patchJson("/seances/{$workout->id}/charge", ['position' => 0, 'weight' => 500])
            ->assertForbidden();
    }

    public function test_a_weight_must_be_a_sensible_number(): void
    {
        $user = User::factory()->create();
        $workout = Workout::factory()->for($user)->withItems()->create();

        $this->actingAs($user)->patchJson("/seances/{$workout->id}/charge", ['position' => 0, 'weight' => 'lourd'])->assertUnprocessable();
        $this->actingAs($user)->patchJson("/seances/{$workout->id}/charge", ['position' => 9, 'weight' => 10])->assertNotFound();
        $this->actingAs($user)->post('/seances', ['name' => 'X', 'items' => [
            ['exercise' => 'squat', 'mode' => 'reps', 'value' => 5, 'weight' => -5, 'sets' => 3, 'rest_sets' => 60, 'rest_after' => 60],
        ]])->assertSessionHasErrors('items.0.weight');
    }

    public function test_degressive_weights_and_drop_sets_are_kept(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->post('/seances', ['name' => 'Dégressif', 'items' => [[
            'exercise' => 'developpe-couche', 'mode' => 'reps', 'value' => 10, 'sets' => 4, 'rest_sets' => 90, 'rest_after' => 90,
            'weight' => 80,
            // Trois charges pour quatre séries : la quatrième reprend la troisième.
            'set_weights' => [80, 72.5, 65],
            'drops' => [['reps' => 6, 'weight' => 50], ['reps' => 4, 'weight' => 40]],
            'drop_on' => 'all',
        ]]])->assertSessionHasNoErrors();

        $item = $user->workouts()->sole()->items->sole();
        $this->assertEquals([80, 72.5, 65, 65], $item->set_weights);
        $this->assertSame([['reps' => 6, 'weight' => 50], ['reps' => 4, 'weight' => 40]], array_map(fn ($d) => ['reps' => $d['reps'], 'weight' => (int) $d['weight']], $item->drops));
        $this->assertSame('all', $item->drop_on);
    }

    public function test_a_timed_exercise_carries_no_weight_plan(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->post('/seances', ['name' => 'Gainage', 'items' => [[
            'exercise' => 'gainage-planche', 'mode' => 'time', 'value' => 45, 'sets' => 3, 'rest_sets' => 30, 'rest_after' => 60,
            'set_weights' => [10, 10, 10], 'drops' => [['reps' => 5, 'weight' => 5]],
        ]]])->assertSessionHasNoErrors();

        $item = $user->workouts()->sole()->items->sole();
        $this->assertNull($item->set_weights);
        $this->assertNull($item->drops);
    }

    public function test_the_player_changes_the_weight_of_a_set_or_a_drop(): void
    {
        $user = User::factory()->create();
        $workout = Workout::factory()->for($user)->create();
        $workout->items()->create([
            'position' => 0, 'exercise' => 'developpe-couche', 'mode' => 'reps', 'value' => 10, 'sets' => 3, 'rest_sets' => 90, 'rest_after' => 90,
            'set_weights' => [80, 70, 60], 'drops' => [['reps' => 6, 'weight' => 40]], 'drop_on' => 'last',
        ]);

        $this->actingAs($user)->patchJson("/seances/{$workout->id}/charge", ['position' => 0, 'set' => 1, 'weight' => 72.5])->assertOk();
        $this->actingAs($user)->patchJson("/seances/{$workout->id}/charge", ['position' => 0, 'drop' => 0, 'weight' => 45])->assertOk();
        $this->actingAs($user)->patchJson("/seances/{$workout->id}/charge", ['position' => 0, 'drop' => 2, 'weight' => 45])->assertNotFound();

        $item = $workout->items()->sole();
        $this->assertEquals([80, 72.5, 60], $item->set_weights);
        $this->assertEquals(45, $item->drops[0]['weight']);
    }

    public function test_drop_set_reps_count_in_the_estimate(): void
    {
        $base = ['mode' => 'reps', 'value' => 10, 'sets' => 3, 'rest_sets' => 60, 'rest_after' => 90];

        $this->assertSame(
            WorkoutEstimate::seconds([$base]) + 10 * 3,
            WorkoutEstimate::seconds([[...$base, 'drops' => [['reps' => 6], ['reps' => 4]], 'drop_on' => 'last']]),
        );
    }
}
