<?php
declare(strict_types=1);

namespace TalentEngine;

/**
 * Valide et normalise une candidature brute (formulaire web ou ligne CSV).
 *
 * Retourne [données normalisées, erreurs par champ]. Aucune donnée n'est
 * stockée ni scorée tant que la liste d'erreurs n'est pas vide.
 */
final class Validator
{
    public const EDUCATION = [
        'bac'         => 'Bac',
        'bac+2'       => 'Bac+2',
        'bac+3'       => 'Bac+3 / Licence',
        'bac+5'       => 'Bac+5 / Master et plus',
        'autodidacte' => 'Autodidacte / bootcamp',
        'autre'       => 'Autre',
    ];

    public const AVAILABILITY = [
        'full_time' => 'Temps plein',
        'part_time' => 'Temps partiel (20 h/semaine ou plus)',
        'limited'   => 'Limitée (moins de 20 h/semaine)',
    ];

    public const LEVELS = ['A1', 'A2', 'B1', 'B2', 'C1', 'C2', 'natif'];

    private const TRUTHY = ['1', 'on', 'oui', 'yes', 'true'];

    public function __construct(private readonly array $config)
    {
    }

    /** @return array{0: array<string, mixed>, 1: array<string, string>} */
    public function validate(array $in): array
    {
        $d = [];
        $e = [];

        // Champ piège invisible pour les humains : s'il est rempli, c'est un robot.
        if ($this->text($in, 'website') !== '') {
            return [[], ['_form' => 'Soumission rejetée.']];
        }

        foreach (['first_name' => 'Le prénom', 'last_name' => 'Le nom'] as $key => $label) {
            $d[$key] = $this->text($in, $key);
            if ($d[$key] === '') {
                $e[$key] = "$label est obligatoire.";
            } elseif (mb_strlen($d[$key]) < 2 || mb_strlen($d[$key]) > 80) {
                $e[$key] = "$label doit contenir entre 2 et 80 caractères.";
            } elseif (!preg_match("/^[\p{L}\p{M}\s'’.-]+$/u", $d[$key])) {
                $e[$key] = "$label contient des caractères non autorisés.";
            }
        }

        $d['email'] = mb_strtolower($this->text($in, 'email'));
        if ($d['email'] === '') {
            $e['email'] = "L'e-mail est obligatoire.";
        } elseif (mb_strlen($d['email']) > 190 || !filter_var($d['email'], FILTER_VALIDATE_EMAIL)) {
            $e['email'] = "L'e-mail n'est pas valide.";
        }

        $d['phone'] = preg_replace('/[\s.\-()]/', '', $this->text($in, 'phone'));
        if ($d['phone'] !== '' && !preg_match('/^\+?\d{8,15}$/', $d['phone'])) {
            $e['phone'] = 'Le téléphone doit contenir 8 à 15 chiffres (indicatif + autorisé).';
        }

        $d['country'] = $this->text($in, 'country');
        if ($d['country'] === '' || mb_strlen($d['country']) > 80) {
            $e['country'] = 'Le pays est obligatoire (80 caractères max).';
        }
        $d['city'] = $this->text($in, 'city');
        if (mb_strlen($d['city']) > 80) {
            $e['city'] = 'La ville ne doit pas dépasser 80 caractères.';
        }

        $d['program'] = $this->text($in, 'program');
        if (!isset($this->config['programs'][$d['program']])) {
            $e['program'] = 'Veuillez choisir un programme.';
        }

        $d['education'] = $this->text($in, 'education');
        if (!isset(self::EDUCATION[$d['education']])) {
            $e['education'] = 'Veuillez choisir un niveau de formation.';
        }

        $years = str_replace(',', '.', $this->text($in, 'experience_years'));
        if ($years === '' || !is_numeric($years) || (float) $years < 0 || (float) $years > 40) {
            $e['experience_years'] = "Indiquez un nombre d'années d'expérience entre 0 et 40.";
            $d['experience_years'] = 0.0;
        } else {
            $d['experience_years'] = round((float) $years, 1);
        }

        // Compétences : tableau (formulaire) ou texte "php|sql" (CSV). Les valeurs hors catalogue sont ignorées.
        $skills = $in['skills'] ?? [];
        if (is_string($skills)) {
            $skills = preg_split('/[|;,]/', $skills) ?: [];
        }
        $skills = array_map(static fn($s) => mb_strtolower(trim((string) $s)), (array) $skills);
        $d['skills'] = array_values(array_unique(array_intersect($skills, array_keys($this->config['skills']))));

        $d['other_skills'] = $this->text($in, 'other_skills');
        if (mb_strlen($d['other_skills']) > 300) {
            $e['other_skills'] = 'Les autres compétences ne doivent pas dépasser 300 caractères.';
        }

        $d['availability'] = $this->text($in, 'availability');
        if (!isset(self::AVAILABILITY[$d['availability']])) {
            $e['availability'] = 'Veuillez indiquer votre disponibilité.';
        }

        $d['available_from'] = $this->text($in, 'available_from');
        if ($d['available_from'] !== '') {
            $date = \DateTimeImmutable::createFromFormat('!Y-m-d', $d['available_from']);
            if (!$date || $date->format('Y-m-d') !== $d['available_from']) {
                $e['available_from'] = 'La date doit être au format AAAA-MM-JJ.';
            }
        }

        foreach (['french_level' => 'français', 'english_level' => 'anglais'] as $key => $lang) {
            $d[$key] = $this->text($in, $key);
            if (!in_array($d[$key], self::LEVELS, true)) {
                $e[$key] = "Veuillez indiquer votre niveau de $lang.";
            }
        }

        $d['portfolio_url'] = $this->text($in, 'portfolio_url');
        if ($d['portfolio_url'] !== '') {
            $scheme = strtolower((string) parse_url($d['portfolio_url'], PHP_URL_SCHEME));
            if (mb_strlen($d['portfolio_url']) > 300
                || !filter_var($d['portfolio_url'], FILTER_VALIDATE_URL)
                || !in_array($scheme, ['http', 'https'], true)) {
                $e['portfolio_url'] = 'Le lien doit être une adresse web valide (http ou https).';
            }
        }

        $d['project_description'] = $this->text($in, 'project_description', multiline: true);
        if (mb_strlen($d['project_description']) > 2000) {
            $e['project_description'] = 'La description du projet ne doit pas dépasser 2000 caractères.';
        }

        $d['motivation'] = $this->text($in, 'motivation', multiline: true);
        if (mb_strlen($d['motivation']) < 30) {
            $e['motivation'] = 'Votre motivation doit contenir au moins 30 caractères.';
        } elseif (mb_strlen($d['motivation']) > 3000) {
            $e['motivation'] = 'Votre motivation ne doit pas dépasser 3000 caractères.';
        }

        $consent = $in['consent'] ?? '';
        if (!($consent === true || in_array(mb_strtolower(trim((string) $consent)), self::TRUTHY, true))) {
            $e['consent'] = 'Votre accord sur le traitement des données est nécessaire.';
        }

        return [$d, $e];
    }

    private function text(array $in, string $key, bool $multiline = false): string
    {
        $value = $in[$key] ?? '';
        if (!is_scalar($value)) {
            return '';
        }
        $value = trim((string) $value);
        // Supprime les caractères de contrôle ; conserve les retours à la ligne pour les textes longs.
        $value = preg_replace($multiline ? '/[^\P{C}\n]/u' : '/\p{C}/u', '', $value) ?? '';
        return $multiline ? $value : preg_replace('/\s+/u', ' ', $value);
    }
}
