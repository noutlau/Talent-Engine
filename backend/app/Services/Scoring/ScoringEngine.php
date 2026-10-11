<?php

namespace App\Services\Scoring;

use App\Enums\Category;
use App\Enums\SkillLevel;
use App\Models\Candidate;

/**
 * Calcule le score d'une candidature à partir des règles de config/talent.php.
 *
 * Aucune écriture en base : même candidature, mêmes règles, même résultat.
 * Chaque critère renvoie ses points ET une explication lisible par un recruteur.
 */
class ScoringEngine
{
    private array $rules;

    private string $version;

    private string $startDate;

    private array $categories;

    public function __construct(?array $config = null)
    {
        $config ??= config('talent');
        $this->rules = $config['criteria'];
        $this->version = $config['version'];
        $this->startDate = $config['program_start_date'];
        $this->categories = $config['categories'];
    }

    /** @param  Candidate  $candidate  avec la relation `skills` chargée (pivot `level`) */
    public function score(Candidate $candidate): ScoreResult
    {
        $details = [
            $this->education($candidate),
            $this->skills($candidate),
            $this->experience($candidate),
            $this->projects($candidate),
            $this->availability($candidate),
            $this->completeness($candidate),
        ];

        $total = max(0, min(100, array_sum(array_map(fn (CriterionScore $d) => $d->points, $details))));
        $category = Category::fromScore($total, $this->categories);

        return new ScoreResult($total, $category, $details, $this->flags($candidate, $details, $category), $this->version);
    }

    private function education(Candidate $c): CriterionScore
    {
        $rule = $this->rules['education'];
        $relevance = $c->education_field?->relevance() ?? 'aucune';

        return $this->criterion('education', 'Formation', $rule['points_by_relevance'][$relevance], $rule['max'],
            $c->education_field
                ? sprintf('%s : pertinence %s pour le programme.', $c->education_field->label(), $relevance)
                : 'Aucune formation renseignée.');
    }

    private function skills(Candidate $c): CriterionScore
    {
        $rule = $this->rules['skills'];
        $sought = $c->skills->where('is_sought', true);
        $raw = $sought->sum(fn ($skill) => $rule['points_by_level'][$skill->pivot->level]);
        $listed = $sought
            ->map(fn ($skill) => $skill->name.' ('.SkillLevel::from($skill->pivot->level)->label().')')
            ->implode(', ');

        return $this->criterion('skills', 'Compétences techniques', $raw, $rule['max'], $sought->isEmpty()
            ? 'Aucune compétence recherchée déclarée.'
            : sprintf('%d compétence(s) recherchée(s) : %s.', $sought->count(), $listed)
                .($raw > $rule['max'] ? sprintf(' Total brut %d pts, plafonné à %d.', $raw, $rule['max']) : ''));
    }

    private function experience(Candidate $c): CriterionScore
    {
        $rule = $this->rules['experience'];
        $years = (float) $c->experience_years;

        $points = 0;
        if ($years > 0) {
            $points = $rule['below_one_year'];
            foreach ($rule['tiers'] as $minYears => $tierPoints) {
                if ($years >= $minYears) {
                    $points = $tierPoints;
                    break;
                }
            }
        }

        return $this->criterion('experience', 'Expérience', $points, $rule['max'], sprintf(
            "%s an(s) d'expérience (%s).", $this->formatYears($years), $c->experience_type?->label() ?? 'type non précisé'
        ));
    }

    private function projects(Candidate $c): CriterionScore
    {
        $rule = $this->rules['projects'];
        $points = 0;
        $parts = [];

        if (filled($c->github_url)) {
            $points += $rule['github'];
            $parts[] = "lien GitHub/GitLab (+{$rule['github']})";
        }
        if (filled($c->portfolio_url)) {
            $points += $rule['portfolio'];
            $parts[] = "portfolio (+{$rule['portfolio']})";
        }

        $length = mb_strlen(trim((string) $c->projects_description));
        if ($length >= 150) {
            $points += $rule['description_detailed'];
            $parts[] = "projets décrits en détail (+{$rule['description_detailed']})";
        } elseif ($length >= 50) {
            $points += $rule['description_short'];
            $parts[] = "projets décrits brièvement (+{$rule['description_short']})";
        }

        return $this->criterion('projects', 'Qualité des réalisations', $points, $rule['max'],
            $parts ? ucfirst(implode(', ', $parts)).'.' : 'Aucune réalisation présentée (ni lien, ni description).');
    }

    private function availability(Candidate $c): CriterionScore
    {
        $rule = $this->rules['availability'];
        $points = $rule['points_by_type'][$c->availability->value];
        $explanation = $c->availability->label();

        if ($this->startsLate($c)) {
            $points -= $rule['late_start_penalty'];
            $explanation .= sprintf(', disponible seulement le %s (−%d)', $c->available_from->format('d/m/Y'), $rule['late_start_penalty']);
        }

        return $this->criterion('availability', 'Disponibilité', $points, $rule['max'], $explanation.'.');
    }

    private function completeness(Candidate $c): CriterionScore
    {
        $rule = $this->rules['completeness'];
        $filled = array_filter($rule['fields'], fn (string $field) => filled($c->getAttribute($field)));
        $percent = (int) floor(100 * count($filled) / count($rule['fields']));

        $points = $rule['below'];
        foreach ($rule['tiers'] as $minPercent => $tierPoints) {
            if ($percent >= $minPercent) {
                $points = $tierPoints;
                break;
            }
        }

        return $this->criterion('completeness', 'Complétude du profil', $points, $rule['max'], sprintf(
            '%d %% des informations facultatives renseignées (%d/%d).', $percent, count($filled), count($rule['fields'])
        ));
    }

    /**
     * Alertes pour le recruteur : elles ne changent pas le score, elles signalent
     * ce que le score ne sait pas voir.
     *
     * @param  list<CriterionScore>  $details
     * @return list<string>
     */
    private function flags(Candidate $c, array $details, Category $category): array
    {
        $flags = [];
        $projects = collect($details)->firstWhere('criterion', 'projects');

        if ($projects->points >= 12 && in_array($category, [Category::AApprofondir, Category::Faible], true)) {
            $flags[] = 'Pépite potentielle : réalisations solides malgré un score global modeste.';
        }
        if ($projects->points === 0) {
            $flags[] = 'Aucune réalisation présentée : à demander au candidat avant de conclure.';
        }
        if ($this->startsLate($c)) {
            $flags[] = 'Disponible après le démarrage du programme ('.$c->available_from->format('d/m/Y').').';
        }
        $unsought = $c->skills->where('is_sought', false)->pluck('name');
        if ($unsought->isNotEmpty()) {
            $flags[] = 'Compétences hors critères à évaluer : '.$unsought->implode(', ').'.';
        }

        return $flags;
    }

    private function startsLate(Candidate $c): bool
    {
        return $c->available_from !== null && $c->available_from->format('Y-m-d') > $this->startDate;
    }

    /** Borne les points entre 0 et le maximum du critère (ex. plafond de 30 sur les compétences). */
    private function criterion(string $key, string $label, int $points, int $max, string $explanation): CriterionScore
    {
        return new CriterionScore($key, $label, max(0, min($max, $points)), $max, $explanation);
    }

    private function formatYears(float $years): string
    {
        return rtrim(rtrim(number_format($years, 1, ',', ''), '0'), ',');
    }
}
