<?php
declare(strict_types=1);

require __DIR__ . '/../src/web.php';

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf();
    if (hash_equals($appConfig['admin_password'], (string) ($_POST['password'] ?? ''))) {
        session_regenerate_id(true);
        $_SESSION['admin'] = true;
        header('Location: admin.php');
        exit;
    }
    usleep(500_000); // ralentit les essais en rafale
    $error = 'Mot de passe incorrect.';
}

layout_start('Connexion');
?>
<form method="post" class="card narrow">
    <h1>Espace recruteur</h1>
    <?= csrf_field() ?>
    <?php if ($error): ?><div class="notice error"><?= e($error) ?></div><?php endif; ?>
    <label>Mot de passe<input type="password" name="password" autofocus required></label>
    <button class="btn primary">Se connecter</button>
    <p class="muted">Démo : <code>skullvi-demo</code> (modifiable via <code>TE_ADMIN_PASSWORD</code>).</p>
</form>
<?php layout_end();
