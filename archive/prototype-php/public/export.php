<?php
declare(strict_types=1);

use TalentEngine\Csv;
use TalentEngine\Ranker;
use TalentEngine\Repository;
use TalentEngine\Scorer;

require __DIR__ . '/../src/web.php';
require_admin();

$program = isset($scoring['programs'][query('program')]) ? query('program') : array_key_first($scoring['programs']);

header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename="classement-' . $program . '-' . date('Y-m-d') . '.csv"');

$out = fopen('php://output', 'w');
fwrite($out, "\xEF\xBB\xBF"); // BOM pour qu'Excel lise l'UTF-8
$header = ['Rang', 'Prénom', 'Nom', 'E-mail', 'Pays', 'Score', 'Priorité', 'Statut de revue', 'Critères éliminatoires', 'Alertes', 'Reçue le'];
fputcsv($out, $header, ';', '"', '');
foreach (Ranker::rank($repository->all($program)) as $c) {
    fputcsv($out, array_map(Csv::safeCell(...), [
        $c['rank'], $c['first_name'], $c['last_name'], $c['email'], $c['country'], $c['score'],
        Scorer::TIERS[$c['tier']], Repository::REVIEW_STATUSES[$c['review_status']],
        implode(' | ', $c['disqualifications']), implode(' | ', $c['flags']), $c['created_at'],
    ]), ';', '"', '');
}
fclose($out);
