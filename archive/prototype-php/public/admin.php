<?php
declare(strict_types=1);

use TalentEngine\Ranker;
use TalentEngine\Repository;
use TalentEngine\Scorer;

require __DIR__ . '/../src/web.php';
require_admin();

$programs = $scoring['programs'];
$program = isset($programs[query('program')]) ? query('program') : array_key_first($programs);
$tier = isset(Scorer::TIERS[query('tier')]) ? query('tier') : '';
$status = isset(Repository::REVIEW_STATUSES[query('status')]) ? query('status') : '';
$q = mb_strtolower(query('q'));

// Le rang est calculé sur tout le programme, puis les filtres ne font que masquer des lignes.
$ranked = Ranker::rank($repository->all($program));
$counts = array_count_values(array_column($ranked, 'tier'));
$rows = array_filter($ranked, static fn(array $c) =>
    ($tier === '' || $c['tier'] === $tier)
    && ($status === '' || $c['review_status'] === $status)
    && ($q === '' || str_contains(mb_strtolower("{$c['first_name']} {$c['last_name']} {$c['email']} {$c['country']} {$c['city']}"), $q))
);

$link = static fn(array $params): string => 'admin.php?' . http_build_query(array_filter(
    $params + ['program' => $program, 'tier' => $tier, 'status' => $status, 'q' => $q],
    static fn($v) => $v !== ''
));

layout_start('Classement', admin: true);
?>
<div class="page-head">
    <h1>Classement des candidatures</h1>
    <div class="tabs">
        <?php foreach ($programs as $key => $p): ?>
            <a class="<?= $key === $program ? 'active' : '' ?>" href="admin.php?program=<?= e($key) ?>"><?= e($p['label']) ?></a>
        <?php endforeach; ?>
    </div>
</div>

<div class="stats">
    <a class="stat" href="<?= e($link(['tier' => ''])) ?>"><strong><?= count($ranked) ?></strong><span>Candidatures</span></a>
    <?php foreach (Scorer::TIERS as $key => $label): ?>
        <a class="stat tier-<?= e($key) ?><?= $tier === $key ? ' selected' : '' ?>" href="<?= e($link(['tier' => $key])) ?>">
            <strong><?= $counts[$key] ?? 0 ?></strong><span><?= e($label) ?></span></a>
    <?php endforeach; ?>
</div>

<form class="filters" method="get">
    <input type="hidden" name="program" value="<?= e($program) ?>">
    <input type="hidden" name="tier" value="<?= e($tier) ?>">
    <input type="search" name="q" value="<?= e($q) ?>" placeholder="Rechercher un nom, un e-mail, un pays…">
    <select name="status">
        <option value="">Tous les statuts de revue</option>
        <?php foreach (Repository::REVIEW_STATUSES as $key => $label): ?>
            <option value="<?= e($key) ?>"<?= $status === $key ? ' selected' : '' ?>><?= e($label) ?></option>
        <?php endforeach; ?>
    </select>
    <button class="btn">Filtrer</button>
    <a class="btn ghost" href="export.php?program=<?= e($program) ?>">Exporter en CSV</a>
</form>

<?php if (!$rows): ?>
    <div class="card empty">Aucune candidature ne correspond. <?= !$ranked ? 'Importez des données de démo : <code>php bin/import.php data/sample.csv --reset</code>' : '' ?></div>
<?php else: ?>
<div class="card table-wrap">
<table>
    <thead><tr><th>#</th><th>Candidat</th><th>Score</th><th>Priorité</th><th>Alertes</th><th>Revue</th><th>Reçue le</th></tr></thead>
    <tbody>
    <?php foreach ($rows as $c): ?>
        <tr>
            <td class="rank"><?= $c['rank'] ?></td>
            <td><a href="candidate.php?id=<?= $c['id'] ?>"><strong><?= e($c['first_name'] . ' ' . $c['last_name']) ?></strong></a>
                <div class="muted"><?= e(trim($c['city'] . ', ' . $c['country'], ', ')) ?></div></td>
            <td><div class="score"><span class="bar"><i style="width: <?= $c['score'] ?>%"></i></span><b><?= $c['score'] ?></b></div></td>
            <td><?= tier_badge($c['tier']) ?></td>
            <td><?php $n = count($c['flags']) + count($c['disqualifications']); ?>
                <?= $n ? '<span class="flag-count" title="' . e(implode("\n", array_merge($c['disqualifications'], $c['flags']))) . '">⚑ ' . $n . '</span>' : '<span class="muted">—</span>' ?></td>
            <td><?= e(Repository::REVIEW_STATUSES[$c['review_status']]) ?></td>
            <td class="muted"><?= e(substr($c['created_at'], 0, 10)) ?></td>
        </tr>
    <?php endforeach; ?>
    </tbody>
</table>
</div>
<?php endif; ?>
<p class="muted small">Ordre : profils qualifiés d'abord, puis score, puis preuves concrètes, puis date de réception.
    Règles de scoring v<?= e($scoring['version']) ?> (<code>config/scoring.php</code>).</p>
<?php layout_end();
