<?php

namespace App\Support;

use App\Actions\SuggestWorkout;
use App\Enums\EquipmentKind;
use App\Enums\MuscleGroup;
use Illuminate\Support\Collection;

/**
 * Les zones de chaque grand muscle (database/data/zones.php) : le haut, le
 * bas, l'intérieur des pectoraux… Une séance qui travaille un muscle devrait
 * en toucher toutes les zones ; il suffit de savoir lesquelles ses exercices
 * couvrent, et quoi ajouter pour les autres.
 */
final class MuscleZones
{
    /** @var array<string, array{label: string, muscles: list<string>, zones: array<string, array{label: string, hint: string}>}>|null */
    private static ?array $groups = null;

    /**
     * @return array<string, array{label: string, muscles: list<string>, zones: array<string, array{label: string, hint: string}>}>
     */
    public static function groups(): array
    {
        return self::$groups ??= require database_path('data/zones.php');
    }

    public static function exists(string $zone): bool
    {
        return self::groupOf($zone) !== null;
    }

    public static function groupOf(string $zone): ?string
    {
        foreach (self::groups() as $key => $group) {
            if (isset($group['zones'][$zone])) {
                return $key;
            }
        }

        return null;
    }

    /**
     * Les zones des groupes dont un muscle est visé.
     *
     * @param  list<string>  $muscles
     * @return list<string>
     */
    public static function zonesOfMuscles(array $muscles): array
    {
        return collect(self::groups())
            ->filter(fn (array $group): bool => array_intersect($group['muscles'], $muscles) !== [])
            ->flatMap(fn (array $group): array => array_keys($group['zones']))
            ->values()
            ->all();
    }

    /**
     * Pour l'éditeur et l'assistant : les groupes, leurs muscles et leurs zones.
     *
     * @return list<array{key: string, label: string, muscles: list<string>, zones: list<array{key: string, label: string, hint: string}>}>
     */
    public static function forClient(): array
    {
        return array_values(array_map(
            fn (string $key, array $group): array => [
                'key' => $key,
                'label' => $group['label'],
                'muscles' => $group['muscles'],
                'zones' => array_values(array_map(
                    fn (string $zone, array $definition): array => ['key' => $zone, ...$definition],
                    array_keys($group['zones']),
                    $group['zones'],
                )),
            ],
            array_keys(self::groups()),
            self::groups(),
        ));
    }

    /**
     * Les meilleurs exercices pour travailler une zone qui manque : les
     * classiques d'abord, ceux qui visent surtout cette zone, dans le matériel
     * demandé si possible.
     *
     * @param  list<string>  $exclude  exercices déjà dans la séance
     * @return list<string>
     */
    public static function suggest(string $zone, array $exclude = [], ?EquipmentKind $equipment = null, int $limit = 5): array
    {
        $group = self::groups()[self::groupOf($zone)];

        /** @var Collection<string, array<string, mixed>> $candidates */
        $candidates = ExerciseCatalog::all()
            ->except($exclude)
            ->filter(fn (array $exercise): bool => in_array($zone, $exercise['zones'] ?? [], true))
            ->reject(fn (array $exercise): bool => in_array($exercise['group'], [MuscleGroup::Cardio->value, MuscleGroup::Mobilite->value], true));

        if ($equipment !== null) {
            $kept = $candidates->filter(fn (array $exercise): bool => ExerciseCatalog::equipment($exercise['slug'])->kind() === $equipment);
            $candidates = $kept->isNotEmpty() ? $kept : $candidates;
        }

        return $candidates
            ->sortByDesc(function (array $exercise) use ($group): float {
                $score = SuggestWorkout::isStaple($exercise['slug']) ? 1.0 : 0.0;
                $score -= SuggestWorkout::isAdvanced($exercise['slug']) ? 1.0 : 0.0;
                // Le muscle de la zone en premier rôle, et peu d'autres zones : un exercice ciblé.
                $score += array_intersect($group['muscles'], $exercise['primary']) !== [] ? 0.6 : 0.0;
                $score -= 0.1 * count($exercise['zones'] ?? []);

                return $score;
            })
            ->keys()
            ->take($limit)
            ->values()
            ->all();
    }
}
