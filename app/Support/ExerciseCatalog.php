<?php

namespace App\Support;

use App\Enums\Equipment;
use App\Enums\ExerciseMode;
use App\Enums\Muscle;
use App\Enums\MuscleGroup;
use Illuminate\Support\Collection;

/**
 * La bibliothèque d'exercices. Elle vit dans le code (database/data/exercises.php)
 * et non en base : c'est un contenu éditorial, livré et versionné avec
 * l'application, pas une donnée que les utilisateurs modifient.
 */
final class ExerciseCatalog
{
    /** @var Collection<string, array<string, mixed>>|null */
    private static ?Collection $exercises = null;

    /**
     * @return Collection<string, array<string, mixed>> indexé par slug
     */
    public static function all(): Collection
    {
        return self::$exercises ??= collect(require database_path('data/exercises.php'))
            ->keyBy('slug');
    }

    /**
     * @return list<string>
     */
    public static function slugs(): array
    {
        return self::all()->keys()->all();
    }

    /**
     * @return array<string, mixed>|null
     */
    public static function find(string $slug): ?array
    {
        return self::all()->get($slug);
    }

    public static function mode(string $slug): ExerciseMode
    {
        return ExerciseMode::from(self::all()[$slug]['mode']);
    }

    public static function equipment(string $slug): Equipment
    {
        return Equipment::from(self::all()[$slug]['equipment']);
    }

    public static function group(string $slug): MuscleGroup
    {
        return MuscleGroup::from(self::all()[$slug]['group']);
    }

    /**
     * Chemins publics des images d'un exercice : départ puis arrivée, ou une
     * seule quand la source n'en propose qu'une.
     *
     * @return list<string>
     */
    public static function images(string $slug): array
    {
        return array_map(
            fn (string $file): string => "/images/exercices/{$slug}/{$file}",
            self::all()[$slug]['images'],
        );
    }

    /**
     * Ce que le navigateur reçoit : les libellés sont résolus ici, l'interface
     * n'a pas à connaître les énumérations.
     *
     * @param  iterable<string>|null  $only  limiter aux exercices d'une séance
     * @return list<array<string, mixed>>
     */
    public static function forClient(?iterable $only = null): array
    {
        $exercises = $only === null
            ? self::all()
            : self::all()->only(collect($only)->unique()->all());

        return $exercises->map(fn (array $exercise): array => [
            'slug' => $exercise['slug'],
            'name' => $exercise['name'],
            'group' => $exercise['group'],
            'group_label' => MuscleGroup::from($exercise['group'])->label(),
            'equipment' => $exercise['equipment'],
            'equipment_label' => Equipment::from($exercise['equipment'])->label(),
            'mode' => $exercise['mode'],
            'primary' => $exercise['primary'],
            'secondary' => $exercise['secondary'],
            'steps' => $exercise['steps'],
            'tip' => $exercise['tip'],
            'images' => self::images($exercise['slug']),
            'credit' => $exercise['credit'],
            // Les noms écrits sur les machines (souvent en anglais) et les surnoms de salle, pour la recherche.
            'aka' => $exercise['aka'] ?? [],
            // Les parties du muscle que l'exercice accentue (clés de database/data/zones.php).
            'zones' => $exercise['zones'] ?? [],
        ])->values()->all();
    }

    /**
     * Libellés des muscles, par clé de la silhouette.
     *
     * @return array<string, string>
     */
    public static function muscles(): array
    {
        return collect(Muscle::cases())
            ->mapWithKeys(fn (Muscle $muscle): array => [$muscle->value => $muscle->label()])
            ->all();
    }

    /**
     * Les groupes dans l'ordre de la bibliothèque.
     *
     * @return list<array{value: string, label: string}>
     */
    public static function groups(): array
    {
        return array_map(
            fn (MuscleGroup $group): array => ['value' => $group->value, 'label' => $group->label()],
            MuscleGroup::cases(),
        );
    }
}
