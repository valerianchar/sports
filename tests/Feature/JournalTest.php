<?php

namespace Tests\Feature;

use App\Models\SetLog;
use App\Models\User;
use App\Models\Workout;
use App\Models\WorkoutLog;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia;
use Tests\TestCase;

class JournalTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private Workout $workout;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create();
        $this->workout = Workout::factory()->for($this->user)->withItems(['developpe-couche', 'gainage-planche'])->create(['name' => 'Push']);
    }

    /**
     * Une séance terminée il y a `$daysAgo` jours : le gainage d'abord, puis
     * quatre séries de développé couché à `$weight` kg, la dernière prolongée
     * d'un palier de drop à 6 × 50 kg.
     */
    private function finish(float $weight = 60, int $daysAgo = 0, int $planned = 5, ?User $user = null, ?Workout $workout = null): WorkoutLog
    {
        $at = now()->subDays($daysAgo);
        $sets = [['exercise' => 'gainage-planche', 'position' => 1, 'set' => 1, 'seconds' => 45, 'at' => $at->copy()->subMinutes(30)->toIso8601String()]];

        foreach (range(1, 4) as $set) {
            $sets[] = ['exercise' => 'developpe-couche', 'position' => 0, 'set' => $set, 'reps' => 8, 'target_reps' => 8, 'weight' => $weight, 'at' => $at->copy()->subMinutes(10 - $set)->toIso8601String()];
        }

        $sets[] = ['exercise' => 'developpe-couche', 'position' => 0, 'set' => 4, 'drop' => 0, 'reps' => 6, 'weight' => 50, 'at' => $at->toIso8601String()];

        $user ??= $this->user;
        $workout ??= $this->workout;

        $id = $this->actingAs($user)->postJson("/seances/{$workout->id}/journal", [
            'client_id' => (string) Str::uuid(),
            'duration_seconds' => 2400,
            'sets_done' => 5,
            'exercises_done' => 2,
            'planned_sets' => $planned,
            'finished_at' => $at->toIso8601String(),
            'sets' => $sets,
        ])->assertCreated()->json('id');

        return WorkoutLog::findOrFail($id);
    }

    public function test_the_journal_lists_my_sessions_newest_first_with_their_figures(): void
    {
        $older = $this->finish(55, 8);
        $latest = $this->finish(60);
        $latest->update(['rpe' => 7]);

        $stranger = User::factory()->create();
        $this->finish(100, 1, user: $stranger, workout: Workout::factory()->for($stranger)->withItems()->create(['name' => 'Pas à moi']));

        $this->actingAs($this->user)->get('/journal')
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('Journal/Index')
                ->has('sessions.data', 2)
                ->where('sessions.data.0.id', $latest->id)
                ->where('sessions.data.0.name', 'Push')
                ->where('sessions.data.0.completed', true)
                ->where('sessions.data.0.day_label', 'Aujourd’hui')
                ->where('sessions.data.0.minutes', 40)
                // Les paliers de drop prolongent une série, ils n'en ajoutent pas.
                ->where('sessions.data.0.sets', 5)
                ->where('sessions.data.0.tonnage', fn ($t) => (float) $t === 4 * 8 * 60.0 + 6 * 50)
                ->where('sessions.data.0.kcal', 200)
                ->where('sessions.data.0.rpe', 7)
                ->where('sessions.data.0.exercises', ['Gainage planche', 'Développé couché'])
                ->where('sessions.data.0.week_label', fn (string $label) => str_starts_with($label, 'Semaine du '))
                ->where('sessions.data.1.id', $older->id)
                ->where('sessions.data.1.tonnage', fn ($t) => (float) $t === 4 * 8 * 55.0 + 6 * 50)
                ->where('sessions.data.1.rpe', null));
    }

    public function test_the_journal_is_paginated_by_twenty(): void
    {
        foreach (range(1, 21) as $i) {
            $this->user->workoutLogs()->create([
                'client_id' => (string) Str::uuid(),
                'name' => "Séance {$i}",
                'duration_seconds' => 1800,
                'sets_done' => 10,
                'exercises_done' => 3,
                'finished_at' => now()->subDays($i),
            ]);
        }

        $this->actingAs($this->user)->get('/journal')
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->has('sessions.data', 20)
                ->where('sessions.data.0.name', 'Séance 1')
                // Sans le détail des séries, on s'en tient au compte du téléphone.
                ->where('sessions.data.0.sets', 10));

        $this->actingAs($this->user)->get('/journal?page=2')
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->has('sessions.data', 1)
                ->where('sessions.data.0.name', 'Séance 21'));
    }

    public function test_an_interrupted_session_is_listed_but_flagged(): void
    {
        $log = $this->finish(60, planned: 10);

        $this->assertFalse($log->completed);

        $this->actingAs($this->user)->get('/journal')
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->has('sessions.data', 1)
                ->where('sessions.data.0.completed', false)
                ->where('sessions.data.0.kcal', null));

        $this->actingAs($this->user)->get("/journal/{$log->id}")
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('session.completed', false)
                ->where('session.planned_sets', 10)
                ->where('session.kcal', null));
    }

    public function test_the_detail_groups_the_sets_by_exercise_in_performed_order(): void
    {
        $log = $this->finish(60);

        $this->actingAs($this->user)->get("/journal/{$log->id}")
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('Journal/Show')
                ->where('session.id', $log->id)
                ->where('session.name', 'Push')
                ->has('session.exercises', 2)
                // Le gainage a été fait en premier, bien qu'il soit second dans la séance.
                ->where('session.exercises.0.slug', 'gainage-planche')
                ->where('session.exercises.0.sets.0.seconds', 45)
                ->where('session.exercises.0.sets.0.reps', null)
                ->where('session.exercises.0.best_e1rm', null)
                ->where('session.exercises.1.slug', 'developpe-couche')
                ->where('session.exercises.1.name', 'Développé couché')
                ->where('session.exercises.1.images.0', fn (string $src) => str_starts_with($src, '/images/exercices/developpe-couche/'))
                ->has('session.exercises.1.sets', 4)
                ->where('session.exercises.1.sets.0.reps', 8)
                ->where('session.exercises.1.sets.0.weight', fn ($w) => (float) $w === 60.0)
                ->where('session.exercises.1.sets.0.target', 8)
                ->where('session.exercises.1.sets.0.drops', [])
                ->where('session.exercises.1.sets.3.drops.0.reps', 6)
                ->where('session.exercises.1.sets.3.drops.0.weight', fn ($w) => (float) $w === 50.0)
                ->where('session.exercises.1.best_e1rm', fn ($e) => (float) $e === 76.0)
                ->where('session.exercises.1.record', null));
    }

    public function test_a_beaten_record_is_marked_on_the_exercise(): void
    {
        $this->finish(60, 7);
        $log = $this->finish(62.5);

        $this->actingAs($this->user)->get("/journal/{$log->id}")
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('session.exercises.0.record', null)
                ->where('session.exercises.1.record', 'weight'));
    }

    public function test_reps_counted_per_side_are_shown_as_such(): void
    {
        $log = $this->finish(60);
        // Le journal double le volume d'une série comptée de chaque côté.
        SetLog::query()->where('workout_log_id', $log->id)->where('set_number', 1)->whereNull('drop')
            ->where('exercise', 'developpe-couche')->update(['volume' => 960]);

        $this->actingAs($this->user)->get("/journal/{$log->id}")
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('session.exercises.1.sets.0.per_side', true)
                ->where('session.exercises.1.sets.1.per_side', false));
    }

    public function test_someone_elses_session_stays_out_of_reach(): void
    {
        $log = $this->finish(60);
        $stranger = User::factory()->create();

        $this->actingAs($stranger)->get("/journal/{$log->id}")->assertNotFound();
        $this->actingAs($stranger)->delete("/journal/{$log->id}")->assertNotFound();
        $this->actingAs($stranger)->get('/journal')
            ->assertInertia(fn (AssertableInertia $page) => $page->has('sessions.data', 0));

        $this->assertModelExists($log);
    }

    public function test_deleting_a_session_removes_its_sets(): void
    {
        $kept = $this->finish(60, 3);
        $log = $this->finish(60);

        $this->actingAs($this->user)->delete("/journal/{$log->id}")
            ->assertRedirect('/journal')
            ->assertSessionHas('success');

        $this->assertModelMissing($log);
        $this->assertSame(0, SetLog::query()->where('workout_log_id', $log->id)->count());
        $this->assertSame(6, SetLog::query()->where('workout_log_id', $kept->id)->count());
    }

    public function test_guests_are_sent_to_the_login(): void
    {
        $this->get('/journal')->assertRedirect('/connexion');
    }
}
