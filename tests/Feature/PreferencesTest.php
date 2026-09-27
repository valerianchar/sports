<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PreferencesTest extends TestCase
{
    use RefreshDatabase;

    public function test_new_accounts_beep_and_count_down_five_seconds(): void
    {
        $user = User::factory()->create()->fresh();

        $this->assertTrue($user->sound);
        $this->assertSame(5, $user->prep_seconds);
    }

    public function test_the_player_settings_can_be_changed(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->from('/')->put('/reglages', ['sound' => false, 'prep_seconds' => 10])
            ->assertRedirect('/');

        $user->refresh();
        $this->assertFalse($user->sound);
        $this->assertSame(10, $user->prep_seconds);
    }

    public function test_the_countdown_is_capped(): void
    {
        $this->actingAs(User::factory()->create())
            ->put('/reglages', ['sound' => true, 'prep_seconds' => 60])
            ->assertSessionHasErrors('prep_seconds');
    }
}
