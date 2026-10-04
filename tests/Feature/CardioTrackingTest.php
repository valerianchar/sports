<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Workout;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia;
use Tests\TestCase;

class CardioTrackingTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private Workout $workout;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create();
        $this->workout = Workout::factory()->for($this->user)->withItems(['velo', 'rameur'])->create(['name' => 'Cardio']);
    }

    /**
     * Une demi-heure de cardio : 10 minutes de vélo, 15 de rameur.
     *
     * @return array<string, mixed>
     */
    private function ride(int $planned = 2): array
    {
        $at = now()->toIso8601String();

        return $this->actingAs($this->user)->postJson("/seances/{$this->workout->id}/journal", [
            'client_id' => (string) Str::uuid(),
            'duration_seconds' => 1800,
            'sets_done' => 2,
            'exercises_done' => 2,
            'planned_sets' => $planned,
            'finished_at' => $at,
            'sets' => [
                ['exercise' => 'velo', 'position' => 0, 'set' => 1, 'seconds' => 600, 'at' => $at],
                ['exercise' => 'rameur', 'position' => 1, 'set' => 1, 'seconds' => 900, 'at' => $at],
            ],
        ])->assertCreated()->json();
    }

    public function test_the_end_screen_gets_the_calories_of_a_complete_session(): void
    {
        $this->user->bodyWeights()->create(['measured_on' => now()->subDays(3)->toDateString(), 'kg' => 90]);

        // 4 MET × 0,5 h × 90 kg = 180, + vélo (2,8 MET × 600 s) et rameur (3 MET × 900 s) = 289,5.
        $this->assertSame(290, $this->ride()['kcal']);
        $this->assertNull($this->ride(planned: 5)['kcal'], 'Une séance incomplète ne compte pas.');
    }

    public function test_the_home_screen_counts_cardio_minutes_and_calories(): void
    {
        $this->ride();

        $this->actingAs($this->user)->get('/')
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('kpis.week.cardio_minutes', 25)
                ->where('kpis.cardio_weeks.7.value', 25)
                // Sans pesée : 75 kg.
                ->where('kpis.week.kcal', 241)
                ->where('kpis.body.latest', null));

        $this->actingAs($this->user)->get('/progres')
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('overview.totals.cardio_minutes', 25)
                ->where('overview.totals.kcal', 241)
                ->where('overview.weeks.11.cardio_minutes', 25));
    }

    public function test_a_target_weight_shows_what_is_left(): void
    {
        $this->actingAs($this->user)->post('/progres/poids', ['kg' => '84.5']);
        $this->actingAs($this->user)->patch('/progres/objectif-poids', ['kg' => '78'])->assertSessionHasNoErrors();

        $this->actingAs($this->user)->get('/progres?vue=corps')
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('body.target', fn ($kg) => (float) $kg === 78.0)
                ->where('body.to_go', fn ($kg) => (float) $kg === 6.5));

        $this->actingAs($this->user)->get('/')
            ->assertInertia(fn (AssertableInertia $page) => $page->where('kpis.body.to_go', fn ($kg) => (float) $kg === 6.5));

        $this->actingAs($this->user)->patch('/progres/objectif-poids', ['kg' => null])->assertSessionHasNoErrors();
        $this->assertNull($this->user->fresh()->target_weight);

        $this->actingAs($this->user)->patch('/progres/objectif-poids', ['kg' => '400'])->assertSessionHasErrors('kg');
    }
}
