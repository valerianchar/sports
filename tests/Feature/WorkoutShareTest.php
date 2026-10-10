<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Workout;
use App\Notifications\WorkoutShared;
use App\Support\WorkoutEstimate;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Notifications\AnonymousNotifiable;
use Illuminate\Support\Facades\Notification;
use Inertia\Testing\AssertableInertia;
use Tests\TestCase;

/**
 * Dupliquer, partager (lien, mail) et importer une séance ; les supersets.
 */
class WorkoutShareTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_workout_is_duplicated_with_its_settings(): void
    {
        $user = User::factory()->create();
        $workout = Workout::factory()->for($user)->withItems(['developpe-couche', 'butterfly'])->create(['name' => 'Push']);
        $workout->items()->first()->update(['weight' => 60, 'superset' => true]);

        $this->actingAs($user)->post("/seances/{$workout->id}/dupliquer")->assertRedirect();

        $copy = $user->workouts()->latest('id')->first();
        $this->assertSame('Push (copie)', $copy->name);
        $this->assertSame(['developpe-couche', 'butterfly'], $copy->items()->pluck('exercise')->all());
        $this->assertSame(60.0, $copy->items()->first()->weight);
        $this->assertTrue($copy->items()->first()->superset);
    }

    public function test_someone_else_imports_a_shared_workout(): void
    {
        $owner = User::factory()->create(['name' => 'Valérian Charrier']);
        $workout = Workout::factory()->for($owner)->withItems()->create(['name' => 'Push']);

        $url = $this->actingAs($owner)->postJson("/seances/{$workout->id}/partage")->assertOk()->json('url');
        $this->assertSame($url, $this->actingAs($owner)->postJson("/seances/{$workout->id}/partage")->json('url'), 'Le même lien à chaque partage');

        $friend = User::factory()->create();
        $this->actingAs($friend)->get(parse_url($url, PHP_URL_PATH))
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('Workouts/Shared')
                ->where('author', 'Valérian')
                ->where('mine', false)
                ->where('workout.name', 'Push'));

        $this->actingAs($friend)->post(parse_url($url, PHP_URL_PATH))->assertRedirect('/seances');
        $this->assertSame(['Push'], $friend->workouts()->pluck('name')->all());
        $this->assertSame(1, $owner->workouts()->count());
    }

    public function test_only_the_owner_shares_and_unknown_links_are_not_found(): void
    {
        $workout = Workout::factory()->withItems()->create();

        $this->actingAs(User::factory()->create())->postJson("/seances/{$workout->id}/partage")->assertForbidden();
        $this->actingAs(User::factory()->create())->get('/partage/inconnu')->assertNotFound();
    }

    public function test_a_workout_is_shared_by_email(): void
    {
        Notification::fake();
        $owner = User::factory()->create(['name' => 'Valérian Charrier']);
        $workout = Workout::factory()->for($owner)->withItems()->create(['name' => 'Push']);

        $this->actingAs($owner)->postJson("/seances/{$workout->id}/partage/mail", ['email' => 'ami@exemple.fr'])->assertOk();

        Notification::assertSentOnDemand(WorkoutShared::class, function (WorkoutShared $notification, array $channels, AnonymousNotifiable $notifiable): bool {
            $mail = $notification->toMail($notifiable);

            return $notifiable->routes['mail'] === 'ami@exemple.fr'
                && $mail->subject === 'Valérian te partage sa séance « Push »'
                && str_contains($mail->actionUrl, '/partage/');
        });

        $this->actingAs($owner)->postJson("/seances/{$workout->id}/partage/mail", ['email' => 'pas-une-adresse'])->assertJsonValidationErrors('email');
    }

    public function test_a_superset_rests_once_per_round(): void
    {
        $item = ['mode' => 'reps', 'value' => 10, 'sets' => 3, 'rest_sets' => 60, 'rest_after' => 90];
        $tempo = (int) config('sport.seconds_per_rep');

        // Deux exercices séparés : 2 × (3 séries + 2 repos) + un repos entre eux.
        $this->assertSame(2 * (3 * 10 * $tempo + 2 * 60) + 90, WorkoutEstimate::seconds([$item, $item]));
        // En superset : les 6 séries, et 2 repos seulement (après chaque tour).
        $this->assertSame(6 * 10 * $tempo + 2 * 60, WorkoutEstimate::seconds([[...$item, 'superset' => true], $item]));
    }

    public function test_the_last_exercise_cannot_be_linked_to_nothing(): void
    {
        $user = User::factory()->create();
        $item = fn (string $slug): array => ['exercise' => $slug, 'mode' => 'reps', 'value' => 10, 'sets' => 3, 'rest_sets' => 60, 'rest_after' => 90, 'superset' => true];

        $this->actingAs($user)->post('/seances', ['name' => 'Bras', 'items' => [$item('curl-barre'), $item('extension-triceps-poulie')]]);

        $this->assertSame([true, false], $user->workouts()->sole()->items()->orderBy('position')->pluck('superset')->all());
    }

    public function test_the_player_learns_whether_to_warm_up(): void
    {
        $user = User::factory()->create()->fresh();
        $workout = Workout::factory()->for($user)->withItems()->create();

        $this->actingAs($user)->get("/seances/{$workout->id}/lancer")
            ->assertInertia(fn (AssertableInertia $page) => $page->where('preferences.warmup_sets', true));
    }
}
