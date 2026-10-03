<?php

namespace Tests\Feature;

use App\Enums\CountdownSound;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class CustomSoundTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Un vrai petit WAV : 0,1 s de silence en 8 kHz mono.
     */
    private function wav(string $name = 'bip.wav'): UploadedFile
    {
        $samples = str_repeat("\x80", 800);
        $header = 'RIFF'.pack('V', 36 + strlen($samples)).'WAVEfmt '.pack('VvvVVvv', 16, 1, 1, 8000, 8000, 1, 8).'data'.pack('V', strlen($samples));

        return UploadedFile::fake()->createWithContent($name, $header.$samples);
    }

    public function test_new_accounts_count_down_with_a_beep(): void
    {
        $this->assertSame(CountdownSound::Bip, User::factory()->create()->fresh()->countdown_sound);
    }

    public function test_a_built_in_sound_can_be_chosen(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->put('/reglages', ['sound' => true, 'prep_seconds' => 5, 'countdown_seconds' => 5, 'volume' => 80, 'countdown_sound' => 'cloche'])
            ->assertSessionHasNoErrors();

        $this->assertSame(CountdownSound::Cloche, $user->fresh()->countdown_sound);
    }

    public function test_my_sound_needs_a_file_first(): void
    {
        $this->actingAs(User::factory()->create())
            ->put('/reglages', ['sound' => true, 'prep_seconds' => 5, 'countdown_seconds' => 5, 'volume' => 80, 'countdown_sound' => 'perso'])
            ->assertSessionHasErrors(['countdown_sound' => 'Envoie d’abord un fichier pour utiliser « Mon son ».']);
    }

    public function test_an_uploaded_sound_is_stored_selected_and_served_to_its_owner(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->from('/')->post('/reglages/son', ['sound' => $this->wav('Mon décompte.wav')])
            ->assertRedirect('/')
            ->assertSessionHasNoErrors();

        $user->refresh();
        $this->assertSame(CountdownSound::Perso, $user->countdown_sound);
        $this->assertSame('Mon décompte.wav', $user->custom_sound_name);
        Storage::disk('local')->assertExists($user->custom_sound_path);
        $this->assertStringContainsString('/reglages/son', $user->custom_sound_url);

        $this->actingAs($user)->get('/reglages/son')->assertOk()->assertStreamed();
    }

    public function test_replacing_the_sound_removes_the_old_file(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user)->post('/reglages/son', ['sound' => $this->wav()]);
        $first = $user->fresh()->custom_sound_path;

        $this->actingAs($user)->post('/reglages/son', ['sound' => $this->wav('autre.wav')]);

        Storage::disk('local')->assertMissing($first);
        Storage::disk('local')->assertExists($user->fresh()->custom_sound_path);
    }

    public function test_only_sounds_are_accepted_and_small_ones(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->post('/reglages/son', ['sound' => UploadedFile::fake()->create('photo.jpg', 10, 'image/jpeg')])
            ->assertSessionHasErrors(['sound' => 'Ce fichier n’est pas un son lisible (mp3, m4a, wav ou ogg).']);

        $this->actingAs($user)->post('/reglages/son', ['sound' => UploadedFile::fake()->create('long.mp3', 3000, 'audio/mpeg')])
            ->assertSessionHasErrors(['sound' => 'Le fichier dépasse 2 Mo : garde quelques secondes seulement.']);

        $this->assertNull($user->fresh()->custom_sound_path);
    }

    public function test_without_a_sound_there_is_nothing_to_fetch(): void
    {
        $this->actingAs(User::factory()->create())->get('/reglages/son')->assertNotFound();
    }

    public function test_deleting_the_sound_goes_back_to_the_beep(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user)->post('/reglages/son', ['sound' => $this->wav()]);
        $path = $user->fresh()->custom_sound_path;

        $this->actingAs($user)->delete('/reglages/son')->assertSessionHasNoErrors();

        $user->refresh();
        Storage::disk('local')->assertMissing($path);
        $this->assertNull($user->custom_sound_path);
        $this->assertSame(CountdownSound::Bip, $user->countdown_sound);
    }

    public function test_the_player_learns_which_sound_to_play(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user)->post('/reglages/son', ['sound' => $this->wav()]);
        $workout = $user->workouts()->create(['name' => 'Test']);
        $workout->items()->create(['position' => 0, 'exercise' => 'squat', 'mode' => 'reps', 'value' => 10, 'sets' => 3, 'rest_sets' => 60, 'rest_after' => 90]);

        $this->actingAs($user->fresh())->get("/seances/{$workout->id}/lancer")
            ->assertInertia(fn ($page) => $page
                ->where('preferences.countdown_sound', 'perso')
                ->where('preferences.custom_sound_url', fn (string $url): bool => str_contains($url, '/reglages/son')));
    }
}
