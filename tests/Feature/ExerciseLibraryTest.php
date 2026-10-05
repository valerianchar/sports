<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia;
use Tests\TestCase;

class ExerciseLibraryTest extends TestCase
{
    use RefreshDatabase;

    /**
     * On cherche une machine par ce qui est écrit dessus : la bibliothèque reçoit
     * les autres noms de chaque exercice pour que la recherche les parcoure.
     */
    public function test_the_library_sends_the_names_printed_on_the_machines(): void
    {
        $this->actingAs(User::factory()->create())->get('/exercices')
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('Exercises/Index')
                ->where('exercises', function ($exercises): bool {
                    $bySlug = collect($exercises)->keyBy('slug');

                    return $bySlug->every(fn ($exercise): bool => is_array($exercise['aka']))
                        && in_array('Seated Leg Curl', $bySlug['leg-curl-assis']['aka'], true)
                        && in_array('Hip Abduction', $bySlug['abducteurs']['aka'], true)
                        && in_array('Calf Extension', $bySlug['presse-a-mollets']['aka'], true);
                }));
    }
}
