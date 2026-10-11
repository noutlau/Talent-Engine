<?php

namespace Database\Seeders;

use App\Models\Candidate;
use App\Models\Skill;
use App\Services\CandidateService;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;

/** Candidats fictifs couvrant les quatre catégories et les principales alertes. */
class DemoCandidateSeeder extends Seeder
{
    private const PROJECT = "Application de gestion de stock pour une boutique de quartier : analyse du besoin avec la gérante, "
        ."API REST Laravel, interface React, base MySQL, tests PHPUnit et déploiement sur un VPS. Utilisée chaque jour depuis six mois.";

    private const MOTIVATION = "Je veux rejoindre SKULLVI pour apprendre auprès d'une équipe expérimentée et contribuer à des projets réels.";

    public function run(CandidateService $service): void
    {
        $skillIds = Skill::pluck('id', 'name');

        foreach ($this->candidates() as $i => [$data, $skills]) {
            if (Candidate::where('email', $data['email'])->exists()) {
                continue;
            }
            Carbon::setTestNow(Carbon::parse('2026-10-01 08:00')->addHours($i * 7)); // dates de dépôt réalistes
            $service->create($data + [
                'skills' => collect($skills)->map(fn ($level, $name) => ['id' => $skillIds[$name], 'level' => $level])->values()->all(),
            ]);
        }
        Carbon::setTestNow();

        // Second passage : un doublon de téléphone est ainsi signalé sur les deux candidatures.
        Candidate::all()->each(fn (Candidate $candidate) => $service->rescore($candidate));
    }

    private function candidates(): array
    {
        // Valeurs par défaut, placées à DROITE de `+` : les valeurs de chaque candidat restent prioritaires.
        $base = ['experience_type' => 'professionnelle', 'availability' => 'temps_plein', 'country' => 'Cameroun'];

        return [
            [['first_name' => 'Aïcha', 'last_name' => 'Mbarga', 'email' => 'aicha.mbarga@example.com', 'phone' => '+237690112233', 'city' => 'Douala',
                'linkedin_url' => 'https://www.linkedin.com/in/aicha-demo', 'github_url' => 'https://github.com/aicha-demo', 'portfolio_url' => 'https://aicha.example.dev',
                'education_level' => 'bac+3', 'education_field' => 'informatique', 'institution' => 'Université de Douala', 'graduation_year' => 2023,
                'experience_years' => 3, 'projects_description' => self::PROJECT, 'motivation' => self::MOTIVATION, 'available_from' => '2026-10-20'] + $base,
                ['JavaScript' => 'avance', 'React' => 'avance', 'PHP' => 'avance', 'Laravel' => 'intermediaire', 'SQL' => 'intermediaire', 'Git' => 'avance']],

            [['first_name' => 'Julie', 'last_name' => 'Martin', 'email' => 'julie.martin@example.com', 'country' => 'France', 'city' => 'Lyon',
                'github_url' => 'https://gitlab.com/julie-demo', 'education_level' => 'bac+5', 'education_field' => 'data_ia', 'institution' => 'INSA Lyon', 'graduation_year' => 2020,
                'experience_years' => 6, 'projects_description' => self::PROJECT, 'motivation' => self::MOTIVATION] + $base,
                ['Python' => 'avance', 'SQL' => 'avance', 'Docker' => 'intermediaire', 'API REST' => 'avance', 'Git' => 'avance', 'Flutter' => 'debutant']],

            [['first_name' => 'Koffi', 'last_name' => 'Mensah', 'email' => 'koffi.mensah@example.com', 'phone' => '+22890123456', 'country' => 'Togo', 'city' => 'Lomé',
                'portfolio_url' => 'https://koffi.example.dev', 'education_level' => 'bac+2', 'education_field' => 'reseaux_telecom', 'experience_type' => 'freelance',
                'experience_years' => 2, 'projects_description' => 'Site de réservation pour un salon de coiffure, en Node.js avec paiement mobile.'] + $base,
                ['JavaScript' => 'intermediaire', 'Node.js' => 'intermediaire', 'HTML/CSS' => 'avance', 'Git' => 'intermediaire', 'SQL' => 'debutant']],

            [['first_name' => 'Nadia', 'last_name' => 'Haddad', 'email' => 'nadia.haddad@example.com', 'phone' => '+212612345678', 'country' => 'Maroc', 'city' => 'Rabat',
                'github_url' => 'https://github.com/nadia-demo', 'education_level' => 'bac+3', 'education_field' => 'informatique', 'institution' => 'Université Mohammed V',
                'experience_years' => 2, 'projects_description' => 'Plateforme de covoiturage étudiant en React et Laravel.', 'motivation' => self::MOTIVATION] + $base,
                ['JavaScript' => 'avance', 'React' => 'intermediaire', 'Git' => 'intermediaire', 'SQL' => 'debutant']],

            [['first_name' => 'Moussa', 'last_name' => 'Diallo', 'email' => 'moussa.diallo@example.com', 'country' => 'Sénégal', 'city' => 'Dakar',
                'github_url' => 'https://github.com/moussa-demo', 'portfolio_url' => 'https://moussa.example.dev', 'education_level' => 'autre', 'education_field' => 'autoformation',
                'experience_type' => 'personnelle', 'experience_years' => 0.5,
                'projects_description' => "Application mobile de suivi des cotisations d'une tontine, utilisée par 40 membres de ma famille et de mon quartier. Backend PHP, base SQL, notifications par SMS."] + $base,
                ['PHP' => 'intermediaire', 'HTML/CSS' => 'intermediaire', 'Flutter' => 'intermediaire']],

            [['first_name' => 'Grâce', 'last_name' => 'Kabila', 'email' => 'grace.kabila@example.com', 'phone' => '+243812345678', 'country' => 'RD Congo', 'city' => 'Kinshasa',
                'education_level' => 'bac+2', 'education_field' => 'gestion_si', 'experience_type' => 'freelance', 'experience_years' => 1, 'availability' => 'temps_partiel',
                'available_from' => '2026-12-15', 'projects_description' => 'Plusieurs sites WordPress pour des associations locales.', 'motivation' => self::MOTIVATION] + $base,
                ['PHP' => 'debutant', 'HTML/CSS' => 'intermediaire', 'WordPress' => 'avance']],

            [['first_name' => 'Ibrahim', 'last_name' => 'Traoré', 'email' => 'ibrahim.traore@example.com', 'country' => 'Burkina Faso', 'city' => 'Ouagadougou',
                'education_level' => 'bac+5', 'education_field' => 'sciences', 'institution' => 'Université Joseph Ki-Zerbo', 'graduation_year' => 2025,
                'experience_type' => 'academique', 'experience_years' => 1, 'motivation' => self::MOTIVATION] + $base,
                ['Python' => 'intermediaire', 'SQL' => 'debutant']],

            [['first_name' => 'Fatou', 'last_name' => 'Ndiaye', 'email' => 'fatou.ndiaye@example.com', 'country' => 'Sénégal',
                'education_level' => 'bac', 'education_field' => 'autre', 'experience_type' => 'aucune', 'experience_years' => 0] + $base,
                ['HTML/CSS' => 'debutant']],

            [['first_name' => 'Yao', 'last_name' => 'Kouassi', 'email' => 'yao.kouassi@example.com', 'phone' => '+22890123456', 'country' => "Côte d'Ivoire", 'city' => 'Abidjan',
                'education_level' => 'bac+3', 'education_field' => 'informatique', 'experience_type' => 'academique', 'experience_years' => 1, 'availability' => 'temps_partiel'] + $base,
                ['JavaScript' => 'intermediaire', 'Vue.js' => 'debutant', 'Git' => 'intermediaire', 'Excel / Power BI' => 'avance']],
        ];
    }
}
