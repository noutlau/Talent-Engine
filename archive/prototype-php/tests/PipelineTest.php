<?php
declare(strict_types=1);

use TalentEngine\ApplicationService;
use TalentEngine\Csv;
use TalentEngine\Ranker;
use TalentEngine\Repository;
use TalentEngine\Scorer;
use TalentEngine\Validator;

/** Base SQLite en mémoire : chaque test part d'un état vierge. */
$makeService = static function (?array $config = null): array {
    $config ??= scoring_config();
    $repository = Repository::open(':memory:');
    return [new ApplicationService(new Validator($config), new Scorer($config), $repository), $repository];
};

$candidate = static fn(int $id, int $score, bool $qualified = true, int $proof = 10, string $createdAt = '2026-10-01 10:00:00') => [
    'id' => $id, 'score' => $score, 'qualified' => $qualified, 'created_at' => $createdAt,
    'breakdown' => ['proof' => ['points' => $proof]],
];

return [
    'une candidature valide est stockée avec son score et son détail' => function () use ($makeService) {
        [$service, $repository] = $makeService();
        $outcome = $service->submit(valid_input());
        assert_true($outcome['ok']);
        $stored = $repository->find($outcome['id']);
        assert_same('aicha@example.com', $stored['email']);
        assert_same(100, $stored['score']);
        assert_same('nouveau', $stored['review_status']);
        assert_same(['javascript', 'php', 'sql', 'laravel', 'react', 'tests'], $stored['skills']);
        assert_same(6, count($stored['breakdown']));
    },

    'une candidature invalide n\'est jamais stockée' => function () use ($makeService) {
        [$service, $repository] = $makeService();
        $outcome = $service->submit(valid_input(['email' => 'faux']));
        assert_same(false, $outcome['ok']);
        assert_same([], $repository->all());
    },

    'le même e-mail ne peut pas postuler deux fois au même programme (casse ignorée)' => function () use ($makeService) {
        [$service] = $makeService();
        assert_true($service->submit(valid_input())['ok']);
        $second = $service->submit(valid_input(['email' => 'AICHA@example.com']));
        assert_same(false, $second['ok']);
        assert_true(isset($second['errors']['email']));
    },

    'le même e-mail peut postuler à un autre programme' => function () use ($makeService) {
        [$service] = $makeService();
        assert_true($service->submit(valid_input())['ok']);
        assert_true($service->submit(valid_input(['program' => 'data']))['ok']);
    },

    'un téléphone déjà utilisé est accepté mais signalé comme doublon possible' => function () use ($makeService) {
        [$service] = $makeService();
        $service->submit(valid_input());
        $outcome = $service->submit(valid_input(['email' => 'autre@example.com']));
        assert_true($outcome['ok']);
        assert_true((bool) array_filter($outcome['result']['flags'], static fn($f) => str_contains($f, 'Doublon')));
    },

    'les contenus HTML sont stockés tels quels (l\'échappement se fait à l\'affichage)' => function () use ($makeService) {
        [$service, $repository] = $makeService();
        $motivation = '<script>alert("x")</script> ' . str_repeat('motivation sincère ', 3);
        $id = $service->submit(valid_input(['motivation' => $motivation]))['id'];
        assert_same(trim($motivation), $repository->find($id)['motivation']);
    },

    'changer les règles puis rescorer met à jour les scores existants' => function () use ($makeService) {
        $config = scoring_config();
        [$service, $repository] = $makeService($config);
        $id = $service->submit(valid_input(['french_level' => 'B1']))['id'];
        assert_same('prioritaire', $repository->find($id)['tier']);

        $config['programs']['dev']['min_french'] = 'B2';
        $config['version'] = 'test-2';
        $stricter = new ApplicationService(new Validator($config), new Scorer($config), $repository);
        assert_same(1, $stricter->rescoreAll());
        $updated = $repository->find($id);
        assert_same('non_qualifie', $updated['tier']);
        assert_same('test-2', $updated['scoring_version']);
    },

    'le classement : qualifiés d\'abord, puis score, preuves, ancienneté' => function () use ($candidate) {
        $ranked = Ranker::rank([
            $candidate(1, 95, qualified: false),
            $candidate(2, 60),
            $candidate(3, 80, proof: 10),
            $candidate(4, 80, proof: 20),
            $candidate(5, 60, createdAt: '2026-09-30 10:00:00'),
        ]);
        assert_same([4, 3, 5, 2, 1], array_column($ranked, 'id'));
        assert_same([1, 2, 3, 4, 5], array_column($ranked, 'rank'));
    },

    'l\'export CSV neutralise les formules Excel' => function () {
        assert_same("'=HYPERLINK(\"http://x\")", Csv::safeCell('=HYPERLINK("http://x")'));
        assert_same("'+33612345678", Csv::safeCell('+33612345678'));
        assert_same('Aïcha', Csv::safeCell('Aïcha'));
    },

    'l\'import CSV lit les séparateurs ; et le BOM d\'Excel' => function () {
        $file = tempnam(sys_get_temp_dir(), 'te');
        file_put_contents($file, "\xEF\xBB\xBFfirst_name;email\nAïcha;a@example.com\n\nJean;\"j;p@example.com\"\n");
        $rows = Csv::read($file);
        unlink($file);
        assert_same(2, count($rows));
        assert_same('Aïcha', $rows[0]['first_name']);
        assert_same('j;p@example.com', $rows[1]['email']);
    },

    'le fichier d\'exemple s\'importe : 11 acceptées, 2 rejetées' => function () use ($makeService) {
        [$service] = $makeService();
        $outcomes = array_map(static fn($row) => $service->submit($row)['ok'], Csv::read(__DIR__ . '/../data/sample.csv'));
        assert_same(11, count(array_filter($outcomes)));
        assert_same(2, count($outcomes) - count(array_filter($outcomes)));
    },
];
