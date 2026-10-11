<?php

namespace Tests\Unit;

use App\Enums\Category;
use App\Models\Candidate;
use App\Models\Skill;
use App\Services\Scoring\ScoreResult;
use App\Services\Scoring\ScoringEngine;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Relations\Pivot;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * Le moteur de scoring est testé sans base de données : on construit des candidats en mémoire.
 */
class ScoringEngineTest extends TestCase
{
    /** Profil idéal de référence ; chaque test ne modifie que ce qui l'intéresse. */
    private function candidate(array $attributes = [], ?array $skills = null): Candidate
    {
        $candidate = new Candidate($attributes + [
            'first_name' => 'Aïcha', 'last_name' => 'Mbarga', 'email' => 'aicha@example.com', 'phone' => '+237690112233',
            'city' => 'Douala', 'country' => 'Cameroun', 'linkedin_url' => 'https://linkedin.com/in/a',
            'github_url' => 'https://github.com/a', 'portfolio_url' => 'https://a.example.dev',
            'education_level' => 'bac+3', 'education_field' => 'informatique', 'institution' => 'Université de Douala', 'graduation_year' => 2023,
            'experience_years' => 3, 'experience_type' => 'professionnelle',
            'projects_description' => str_repeat('Application de gestion de stock en Laravel et React. ', 4),
            'motivation' => 'Apprendre et contribuer.', 'availability' => 'temps_plein', 'available_from' => '2026-10-20',
        ]);

        $skills ??= ['JavaScript' => 'avance', 'React' => 'avance', 'PHP' => 'avance', 'Laravel' => 'avance', 'SQL' => 'avance', 'Git' => 'avance'];
        $candidate->setRelation('skills', new Collection(array_map(function (string $name, string $level) {
            $skill = new Skill(['name' => $name, 'is_sought' => ! in_array($name, ['WordPress', 'Figma'], true)]);
            $skill->setRelation('pivot', new Pivot(['level' => $level]));

            return $skill;
        }, array_keys($skills), $skills)));

        return $candidate;
    }

    private function score(array $attributes = [], ?array $skills = null): ScoreResult
    {
        return (new ScoringEngine)->score($this->candidate($attributes, $skills));
    }

    public function test_les_maximums_des_criteres_totalisent_100(): void
    {
        $this->assertSame(100, array_sum(array_column(config('talent.criteria'), 'max')));
    }

    public function test_un_profil_ideal_obtient_100_et_est_prioritaire(): void
    {
        $result = $this->score();

        $this->assertSame(100, $result->total);
        $this->assertSame(Category::Prioritaire, $result->category);
        $this->assertSame([], $result->flags);
    }

    public function test_le_score_total_est_exactement_la_somme_du_detail(): void
    {
        $result = $this->score(['experience_years' => 1.5, 'github_url' => null, 'education_field' => 'sciences'], ['PHP' => 'debutant']);

        $this->assertSame($result->total, array_sum(array_map(fn ($d) => $d->points, $result->details)));
        $this->assertCount(6, $result->details);
    }

    #[DataProvider('formations')]
    public function test_formation_selon_la_pertinence_du_domaine(string $field, int $expected): void
    {
        $this->assertSame($expected, $this->score(['education_field' => $field])->points('education'));
    }

    public static function formations(): array
    {
        return [
            'informatique : forte' => ['informatique', 15],
            'data / IA : forte' => ['data_ia', 15],
            'sciences : partielle' => ['sciences', 10],
            'autoformation : partielle (pas pénalisée)' => ['autoformation', 10],
            'autre : faible' => ['autre', 5],
        ];
    }

    public function test_competences_selon_le_niveau(): void
    {
        // avancé 5 + intermédiaire 3 + débutant 1
        $result = $this->score(skills: ['PHP' => 'avance', 'SQL' => 'intermediaire', 'Git' => 'debutant']);

        $this->assertSame(9, $result->points('skills'));
    }

    public function test_competences_plafonnees_a_30(): void
    {
        $skills = array_fill_keys(['PHP', 'SQL', 'Git', 'React', 'Laravel', 'JavaScript', 'Python', 'Docker'], 'avance'); // 40 pts bruts

        $this->assertSame(30, $this->score(skills: $skills)->points('skills'));
    }

    public function test_une_competence_non_recherchee_ne_rapporte_rien_mais_est_signalee(): void
    {
        $result = $this->score(skills: ['WordPress' => 'avance']);

        $this->assertSame(0, $result->points('skills'));
        $this->assertStringContainsString('WordPress', implode(' ', $result->flags));
    }

    #[DataProvider('experiences')]
    public function test_experience_par_paliers(float $years, int $expected): void
    {
        $this->assertSame($expected, $this->score(['experience_years' => $years])->points('experience'));
    }

    public static function experiences(): array
    {
        return [
            'aucune' => [0, 0],
            'moins d\'un an' => [0.5, 5],
            '1 an' => [1, 10],
            '1,5 an' => [1.5, 10],
            '2 ans' => [2, 15],
            '3 ans' => [3, 20],
            '10 ans (plafond)' => [10, 20],
        ];
    }

    public function test_realisations_github_portfolio_et_description(): void
    {
        $this->assertSame(15, $this->score()->points('projects'));
        $this->assertSame(6, $this->score(['github_url' => null, 'portfolio_url' => null])->points('projects'));
        $this->assertSame(8, $this->score(['portfolio_url' => null, 'projects_description' => 'Un site vitrine pour une association sportive de quartier.'])->points('projects'));
        $this->assertSame(0, $this->score(['github_url' => null, 'portfolio_url' => null, 'projects_description' => null])->points('projects'));
    }

    public function test_disponibilite_et_penalite_de_demarrage_tardif(): void
    {
        $this->assertSame(10, $this->score()->points('availability'));
        $this->assertSame(6, $this->score(['availability' => 'temps_partiel'])->points('availability'));

        $late = $this->score(['available_from' => '2027-01-15']);
        $this->assertSame(7, $late->points('availability'));
        $this->assertStringContainsString('15/01/2027', implode(' ', $late->flags));
    }

    public function test_completude_par_paliers(): void
    {
        $this->assertSame(10, $this->score()->points('completeness'));
        // 8 champs sur 10 renseignés = 80 %
        $this->assertSame(8, $this->score(['phone' => null, 'linkedin_url' => null])->points('completeness'));
        // 6 sur 10 = 60 %
        $this->assertSame(6, $this->score(['phone' => null, 'linkedin_url' => null, 'city' => null, 'institution' => null])->points('completeness'));
        // aucun = 0 %
        $empty = array_fill_keys(config('talent.criteria.completeness.fields'), null);
        $this->assertSame(3, $this->score($empty)->points('completeness'));
    }

    #[DataProvider('seuils')]
    public function test_categorie_selon_les_seuils_inclusifs(int $score, Category $expected): void
    {
        $this->assertSame($expected, Category::fromScore($score));
    }

    public static function seuils(): array
    {
        return [
            '100' => [100, Category::Prioritaire],
            '89 (exemple du cahier des charges)' => [89, Category::Prioritaire],
            '80' => [80, Category::Prioritaire],
            '79' => [79, Category::Interessant],
            '65' => [65, Category::Interessant],
            '64' => [64, Category::AApprofondir],
            '50' => [50, Category::AApprofondir],
            '49' => [49, Category::Faible],
            '0' => [0, Category::Faible],
        ];
    }

    public function test_une_pepite_est_signalee(): void
    {
        // Réalisations au maximum, mais formation, compétences et expérience faibles.
        $result = $this->score(['education_field' => 'autre', 'experience_years' => 0, 'experience_type' => 'personnelle'], ['HTML/CSS' => 'debutant']);

        $this->assertContains($result->category, [Category::Faible, Category::AApprofondir]);
        $this->assertStringContainsString('Pépite', implode(' ', $result->flags));
    }

    public function test_le_score_reste_entre_0_et_100_sur_300_profils_aleatoires(): void
    {
        mt_srand(42);
        $levels = ['debutant', 'intermediaire', 'avance'];
        $names = ['PHP', 'SQL', 'Git', 'React', 'Laravel', 'JavaScript', 'Python', 'Docker', 'WordPress', 'Figma'];

        for ($i = 0; $i < 300; $i++) {
            $skills = [];
            foreach ((array) array_rand(array_flip($names), mt_rand(1, count($names))) as $name) {
                $skills[$name] = $levels[array_rand($levels)];
            }
            $result = $this->score([
                'experience_years' => mt_rand(0, 400) / 10,
                'education_field' => ['informatique', 'sciences', 'autre', 'autoformation'][mt_rand(0, 3)],
                'availability' => ['temps_plein', 'temps_partiel'][mt_rand(0, 1)],
                'github_url' => mt_rand(0, 1) ? 'https://github.com/x' : null,
                'projects_description' => str_repeat('x', mt_rand(0, 300)),
                'available_from' => mt_rand(0, 1) ? '2027-03-01' : null,
            ], $skills);

            $this->assertGreaterThanOrEqual(0, $result->total);
            $this->assertLessThanOrEqual(100, $result->total);
            foreach ($result->details as $detail) {
                $this->assertLessThanOrEqual($detail->max, $detail->points, $detail->criterion);
            }
        }
    }
}
