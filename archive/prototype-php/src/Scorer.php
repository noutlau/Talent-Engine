<?php
declare(strict_types=1);

namespace TalentEngine;

/**
 * Qualifie et score une candidature déjà validée.
 *
 * Fonction pure (pas de base de données, pas d'horloge) : même entrée,
 * même résultat. Chaque point attribué est accompagné d'une explication
 * lisible, pour qu'un recruteur puisse comprendre et contester le score.
 */
final class Scorer
{
    public const TIERS = [
        'prioritaire'  => 'Prioritaire',
        'a_examiner'   => 'À examiner',
        'en_attente'   => 'En attente',
        'non_qualifie' => 'Non qualifié',
    ];

    private const LEVEL_RANK = ['A1' => 1, 'A2' => 2, 'B1' => 3, 'B2' => 4, 'C1' => 5, 'C2' => 6, 'natif' => 7];
    private const AVAILABILITY_RANK = ['limited' => 1, 'part_time' => 2, 'full_time' => 3];

    public function __construct(private readonly array $config)
    {
    }

    public function score(array $c): array
    {
        $program = $this->config['programs'][$c['program']]
            ?? throw new \InvalidArgumentException("Programme inconnu : {$c['program']}");
        $w = $this->config['weights'];

        $breakdown = [
            'skills'       => $this->skills($c, $program, $w['skills']),
            'background'   => $this->background($c, $w['background']),
            'proof'        => $this->proof($c, $w['proof']),
            'motivation'   => $this->motivation($c, $program, $w['motivation']),
            'availability' => $this->availability($c, $w['availability']),
            'languages'    => $this->languages($c, $w['languages']),
        ];
        $score = max(0, min(100, array_sum(array_column($breakdown, 'points'))));

        // Critères éliminatoires : le score est conservé, mais le profil sort du classement prioritaire.
        $disqualifications = [];
        if (self::LEVEL_RANK[$c['french_level']] < self::LEVEL_RANK[$program['min_french']]) {
            $disqualifications[] = "Niveau de français ({$c['french_level']}) inférieur au minimum requis ({$program['min_french']}).";
        }
        if (self::AVAILABILITY_RANK[$c['availability']] < self::AVAILABILITY_RANK[$program['min_availability']]) {
            $disqualifications[] = 'Disponibilité insuffisante pour suivre le programme.';
        }
        $qualified = $disqualifications === [];

        $thresholds = $this->config['thresholds'];
        $tier = match (true) {
            !$qualified                            => 'non_qualifie',
            $score >= $thresholds['prioritaire']   => 'prioritaire',
            $score >= $thresholds['a_examiner']    => 'a_examiner',
            default                                => 'en_attente',
        };

        // Signaux d'alerte : ils n'influencent pas le score, ils orientent la revue humaine.
        $flags = [];
        if ($c['portfolio_url'] === '' && $c['project_description'] === '') {
            $flags[] = 'Aucune preuve concrète (lien ou projet) : à demander au candidat.';
        }
        if ($qualified && $tier === 'en_attente' && $breakdown['proof']['points'] >= 0.75 * $w['proof']) {
            $flags[] = 'Pépite potentielle : preuves solides malgré un score global modeste.';
        }
        if ($c['available_from'] !== '' && $c['available_from'] > $program['start_date']) {
            $flags[] = "Disponible seulement à partir du {$c['available_from']} (démarrage le {$program['start_date']}).";
        }
        if ($c['other_skills'] !== '') {
            $flags[] = "Compétences hors catalogue à évaluer : {$c['other_skills']}.";
        }

        return [
            'score'             => $score,
            'tier'              => $tier,
            'qualified'         => $qualified,
            'disqualifications' => $disqualifications,
            'breakdown'         => $breakdown,
            'flags'             => $flags,
            'version'           => $this->config['version'],
        ];
    }

    private function skills(array $c, array $program, int $max): array
    {
        $core = array_values(array_intersect($c['skills'], $program['core_skills']));
        $bonus = array_values(array_intersect($c['skills'], $program['bonus_skills']));
        $coreRatio = min(count($core), $program['core_expected']) / $program['core_expected'];
        $bonusRatio = min(count($bonus), 3) / 3;

        return $this->criterion('Compétences techniques', $max, 0.8 * $coreRatio + 0.2 * $bonusRatio, sprintf(
            '%d compétence(s) clé(s) sur %d attendues, %d compétence(s) bonus (3 max).',
            count($core), $program['core_expected'], count($bonus)
        ));
    }

    private function background(array $c, int $max): array
    {
        // La formation pèse peu volontairement : un autodidacte avec de l'expérience ne doit pas être pénalisé.
        $education = ['bac' => 0.4, 'bac+2' => 0.7, 'bac+3' => 1.0, 'bac+5' => 1.0, 'autodidacte' => 0.6, 'autre' => 0.4];
        $experienceRatio = min($c['experience_years'], 4) / 4;

        return $this->criterion('Parcours', $max, 0.7 * $experienceRatio + 0.3 * $education[$c['education']], sprintf(
            '%s an(s) d\'expérience (plafond à 4 ans), formation : %s.',
            rtrim(rtrim(number_format($c['experience_years'], 1, ',', ''), '0'), ','),
            Validator::EDUCATION[$c['education']]
        ));
    }

    private function proof(array $c, int $max): array
    {
        $link = 0.0;
        $linkDetail = 'aucun lien';
        if ($c['portfolio_url'] !== '') {
            $host = strtolower((string) parse_url($c['portfolio_url'], PHP_URL_HOST));
            $isCodeHost = (bool) preg_match('/(^|\.)(github\.com|gitlab\.com|bitbucket\.org)$/', $host);
            $link = $isCodeHost ? 1.0 : 0.8;
            $linkDetail = $isCodeHost ? 'lien vers du code' : 'lien portfolio';
        }

        $length = mb_strlen($c['project_description']);
        $project = $length >= 150 ? 1.0 : ($length >= 50 ? 0.5 : 0.0);
        $projectDetail = match (true) {
            $length >= 150 => 'projet décrit en détail',
            $length >= 50  => 'projet décrit brièvement',
            default        => 'aucun projet décrit',
        };

        return $this->criterion('Preuves concrètes', $max, 0.5 * $link + 0.5 * $project, ucfirst("$linkDetail, $projectDetail."));
    }

    private function motivation(array $c, array $program, int $max): array
    {
        $text = mb_strtolower($c['motivation']);
        $length = mb_strlen($text);
        $lengthRatio = $length >= 300 ? 1.0 : ($length >= 120 ? 0.66 : 0.33);
        $found = array_values(array_filter($program['motivation_keywords'], static fn($k) => str_contains($text, $k)));
        $keywordRatio = min(count($found), 3) / 3;

        return $this->criterion('Motivation', $max, 0.6 * $lengthRatio + 0.4 * $keywordRatio, sprintf(
            '%d caractères, %d thème(s) attendu(s) abordé(s)%s. Signal indicatif : à lire par un humain.',
            $length, count($found), $found ? ' (' . implode(', ', array_slice($found, 0, 3)) . ')' : ''
        ));
    }

    private function availability(array $c, int $max): array
    {
        $ratio = ['full_time' => 1.0, 'part_time' => 0.6, 'limited' => 0.2][$c['availability']];
        return $this->criterion('Disponibilité', $max, $ratio, Validator::AVAILABILITY[$c['availability']] . '.');
    }

    private function languages(array $c, int $max): array
    {
        $fr = self::LEVEL_RANK[$c['french_level']];
        $en = self::LEVEL_RANK[$c['english_level']];
        $frRatio = $fr >= 5 ? 1.0 : ($fr === 4 ? 0.66 : ($fr === 3 ? 0.33 : 0.0));
        $enRatio = $en >= 3 ? 1.0 : ($en === 2 ? 0.5 : 0.0);

        return $this->criterion('Langues', $max, 0.6 * $frRatio + 0.4 * $enRatio,
            "Français {$c['french_level']}, anglais {$c['english_level']}.");
    }

    /** Arrondit chaque critère pour que la somme affichée corresponde exactement au score total. */
    private function criterion(string $label, int $max, float $ratio, string $detail): array
    {
        return [
            'label'  => $label,
            'points' => (int) round($max * max(0.0, min(1.0, $ratio))),
            'max'    => $max,
            'detail' => $detail,
        ];
    }
}
