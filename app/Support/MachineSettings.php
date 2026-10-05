<?php

namespace App\Support;

use App\Enums\MuscleGroup;

/**
 * Les réglages des machines de cardio — vitesse et inclinaison du tapis,
 * niveau de résistance du vélo, du rameur, de l'elliptique… — et ce qu'on y
 * met selon l'effort : tranquille (échauffement, retour au calme), régulier
 * (bloc continu) ou intense (fractionné).
 */
final class MachineSettings
{
    /** @var array<string, array{label: string, unit: string, min: float, max: float, step: float}> */
    public const FIELDS = [
        'speed' => ['label' => 'Vitesse', 'unit' => 'km/h', 'min' => 0.5, 'max' => 25, 'step' => 0.5],
        'incline' => ['label' => 'Inclinaison', 'unit' => '%', 'min' => -3, 'max' => 30, 'step' => 0.5],
        'level' => ['label' => 'Niveau', 'unit' => '', 'min' => 1, 'max' => 30, 'step' => 1],
    ];

    /**
     * Ce qui se règle sur chaque machine. L'air bike et la corde n'ont rien :
     * c'est l'effort qui fait la résistance. Le tapis curve, sans moteur, se
     * freine.
     *
     * @var array<string, list<string>>
     */
    private const BY_EQUIPMENT = [
        'treadmill' => ['speed', 'incline'],
        'curve-treadmill' => ['level'],
        'bike' => ['level'],
        'recumbent-bike' => ['level'],
        'spin-bike' => ['level'],
        'elliptical' => ['level', 'incline'],
        'stepper' => ['level'],
        'stair-climber' => ['level'],
        'rower' => ['level'],
        'skierg' => ['level'],
        'arm-ergometer' => ['level'],
    ];

    /** @var array<string, array{easy: float, steady: float, hard: float}> vitesses par exercice de tapis */
    private const SPEED = [
        'course' => ['easy' => 6.5, 'steady' => 9, 'hard' => 12],
        'marche-inclinee' => ['easy' => 4.5, 'steady' => 5.5, 'hard' => 6],
        'sprints-fractionnes' => ['easy' => 6, 'steady' => 10, 'hard' => 14],
    ];

    /** Le rameur et le SkiErg se règlent sur un volet de 1 à 10. */
    private const DAMPER = ['rower', 'skierg'];

    /**
     * @return list<string>
     */
    public static function fields(string $equipment): array
    {
        return self::BY_EQUIPMENT[$equipment] ?? [];
    }

    /**
     * Les réglages conseillés d'un exercice pour un effort donné ; rien hors cardio.
     *
     * @param  'easy'|'steady'|'hard'  $effort
     * @return array{speed?: float, incline?: float, level?: int}
     */
    public static function defaults(string $slug, string $effort = 'steady'): array
    {
        $exercise = ExerciseCatalog::find($slug);

        if ($exercise === null || $exercise['group'] !== MuscleGroup::Cardio->value) {
            return [];
        }

        $equipment = $exercise['equipment'];
        $settings = [];

        foreach (self::fields($equipment) as $field) {
            $settings[$field] = match ($field) {
                'speed' => (self::SPEED[$slug] ?? ['easy' => 5.5, 'steady' => 8.5, 'hard' => 12])[$effort],
                'incline' => match (true) {
                    $slug === 'marche-inclinee' => ['easy' => 6.0, 'steady' => 10.0, 'hard' => 12.0][$effort],
                    $equipment === 'elliptical' => ['easy' => 4.0, 'steady' => 8.0, 'hard' => 12.0][$effort],
                    // 1 % d'inclinaison compense l'absence de vent : la course sur tapis ressemble au dehors.
                    default => 1.0,
                },
                'level' => in_array($equipment, self::DAMPER, true)
                    ? ['easy' => 3, 'steady' => 5, 'hard' => 7][$effort]
                    : ['easy' => 4, 'steady' => 8, 'hard' => 12][$effort],
            };
        }

        return $settings;
    }

    /**
     * Pour l'éditeur et le lecteur : les champs, ce que chaque machine règle,
     * et les valeurs par défaut des exercices de cardio.
     *
     * @return array{fields: array<string, array<string, mixed>>, equipment: array<string, list<string>>, defaults: array<string, array<string, float|int>>}
     */
    public static function forClient(): array
    {
        return [
            'fields' => self::FIELDS,
            'equipment' => self::BY_EQUIPMENT,
            'defaults' => ExerciseCatalog::all()
                ->filter(fn (array $exercise): bool => self::fields($exercise['equipment']) !== [])
                ->map(fn (array $exercise): array => self::defaults($exercise['slug']))
                ->filter()
                ->all(),
        ];
    }
}
