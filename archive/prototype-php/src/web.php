<?php
declare(strict_types=1);

use TalentEngine\ApplicationService;
use TalentEngine\Repository;
use TalentEngine\Scorer;
use TalentEngine\Validator;

require __DIR__ . '/bootstrap.php';

session_set_cookie_params(['httponly' => true, 'samesite' => 'Lax']);
session_start();
header('X-Frame-Options: DENY');
header('X-Content-Type-Options: nosniff');

$appConfig = require __DIR__ . '/../config/app.php';
$scoring = require __DIR__ . '/../config/scoring.php';
$repository = Repository::open($appConfig['db_path']);
$service = new ApplicationService(new Validator($scoring), new Scorer($scoring), $repository);

function e(mixed $value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

/** Paramètre GET en texte ; ignore les tableaux injectés (`?x[]=1`). */
function query(string $key): string
{
    return is_string($_GET[$key] ?? null) ? trim($_GET[$key]) : '';
}

function csrf_token(): string
{
    return $_SESSION['csrf'] ??= bin2hex(random_bytes(32));
}

function csrf_field(): string
{
    return '<input type="hidden" name="csrf" value="' . e(csrf_token()) . '">';
}

function require_csrf(): void
{
    if (!hash_equals(csrf_token(), (string) ($_POST['csrf'] ?? ''))) {
        http_response_code(400);
        exit('Session expirée : rechargez la page et réessayez.');
    }
}

function require_admin(): void
{
    if (empty($_SESSION['admin'])) {
        header('Location: login.php');
        exit;
    }
}

function layout_start(string $title, bool $admin = false): void
{
    ?><!doctype html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= e($title) ?> · Talent Engine</title>
    <link rel="stylesheet" href="style.css">
</head>
<body>
<header class="topbar">
    <a class="brand" href="<?= $admin ? 'admin.php' : 'index.php' ?>">Talent<span>Engine</span></a>
    <nav>
        <?php if ($admin): ?>
            <a href="admin.php">Classement</a>
            <a href="index.php">Formulaire public</a>
            <a href="logout.php">Déconnexion</a>
        <?php else: ?>
            <a href="admin.php">Espace recruteur</a>
        <?php endif; ?>
    </nav>
</header>
<main>
<?php
}

function layout_end(): void
{
    echo "</main>\n</body>\n</html>\n";
}

function tier_badge(string $tier): string
{
    return '<span class="badge tier-' . e($tier) . '">' . e(Scorer::TIERS[$tier] ?? $tier) . '</span>';
}
