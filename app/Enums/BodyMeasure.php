<?php

namespace App\Enums;

/**
 * Les mensurations suivies, en centimètres. Chacune est une colonne de
 * body_measurements ; l'ordre est celui de l'affichage, le tour de taille en
 * premier : c'est lui qui renseigne le mieux sur la graisse abdominale.
 */
enum BodyMeasure: string
{
    case Waist = 'waist';
    case Hips = 'hips';
    case Chest = 'chest';
    case Arm = 'arm';
    case Thigh = 'thigh';
    case Calf = 'calf';
    case Neck = 'neck';

    public function label(): string
    {
        return match ($this) {
            self::Waist => 'Tour de taille',
            self::Hips => 'Hanches',
            self::Chest => 'Poitrine',
            self::Arm => 'Bras',
            self::Thigh => 'Cuisse',
            self::Calf => 'Mollet',
            self::Neck => 'Cou',
        };
    }

    /** Où placer le mètre ruban, pour mesurer toujours au même endroit. */
    public function hint(): string
    {
        return match ($this) {
            self::Waist => 'Au niveau du nombril, ventre relâché, en fin d’expiration.',
            self::Hips => 'Au plus large des fesses, pieds joints.',
            self::Chest => 'Sous les aisselles, à hauteur des mamelons, bras le long du corps.',
            self::Arm => 'Au plus large du biceps, bras détendu, toujours le même côté.',
            self::Thigh => 'Au plus large, debout, toujours le même côté.',
            self::Calf => 'Au plus large, debout, toujours le même côté.',
            self::Neck => 'Juste sous la pomme d’Adam.',
        };
    }

    /** @return list<string> */
    public static function columns(): array
    {
        return array_column(self::cases(), 'value');
    }
}
