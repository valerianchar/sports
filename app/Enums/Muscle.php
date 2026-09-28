<?php

namespace App\Enums;

/**
 * Les muscles de la silhouette (resources/js/data/body.js) : chaque valeur
 * nomme une zone du tracé, face ou dos. Les abducteurs n'ont pas de zone à eux —
 * le moyen fessier, qui fait l'essentiel du travail, s'allume sur les fessiers.
 * Les élévations latérales, qui visent le faisceau moyen, allument l'avant et
 * l'arrière de l'épaule.
 */
enum Muscle: string
{
    case Chest = 'chest';
    case FrontDeltoids = 'front-deltoids';
    case RearDeltoids = 'rear-deltoids';
    case Biceps = 'biceps';
    case Triceps = 'triceps';
    case Forearm = 'forearm';
    case Abs = 'abs';
    case Obliques = 'obliques';
    case Trapezius = 'trapezius';
    case UpperBack = 'upper-back';
    case LowerBack = 'lower-back';
    case Gluteal = 'gluteal';
    case Quadriceps = 'quadriceps';
    case Hamstring = 'hamstring';
    case Adductors = 'adductors';
    case Calves = 'calves';
    case Tibialis = 'tibialis';
    case Neck = 'neck';

    public function label(): string
    {
        return match ($this) {
            self::Chest => 'Pectoraux',
            self::FrontDeltoids => 'Épaules (avant)',
            self::RearDeltoids => 'Épaules (arrière)',
            self::Biceps => 'Biceps',
            self::Triceps => 'Triceps',
            self::Forearm => 'Avant-bras',
            self::Abs => 'Abdominaux',
            self::Obliques => 'Obliques',
            self::Trapezius => 'Trapèzes',
            self::UpperBack => 'Dorsaux',
            self::LowerBack => 'Lombaires',
            self::Gluteal => 'Fessiers',
            self::Quadriceps => 'Quadriceps',
            self::Hamstring => 'Ischio-jambiers',
            self::Adductors => 'Adducteurs',
            self::Calves => 'Mollets',
            self::Tibialis => 'Tibias',
            self::Neck => 'Cou',
        };
    }
}
