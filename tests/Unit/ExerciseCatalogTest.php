<?php

namespace Tests\Unit;

use App\Enums\Equipment;
use App\Enums\EquipmentKind;
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

        // Les autres noms (plaques des machines, surnoms) : une liste de libellés, sans doublon.
        $this->assertIsList($exercise['aka']);
        $this->assertSame(array_unique($exercise['aka']), $exercise['aka']);

        foreach ($exercise['aka'] as $alias) {
            $this->assertIsString($alias);
            $this->assertNotSame('', trim($alias));
        }
    }

    /**
     * Les machines photographiées en salle (plaques Matrix) se retrouvent par le
     * nom écrit dessus — dans le nom de la fiche ou dans ses autres noms.
     */
    public function test_the_machines_of_the_gym_are_findable_by_their_plate(): void
    {
        $plates = [
            'Calf Press' => 'presse-a-mollets',
            'Seated Leg Curl' => 'leg-curl-assis',
            'Hip Abduction' => 'abducteurs',
            'Hip Adduction' => 'adducteurs',
            'Prone Leg Curl' => 'leg-curl',
            'Converging Chest Press' => 'presse-pectorale-convergente',
            'Diverging Lat Pulldown' => 'tirage-vertical-divergent',
            'Diverging Seated Row' => 'rowing-machine',
            'Pectoral Fly / Rear Delt' => 'oiseau-inverse',
            'Dip/Chin Assist' => 'tractions-assistees',
            'Rotary Torso' => 'rotation-du-buste-machine',
            'Abdominal Crunch' => 'crunch-machine',
            'Triceps Press' => 'dips-machine',
            'Kneeling Leg Curl' => 'leg-curl-debout',
        ];

        foreach ($plates as $plate => $slug) {
            $exercise = ExerciseCatalog::find($slug);
            $names = array_map(mb_strtolower(...), [$exercise['name'], ...$exercise['aka']]);

            $this->assertNotEmpty(
                array_filter($names, fn (string $name): bool => str_contains($name, mb_strtolower($plate))),
                "{$plate} → {$slug}",
            );
        }
    }

    public function test_the_machines_added_from_the_gym_are_complete(): void
    {
        $added = [
            'presse-a-mollets' => Equipment::CalfPress,
            'reverse-hyper' => Equipment::ReverseHyper,
            'shrugs-machine-iso-laterale' => Equipment::ShrugMachine,
            'fentes-machine' => Equipment::SquatLunge,
            'hip-thrust-debout-machine' => Equipment::HipThrustMachine,
        ];

        foreach ($added as $slug => $equipment) {
            $this->assertSame($equipment, ExerciseCatalog::equipment($slug), $slug);
            $this->assertSame(EquipmentKind::Machine, $equipment->kind(), $slug);
            $this->assertNotEmpty(ExerciseCatalog::find($slug)['aka'], $slug);
        }

        // La presse à mollets est une machine à part, pas les mollets à la presse à cuisses.
        $this->assertNotSame(ExerciseCatalog::equipment('mollets-presse'), ExerciseCatalog::equipment('presse-a-mollets'));
        $this->assertSame(['calves'], ExerciseCatalog::find('presse-a-mollets')['primary']);
    }

    public function test_every_equipment_is_used(): void
    {
        $used = ExerciseCatalog::all()->pluck('equipment')->unique();

        foreach (Equipment::cases() as $equipment) {
            $this->assertContains($equipment->value, $used, $equipment->label());
        }
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
        $this->assertSame(['exercise' => 'rameur', 'mode' => 'time', 'value' => 300, 'sets' => 1, 'rest_sets' => 0, 'rest_after' => 60, 'level' => 5], WorkoutDefaults::for('rameur'));
        $this->assertSame(10, WorkoutDefaults::for('squat')['value']);
        $this->assertSame(30, WorkoutDefaults::for('gainage-planche')['value']);
    }

    /**
     * Les clés de zones (pectoraux.haut, dos.largeur…) définies dans database/data/zones.php.
     *
     * @return list<string>
     */
    private static function zoneKeys(): array
    {
        $groups = require __DIR__.'/../../database/data/zones.php';

        return array_merge(...array_map(fn (array $group): array => array_keys($group['zones']), array_values($groups)));
    }

    public function test_every_exercise_lists_known_zones(): void
    {
        $keys = self::zoneKeys();

        foreach (ExerciseCatalog::all() as $slug => $exercise) {
            $this->assertArrayHasKey('zones', $exercise, $slug);
            $this->assertIsList($exercise['zones'], $slug);
            $this->assertSame(array_values(array_unique($exercise['zones'])), $exercise['zones'], "Zone en double : {$slug}");

            foreach ($exercise['zones'] as $zone) {
                $this->assertContains($zone, $keys, "Zone inconnue pour {$slug} : {$zone}");
            }
        }
    }

    /**
     * L'assistant propose de quoi compléter une zone oubliée : chacune doit
     * avoir plusieurs exercices.
     */
    public function test_every_zone_is_trained_by_several_exercises(): void
    {
        $used = ExerciseCatalog::all()->pluck('zones')->flatten()->countBy();

        foreach (self::zoneKeys() as $zone) {
            $this->assertGreaterThanOrEqual(3, $used->get($zone, 0), $zone);
        }
    }

    public function test_the_zones_match_the_classic_exercises(): void
    {
        $expected = [
            'developpe-incline-halteres' => 'pectoraux.haut',
            'developpe-couche' => 'pectoraux.milieu',
            'butterfly' => 'pectoraux.interieur',
            'dips-pectoraux' => 'pectoraux.bas',
            'tractions' => 'dos.largeur',
            'curl-marteau' => 'biceps.brachial',
            'extension-nuque-haltere' => 'triceps.longue',
            'leg-extension' => 'quadriceps.droit',
            'mollets-assis' => 'mollets.soleaire',
        ];

        foreach ($expected as $slug => $zone) {
            $this->assertContains($zone, ExerciseCatalog::find($slug)['zones'], $slug);
        }

        // Un étirement ne travaille pas de zone.
        $this->assertSame([], ExerciseCatalog::find('etirement-pectoraux-mur')['zones']);
        $this->assertSame(ExerciseCatalog::find('butterfly')['zones'], collect(ExerciseCatalog::forClient(['butterfly']))->first()['zones']);
    }
}
