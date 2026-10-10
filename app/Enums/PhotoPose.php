<?php

namespace App\Enums;

/**
 * La pose d'une photo de progression : on ne compare que des photos prises
 * de la même façon.
 */
enum PhotoPose: string
{
    case Face = 'face';
    case Profil = 'profil';
    case Dos = 'dos';

    public function label(): string
    {
        return match ($this) {
            self::Face => 'Face',
            self::Profil => 'Profil',
            self::Dos => 'Dos',
        };
    }
}
