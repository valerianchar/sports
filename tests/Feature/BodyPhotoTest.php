<?php

namespace Tests\Feature;

use App\Enums\PhotoPose;
use App\Models\BodyPhoto;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia;
use Tests\TestCase;

class BodyPhotoTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('local');
        Storage::fake('public');
    }

    /**
     * Un vrai fichier envoyé, dont le type est lu dans le contenu comme en
     * production — un faux fichier de test le déduirait de son nom.
     */
    private function upload(string $name, string $content): UploadedFile
    {
        $path = tempnam(sys_get_temp_dir(), 'photo');
        file_put_contents($path, $content);

        return new UploadedFile($path, $name, null, null, true);
    }

    /** Un vrai petit PNG : un pixel. */
    private function png(string $name = 'face.png'): UploadedFile
    {
        return $this->upload($name, base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNk+M9QDwADhgGAWjR9awAAAABJRU5ErkJggg=='));
    }

    /** Un en-tête JPEG (JFIF) : de quoi être reconnu à son contenu. */
    private function jpeg(string $name = 'IMG_0001.jpg'): UploadedFile
    {
        return $this->upload($name, "\xFF\xD8\xFF\xE0\x00\x10JFIF\x00\x01\x01\x00\x00\x01\x00\x01\x00\x00".str_repeat("\x00", 64)."\xFF\xD9");
    }

    public function test_a_photo_is_stored_privately_and_served_to_its_owner_only(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->from('/progres')->post('/progres/photos', ['photo' => $this->jpeg(), 'pose' => 'face', 'note' => '  Début   du régime '])
            ->assertRedirect('/progres')
            ->assertSessionHasNoErrors();

        $photo = $user->bodyPhotos()->sole();
        $this->assertSame(PhotoPose::Face, $photo->pose);
        $this->assertSame(now('Europe/Paris')->toDateString(), $photo->taken_on->toDateString());
        $this->assertSame('Début du régime', $photo->note);
        $this->assertStringStartsWith("photos/{$user->id}/", $photo->path);
        $this->assertStringEndsWith('.jpg', $photo->path);
        Storage::disk('local')->assertExists($photo->path);
        $this->assertSame([], Storage::disk('public')->allFiles(), 'Jamais sur le disque public.');

        $response = $this->actingAs($user)->get("/progres/photos/{$photo->id}")->assertOk()->assertStreamed();
        $this->assertStringContainsString('private', $response->headers->get('Cache-Control'));
        $this->assertStringNotContainsString('public', $response->headers->get('Cache-Control'));
        $this->assertSame('image/jpeg', $response->headers->get('Content-Type'));

        $this->actingAs(User::factory()->create())->get("/progres/photos/{$photo->id}")->assertForbidden();
    }

    public function test_guests_never_see_a_photo(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user)->post('/progres/photos', ['photo' => $this->png(), 'pose' => 'dos']);
        $photo = $user->bodyPhotos()->sole();

        auth()->logout();

        $this->get("/progres/photos/{$photo->id}")->assertRedirect('/connexion');
        $this->post('/progres/photos', ['photo' => $this->png(), 'pose' => 'dos'])->assertRedirect('/connexion');
    }

    public function test_only_photos_of_a_known_pose_and_reasonable_size_are_accepted(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->post('/progres/photos', ['photo' => $this->upload('photo.jpg', 'pas une image'), 'pose' => 'face'])
            ->assertSessionHasErrors(['photo' => 'Ce fichier n’est pas une photo lisible (JPEG, PNG ou WebP).']);
        $this->actingAs($user)->post('/progres/photos', ['photo' => $this->upload('anim.gif', base64_decode('R0lGODlhAQABAIAAAAAAAP///yH5BAEAAAAALAAAAAABAAEAAAIBRAA7')), 'pose' => 'face'])
            ->assertSessionHasErrors('photo');
        $this->actingAs($user)->post('/progres/photos', ['photo' => UploadedFile::fake()->create('enorme.jpg', 9000, 'image/jpeg'), 'pose' => 'face'])
            ->assertSessionHasErrors(['photo' => 'La photo dépasse 8 Mo.']);
        $this->actingAs($user)->post('/progres/photos', ['photo' => $this->png(), 'pose' => 'cote'])->assertSessionHasErrors('pose');
        $this->actingAs($user)->post('/progres/photos', ['pose' => 'face'])->assertSessionHasErrors('photo');
        $this->actingAs($user)->post('/progres/photos', ['photo' => $this->png(), 'pose' => 'face', 'taken_on' => now()->addDays(2)->toDateString()])->assertSessionHasErrors('taken_on');

        $this->assertSame(0, $user->bodyPhotos()->count());
        $this->assertSame([], Storage::disk('local')->allFiles());
    }

    public function test_only_the_owner_deletes_a_photo_and_its_file_goes_with_it(): void
    {
        $owner = User::factory()->create();
        $this->actingAs($owner)->post('/progres/photos', ['photo' => $this->png(), 'pose' => 'profil']);
        $photo = $owner->bodyPhotos()->sole();

        $this->actingAs(User::factory()->create())->delete("/progres/photos/{$photo->id}")->assertForbidden();
        $this->assertModelExists($photo);
        Storage::disk('local')->assertExists($photo->path);

        $this->actingAs($owner)->delete("/progres/photos/{$photo->id}")->assertRedirect();
        $this->assertModelMissing($photo);
        Storage::disk('local')->assertMissing($photo->path);
    }

    public function test_the_body_tab_lists_photo_urls_without_paths_or_contents(): void
    {
        $user = User::factory()->create();
        $user->bodyWeights()->create(['kg' => 82, 'measured_on' => now('Europe/Paris')->subDays(12)->toDateString()]);
        $this->actingAs($user)->post('/progres/photos', ['photo' => $this->jpeg(), 'pose' => 'face', 'taken_on' => now('Europe/Paris')->subDays(10)->toDateString()]);
        $this->actingAs($user)->post('/progres/photos', ['photo' => $this->png(), 'pose' => 'face']);
        [$latest, $older] = BodyPhoto::query()->orderByDesc('taken_on')->get()->all();

        $this->actingAs($user)->get('/progres?vue=corps')->assertOk()->assertInertia(fn (AssertableInertia $page) => $page
            ->has('body.photos', 2)
            ->has('body.poses', 3)
            ->where('body.photos.0.id', $latest->id)
            ->where('body.photos.0.url', "/progres/photos/{$latest->id}")
            ->where('body.photos.0.kg', null)
            ->where('body.photos.1.url', "/progres/photos/{$older->id}")
            ->where('body.photos.1.kg', fn ($kg) => (float) $kg === 82.0)
            ->missing('body.photos.0.path'));
    }
}
