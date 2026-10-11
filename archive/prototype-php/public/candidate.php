<?php
declare(strict_types=1);

use TalentEngine\Repository;
use TalentEngine\Validator;

require __DIR__ . '/../src/web.php';
require_admin();

$id = (int) query('id');
$c = $repository->find($id);
if ($c === null) {
    http_response_code(404);
    layout_start('Introuvable', admin: true);
    echo '<div class="card empty">Candidature introuvable. <a href="admin.php">Retour au classement</a></div>';
    layout_end();
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf();
    $newStatus = is_string($_POST['review_status'] ?? null) ? $_POST['review_status'] : '';
    if (isset(Repository::REVIEW_STATUSES[$newStatus])) {
        $repository->updateReview($id, $newStatus, trim(is_string($_POST['reviewer_note'] ?? null) ? $_POST['reviewer_note'] : ''));
    }
    header("Location: candidate.php?id=$id&saved=1");
    exit;
}

$skillLabels = array_map(static fn($s) => $scoring['skills'][$s] ?? $s, $c['skills']);
$program = $scoring['programs'][$c['program']] ?? ['label' => $c['program']];

layout_start($c['first_name'] . ' ' . $c['last_name'], admin: true);
?>
<p><a href="admin.php?program=<?= e($c['program']) ?>">← Retour au classement</a></p>

<div class="page-head">
    <div>
        <h1><?= e($c['first_name'] . ' ' . $c['last_name']) ?></h1>
        <p class="muted"><?= e($program['label']) ?> · reçue le <?= e($c['created_at']) ?> UTC</p>
    </div>
    <div class="big-score"><b><?= $c['score'] ?></b><span>/100</span><?= tier_badge($c['tier']) ?></div>
</div>

<?php if (isset($_GET['saved'])): ?><div class="notice success">Revue enregistrée.</div><?php endif; ?>

<div class="columns">
    <section class="card">
        <h2>Pourquoi ce score ?</h2>
        <?php foreach ($c['disqualifications'] as $reason): ?>
            <div class="notice error">Critère éliminatoire : <?= e($reason) ?></div>
        <?php endforeach; ?>
        <table class="breakdown">
            <?php foreach ($c['breakdown'] as $item): ?>
                <tr>
                    <th><?= e($item['label']) ?></th>
                    <td class="score"><span class="bar"><i style="width: <?= $item['max'] ? round(100 * $item['points'] / $item['max']) : 0 ?>%"></i></span>
                        <b><?= $item['points'] ?>/<?= $item['max'] ?></b></td>
                </tr>
                <tr class="detail"><td colspan="2"><?= e($item['detail']) ?></td></tr>
            <?php endforeach; ?>
        </table>
        <?php if ($c['flags']): ?>
            <h3>Points d'attention</h3>
            <ul class="flags"><?php foreach ($c['flags'] as $flag): ?><li><?= e($flag) ?></li><?php endforeach; ?></ul>
        <?php endif; ?>
        <p class="muted small">Règles v<?= e($c['scoring_version']) ?>. Le score aide à prioriser la lecture ; la décision reste humaine.</p>
    </section>

    <section class="card">
        <h2>Revue</h2>
        <form method="post">
            <?= csrf_field() ?>
            <label>Statut
                <select name="review_status">
                    <?php foreach (Repository::REVIEW_STATUSES as $key => $label): ?>
                        <option value="<?= e($key) ?>"<?= $c['review_status'] === $key ? ' selected' : '' ?>><?= e($label) ?></option>
                    <?php endforeach; ?>
                </select></label>
            <label>Note interne<textarea name="reviewer_note" rows="4" maxlength="2000"><?= e($c['reviewer_note']) ?></textarea></label>
            <button class="btn primary">Enregistrer</button>
        </form>

        <h2>Profil</h2>
        <dl class="profile">
            <dt>E-mail</dt><dd><a href="mailto:<?= e($c['email']) ?>"><?= e($c['email']) ?></a></dd>
            <dt>Téléphone</dt><dd><?= e($c['phone'] ?: '—') ?></dd>
            <dt>Localisation</dt><dd><?= e(trim($c['city'] . ', ' . $c['country'], ', ')) ?></dd>
            <dt>Formation</dt><dd><?= e(Validator::EDUCATION[$c['education']] ?? $c['education']) ?></dd>
            <dt>Expérience</dt><dd><?= e($c['experience_years']) ?> an(s)</dd>
            <dt>Compétences</dt><dd><?= e(implode(', ', $skillLabels) ?: '—') ?><?= $c['other_skills'] ? '<br><span class="muted">+ ' . e($c['other_skills']) . '</span>' : '' ?></dd>
            <dt>Disponibilité</dt><dd><?= e(Validator::AVAILABILITY[$c['availability']] ?? $c['availability']) ?><?= $c['available_from'] ? ' · dès le ' . e($c['available_from']) : '' ?></dd>
            <dt>Langues</dt><dd>Français <?= e($c['french_level']) ?> · Anglais <?= e($c['english_level']) ?></dd>
            <dt>Lien</dt><dd><?= $c['portfolio_url'] ? '<a href="' . e($c['portfolio_url']) . '" target="_blank" rel="noopener noreferrer">' . e($c['portfolio_url']) . '</a>' : '—' ?></dd>
        </dl>
    </section>
</div>

<div class="columns">
    <section class="card"><h2>Motivation</h2><p class="prose"><?= nl2br(e($c['motivation'])) ?></p></section>
    <section class="card"><h2>Projet réalisé</h2><p class="prose"><?= $c['project_description'] ? nl2br(e($c['project_description'])) : '<span class="muted">Non renseigné.</span>' ?></p></section>
</div>
<?php layout_end();
