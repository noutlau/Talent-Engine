<?php

namespace App\Exports;

use App\Enums\SkillLevel;
use App\Models\Candidate;

/** Une ligne de l'export CSV du classement : lisible dans Excel, sans retraitement. */
final class CandidatesCsv
{
    public const HEADERS = [
        'Rang', 'Prénom', 'Nom', 'Email', 'Téléphone', 'Ville', 'Pays',
        'Score', 'Catégorie',
        'Formation /15', 'Compétences /30', 'Expérience /20', 'Réalisations /15', 'Disponibilité /10', 'Complétude /10',
        "Niveau d'études", 'Domaine', 'Compétences déclarées', "Années d'expérience", "Type d'expérience",
        'Disponibilité', 'Disponible le', 'GitHub', 'Portfolio', 'LinkedIn',
        'Alertes', 'Statut de revue', 'Note interne', 'Reçue le',
    ];

    private const CRITERIA = ['education', 'skills', 'experience', 'projects', 'availability', 'completeness'];

    /** @param  Candidate  $c  avec `rank_position`, `skills` et `scoreDetails` chargés */
    public static function row(Candidate $c): array
    {
        $points = $c->scoreDetails->pluck('points', 'criterion');

        return array_map(self::safeCell(...), [
            $c->rank_position, $c->first_name, $c->last_name, $c->email, $c->phone, $c->city, $c->country,
            $c->score, $c->category->label(),
            ...array_map(fn (string $criterion) => $points[$criterion] ?? '', self::CRITERIA),
            $c->education_level->label(), $c->education_field->label(),
            $c->skills->map(fn ($s) => $s->name.' ('.SkillLevel::from($s->pivot->level)->label().')')->implode(', '),
            str_replace('.', ',', (string) $c->experience_years), // virgule décimale pour Excel en français
            $c->experience_type->label(),
            $c->availability->label(), $c->available_from?->format('d/m/Y'),
            $c->github_url, $c->portfolio_url, $c->linkedin_url,
            implode(' | ', $c->flags ?? []),
            $c->review_status->label(), $c->reviewer_note,
            $c->created_at?->format('d/m/Y H:i'),
        ]);
    }

    /**
     * Neutralise l'injection de formules : une cellule commençant par = + - @ serait
     * exécutée par Excel à l'ouverture (ex. un « nom » valant =HYPERLINK(...)).
     */
    public static function safeCell(mixed $value): string
    {
        $value = (string) $value;

        return preg_match('/^[=+\-@\t\r]/', $value) ? "'".$value : $value;
    }
}
