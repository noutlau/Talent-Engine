<?php

namespace App\Enums;

enum SkillLevel: string
{
    use HasOptions;

    case Debutant = 'debutant';
    case Intermediaire = 'intermediaire';
    case Avance = 'avance';

    public function label(): string
    {
        return match ($this) {
            self::Debutant => 'Débutant',
            self::Intermediaire => 'Intermédiaire',
            self::Avance => 'Avancé',
        };
    }
}
