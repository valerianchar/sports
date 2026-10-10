<?php

namespace Tests\Feature;

use App\Models\PushSubscription;
use App\Models\User;
use App\Models\Workout;
use App\Support\PushSender;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia;
use Tests\TestCase;

/**
 * Le programme de la semaine : séance du jour, objectif, rappels.
 */
class ScheduleTest extends TestCase
{
    use RefreshDatabase;

    /** @return list<array<string, mixed>> */
    private function week(array $overrides = []): array
    {
        return array_map(fn (int $weekday): array => ['weekday' => $weekday, 'workout_id' => null, 'time' => null, 'remind' => false, ...($overrides[$weekday] ?? [])], range(1, 7));
    }

    public function test_the_week_is_planned_with_a_goal(): void
    {
        $user = User::factory()->create();
        $push = Workout::factory()->for($user)->withItems()->create(['name' => 'Push']);

        $this->actingAs($user)->put('/programme', ['weekly_goal' => 3, 'days' => $this->week([1 => ['workout_id' => $push->id, 'time' => '18:30', 'remind' => true], 3 => ['workout_id' => $push->id, 'remind' => true]])])
            ->assertSessionHasNoErrors();

        $this->assertSame(3, $user->fresh()->weekly_goal);
        $this->assertTrue($user->schedules()->where('weekday', 1)->sole()->remind);
        $this->assertFalse($user->schedules()->where('weekday', 3)->sole()->remind, 'Pas de rappel sans heure');

        $this->actingAs($user)->get('/programme')->assertInertia(fn (AssertableInertia $page) => $page
            ->component('Programme/Index')
            ->where('days.0.workout_id', $push->id)
            ->where('days.0.time', '18:30')
            ->where('weeklyGoal', 3));
    }

    public function test_someone_elses_workout_cannot_be_planned(): void
    {
        $user = User::factory()->create();
        $other = Workout::factory()->withItems()->create();

        $this->actingAs($user)->put('/programme', ['weekly_goal' => 0, 'days' => $this->week([2 => ['workout_id' => $other->id]])])
            ->assertSessionHasErrors('days.1.workout_id');
    }

    public function test_today_shows_the_planned_workout_and_the_week(): void
    {
        $user = User::factory()->create(['weekly_goal' => 3]);
        $push = Workout::factory()->for($user)->withItems()->create(['name' => 'Push']);
        $today = CarbonImmutable::now('Europe/Paris');
        $user->schedules()->create(['weekday' => $today->dayOfWeekIso, 'workout_id' => $push->id, 'time' => '18:00']);
        $push->logs()->forceCreate(['name' => 'Push', 'client_id' => (string) Str::uuid(), 'user_id' => $user->id, 'duration_seconds' => 600, 'sets_done' => 1, 'exercises_done' => 1, 'finished_at' => now(), 'completed' => true]);

        $this->actingAs($user)->get('/')->assertInertia(fn (AssertableInertia $page) => $page
            ->where('todayWorkout.name', 'Push')
            ->where('week.today.time', '18:00')
            ->where('week.goal', 3)
            ->where('week.done', 1)
            ->where("week.days.{$this->index($today)}.done", true)
            ->where("week.days.{$this->index($today)}.today", true));
    }

    private function index(CarbonImmutable $day): int
    {
        return $day->dayOfWeekIso - 1;
    }

    public function test_the_reminder_goes_out_once_at_the_planned_time(): void
    {
        config(['services.webpush.public_key' => 'cle', 'services.webpush.private_key' => 'cle']);
        $sent = [];
        $this->app->instance(PushSender::class, new class($sent) extends PushSender
        {
            public function __construct(private array &$sent) {}

            public function send(PushSubscription $subscription, array $payload): bool
            {
                $this->sent[] = $payload;

                return true;
            }
        });

        $now = CarbonImmutable::now('Europe/Paris');
        $user = User::factory()->create();
        $user->pushSubscriptions()->create(['endpoint' => 'https://web.push.apple.com/x', 'endpoint_hash' => hash('sha256', 'x'), 'public_key' => 'k', 'auth_token' => 'a']);
        $push = Workout::factory()->for($user)->withItems()->create(['name' => 'Push']);
        $user->schedules()->create(['weekday' => $now->dayOfWeekIso, 'workout_id' => $push->id, 'time' => $now->subMinutes(2)->format('H:i'), 'remind' => true]);
        $user->schedules()->create(['weekday' => $now->addDay()->dayOfWeekIso, 'workout_id' => $push->id, 'time' => '00:00', 'remind' => true]);

        $this->artisan('alertes:envoyer', ['--une-fois' => true])->assertSuccessful();
        $this->artisan('alertes:envoyer', ['--une-fois' => true])->assertSuccessful();

        $this->assertCount(1, $sent);
        $this->assertSame("C'est l'heure : Push", $sent[0]['title']);
    }
}
