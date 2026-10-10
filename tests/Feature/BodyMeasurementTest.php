<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia;
use Tests\TestCase;

class BodyMeasurementTest extends TestCase
{
    use RefreshDatabase;

    public function test_measurements_of_the_same_day_complete_one_row(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->from('/progres')->post('/progres/mensurations', ['waist' => '92.4'])
            ->assertRedirect('/progres')
            ->assertSessionHasNoErrors();
        $this->actingAs($user)->post('/progres/mensurations', ['arm' => 35, 'waist' => 91])->assertSessionHasNoErrors();
        $this->actingAs($user)->post('/progres/mensurations', ['neck' => '38.25'])->assertSessionHasNoErrors();

        $this->assertSame(1, $user->bodyMeasurements()->count(), 'Une ligne par jour : chaque saisie la complète.');
        $row = $user->bodyMeasurements()->first();
        $this->assertSame(91.0, $row->waist, 'La dernière valeur du jour l’emporte.');
        $this->assertSame(35.0, $row->arm);
        $this->assertSame(38.3, $row->neck);
        $this->assertNull($row->hips, 'Une mesure non prise reste vide.');
    }

    public function test_the_day_is_the_paris_day(): void
    {
        $user = User::factory()->create();
        // 23 h 30 à Paris le 9 octobre = 21 h 30 UTC.
        $this->travelTo(now()->setDate(2026, 10, 9)->setTime(21, 30));

        $this->actingAs($user)->post('/progres/mensurations', ['waist' => 90])->assertSessionHasNoErrors();
        $this->travelTo(now()->setDate(2026, 10, 9)->setTime(22, 30));
        $this->actingAs($user)->post('/progres/mensurations', ['waist' => 89])->assertSessionHasNoErrors();

        $this->assertSame(['2026-10-09', '2026-10-10'], $user->bodyMeasurements()->orderBy('measured_on')->get()->map(fn ($m) => $m->measured_on->toDateString())->all());
    }

    public function test_a_past_day_can_be_given(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->post('/progres/mensurations', ['hips' => 101, 'measured_on' => now()->subDays(3)->toDateString()])->assertSessionHasNoErrors();
        $this->actingAs($user)->post('/progres/mensurations', ['hips' => 100, 'measured_on' => now()->addDays(2)->toDateString()])->assertSessionHasErrors('measured_on');

        $this->assertSame(1, $user->bodyMeasurements()->count());
    }

    public function test_at_least_one_measurement_within_range_is_required(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->post('/progres/mensurations', [])->assertSessionHasErrors(['waist' => 'Indique au moins une mesure.']);
        $this->actingAs($user)->post('/progres/mensurations', ['waist' => '9'])->assertSessionHasErrors(['waist' => 'Tour de taille, en centimètres : au moins 10.']);
        $this->actingAs($user)->post('/progres/mensurations', ['calf' => 301])->assertSessionHasErrors('calf');
        $this->actingAs($user)->post('/progres/mensurations', ['chest' => 'beaucoup'])->assertSessionHasErrors('chest');
        $this->actingAs($user)->post('/progres/mensurations', ['thigh' => 10, 'arm' => 300])->assertSessionHasNoErrors();

        $this->assertSame(1, $user->bodyMeasurements()->count());
    }

    public function test_only_the_owner_deletes_a_measurement(): void
    {
        $user = User::factory()->create();
        $mine = $user->bodyMeasurements()->create(['measured_on' => today(), 'waist' => 80]);
        $theirs = User::factory()->create()->bodyMeasurements()->create(['measured_on' => today(), 'waist' => 70]);

        $this->actingAs($user)->delete("/progres/mensurations/{$theirs->id}")->assertForbidden();
        $this->assertModelExists($theirs);

        $this->actingAs($user)->delete("/progres/mensurations/{$mine->id}")->assertRedirect();
        $this->assertModelMissing($mine);
    }

    public function test_guests_cannot_measure(): void
    {
        $this->post('/progres/mensurations', ['waist' => 80])->assertRedirect('/connexion');
    }

    public function test_the_body_tab_shows_latest_values_changes_and_charts(): void
    {
        $user = User::factory()->create();
        $user->bodyMeasurements()->create(['measured_on' => now('Europe/Paris')->subDays(60)->toDateString(), 'waist' => 95, 'arm' => 33]);
        $user->bodyMeasurements()->create(['measured_on' => now('Europe/Paris')->subDays(35)->toDateString(), 'waist' => 93]);
        $user->bodyMeasurements()->create(['measured_on' => now('Europe/Paris')->subDays(2)->toDateString(), 'waist' => 90.5]);

        $this->actingAs($user)->get('/progres?vue=corps')->assertOk()->assertInertia(fn (AssertableInertia $page) => $page
            ->component('Progress/Index')
            ->has('body.measurements.kinds', 7)
            ->where('body.measurements.kinds.0.key', 'waist')
            ->where('body.measurements.kinds.0.latest', fn ($cm) => (float) $cm === 90.5)
            ->where('body.measurements.kinds.0.change_30d', fn ($cm) => (float) $cm === -2.5)
            ->where('body.measurements.kinds.0.change_total', fn ($cm) => (float) $cm === -4.5)
            ->has('body.measurements.kinds.0.chart', 3)
            ->where('body.measurements.kinds.3.key', 'arm')
            ->where('body.measurements.kinds.3.latest', fn ($cm) => (float) $cm === 33.0)
            ->where('body.measurements.kinds.3.change_30d', null)
            ->where('body.measurements.kinds.3.change_total', null)
            ->where('body.measurements.kinds.1.latest', null)
            ->has('body.measurements.entries', 3)
            ->where('body.measurements.entries.0.values', fn ($values) => collect($values)->keys()->all() === ['waist']));
    }
}
