<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia;
use Tests\TestCase;

class WorkoutAssistantTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_assistant_opens_with_sensible_defaults(): void
    {
        $this->actingAs(User::factory()->create())->get('/seances/assistant')
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('Workouts/Assistant')
                ->where('proposal', null)
                ->where('input.minutes', 45)
                ->where('input.goal', 'volume')
                ->has('goals', 3)
                ->has('equipments', 3));
    }

    public function test_it_proposes_a_session(): void
    {
        $this->actingAs(User::factory()->create())
            ->get('/seances/assistant/proposition?'.http_build_query([
                'muscles' => ['chest', 'triceps'], 'minutes' => 40, 'goal' => 'force', 'equipment' => 'machine', 'warmup' => 1, 'variant' => 3,
            ]))
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('Workouts/Assistant')
                ->where('proposal.name', 'Pectoraux · Bras — 40 min')
                ->has('proposal.items')
                ->where('input.muscles', ['chest', 'triceps'])
                ->where('input.warmup', true)
                ->where('input.variant', 3)
                ->has('exercises')
                ->where('proposal.prescriptions.reps.value', 5)
                ->where('proposal.prescriptions.time.value', 30)
                ->has('proposal.alternatives')
                // La bibliothèque entière ne voyage que sur demande.
                ->missing('library'));
    }

    public function test_every_proposed_exercise_comes_with_replacements_the_page_can_show(): void
    {
        $response = $this->actingAs(User::factory()->create())
            ->get('/seances/assistant/proposition?'.http_build_query(['muscles' => ['quadriceps', 'hamstring'], 'minutes' => 45, 'goal' => 'volume']));

        $props = $response->viewData('page')['props'];
        $known = array_column($props['exercises'], 'slug');

        foreach ($props['proposal']['items'] as $item) {
            $alternatives = $props['proposal']['alternatives'][$item['exercise']];

            $this->assertNotEmpty($alternatives, $item['exercise']);
            $this->assertSame([], array_values(array_diff($alternatives, $known)), 'Remplaçants sans fiche envoyée');
        }
    }

    public function test_the_full_library_loads_on_demand(): void
    {
        $this->actingAs(User::factory()->create())
            ->get('/seances/assistant/proposition?'.http_build_query(['muscles' => ['chest'], 'minutes' => 30, 'goal' => 'volume']), [
                'X-Inertia' => 'true',
                // La version des assets, calculée comme le middleware (absente en CI : pas de build).
                'X-Inertia-Version' => file_exists($manifest = public_path('build/manifest.json')) ? hash_file('xxh128', $manifest) : '',
                'X-Inertia-Partial-Component' => 'Workouts/Assistant',
                'X-Inertia-Partial-Data' => 'library',
            ])
            ->assertOk()
            ->assertJsonCount(365, 'props.library');
    }

    public function test_chosen_reps_and_rests_reach_the_proposal(): void
    {
        $this->actingAs(User::factory()->create())
            ->get('/seances/assistant/proposition?'.http_build_query([
                'muscles' => ['biceps'], 'minutes' => 20, 'goal' => 'volume', 'reps' => 8, 'rest_sets' => 90, 'rest_after' => 45,
            ]))
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('proposal.items.0.value', 8)
                ->where('proposal.items.0.rest_sets', 90)
                ->where('proposal.prescriptions.reps.value', 8)
                ->where('input.reps', 8)
                ->where('input.rest_after', 45)
                ->where('goals.1.prescription.rest_sets', 75));
    }

    public function test_at_least_one_muscle_is_required(): void
    {
        $this->actingAs(User::factory()->create())
            ->from('/seances/assistant')
            ->get('/seances/assistant/proposition?minutes=45&goal=volume')
            ->assertRedirect('/seances/assistant')
            ->assertSessionHasErrors(['muscles' => 'Choisis au moins un muscle à travailler.']);
    }

    public function test_unknown_muscles_and_goals_are_refused(): void
    {
        $this->actingAs(User::factory()->create())
            ->get('/seances/assistant/proposition?muscles[]=nez&minutes=45&goal=magie')
            ->assertSessionHasErrors(['muscles.0', 'goal']);
    }

    public function test_a_proposal_can_be_saved_then_edited(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->post('/seances', [
            'name' => 'Pectoraux — 30 min',
            'items' => [['exercise' => 'developpe-couche', 'mode' => 'reps', 'value' => 10, 'sets' => 3, 'rest_sets' => 75, 'rest_after' => 90]],
            'edit' => true,
        ])->assertRedirect('/seances/'.$user->workouts()->sole()->id.'/modifier');
    }
}
