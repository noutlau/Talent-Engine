<?php

namespace App\Enums;

enum EducationLevel: string
{
    use HasOptions;

    case Bac = 'bac';
    case Bac2 = 'bac+2';
    case Bac3 = 'bac+3';
    case Bac5 = 'bac+5';
    case Autre = 'autre';

    public function label(): string
    {
        return match ($this) {
            self::Bac => 'Bac',
            self::Bac2 => 'Bac+2 (BTS, DUT)',
            self::Bac3 => 'Bac+3 (Licence)',
            self::Bac5 => 'Bac+5 et plus (Master, ingénieur)',
            self::Autre => 'Autre / sans diplôme',
        };
    }
}
