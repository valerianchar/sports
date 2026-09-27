<?php

namespace App\Enums;

/**
 * Les groupes de la bibliothèque, dans l'ordre où la maquette les présente :
 * du haut du corps vers le bas, le cardio à la fin.
 */
enum MuscleGroup: string
{
    case Pectoraux = 'pectoraux';
    case Dos = 'dos';
    case Epaules = 'epaules';
    case Bras = 'bras';
    case Jambes = 'jambes';
    case Fessiers = 'fessiers';
    case Abdos = 'abdos';
    case Cardio = 'cardio';

    public function label(): string
    {
        return match ($this) {
            self::Pectoraux => 'Pectoraux',
            self::Dos => 'Dos',
            self::Epaules => 'Épaules',
            self::Bras => 'Bras',
            self::Jambes => 'Jambes',
            self::Fessiers => 'Fessiers',
            self::Abdos => 'Abdos',
            self::Cardio => 'Cardio',
        };
    }
}
