<?php

namespace App\Enums;

/**
 * Domaine de formation, proposé en liste fermée plutôt qu'en texte libre :
 * la pertinence est alors déterministe et explicable.
 */
enum EducationField: string
{
    use HasOptions;

    case Informatique = 'informatique';
    case DataIa = 'data_ia';
    case ReseauxTelecom = 'reseaux_telecom';
    case Sciences = 'sciences';
    case GestionSi = 'gestion_si';
    case Autoformation = 'autoformation';
    case Autre = 'autre';

    public function label(): string
    {
        return match ($this) {
            self::Informatique => 'Informatique / génie logiciel',
            self::DataIa => 'Data / intelligence artificielle',
            self::ReseauxTelecom => 'Réseaux / télécoms',
            self::Sciences => 'Mathématiques / physique / électronique',
            self::GestionSi => 'Gestion des systèmes d\'information',
            self::Autoformation => 'Bootcamp / autoformation',
            self::Autre => 'Autre domaine',
        };
    }

    /** Clé de `config('talent.criteria.education.points_by_relevance')`. */
    public function relevance(): string
    {
        return match ($this) {
            self::Informatique, self::DataIa => 'forte',
            // Un autodidacte n'est pas pénalisé comme un domaine hors sujet.
            self::ReseauxTelecom, self::Sciences, self::GestionSi, self::Autoformation => 'partielle',
            self::Autre => 'faible',
        };
    }
}
