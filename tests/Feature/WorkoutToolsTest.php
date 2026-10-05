<?php

namespace Tests\Feature;

use App\Enums\EquipmentKind;
use App\Models\User;
use App\Models\Workout;
use App\Support\ExerciseCatalog;
use App\Support\MachineSettings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia;
use Tests\TestCase;

/**
 * Les outils autour d'une séance : variantes d'un exercice, réglages des
 * machines de cardio, et l'assistant qui complète une séance commencée.
 */
class WorkoutToolsTest extends TestCase
{
    use RefreshDatabase;

    public function test_an_exercise_has_variants_working_the_same_muscles(): void
    {
        $slugs = $this->actingAs(User::factory()->create())
            ->getJson('/exercices/leg-curl-assis/equivalents?'.http_build_query(['exclude' => ['leg-curl']]))
            ->assertOk()
            ->json('equivalents');

        $this->assertNotEmpty($slugs);
        $this->assertNotContains('leg-curl', $slugs);
        $this->assertNotContains('leg-curl-assis', $slugs);

        foreach ($slugs as $slug) {
            $this->assertContains('hamstring', ExerciseCatalog::find($slug)['primary'], $slug);
        }

        $this->actingAs(User::factory()->create())->getJson('/exercices/inconnu/equivalents')->assertNotFound();
    }

    public function test_machine_settings_are_saved_with_the_workout(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->post('/seances', [
            'name' => 'Tapis',
            'items' => [['exercise' => 'course', 'mode' => 'time', 'value' => 1200, 'sets' => 1, 'rest_sets' => 0, 'rest_after' => 60, 'speed' => 9.5, 'incline' => 1.5]],
        ])->assertRedirect();

        $item = $user->workouts()->sole()->items()->sole();
        $this->assertSame(9.5, $item->speed);
        $this->assertSame(1.5, $item->incline);
        $this->assertNull($item->level);

        $this->actingAs($user)->post('/seances', [
            'name' => 'Trop vite',
            'items' => [['exercise' => 'course', 'mode' => 'time', 'value' => 600, 'sets' => 1, 'rest_sets' => 0, 'rest_after' => 60, 'speed' => 60]],
        ])->assertSessionHasErrors('items.0.speed');
    }

    public function test_the_player_can_change_the_settings_for_next_time(): void
    {
        $user = User::factory()->create();
        $workout = Workout::factory()->for($user)->withItems(['velo', 'rameur'])->create();

        $this->actingAs($user)->patchJson("/seances/{$workout->id}/reglages", ['position' => 1, 'level' => 7])
            ->assertOk()
            ->assertJson(['level' => 7]);

        $this->assertSame(7, $workout->items()->where('position', 1)->sole()->level);

        $this->actingAs(User::factory()->create())->patchJson("/seances/{$workout->id}/reglages", ['position' => 1, 'level' => 3])->assertForbidden();
    }

    public function test_the_shared_props_tell_what_each_machine_sets(): void
    {
        $this->actingAs(User::factory()->create())->get('/')
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('machine_settings.equipment.treadmill', ['speed', 'incline'])
                ->where('machine_settings.defaults.marche-inclinee.incline', fn ($incline) => (float) $incline === 10.0)
                ->where('machine_settings.fields.level.label', 'Niveau'));
    }

    public function test_the_assistant_completes_a_started_session(): void
    {
        $existing = [
            ['exercise' => 'developpe-couche', 'mode' => 'reps', 'value' => 8, 'sets' => 4, 'rest_sets' => 120, 'rest_after' => 120],
            ['exercise' => 'dips-triceps', 'mode' => 'reps', 'value' => 8, 'sets' => 4, 'rest_sets' => 120, 'rest_after' => 120],
        ];

        $response = $this->actingAs(User::factory()->create())
            ->postJson('/seances/assistant/completer', ['items' => $existing, 'minutes' => 20])
            ->assertOk();

        $items = $response->json('items');
        $this->assertNotEmpty($items);
        $this->assertNotContains('developpe-couche', array_column($items, 'exercise'));

        foreach ($items as $item) {
            // Dans l'esprit de la séance : mêmes répétitions, mêmes séries, mêmes repos.
            if ($item['mode'] === 'reps') {
                $this->assertSame(8, $item['value']);
            }

            $this->assertSame(4, $item['sets']);
            $this->assertSame(120, $item['rest_sets']);
            $this->assertNotEmpty(array_intersect(ExerciseCatalog::find($item['exercise'])['primary'], ['chest', 'triceps']), $item['exercise']);
        }

        $this->assertEqualsWithDelta(20 * 60, $response->json('seconds'), 20 * 60 * 0.35);
    }

    public function test_completing_can_aim_at_other_muscles(): void
    {
        $items = $this->actingAs(User::factory()->create())
            ->postJson('/seances/assistant/completer', [
                'items' => [['exercise' => 'squat', 'mode' => 'reps', 'value' => 10, 'sets' => 3, 'rest_sets' => 90, 'rest_after' => 90]],
                'minutes' => 15,
                'muscles' => ['biceps'],
            ])
            ->assertOk()
            ->json('items');

        $this->assertContains('biceps', ExerciseCatalog::find($items[0]['exercise'])['primary']);

        $this->actingAs(User::factory()->create())
            ->postJson('/seances/assistant/completer', ['items' => [], 'minutes' => 500])
            ->assertJsonValidationErrors('minutes');
    }

    public function test_completing_can_ask_for_machines_or_bodyweight(): void
    {
        $existing = [['exercise' => 'developpe-couche', 'mode' => 'reps', 'value' => 10, 'sets' => 3, 'rest_sets' => 90, 'rest_after' => 90]];

        foreach (['machine' => EquipmentKind::Machine, 'bodyweight' => EquipmentKind::Bodyweight] as $value => $kind) {
            $items = $this->actingAs(User::factory()->create())
                ->postJson('/seances/assistant/completer', ['items' => $existing, 'minutes' => 20, 'muscles' => ['chest', 'upper-back'], 'equipment' => $value])
                ->assertOk()
                ->json('items');

            $this->assertNotEmpty($items);

            foreach ($items as $item) {
                $this->assertSame($kind, ExerciseCatalog::equipment($item['exercise'])->kind(), "{$item['exercise']} ({$value})");
            }
        }
    }

    public function test_the_editor_offers_the_equipment_choices(): void
    {
        $this->actingAs(User::factory()->create())->get('/seances/nouvelle')
            ->assertInertia(fn (AssertableInertia $page) => $page->where('equipments.0.value', 'machine')->has('equipments', 3));
    }

    public function test_assistant_cardio_blocks_come_with_machine_settings(): void
    {
        $props = $this->actingAs(User::factory()->create())
            ->get('/seances/assistant/proposition?'.http_build_query(['minutes' => 45, 'goal' => 'cardio', 'style' => 'continu', 'equipment' => 'machine']))
            ->viewData('page')['props'];

        foreach ($props['proposal']['items'] as $item) {
            $fields = MachineSettings::fields(ExerciseCatalog::find($item['exercise'])['equipment']);

            foreach ($fields as $field) {
                $this->assertArrayHasKey($field, $item, "{$item['exercise']} sans {$field}");
            }
        }
    }
}
