<?php

namespace App\Enums;

/**
 * Comment les bips du lecteur partagent le son du téléphone avec la musique.
 * iOS décide du reste : une page web ne peut ni forcer la baisse de la musique
 * ni sonner en mode silencieux sans la couper. « Comme une vidéo » fait de la
 * séance un média en lecture (resources/js/nowPlaying.js) : écran verrouillé,
 * centre de contrôle, bips en arrière-plan — au prix de la musique.
 */
enum AudioMode: string
{
    case Mix = 'melange';
    case Priority = 'prioritaire';

    public function label(): string
    {
        return match ($this) {
            self::Mix => 'Avec la musique',
            self::Priority => 'Comme une vidéo',
        };
    }

    public function description(): string
    {
        return match ($this) {
            self::Mix => 'Les bips se glissent par-dessus ta musique sans la couper. En mode silencieux, l’iPhone les tait.',
            self::Priority => 'La séance s’affiche sur l’écran verrouillé et dans le centre de contrôle, avec la progression et les boutons ; les bips sonnent même appli fermée et en mode silencieux. Mais l’iPhone met ta musique en pause.',
        };
    }
}
