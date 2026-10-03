<?php

namespace App\Enums;

/**
 * Familles de matériel, pour le filtre de l'assistant : on vient parfois à la
 * salle pour les machines, parfois pour la barre, parfois sans rien.
 */
enum EquipmentKind: string
{
    case Machine = 'machine';
    case Free = 'free';
    case Bodyweight = 'bodyweight';
    case Conditioning = 'conditioning';

    public function label(): string
    {
        return match ($this) {
            self::Machine => 'Machines et poulies',
            self::Free => 'Charges libres',
            self::Bodyweight => 'Poids du corps',
            self::Conditioning => 'Cardio et fonctionnel',
        };
    }
}
