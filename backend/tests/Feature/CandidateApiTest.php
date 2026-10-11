<?php

namespace Tests\Feature;

use App\Exports\CandidatesCsv;
use App\Models\Candidate;
use App\Models\Skill;
use App\Models\User;
use Database\Seeders\SkillSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class CandidateApiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(SkillSeeder::class);
    }

    private function payload(array $overrides = []): array
    {
        $skill = fn (string $name, string $level) => ['id' => Skill::where('name', $name)->value('id'), 'level' => $level];

        return $overrides + [
            'first_name' => 'Aïcha', 'last_name' => 'Mbarga', 'email' => 'aicha@example.com', 'phone' => '+237 690 11 22 33',
            'city' => 'Douala', 'country' => 'Cameroun', 'github_url' => 'https://github.com/aicha',
            'education_level' => 'bac+3', 'education_field' => 'informatique',
            'experience_years' => '3', 'experience_type' => 'professionnelle',
            'projects_description' => str_repeat('Application de gestion de stock en Laravel et React. ', 4),
            'availability' => 'temps_plein',
            'skills' => [$skill('PHP', 'avance'), $skill('React', 'intermediaire'), $skill('SQL', 'debutant')],
            'consent' => true,
        ];
    }

    private function actingAsAdmin(): void
    {
        Sanctum::actingAs(User::factory()->create());
    }

    /** Insère un candidat déjà scoré, pour tester le classement sans dépendre du barème. */
    private function candidateWithScore(string $name, int $score, int $projects = 0, string $category = 'faible'): Candidate
    {
        $candidate = new Candidate($this->payload(['first_name' => $name, 'email' => strtolower($name).'@example.com', 'phone' => null]));

        return tap($candidate->forceFill([
            'score' => $score, 'projects_points' => $projects, 'category' => $category, 'scoring_version' => 'test',
        ]))->save();
    }

    // --- Dépôt d'une candidature -------------------------------------------------------------

    public function test_une_candidature_valide_est_creee_scoree_et_justifiee(): void
    {
        $this->postJson('/api/candidates', $this->payload())
            ->assertCreated()
            ->assertJsonMissingPath('score'); // le candidat ne voit pas son score

        $candidate = Candidate::with(['skills', 'scoreDetails'])->sole();
        $this->assertSame('aicha@example.com', $candidate->email);
        $this->assertSame('+237690112233', $candidate->phone);
        $this->assertSame(['PHP' => 'avance', 'React' => 'intermediaire', 'SQL' => 'debutant'],
            $candidate->skills->mapWithKeys(fn ($s) => [$s->name => $s->pivot->level])->sortKeys()->all());
        $this->assertCount(6, $candidate->scoreDetails);
        $this->assertSame($candidate->score, $candidate->scoreDetails->sum('points'));
        $this->assertGreaterThan(0, $candidate->score);
        $this->assertSame('1.0', $candidate->scoring_version);
    }

    public function test_les_donnees_sont_normalisees(): void
    {
        $this->postJson('/api/candidates', $this->payload(['email' => '  Jean.P@Example.COM ', 'experience_years' => '2,5']))->assertCreated();

        $candidate = Candidate::sole();
        $this->assertSame('jean.p@example.com', $candidate->email);
        $this->assertSame(2.5, $candidate->experience_years);
    }

    public function test_email_invalide_refuse_et_rien_n_est_cree(): void
    {
        $this->postJson('/api/candidates', $this->payload(['email' => 'laure@@gmail']))
            ->assertUnprocessable()
            ->assertJsonPath('errors.email.0', 'Adresse email invalide.');

        $this->assertDatabaseCount('candidates', 0);
    }

    public function test_champs_obligatoires_manquants(): void
    {
        $this->postJson('/api/candidates', [])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['first_name', 'last_name', 'email', 'country', 'education_level', 'education_field',
                'experience_years', 'experience_type', 'availability', 'skills', 'consent'])
            ->assertJsonMissingValidationErrors(['phone', 'github_url', 'motivation']);
    }

    public function test_doublon_d_email_refuse_meme_avec_une_casse_differente(): void
    {
        $this->postJson('/api/candidates', $this->payload())->assertCreated();

        $this->postJson('/api/candidates', $this->payload(['email' => 'AICHA@example.com']))
            ->assertUnprocessable()
            ->assertJsonPath('errors.email.0', 'Une candidature associée à cette adresse email existe déjà.');
        $this->assertDatabaseCount('candidates', 1);
    }

    public function test_competence_inexistante_refusee(): void
    {
        $this->postJson('/api/candidates', $this->payload(['skills' => [['id' => 9999, 'level' => 'avance']]]))
            ->assertUnprocessable()
            ->assertJsonPath('errors', fn ($errors) => in_array('Compétence inexistante.', array_merge(...array_values($errors)), true));
    }

    public function test_niveau_de_competence_invalide_ou_competence_en_double(): void
    {
        $php = Skill::where('name', 'PHP')->value('id');

        $this->postJson('/api/candidates', $this->payload(['skills' => [['id' => $php, 'level' => 'expert']]]))
            ->assertJsonValidationErrors('skills.0.level');
        $this->postJson('/api/candidates', $this->payload(['skills' => [['id' => $php, 'level' => 'avance'], ['id' => $php, 'level' => 'debutant']]]))
            ->assertJsonValidationErrors('skills.0.id');
    }

    public function test_experience_negative_ou_incoherente_refusee(): void
    {
        $this->postJson('/api/candidates', $this->payload(['experience_years' => -1]))
            ->assertJsonValidationErrors('experience_years');
        $this->postJson('/api/candidates', $this->payload(['experience_years' => 2, 'experience_type' => 'aucune']))
            ->assertJsonValidationErrors('experience_type');
    }

    public function test_les_liens_sont_controles(): void
    {
        $this->postJson('/api/candidates', $this->payload(['github_url' => 'https://github.com.site-pirate.com/x']))
            ->assertJsonValidationErrors('github_url');
        $this->postJson('/api/candidates', $this->payload(['portfolio_url' => 'javascript:alert(1)']))
            ->assertJsonValidationErrors('portfolio_url');
    }

    public function test_le_champ_piege_anti_robot_bloque_la_soumission(): void
    {
        $this->postJson('/api/candidates', $this->payload(['website' => 'http://spam.example']))
            ->assertJsonValidationErrors('website');
        $this->assertDatabaseCount('candidates', 0);
    }

    public function test_un_meme_telephone_est_accepte_mais_signale(): void
    {
        $this->postJson('/api/candidates', $this->payload())->assertCreated();
        $this->postJson('/api/candidates', $this->payload(['email' => 'autre@example.com']))->assertCreated();

        $this->assertStringContainsString('Doublon possible', implode(' ', Candidate::latest('id')->first()->flags));
    }

    // --- Espace responsable ------------------------------------------------------------------

    public function test_les_routes_responsable_exigent_une_authentification(): void
    {
        $this->getJson('/api/candidates')->assertUnauthorized();
        $this->getJson('/api/candidates/ranking')->assertUnauthorized();
        $this->getJson('/api/candidates/1')->assertUnauthorized();
        $this->patchJson('/api/candidates/1', ['review_status' => 'retenu'])->assertUnauthorized();
    }

    public function test_connexion_puis_acces_avec_le_jeton(): void
    {
        User::factory()->create(['email' => 'admin@skullvi.test', 'password' => 'secret-123']);

        $this->postJson('/api/login', ['email' => 'admin@skullvi.test', 'password' => 'mauvais'])->assertUnprocessable();

        $token = $this->postJson('/api/login', ['email' => 'admin@skullvi.test', 'password' => 'secret-123'])
            ->assertOk()->json('token');
        $this->withToken($token)->getJson('/api/me')->assertOk()->assertJsonPath('email', 'admin@skullvi.test');
    }

    public function test_les_depots_de_candidatures_ne_bloquent_pas_la_connexion(): void
    {
        // Régression : un limiteur anonyme partageait le même compteur entre les deux routes.
        User::factory()->create(['email' => 'admin@skullvi.test', 'password' => 'secret-123']);
        foreach (range(1, 6) as $i) {
            $this->postJson('/api/candidates', $this->payload(['email' => "c$i@example.com"]));
        }

        $this->postJson('/api/login', ['email' => 'admin@skullvi.test', 'password' => 'secret-123'])->assertOk();
    }

    public function test_trop_de_tentatives_de_connexion_sont_bloquees(): void
    {
        foreach (range(1, 5) as $i) {
            $this->postJson('/api/login', ['email' => 'admin@skullvi.test', 'password' => 'mauvais'])->assertUnprocessable();
        }

        $this->postJson('/api/login', ['email' => 'admin@skullvi.test', 'password' => 'mauvais'])->assertTooManyRequests();
    }

    public function test_classement_exemple_du_cahier_des_charges(): void
    {
        // A = 89, B = 76, C = 92  →  1. C, 2. A, 3. B
        $this->candidateWithScore('A', 89);
        $this->candidateWithScore('B', 76);
        $this->candidateWithScore('C', 92);
        $this->actingAsAdmin();

        $this->getJson('/api/candidates')
            ->assertOk()
            ->assertJsonPath('data.*.first_name', ['C', 'A', 'B'])
            ->assertJsonPath('data.*.rank', [1, 2, 3]);
    }

    public function test_a_score_egal_les_realisations_puis_l_anciennete_departagent(): void
    {
        $this->travelTo('2026-10-01 10:00');
        $this->candidateWithScore('Premier', 70, projects: 5);
        $this->travel(1)->hours();
        $this->candidateWithScore('Second', 70, projects: 5);
        $this->candidateWithScore('Projets', 70, projects: 15);
        $this->actingAsAdmin();

        $this->getJson('/api/candidates')->assertJsonPath('data.*.first_name', ['Projets', 'Premier', 'Second']);
    }

    public function test_les_filtres_conservent_le_rang_global(): void
    {
        $this->candidateWithScore('Haut', 92, category: 'prioritaire');
        $this->candidateWithScore('Milieu', 70, category: 'interessant');
        $this->candidateWithScore('Bas', 30);
        $this->actingAsAdmin();

        $this->getJson('/api/candidates?category=interessant')
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.first_name', 'Milieu')
            ->assertJsonPath('data.0.rank', 2);
        $this->getJson('/api/candidates?min_score=70')->assertJsonCount(2, 'data');
        $this->getJson('/api/candidates?search=bas')->assertJsonPath('data.*.first_name', ['Bas']);
        $this->getJson('/api/candidates?category=inconnue')->assertJsonValidationErrors('category');
    }

    public function test_filtre_par_competence(): void
    {
        $this->postJson('/api/candidates', $this->payload())->assertCreated();
        $python = Skill::where('name', 'Python')->value('id');
        $php = Skill::where('name', 'PHP')->value('id');
        $this->actingAsAdmin();

        $this->getJson("/api/candidates?skill=$php")->assertJsonCount(1, 'data');
        $this->getJson("/api/candidates?skill=$python")->assertJsonCount(0, 'data');
    }

    public function test_tableau_de_bord_compte_les_categories(): void
    {
        $this->candidateWithScore('A', 92, category: 'prioritaire');
        $this->candidateWithScore('B', 85, category: 'prioritaire');
        $this->candidateWithScore('C', 30);
        $this->actingAsAdmin();

        $response = $this->getJson('/api/candidates/ranking')->assertOk()->assertJsonPath('total', 3);
        $counts = collect($response->json('categories'))->pluck('count', 'value');
        $this->assertSame(['prioritaire' => 2, 'interessant' => 0, 'a_approfondir' => 0, 'faible' => 1], $counts->all());
        $response->assertJsonPath('top.0.first_name', 'A');
    }

    public function test_fiche_candidat_avec_justification_du_score(): void
    {
        $this->postJson('/api/candidates', $this->payload())->assertCreated();
        $this->actingAsAdmin();

        $response = $this->getJson('/api/candidates/'.Candidate::sole()->id)
            ->assertOk()
            ->assertJsonCount(6, 'data.score_details')
            ->assertJsonCount(3, 'data.skills')
            ->assertJsonPath('data.rank', 1)
            ->assertJsonPath('data.education_field.label', 'Informatique / génie logiciel');

        $this->assertSame($response->json('data.score'), collect($response->json('data.score_details'))->sum('points'));
        $this->getJson('/api/candidates/9999')->assertNotFound();
    }

    public function test_le_responsable_met_a_jour_la_revue(): void
    {
        $this->postJson('/api/candidates', $this->payload())->assertCreated();
        $id = Candidate::sole()->id;
        $this->actingAsAdmin();

        $this->patchJson("/api/candidates/$id", ['review_status' => 'entretien', 'reviewer_note' => 'Très bon GitHub.'])
            ->assertOk()
            ->assertJsonPath('data.review_status.value', 'entretien')
            ->assertJsonPath('data.reviewer_note', 'Très bon GitHub.');
        $this->patchJson("/api/candidates/$id", ['review_status' => 'embauche_directe'])->assertJsonValidationErrors('review_status');
        // La revue ne permet pas de modifier le score.
        $this->patchJson("/api/candidates/$id", ['score' => 100])->assertOk();
        $this->assertNotSame(100, Candidate::find($id)->score);
    }

    public function test_changer_les_regles_puis_rescorer_met_a_jour_les_scores(): void
    {
        $this->postJson('/api/candidates', $this->payload())->assertCreated();
        $before = Candidate::sole()->score;

        config(['talent.criteria.availability.points_by_type.temps_plein' => 0, 'talent.version' => '2.0']);
        $this->artisan('talent:rescore')->assertSuccessful();

        $candidate = Candidate::sole();
        $this->assertSame($before - 10, $candidate->score);
        $this->assertSame('2.0', $candidate->scoring_version);
        $this->assertSame(6, DB::table('score_details')->where('candidate_id', $candidate->id)->count());
    }

    // --- Exports -----------------------------------------------------------------------------

    public function test_les_exports_exigent_une_authentification(): void
    {
        $this->getJson('/api/candidates/export')->assertUnauthorized();
        $this->getJson('/api/candidates/1/pdf')->assertUnauthorized();
    }

    public function test_export_csv_du_classement_lisible_par_excel(): void
    {
        $this->postJson('/api/candidates', $this->payload())->assertCreated();
        $this->postJson('/api/candidates', $this->payload([
            'first_name' => 'Jean', 'email' => 'jean@example.com', 'phone' => null, 'github_url' => null, 'projects_description' => null,
        ]))->assertCreated();
        $this->actingAsAdmin();

        $response = $this->get('/api/candidates/export')->assertOk();
        $this->assertStringContainsString('text/csv', $response->headers->get('Content-Type'));
        $this->assertStringContainsString('candidatures-', $response->headers->get('Content-Disposition'));

        $csv = $response->streamedContent();
        $this->assertStringStartsWith("\xEF\xBB\xBF", $csv, 'BOM UTF-8 attendu pour Excel');
        $lines = array_map(fn ($line) => str_getcsv($line, ';', '"', ''), explode("\n", trim(substr($csv, 3))));

        $this->assertSame('Rang', $lines[0][0]);
        $this->assertCount(3, $lines); // en-tête + 2 candidats
        $this->assertSame(['1', 'Aïcha'], array_slice($lines[1], 0, 2)); // ordre du classement
        $this->assertSame(['2', 'Jean'], array_slice($lines[2], 0, 2));
        $this->assertStringContainsString('PHP (Avancé)', implode(';', $lines[1]));
    }

    public function test_export_csv_respecte_les_filtres_et_garde_le_rang_global(): void
    {
        $this->candidateWithScore('Haut', 92, category: 'prioritaire');
        $this->candidateWithScore('Milieu', 70, category: 'interessant');
        $this->actingAsAdmin();

        $csv = $this->get('/api/candidates/export?category=interessant')->streamedContent();
        $rows = array_map(fn ($line) => str_getcsv($line, ';', '"', ''), explode("\n", trim(substr($csv, 3))));

        $this->assertCount(2, $rows);
        $this->assertSame(['2', 'Milieu'], array_slice($rows[1], 0, 2));
    }

    public function test_export_csv_neutralise_les_formules_excel(): void
    {
        $this->assertSame("'=HYPERLINK(\"http://pirate\")", CandidatesCsv::safeCell('=HYPERLINK("http://pirate")'));
        $this->assertSame("'+237690112233", CandidatesCsv::safeCell('+237690112233'));
        $this->assertSame('Aïcha', CandidatesCsv::safeCell('Aïcha'));
    }

    public function test_fiche_candidat_en_pdf(): void
    {
        $this->postJson('/api/candidates', $this->payload())->assertCreated();
        $this->actingAsAdmin();

        $response = $this->get('/api/candidates/'.Candidate::sole()->id.'/pdf')->assertOk();

        $this->assertSame('application/pdf', $response->headers->get('Content-Type'));
        $this->assertStringContainsString('fiche-mbarga-aicha.pdf', $response->headers->get('Content-Disposition'));
        $this->assertStringStartsWith('%PDF-', $response->getContent());
        $this->getJson('/api/candidates/9999/pdf')->assertNotFound();
    }

    public function test_les_listes_de_choix_sont_publiques(): void
    {
        $this->getJson('/api/skills')->assertOk()->assertJsonCount(count(SkillSeeder::SKILLS));
        $this->getJson('/api/options')->assertOk()->assertJsonCount(4, 'categories');
    }
}
