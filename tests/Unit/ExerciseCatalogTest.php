<?php

namespace Tests\Unit;

use App\Enums\Equipment;
use App\Enums\ExerciseMode;
use App\Enums\MuscleGroup;
use App\Support\ExerciseCatalog;
use App\Support\WorkoutDefaults;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class ExerciseCatalogTest extends TestCase
{
    /**
     * @return iterable<string, array{string}>
     */
    public static function slugs(): iterable
    {
        foreach (require __DIR__.'/../../database/data/exercises.php' as $exercise) {
            yield $exercise['slug'] => [$exercise['slug']];
        }
    }

    public function test_the_library_holds_the_86_exercises_of_the_mockup(): void
    {
        $this->assertCount(86, ExerciseCatalog::all());
    }

    #[DataProvider('slugs')]
    public function test_every_exercise_is_complete(string $slug): void
    {
        $exercise = ExerciseCatalog::find($slug);

        $this->assertMatchesRegularExpression('/^[a-z0-9]+(-[a-z0-9]+)*$/', $slug);
        $this->assertNotNull(MuscleGroup::tryFrom($exercise['group']));
        $this->assertNotNull(Equipment::tryFrom($exercise['equipment']));
        $this->assertNotNull(ExerciseMode::tryFrom($exercise['mode']));
        $this->assertNotEmpty($exercise['muscles']);
        $this->assertGreaterThanOrEqual(3, count($exercise['steps']), 'Une fiche compte au moins trois étapes.');
    }

    #[DataProvider('slugs')]
    public function test_every_exercise_has_its_two_images_on_disk(string $slug): void
    {
        foreach (ExerciseCatalog::images($slug) as $image) {
            $this->assertFileExists(public_path($image));
        }
    }

    public function test_cardio_defaults_to_one_long_set(): void
    {
        $this->assertSame(['exercise' => 'rameur', 'mode' => 'time', 'value' => 300, 'sets' => 1, 'rest_sets' => 0, 'rest_after' => 60], WorkoutDefaults::for('rameur'));
        $this->assertSame(10, WorkoutDefaults::for('squat')['value']);
        $this->assertSame(30, WorkoutDefaults::for('gainage-planche')['value']);
    }
}
