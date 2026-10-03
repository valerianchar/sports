<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Workout;
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
}
