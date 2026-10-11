<?php

namespace App\Enums;

enum Category: string
{
    use HasOptions;

    case Prioritaire = 'prioritaire';
    case Interessant = 'interessant';
    case AApprofondir = 'a_approfondir';
    case Faible = 'faible';

    public function label(): string
    {
        return match ($this) {
            self::Prioritaire => 'Prioritaire',
            self::Interessant => 'Intéressant',
            self::AApprofondir => 'À approfondir',
            self::Faible => 'Faible priorité',
        };
    }

    public function action(): string
    {
        return match ($this) {
            self::Prioritaire => 'À examiner rapidement',
            self::Interessant => 'À examiner',
            self::AApprofondir => 'Analyse complémentaire',
            self::Faible => 'À conserver / revoir',
        };
    }

    /** Catégorie d'un score, d'après les seuils de `config('talent.categories')`. */
    public static function fromScore(int $score, ?array $thresholds = null): self
    {
        foreach ($thresholds ?? config('talent.categories') as $value => $minimum) {
            if ($score >= $minimum) {
                return self::from($value);
            }
        }

        return self::Faible;
    }
}
