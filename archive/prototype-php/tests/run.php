<?php
declare(strict_types=1);

/**
 * Mini-lanceur de tests sans dépendance : php tests/run.php
 * Chaque fichier *Test.php retourne un tableau ['description' => function () { ... }].
 */

require __DIR__ . '/../src/bootstrap.php';

final class AssertionFailed extends Exception
{
}

function assert_true(bool $condition, string $message = 'condition fausse'): void
{
    if (!$condition) {
        throw new AssertionFailed($message);
    }
}

function assert_same(mixed $expected, mixed $actual, string $message = ''): void
{
    if ($expected !== $actual) {
        throw new AssertionFailed(trim($message . ' attendu ' . var_export($expected, true) . ', obtenu ' . var_export($actual, true)));
    }
}

/** Candidature valide de référence ; chaque test ne modifie que ce qui l'intéresse. */
function valid_input(array $overrides = []): array
{
    return $overrides + [
        'first_name' => 'Aïcha', 'last_name' => 'Mbarga', 'email' => 'aicha@example.com', 'phone' => '+237 690 11 22 33',
        'country' => 'Cameroun', 'city' => 'Douala', 'program' => 'dev', 'education' => 'bac+3', 'experience_years' => '4',
        'skills' => ['javascript', 'php', 'sql', 'laravel', 'react', 'tests'], 'other_skills' => '',
        'availability' => 'full_time', 'available_from' => '', 'french_level' => 'C1', 'english_level' => 'B2',
        'portfolio_url' => 'https://github.com/aicha',
        'project_description' => str_repeat('Application de gestion de stock en Laravel et React. ', 4),
        'motivation' => str_repeat("Je veux apprendre, contribuer et progresser en équipe sur des projets concrets. ", 5),
        'consent' => '1',
    ];
}

function scoring_config(): array
{
    return require __DIR__ . '/../config/scoring.php';
}

$passed = 0;
$failures = [];
foreach (glob(__DIR__ . '/*Test.php') as $file) {
    echo basename($file, '.php') . "\n";
    foreach (require $file as $name => $test) {
        try {
            $test();
            $passed++;
            echo "  \e[32m✓\e[0m $name\n";
        } catch (Throwable $e) {
            $failures[] = "$name : " . $e->getMessage();
            echo "  \e[31m✗ $name\e[0m\n    " . $e->getMessage() . "\n";
        }
    }
}

printf("\n%d test(s) réussi(s), %d échec(s).\n", $passed, count($failures));
exit($failures ? 1 : 0);
