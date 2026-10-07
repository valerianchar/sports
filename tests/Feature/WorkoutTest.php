<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Workout;
use App\Support\ExerciseCatalog;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia;
use Tests\TestCase;

class WorkoutTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @return array<string, mixed>
     */
    private function item(string $exercise = 'developpe-couche', array $overrides = []): array
    {
        return [
            'exercise' => $exercise,
            'mode' => 'reps',
            'value' => 10,
            'sets' => 3,
            'rest_sets' => 60,
            'rest_after' => 90,
            ...$overrides,
        ];
    }

    public function test_the_home_screen_lists_only_my_workouts(): void
    {
        $user = User::factory()->create();
        Workout::factory()->for($user)->withItems()->create(['name' => 'Pecs']);
        Workout::factory()->withItems()->create(['name' => 'Celle d’un autre']);

        $this->actingAs($user)->get('/')
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('Workouts/Index')
                ->has('workouts', 1)
                ->where('workouts.0.name', 'Pecs')
                ->has('workouts.0.items', 2)
                // Seuls les exercices des séances affichées voyagent.
                ->has('exercises', 2));
    }

    public function test_a_workout_is_created_with_its_exercises_in_order(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->post('/seances', [
            'name' => '  Dos  ',
            'items' => [
                $this->item('tractions'),
                $this->item('gainage-planche', ['mode' => 'time', 'value' => 45]),
            ],
        ])->assertRedirect('/');

        $workout = $user->workouts()->sole();
        $this->assertSame('Dos', $workout->name);
        $this->assertSame(['tractions', 'gainage-planche'], $workout->items->pluck('exercise')->all());
        $this->assertSame(45, $workout->items[1]->value);
    }

    public function test_an_unnamed_workout_gets_a_default_name(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->post('/seances', ['name' => '', 'items' => [$this->item()]]);

        $this->assertSame('Séance sans nom', $user->workouts()->sole()->name);
    }

    public function test_saving_with_start_goes_straight_to_the_player(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->post('/seances', ['name' => 'Go', 'items' => [$this->item()], 'start' => true]);

        $response->assertRedirect('/seances/'.$user->workouts()->sole()->id.'/lancer');
    }

    public function test_updating_replaces_the_whole_list(): void
    {
        $user = User::factory()->create();
        $workout = Workout::factory()->for($user)->withItems(['developpe-couche', 'butterfly', 'pompes'])->create();

        $this->actingAs($user)->put("/seances/{$workout->id}", [
            'name' => 'Réordonnée',
            'items' => [$this->item('pompes'), $this->item('developpe-couche', ['sets' => 5])],
        ])->assertRedirect('/');

        $workout->refresh();
        $this->assertSame('Réordonnée', $workout->name);
        $this->assertSame(['pompes', 'developpe-couche'], $workout->items->pluck('exercise')->all());
        $this->assertSame([0, 1], $workout->items->pluck('position')->all());
        $this->assertSame(5, $workout->items[1]->sets);
    }

    public function test_an_unknown_exercise_is_refused(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->post('/seances', ['name' => 'X', 'items' => [$this->item('lancer-de-nain')]])
            ->assertSessionHasErrors(['items.0.exercise' => 'Cet exercice n’existe pas dans la bibliothèque.']);

        $this->assertSame(0, $user->workouts()->count());
    }

    public function test_the_value_bounds_depend_on_the_mode(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->post('/seances', ['name' => 'X', 'items' => [$this->item('pompes', ['value' => 300])]])
            ->assertSessionHasErrors('items.0.value');

        // 300 secondes chronométrées, en revanche, c'est un vélo de 5 minutes.
        $this->actingAs($user)->post('/seances', ['name' => 'X', 'items' => [$this->item('velo', ['mode' => 'time', 'value' => 300])]])
            ->assertSessionHasNoErrors();
    }

    public function test_the_editor_receives_the_full_library(): void
    {
        $user = User::factory()->create();
        $workout = Workout::factory()->for($user)->withItems()->create();

        $this->actingAs($user)->get("/seances/{$workout->id}/modifier")
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('Workouts/Edit')
                ->where('workout.id', $workout->id)
                ->has('exercises', ExerciseCatalog::all()->count())
                ->has('groups', 10));

        $this->actingAs($user)->get('/seances/nouvelle')
            ->assertInertia(fn (AssertableInertia $page) => $page->component('Workouts/Edit')->where('workout', null));
    }

    public function test_someone_elses_workout_is_out_of_reach(): void
    {
        $workout = Workout::factory()->withItems()->create();
        $intruder = User::factory()->create();

        $this->actingAs($intruder)->get("/seances/{$workout->id}/modifier")->assertForbidden();
        $this->actingAs($intruder)->get("/seances/{$workout->id}/lancer")->assertForbidden();
        $this->actingAs($intruder)->put("/seances/{$workout->id}", ['name' => 'Volée', 'items' => []])->assertForbidden();
        $this->actingAs($intruder)->delete("/seances/{$workout->id}")->assertForbidden();

        $this->assertModelExists($workout);
        $this->assertNotSame('Volée', $workout->fresh()->name);
    }

    public function test_a_workout_can_be_deleted(): void
    {
        $user = User::factory()->create();
        $workout = Workout::factory()->for($user)->withItems()->create();

        $this->actingAs($user)->delete("/seances/{$workout->id}")->assertRedirect('/');

        $this->assertModelMissing($workout);
        $this->assertDatabaseCount('workout_items', 0);
    }

    public function test_the_player_gets_the_workout_and_its_exercises(): void
    {
        // Rechargé : les réglages non précisés prennent leur valeur par défaut en base.
        $user = User::factory()->create(['prep_seconds' => 8, 'sound' => false])->fresh();
        $workout = Workout::factory()->for($user)->withItems()->create();

        $this->actingAs($user)->get("/seances/{$workout->id}/lancer")
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('Workouts/Play')
                ->has('workout.items', 2)
                ->where('exercises', fn ($exercises) => collect($exercises)->firstWhere('slug', 'developpe-couche')['images'][0] === '/images/exercices/developpe-couche/0.jpg')
                // Les variantes de chaque exercice voyagent avec, pour en changer hors réseau.
                ->where('alternatives.developpe-couche', fn ($slugs) => count($slugs) > 0)
                ->where('exercises', fn ($exercises) => count($exercises) > 2)
                ->where('preferences', ['sound' => false, 'prep_seconds' => 8, 'countdown_seconds' => 5, 'volume' => 80, 'countdown_sound' => 'bip', 'custom_sound_url' => null, 'audio_mode' => 'melange']));
    }

    public function test_an_empty_workout_cannot_be_played(): void
    {
        $user = User::factory()->create();
        $workout = Workout::factory()->for($user)->create();

        $this->actingAs($user)->get("/seances/{$workout->id}/lancer")
            ->assertRedirect("/seances/{$workout->id}/modifier");
    }

    public function test_the_library_renders(): void
    {
        $this->actingAs(User::factory()->create())->get('/exercices')
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('Exercises/Index')
                ->has('exercises', ExerciseCatalog::all()->count())
                ->where('groups.2', ['value' => 'epaules', 'label' => 'Épaules']));
    }
}
