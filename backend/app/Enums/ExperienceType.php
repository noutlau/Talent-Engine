<?php

namespace App\Enums;

enum ExperienceType: string
{
    use HasOptions;

    case Professionnelle = 'professionnelle';
    case Freelance = 'freelance';
    case Academique = 'academique';
    case Personnelle = 'personnelle';
    case Aucune = 'aucune';

    public function label(): string
    {
        return match ($this) {
            self::Professionnelle => 'Professionnelle (emploi, stage)',
            self::Freelance => 'Freelance',
            self::Academique => 'Académique (projets d\'études)',
            self::Personnelle => 'Projets personnels',
            self::Aucune => 'Aucune pour le moment',
        };
    }
}
