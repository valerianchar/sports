<?php

namespace App\Enums;

/**
 * Les sons du compte à rebours. Les sons intégrés sont synthétisés par le
 * navigateur (resources/js/audio.js) : rien à télécharger, rien sous droits.
 * « Mon son » joue le fichier envoyé par l'utilisateur.
 */
enum CountdownSound: string
{
    case Bip = 'bip';
    case DoubleBip = 'double';
    case Cloche = 'cloche';
    case Sifflet = 'sifflet';
    case Claquement = 'claquement';
    case Voix = 'voix';
    case Perso = 'perso';

    public function label(): string
    {
        return match ($this) {
            self::Bip => 'Bip',
            self::DoubleBip => 'Double bip',
            self::Cloche => 'Cloche',
            self::Sifflet => 'Sifflet',
            self::Claquement => 'Claquement',
            self::Voix => 'Voix',
            self::Perso => 'Mon son',
        };
    }
}
