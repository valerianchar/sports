<?php

namespace Tests\Feature;

use App\Enums\AudioMode;
use App\Models\PushAlert;
use App\Models\PushSubscription;
use App\Models\User;
use App\Support\PushSender;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia;
use Tests\TestCase;

/**
 * Les alertes hors de l'appli : l'abonnement du téléphone, les fins de repos
 * confiées au serveur, leur envoi à l'heure dite — et le mode du son.
 */
class PushAlertTest extends TestCase
{
    use RefreshDatabase;

    /** @var list<array{endpoint: string, payload: array<string, mixed>}> */
    private array $sent = [];

    protected function setUp(): void
    {
        parent::setUp();

        config(['services.webpush.public_key' => 'cle-publique', 'services.webpush.private_key' => 'cle-privee']);

        // Un envoyeur qui note au lieu d'appeler les serveurs de notifications ;
        // l'adresse « …/expire » joue un téléphone désabonné.
        $this->app->instance(PushSender::class, new class($this->sent) extends PushSender
        {
            public function __construct(private array &$sent) {}

            public function send(PushSubscription $subscription, array $payload): bool
            {
                $this->sent[] = ['endpoint' => $subscription->endpoint, 'payload' => $payload];

                return ! str_ends_with($subscription->endpoint, '/expire');
            }
        });
    }

    private function subscribe(User $user, string $endpoint = 'https://web.push.apple.com/abc'): void
    {
        $this->actingAs($user)->postJson('/notifications/abonnement', [
            'endpoint' => $endpoint,
            'keys' => ['p256dh' => 'BPublicKey', 'auth' => 'AuthSecret'],
        ])->assertCreated();
    }

    public function test_a_phone_subscribes_once_and_can_unsubscribe(): void
    {
        $user = User::factory()->create();

        $this->subscribe($user);
        $this->subscribe($user);
        $this->assertSame(1, $user->pushSubscriptions()->count());

        $this->actingAs($user)->deleteJson('/notifications/abonnement', ['endpoint' => 'https://web.push.apple.com/abc'])->assertOk();
        $this->assertSame(0, $user->pushSubscriptions()->count());

        $this->actingAs($user)->postJson('/notifications/abonnement', ['endpoint' => 'http://pas-sur', 'keys' => ['p256dh' => 'x', 'auth' => 'y']])
            ->assertJsonValidationErrors('endpoint');
    }

    public function test_the_test_notification_reaches_every_subscribed_phone(): void
    {
        $user = User::factory()->create();
        $this->subscribe($user);
        $this->subscribe($user, 'https://fcm.googleapis.com/expire');

        $this->actingAs($user)->postJson('/notifications/essai')->assertOk()->assertJson(['sent' => 1]);
        $this->assertCount(2, $this->sent);
        // Le téléphone désabonné est oublié.
        $this->assertSame(1, $user->pushSubscriptions()->count());
    }

    public function test_alerts_go_out_when_due_and_only_once(): void
    {
        $user = User::factory()->create();
        $this->subscribe($user);
        $session = (string) Str::uuid();

        $this->actingAs($user)->putJson("/seances/alertes/{$session}", ['alerts' => [
            ['in' => 0, 'title' => 'C’est reparti !', 'body' => 'Développé couché · série 2/4'],
            ['in' => 90, 'title' => 'Repos'],
        ]])->assertOk()->assertJson(['scheduled' => 2]);

        $this->artisan('alertes:envoyer', ['--une-fois' => true])->assertSuccessful();
        $this->artisan('alertes:envoyer', ['--une-fois' => true])->assertSuccessful();

        $this->assertCount(1, $this->sent);
        $this->assertSame('C’est reparti !', $this->sent[0]['payload']['title']);
        $this->assertSame("seance-{$session}", $this->sent[0]['payload']['tag']);
        $this->assertNull(PushAlert::query()->where('title', 'Repos')->sole()->sent_at);
    }

    public function test_coming_back_to_the_app_cancels_the_alerts(): void
    {
        $user = User::factory()->create();
        $session = (string) Str::uuid();

        $this->actingAs($user)->putJson("/seances/alertes/{$session}", ['alerts' => [['in' => 60, 'title' => 'Repos']]])->assertOk();
        // Repartir en arrière-plan remplace les alertes au lieu de les empiler.
        $this->actingAs($user)->putJson("/seances/alertes/{$session}", ['alerts' => [['in' => 30, 'title' => 'Repos']]])->assertOk();
        $this->assertSame(1, $user->pushAlerts()->count());

        $this->actingAs($user)->deleteJson("/seances/alertes/{$session}")->assertOk();
        $this->assertSame(0, $user->pushAlerts()->count());
    }

    public function test_coming_back_anywhere_cancels_every_pending_alert(): void
    {
        $user = User::factory()->create();
        $other = User::factory()->create();

        foreach ([$user, $user, $other] as $owner) {
            $owner->pushAlerts()->create(['session' => (string) Str::uuid(), 'send_at' => now()->addMinute(), 'title' => 'Repos']);
        }

        $user->pushAlerts()->create(['session' => (string) Str::uuid(), 'send_at' => now()->subMinute(), 'title' => 'Déjà partie', 'sent_at' => now()]);

        // Une séance abandonnée sans repasser par le lecteur : l'appli revenue à l'écran suffit.
        $this->actingAs($user)->deleteJson('/seances/alertes')->assertOk();

        $this->assertSame(0, $user->pushAlerts()->whereNull('sent_at')->count());
        $this->assertSame(1, $user->pushAlerts()->count(), 'Les alertes déjà parties restent');
        $this->assertSame(1, $other->pushAlerts()->count());
    }

    public function test_alerts_late_by_more_than_a_minute_are_dropped(): void
    {
        $user = User::factory()->create();
        $this->subscribe($user);
        $user->pushAlerts()->create(['session' => (string) Str::uuid(), 'send_at' => now()->subMinutes(5), 'title' => 'Vieux']);

        $this->artisan('alertes:envoyer', ['--une-fois' => true])->assertSuccessful();

        $this->assertSame([], $this->sent);
    }

    public function test_someone_else_cannot_cancel_my_alerts(): void
    {
        $user = User::factory()->create();
        $session = (string) Str::uuid();
        $this->actingAs($user)->putJson("/seances/alertes/{$session}", ['alerts' => [['in' => 60, 'title' => 'Repos']]]);

        $this->actingAs(User::factory()->create())->deleteJson("/seances/alertes/{$session}")->assertOk();

        $this->assertSame(1, $user->pushAlerts()->count());
    }

    public function test_the_audio_mode_mixes_with_music_by_default(): void
    {
        $user = User::factory()->create()->fresh();
        $this->assertSame(AudioMode::Mix, $user->audio_mode);

        $this->actingAs($user)->from('/')->put('/reglages', ['sound' => true, 'prep_seconds' => 5, 'countdown_seconds' => 5, 'volume' => 80, 'countdown_sound' => 'bip', 'audio_mode' => 'prioritaire'])
            ->assertRedirect('/');
        $this->assertSame(AudioMode::Priority, $user->fresh()->audio_mode);

        $this->actingAs($user)->get('/')->assertInertia(fn (AssertableInertia $page) => $page
            ->where('auth.user.audio_mode', 'prioritaire')
            ->where('push_public_key', 'cle-publique')
            ->has('audio_modes', 2));
    }
}
