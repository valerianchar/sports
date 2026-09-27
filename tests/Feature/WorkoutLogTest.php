<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Workout;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia;
use Tests\TestCase;

class WorkoutLogTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @return array<string, mixed>
     */
    private function payload(array $overrides = []): array
    {
        return [
            'client_id' => (string) Str::uuid(),
            'duration_seconds' => 2710,
            'sets_done' => 18,
            'exercises_done' => 6,
            'finished_at' => now()->subMinute()->toIso8601String(),
            ...$overrides,
        ];
    }

    public function test_a_finished_workout_is_logged(): void
    {
        $user = User::factory()->create();
        $workout = Workout::factory()->for($user)->withItems()->create(['name' => 'Push']);

        $this->actingAs($user)->postJson("/seances/{$workout->id}/journal", $this->payload())->assertCreated();

        $log = $user->workoutLogs()->sole();
        $this->assertSame('Push', $log->name);
        $this->assertSame(2710, $log->duration_seconds);
        $this->assertSame(18, $log->sets_done);
    }

    public function test_a_replayed_log_is_counted_once(): void
    {
        $user = User::factory()->create();
        $workout = Workout::factory()->for($user)->withItems()->create();
        $payload = $this->payload();

        $this->actingAs($user)->postJson("/seances/{$workout->id}/journal", $payload)->assertCreated();
        $this->actingAs($user)->postJson("/seances/{$workout->id}/journal", $payload)->assertOk();

        $this->assertSame(1, $user->workoutLogs()->count());
    }

    public function test_the_log_survives_the_workout(): void
    {
        $user = User::factory()->create();
        $workout = Workout::factory()->for($user)->withItems()->create(['name' => 'Ancienne']);
        $this->actingAs($user)->postJson("/seances/{$workout->id}/journal", $this->payload());

        $workout->delete();

        $this->assertSame('Ancienne', $user->workoutLogs()->sole()->name);
    }

    public function test_the_home_screen_says_when_a_workout_was_last_done(): void
    {
        $user = User::factory()->create();
        $workout = Workout::factory()->for($user)->withItems()->create();
        $this->actingAs($user)->postJson("/seances/{$workout->id}/journal", $this->payload([
            'finished_at' => now()->subDays(2)->toIso8601String(),
        ]));

        $this->actingAs($user)->get('/')
            ->assertInertia(fn (AssertableInertia $page) => $page->where('workouts.0.last_done', 'il y a 2 jours'));
    }

    public function test_nobody_logs_into_someone_elses_workout(): void
    {
        $workout = Workout::factory()->withItems()->create();

        $this->actingAs(User::factory()->create())
            ->postJson("/seances/{$workout->id}/journal", $this->payload())
            ->assertForbidden();
    }

    public function test_an_invalid_log_is_refused_as_json(): void
    {
        $user = User::factory()->create();
        $workout = Workout::factory()->for($user)->withItems()->create();

        $this->actingAs($user)
            ->postJson("/seances/{$workout->id}/journal", $this->payload(['client_id' => 'pas-un-uuid', 'finished_at' => now()->addDay()->toIso8601String()]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['client_id', 'finished_at']);
    }
}
