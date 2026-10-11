<?php
declare(strict_types=1);

use TalentEngine\Scorer;
use TalentEngine\Validator;

/** Valide puis score : on teste toujours le scorer avec des données réellement normalisées. */
$scoreOf = static function (array $overrides = [], ?array $config = null): array {
    $config ??= scoring_config();
    [$data, $errors] = (new Validator($config))->validate(valid_input($overrides));
    assert_same([], $errors, 'données de test invalides :');
    return (new Scorer($config))->score($data);
};

$weakProfile = [
    'skills' => ['html_css'], 'experience_years' => '0', 'education' => 'bac', 'portfolio_url' => '', 'project_description' => '',
    'motivation' => 'Je cherche une opportunité dans le développement.', 'english_level' => 'A1', 'french_level' => 'B1',
];

return [
    'les poids de la configuration totalisent 100' => function () {
        assert_same(100, array_sum(scoring_config()['weights']));
    },

    'un profil idéal obtient 100 et est prioritaire' => function () use ($scoreOf) {
        $result = $scoreOf();
        assert_same(100, $result['score']);
        assert_same('prioritaire', $result['tier']);
        assert_same([], $result['flags']);
    },

    'le score total est exactement la somme du détail affiché' => function () use ($scoreOf, $weakProfile) {
        foreach ([[], $weakProfile, ['experience_years' => '1.5', 'skills' => ['php']]] as $overrides) {
            $result = $scoreOf($overrides);
            assert_same($result['score'], array_sum(array_column($result['breakdown'], 'points')));
        }
    },

    'un profil faible est en attente avec une alerte "aucune preuve"' => function () use ($scoreOf, $weakProfile) {
        $result = $scoreOf($weakProfile);
        assert_true($result['score'] < 50, "score {$result['score']} trop élevé");
        assert_same('en_attente', $result['tier']);
        assert_true((bool) array_filter($result['flags'], static fn($f) => str_contains($f, 'Aucune preuve')));
    },

    'un français insuffisant élimine le profil sans effacer son score' => function () use ($scoreOf) {
        $result = $scoreOf(['french_level' => 'A2']);
        assert_same(false, $result['qualified']);
        assert_same('non_qualifie', $result['tier']);
        assert_true($result['score'] > 80, 'le score reste visible pour une éventuelle dérogation');
    },

    'une disponibilité trop faible élimine le profil' => function () use ($scoreOf) {
        $result = $scoreOf(['availability' => 'limited']);
        assert_same('non_qualifie', $result['tier']);
        assert_same(1, count($result['disqualifications']));
    },

    'les seuils de priorité sont inclusifs (70 = prioritaire, 69 = à examiner)' => function () use ($scoreOf) {
        $base = $scoreOf(['skills' => ['php']])['score'];
        $config = scoring_config();
        $config['thresholds'] = ['prioritaire' => $base, 'a_examiner' => $base - 10];
        assert_same('prioritaire', $scoreOf(['skills' => ['php']], $config)['tier']);
        $config['thresholds']['prioritaire'] = $base + 1;
        assert_same('a_examiner', $scoreOf(['skills' => ['php']], $config)['tier']);
    },

    'plus de compétences clés ne fait jamais baisser le score' => function () use ($scoreOf) {
        $previous = -1;
        foreach ([[], ['php'], ['php', 'sql'], ['php', 'sql', 'git'], ['php', 'sql', 'git', 'python']] as $skills) {
            $score = $scoreOf(['skills' => $skills])['score'];
            assert_true($score >= $previous, 'score non monotone avec ' . implode(',', $skills));
            $previous = $score;
        }
    },

    'un lien GitHub vaut plus qu\'un lien portfolio générique' => function () use ($scoreOf) {
        $github = $scoreOf(['portfolio_url' => 'https://github.com/x'])['breakdown']['proof']['points'];
        $site = $scoreOf(['portfolio_url' => 'https://mon-site.example'])['breakdown']['proof']['points'];
        $fake = $scoreOf(['portfolio_url' => 'https://github.com.evil.example'])['breakdown']['proof']['points'];
        assert_true($github > $site, 'github devrait valoir plus');
        assert_same($site, $fake, 'un faux domaine github ne doit pas être reconnu comme tel');
    },

    'une pépite (preuves fortes, score modeste) est signalée' => function () use ($scoreOf) {
        $result = $scoreOf([
            'skills' => ['html_css'], 'experience_years' => '0', 'education' => 'autre', 'availability' => 'part_time',
            'motivation' => 'Je cherche une opportunité dans le développement.', 'english_level' => 'A1', 'french_level' => 'B1',
        ]);
        assert_same('en_attente', $result['tier']);
        assert_true((bool) array_filter($result['flags'], static fn($f) => str_contains($f, 'Pépite')));
    },

    'une disponibilité après le démarrage est signalée' => function () use ($scoreOf) {
        $result = $scoreOf(['available_from' => '2027-01-15']);
        assert_true((bool) array_filter($result['flags'], static fn($f) => str_contains($f, '2027-01-15')));
    },

    'le score reste entre 0 et 100 sur 500 profils aléatoires' => function () use ($scoreOf) {
        $config = scoring_config();
        mt_srand(42);
        $pick = static fn(array $values) => $values[array_rand($values)];
        for ($i = 0; $i < 500; $i++) {
            $result = $scoreOf([
                'skills' => array_rand(array_flip(array_keys($config['skills'])), mt_rand(1, 6)),
                'experience_years' => (string) (mt_rand(0, 400) / 10),
                'education' => $pick(array_keys(Validator::EDUCATION)),
                'availability' => $pick(array_keys(Validator::AVAILABILITY)),
                'french_level' => $pick(Validator::LEVELS), 'english_level' => $pick(Validator::LEVELS),
                'program' => $pick(array_keys($config['programs'])),
                'portfolio_url' => $pick(['', 'https://github.com/x', 'https://site.example']),
                'project_description' => str_repeat('x', mt_rand(0, 400)),
            ]);
            assert_true($result['score'] >= 0 && $result['score'] <= 100, "score hors bornes : {$result['score']}");
            assert_true(isset(Scorer::TIERS[$result['tier']]));
        }
    },
];
