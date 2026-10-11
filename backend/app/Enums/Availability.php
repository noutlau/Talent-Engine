<?php

namespace App\Enums;

enum Availability: string
{
    use HasOptions;

    case TempsPlein = 'temps_plein';
    case TempsPartiel = 'temps_partiel';

    public function label(): string
    {
        return match ($this) {
            self::TempsPlein => 'Temps plein',
            self::TempsPartiel => 'Temps partiel',
        };
    }
}
