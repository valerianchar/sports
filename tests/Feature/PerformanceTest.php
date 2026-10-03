<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Workout;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia;
use Tests\TestCase;

class PerformanceTest extends TestCase
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
     * Une séance terminée il y a `$daysAgo` jours : quatre séries de développé
     * couché à `$weight` kg, et un gainage.
     *
     * @return array<string, mixed>
     */
    private function finish(float $weight, int $daysAgo = 0, int $reps = 8, int $target = 8, int $planned = 5): array
    {
        $at = now()->subDays($daysAgo);
        $sets = [];

        foreach (range(1, 4) as $set) {
            $sets[] = ['exercise' => 'developpe-couche', 'position' => 0, 'set' => $set, 'reps' => $reps, 'target_reps' => $target, 'weight' => $weight, 'at' => $at->toIso8601String()];
        }

        $sets[] = ['exercise' => 'gainage-planche', 'position' => 1, 'set' => 1, 'seconds' => 45, 'at' => $at->toIso8601String()];

        return $this->actingAs($this->user)->postJson("/seances/{$this->workout->id}/journal", [
            'client_id' => (string) Str::uuid(),
            'duration_seconds' => 2400,
            'sets_done' => 5,
            'exercises_done' => 2,
            'planned_sets' => $planned,
            'finished_at' => $at->toIso8601String(),
            'sets' => $sets,
        ])->assertCreated()->json();
    }

    public function test_every_set_is_recorded_with_its_estimates(): void
    {
        $this->finish(60);

        $sets = $this->user->setLogs()->orderBy('id')->get();
        $this->assertCount(5, $sets);
        $this->assertSame(76.0, $sets[0]->e1rm);
        $this->assertSame(480.0, $sets[0]->volume);
        $this->assertNull($sets[4]->e1rm);
        $this->assertSame(45, $sets[4]->seconds);
    }

    public function test_beating_a_previous_best_is_announced(): void
    {
        $first = $this->finish(60, 7);
        $this->assertSame([], $first['records'], 'Un premier passage n’est pas un record.');

        $second = $this->finish(62.5);

        $this->assertSame(['weight', 'e1rm'], array_column($second['records'], 'kind'));
        $this->assertEquals(62.5, $second['records'][0]['value']);
        $this->assertEquals(60, $second['records'][0]['previous']);
        $this->assertSame('Développé couché', $second['records'][0]['name']);
    }

    public function test_the_home_screen_shows_the_week(): void
    {
        $this->finish(60, 14);
        $this->finish(60, 7);
        $this->finish(65);

        $this->actingAs($this->user)->get('/')
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('kpis.has_data', true)
                ->where('kpis.week.sessions', 1)
                ->where('kpis.week.tonnage', fn ($t) => (float) $t === 2080.0)
                ->where('kpis.week.tonnage_delta', fn ($d) => (float) $d === 8.3)
                ->where('kpis.streak', 3)
                ->where('kpis.records_30d', 1)
                ->has('kpis.tonnage_weeks', 8)
                ->where('kpis.total_sessions', 3)
                ->where('kpis.neglected.0.label', fn (string $label) => $label !== 'Pectoraux'));
    }

    public function test_a_new_account_has_no_kpis_yet(): void
    {
        $this->actingAs(User::factory()->create())->get('/')
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('kpis.has_data', false)
                ->where('kpis.streak', 0)
                ->where('kpis.neglected', []));
    }

    public function test_the_player_knows_the_last_time_and_the_advice(): void
    {
        $this->finish(60, 3);

        $this->actingAs($this->user)->get("/seances/{$this->workout->id}/lancer")
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('history.developpe-couche.sets.0', ['weight' => 60, 'reps' => 8])
                ->where('history.developpe-couche.next', ['weight' => 62.5, 'trend' => 'up'])
                ->where('history.gainage-planche.next', null));
    }

    public function test_the_progress_page_gathers_every_indicator(): void
    {
        $this->finish(60, 35);
        $this->finish(65, 3);

        $this->actingAs($this->user)->get('/progres?vue=muscles')
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('Progress/Index')
                ->where('tab', 'muscles')
                ->has('overview.weeks', 12)
                ->where('overview.totals.sessions', 2)
                ->where('overview.totals.records', 1)
                ->where('exercises.0.slug', 'developpe-couche')
                ->where('exercises.0.best_weight', fn ($w) => (float) $w === 65.0)
                ->where('exercises.0.trend', fn ($t) => (float) $t === 8.3)
                ->where('exercises.0.next.weight', fn ($w) => (float) $w === 67.5)
                ->where('muscles.week.chest', fn ($n) => (float) $n === 4.0)
                ->where('muscles.balances.0.title', 'Poussée / tirage')
                ->has('body.lifts', 1));
    }

    public function test_an_exercise_has_its_own_history(): void
    {
        $this->finish(60, 10, 8);
        $this->finish(70, 2, 6, 8);

        $this->actingAs($this->user)->get('/progres/exercices/developpe-couche')
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('Progress/Exercise')
                ->has('stats.sessions', 2)
                ->has('stats.chart', 2)
                ->where('stats.records.weight.value', fn ($v) => (float) $v === 70.0)
                ->where('stats.total.sets', 8)
                // Deux répétitions manquées : on redescend.
                ->where('stats.next.trend', 'down'));

        $this->actingAs($this->user)->get('/progres/exercices/lancer-de-nain')->assertNotFound();
    }

    public function test_body_weight_gives_relative_strength(): void
    {
        $this->finish(80);

        $this->actingAs($this->user)->post('/progres/poids', ['kg' => '80'])->assertSessionHasNoErrors();
        $this->actingAs($this->user)->post('/progres/poids', ['kg' => '79.5'])->assertSessionHasNoErrors();
        $this->assertSame(1, $this->user->bodyWeights()->count(), 'Une pesée par jour : la dernière l’emporte.');

        $this->actingAs($this->user)->get('/progres')
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('body.latest', fn ($kg) => (float) $kg === 79.5)
                ->where('body.lifts.0.ratio', fn ($r) => (float) $r === round(101.33 / 79.5, 2)));

        $this->actingAs($this->user)->post('/progres/poids', ['kg' => '12'])->assertSessionHasErrors('kg');
    }

    public function test_a_weighing_belongs_to_its_owner(): void
    {
        $weighing = User::factory()->create()->bodyWeights()->create(['kg' => 70, 'measured_on' => today()]);

        $this->actingAs($this->user)->delete("/progres/poids/{$weighing->id}")->assertForbidden();
        $this->assertModelExists($weighing);
    }

    public function test_the_session_feeling_is_noted(): void
    {
        $clientId = (string) Str::uuid();
        $this->actingAs($this->user)->postJson("/seances/{$this->workout->id}/journal", [
            'client_id' => $clientId, 'duration_seconds' => 1800, 'sets_done' => 4, 'exercises_done' => 1, 'finished_at' => now()->toIso8601String(),
        ])->assertCreated();

        $this->actingAs($this->user)->patchJson("/journal/{$clientId}/ressenti", ['rpe' => 8])->assertOk();
        $this->assertSame(8, $this->user->workoutLogs()->sole()->rpe);

        $this->actingAs(User::factory()->create())->patchJson("/journal/{$clientId}/ressenti", ['rpe' => 2])->assertNotFound();
        $this->actingAs($this->user)->patchJson("/journal/{$clientId}/ressenti", ['rpe' => 11])->assertUnprocessable();
    }

    public function test_a_session_left_unfinished_counts_for_nothing(): void
    {
        $this->finish(60, 7);
        // Huit séries prévues, cinq faites : arrêtée en route.
        $response = $this->finish(100, 1, planned: 8);

        $this->assertSame([], $response['records'], 'Une séance incomplète ne bat aucun record.');
        $this->assertFalse($this->user->workoutLogs()->latest('finished_at')->first()->completed);

        $this->actingAs($this->user)->get('/')
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('kpis.total_sessions', 1)
                ->where('kpis.records_30d', 0)
                ->where('workouts.0.last_done', 'il y a 1 semaine'));

        $this->actingAs($this->user)->get("/seances/{$this->workout->id}/lancer")
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('history.developpe-couche.sets.0.weight', fn ($w) => (float) $w === 60.0));

        $this->actingAs($this->user)->get('/progres/exercices/developpe-couche')
            ->assertInertia(fn (AssertableInertia $page) => $page->has('stats.sessions', 1));
    }

    public function test_a_session_without_its_plan_is_not_counted_either(): void
    {
        $this->actingAs($this->user)->postJson("/seances/{$this->workout->id}/journal", [
            'client_id' => (string) Str::uuid(), 'duration_seconds' => 60, 'sets_done' => 1, 'exercises_done' => 1, 'finished_at' => now()->toIso8601String(),
        ])->assertCreated();

        $this->assertFalse($this->user->workoutLogs()->sole()->completed);
        $this->actingAs($this->user)->get('/')->assertInertia(fn (AssertableInertia $page) => $page->where('kpis.has_data', false));
    }
}
