<?php

namespace App\Enums;

enum ReviewStatus: string
{
    use HasOptions;

    case Nouveau = 'nouveau';
    case EnRevue = 'en_revue';
    case Entretien = 'entretien';
    case Retenu = 'retenu';
    case Refuse = 'refuse';

    public function label(): string
    {
        return match ($this) {
            self::Nouveau => 'Nouveau',
            self::EnRevue => 'En revue',
            self::Entretien => 'Entretien prévu',
            self::Retenu => 'Retenu',
            self::Refuse => 'Refusé',
        };
    }
}
