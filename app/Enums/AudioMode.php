<?php

namespace App\Enums;

/**
 * Comment les bips du lecteur partagent le son du téléphone avec la musique.
 * iOS décide du reste : une page web ne peut ni forcer la baisse de la musique
 * ni sonner en mode silencieux sans la couper.
 */
enum AudioMode: string
{
    case Mix = 'melange';
    case Priority = 'prioritaire';

    public function label(): string
    {
        return match ($this) {
            self::Mix => 'Avec la musique',
            self::Priority => 'Prioritaire',
        };
    }

    public function description(): string
    {
        return match ($this) {
            self::Mix => 'Les bips se glissent par-dessus ta musique sans la couper. En mode silencieux, l’iPhone les tait.',
            self::Priority => 'Les bips sonnent même en mode silencieux, mais l’iPhone met ta musique en pause.',
        };
    }
}
