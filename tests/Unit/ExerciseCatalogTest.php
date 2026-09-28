<?php

namespace Tests\Unit;

use App\Enums\Equipment;
use App\Enums\ExerciseMode;
use App\Enums\Muscle;
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

    public function test_the_library_covers_a_full_gym(): void
    {
        $this->assertGreaterThanOrEqual(350, ExerciseCatalog::all()->count());

        // Chaque groupe de la bibliothèque a de quoi composer une séance.
        foreach (MuscleGroup::cases() as $group) {
            $this->assertGreaterThanOrEqual(15, ExerciseCatalog::all()->where('group', $group->value)->count(), $group->label());
        }
    }

    /**
     * Les slugs des 86 exercices de la première version sont stockés dans des
     * séances en production : aucun ne doit disparaître.
     */
    public function test_no_exercise_of_the_first_version_disappears(): void
    {
        $firstVersion = ['developpe-couche', 'developpe-incline-halteres', 'developpe-couche-smith', 'chest-press', 'butterfly', 'ecarte-vis-a-vis', 'ecarte-halteres', 'dips-pectoraux', 'pompes', 'pompes-trx', 'tirage-vertical-prise-large', 'tirage-vertical-prise-serree', 'rowing-assis', 't-bar-row', 'rowing-barre', 'rowing-haltere-un-bras', 'tractions', 'tractions-assistees', 'pull-over-poulie', 'souleve-de-terre', 'extensions-lombaires', 'rowing-trx', 'developpe-epaules-machine', 'developpe-militaire', 'developpe-arnold', 'elevations-laterales', 'elevations-laterales-poulie', 'oiseau-inverse', 'face-pull', 'elevations-frontales', 'shrugs', 'curl-barre', 'curl-halteres', 'curl-marteau', 'curl-poulie', 'curl-pupitre', 'extension-triceps-poulie', 'barre-au-front', 'dips-triceps', 'extension-nuque-haltere', 'kickback-triceps', 'presse-a-cuisses', 'hack-squat', 'squat', 'squat-smith', 'leg-extension', 'leg-curl', 'fentes-marchees', 'squat-bulgare', 'goblet-squat', 'souleve-de-terre-roumain', 'mollets-debout', 'mollets-assis', 'adducteurs', 'jump-squats', 'chaise', 'hip-thrust', 'hip-thrust-barre', 'kickback-machine', 'abducteurs', 'kettlebell-swing', 'pont-fessier', 'kickback-poulie', 'crunch', 'crunch-poulie-haute', 'releves-de-jambes-suspendu', 'releves-de-genoux', 'gainage-planche', 'gainage-lateral', 'russian-twist', 'mountain-climbers', 'roue-abdominale', 'pallof-press', 'hollow-hold', 'course', 'marche-inclinee', 'sprints-fractionnes', 'velo', 'air-bike', 'rameur', 'elliptique', 'escalier', 'skierg', 'corde-a-sauter', 'burpees', 'jumping-jacks'];

        $this->assertCount(86, $firstVersion);
        $this->assertSame([], array_values(array_diff($firstVersion, ExerciseCatalog::slugs())));
    }

    #[DataProvider('slugs')]
    public function test_every_exercise_is_complete(string $slug): void
    {
        $exercise = ExerciseCatalog::find($slug);

        $this->assertMatchesRegularExpression('/^[a-z0-9]+(-[a-z0-9]+)*$/', $slug);
        $this->assertNotNull(MuscleGroup::tryFrom($exercise['group']));
        $this->assertNotNull(Equipment::tryFrom($exercise['equipment']));
        $this->assertNotNull(ExerciseMode::tryFrom($exercise['mode']));
        $this->assertNotEmpty($exercise['primary'], 'Un exercice sollicite au moins un muscle principal.');

        foreach ([...$exercise['primary'], ...$exercise['secondary']] as $muscle) {
            $this->assertNotNull(Muscle::tryFrom($muscle), "Muscle inconnu : {$muscle}");
        }

        $this->assertEmpty(array_intersect($exercise['primary'], $exercise['secondary']), 'Un muscle est principal ou secondaire, pas les deux.');
        // Une photo wger (Creative Commons) nomme son auteur et sa licence.
        $this->assertTrue(
            in_array($exercise['credit'], ['free-exercise-db', 'illustration'], true)
                || preg_match('/^.+ — CC (BY|BY-SA|0)( [\d.]+)? \(wger\)$/u', (string) $exercise['credit']) === 1,
            "Crédit d'image inattendu : {$exercise['credit']}",
        );
        $this->assertGreaterThanOrEqual(3, count($exercise['steps']), 'Une fiche compte au moins trois étapes.');
    }

    #[DataProvider('slugs')]
    public function test_every_exercise_has_its_images_on_disk(string $slug): void
    {
        $images = ExerciseCatalog::images($slug);
        $this->assertContains(count($images), [1, 2]);

        foreach ($images as $image) {
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
