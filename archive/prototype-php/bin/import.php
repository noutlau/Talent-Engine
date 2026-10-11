<?php
declare(strict_types=1);

/**
 * Importe des candidatures depuis un CSV (export Google Forms, Excel…).
 * Chaque ligne passe par exactement le même pipeline que le formulaire web.
 *
 * Usage : php bin/import.php fichier.csv [--reset]
 */

use TalentEngine\ApplicationService;
use TalentEngine\Csv;
use TalentEngine\Repository;
use TalentEngine\Scorer;
use TalentEngine\Validator;

require __DIR__ . '/../src/bootstrap.php';

$args = array_slice($argv, 1);
$reset = in_array('--reset', $args, true);
$file = current(array_filter($args, static fn($a) => !str_starts_with($a, '--')));
if (!$file) {
    fwrite(STDERR, "Usage : php bin/import.php fichier.csv [--reset]\n");
    exit(1);
}

$appConfig = require __DIR__ . '/../config/app.php';
$scoring = require __DIR__ . '/../config/scoring.php';
$repository = Repository::open($appConfig['db_path']);
$service = new ApplicationService(new Validator($scoring), new Scorer($scoring), $repository);

if ($reset) {
    $repository->deleteAll();
    echo "Base vidée.\n";
}

try {
    $rows = Csv::read($file);
} catch (RuntimeException $e) {
    fwrite(STDERR, $e->getMessage() . "\n");
    exit(1);
}

$imported = 0;
$rejected = 0;
foreach ($rows as $row) {
    // Date d'origine conservée si fournie et bien formée (sinon : date d'import).
    $createdAt = preg_match('/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}$/', $row['created_at'] ?? '') ? $row['created_at'] : null;
    $outcome = $service->submit($row, $createdAt);
    if ($outcome['ok']) {
        $imported++;
        printf("  ✓ ligne %d : %s %s → %d/100 (%s)\n", $row['_line'], $row['first_name'] ?? '', $row['last_name'] ?? '',
            $outcome['result']['score'], Scorer::TIERS[$outcome['result']['tier']]);
    } else {
        $rejected++;
        printf("  ✗ ligne %d rejetée : %s\n", $row['_line'], implode(' ', $outcome['errors']));
    }
}

printf("\n%d importée(s), %d rejetée(s).\n", $imported, $rejected);
exit($rejected > 0 ? 2 : 0);
