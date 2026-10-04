<?php

namespace App\Enums;

/**
 * La façon de mener une séance de cardio : à allure régulière, en fractionné
 * (efforts courts et intenses, récupération entre deux), ou un peu des deux.
 */
enum CardioStyle: string
{
    case Steady = 'continu';
    case Intervals = 'fractionne';
    case Mixed = 'mixte';

    public function label(): string
    {
        return match ($this) {
            self::Steady => 'Continu',
            self::Intervals => 'Fractionné',
            self::Mixed => 'Mixte',
        };
    }

    public function description(): string
    {
        return match ($this) {
            self::Steady => 'Allure régulière, longs blocs',
            self::Intervals => 'Efforts courts, récup entre deux',
            self::Mixed => 'Un bloc continu, puis du fractionné',
        };
    }
}
