<?php
declare(strict_types=1);

use TalentEngine\Validator;

require __DIR__ . '/../src/web.php';

$errors = [];
$old = ['program' => 'dev', 'skills' => []];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf();
    $outcome = $service->submit($_POST);
    if ($outcome['ok']) {
        // Post/Redirect/Get : un rafraîchissement ne renvoie pas la candidature.
        header('Location: index.php?merci=1');
        exit;
    }
    $errors = $outcome['errors'];
    $old = $_POST + ['skills' => []];
}

$scalar = static fn(string $k): string => is_scalar($old[$k] ?? null) ? (string) $old[$k] : '';
$val = static fn(string $k): string => e($scalar($k));
$err = static fn(string $k): string => isset($errors[$k]) ? '<p class="field-error">' . e($errors[$k]) . '</p>' : '';
$invalid = static fn(string $k): string => isset($errors[$k]) ? ' aria-invalid="true"' : '';
$options = static function (array $choices, string $key) use ($scalar): string {
    $html = '<option value="">Choisir…</option>';
    foreach ($choices as $value => $label) {
        $selected = $scalar($key) === (string) $value ? ' selected' : '';
        $html .= '<option value="' . e($value) . '"' . $selected . '>' . e($label) . '</option>';
    }
    return $html;
};
$levels = array_combine(Validator::LEVELS, array_map(static fn($l) => $l === 'natif' ? 'Langue maternelle' : $l, Validator::LEVELS));

layout_start('Candidater');
?>
<section class="intro">
    <h1>Candidater aux programmes SKULLVI</h1>
    <p>Quelques minutes suffisent. Les champs marqués <span class="req">*</span> sont obligatoires.
       Chaque candidature est relue par un membre de l'équipe : le score automatique n'aide qu'à prioriser la lecture.</p>
</section>

<?php if (isset($_GET['merci'])): ?>
    <div class="notice success">
        <strong>Merci, votre candidature a bien été reçue.</strong>
        Nous reviendrons vers vous par e-mail après l'examen des dossiers.
    </div>
<?php endif; ?>

<?php if ($errors): ?>
    <div class="notice error" role="alert">
        <?= isset($errors['_form']) ? e($errors['_form']) : 'Merci de corriger les ' . count($errors) . ' champ(s) signalé(s) ci-dessous.' ?>
    </div>
<?php endif; ?>

<form method="post" class="card form" novalidate>
    <?= csrf_field() ?>
    <div class="hp" aria-hidden="true"><label>Site web <input type="text" name="website" tabindex="-1" autocomplete="off"></label></div>

    <fieldset>
        <legend>1. Vous</legend>
        <div class="grid">
            <label>Prénom <span class="req">*</span><input name="first_name" value="<?= $val('first_name') ?>" maxlength="80" required<?= $invalid('first_name') ?>><?= $err('first_name') ?></label>
            <label>Nom <span class="req">*</span><input name="last_name" value="<?= $val('last_name') ?>" maxlength="80" required<?= $invalid('last_name') ?>><?= $err('last_name') ?></label>
            <label>E-mail <span class="req">*</span><input type="email" name="email" value="<?= $val('email') ?>" maxlength="190" required<?= $invalid('email') ?>><?= $err('email') ?></label>
            <label>Téléphone<input type="tel" name="phone" value="<?= $val('phone') ?>" placeholder="+237 6 12 34 56 78"<?= $invalid('phone') ?>><?= $err('phone') ?></label>
            <label>Pays <span class="req">*</span><input name="country" value="<?= $val('country') ?>" maxlength="80" required<?= $invalid('country') ?>><?= $err('country') ?></label>
            <label>Ville<input name="city" value="<?= $val('city') ?>" maxlength="80"<?= $invalid('city') ?>><?= $err('city') ?></label>
        </div>
    </fieldset>

    <fieldset>
        <legend>2. Programme et parcours</legend>
        <div class="grid">
            <label>Programme visé <span class="req">*</span>
                <select name="program" required<?= $invalid('program') ?>><?= $options(array_map(static fn($p) => $p['label'], $scoring['programs']), 'program') ?></select><?= $err('program') ?></label>
            <label>Niveau de formation <span class="req">*</span>
                <select name="education" required<?= $invalid('education') ?>><?= $options(Validator::EDUCATION, 'education') ?></select><?= $err('education') ?></label>
            <label>Années d'expérience en développement / data <span class="req">*</span>
                <input type="number" name="experience_years" min="0" max="40" step="0.5" value="<?= $val('experience_years') ?>" required<?= $invalid('experience_years') ?>>
                <small>Stages, freelance, projets personnels sérieux comptent.</small><?= $err('experience_years') ?></label>
        </div>
        <div class="skills">
            <span class="label">Compétences que vous savez utiliser</span>
            <div class="chips">
                <?php foreach ($scoring['skills'] as $key => $label): ?>
                    <label class="chip"><input type="checkbox" name="skills[]" value="<?= e($key) ?>"<?= in_array($key, (array) $old['skills'], true) ? ' checked' : '' ?>> <?= e($label) ?></label>
                <?php endforeach; ?>
            </div>
        </div>
        <label>Autres compétences<input name="other_skills" value="<?= $val('other_skills') ?>" maxlength="300" placeholder="Flutter, Figma, Power BI…"<?= $invalid('other_skills') ?>><?= $err('other_skills') ?></label>
    </fieldset>

    <fieldset>
        <legend>3. Ce que vous avez construit</legend>
        <label>Lien GitHub, GitLab ou portfolio<input type="url" name="portfolio_url" value="<?= $val('portfolio_url') ?>" maxlength="300" placeholder="https://github.com/…"<?= $invalid('portfolio_url') ?>><?= $err('portfolio_url') ?></label>
        <label>Décrivez un projet que vous avez réalisé
            <textarea name="project_description" rows="4" maxlength="2000" placeholder="Le problème, ce que vous avez fait, les technologies utilisées…"<?= $invalid('project_description') ?>><?= $val('project_description') ?></textarea><?= $err('project_description') ?></label>
    </fieldset>

    <fieldset>
        <legend>4. Motivation et disponibilité</legend>
        <label>Pourquoi ce programme ? <span class="req">*</span>
            <textarea name="motivation" rows="5" maxlength="3000" required<?= $invalid('motivation') ?>><?= $val('motivation') ?></textarea><?= $err('motivation') ?></label>
        <div class="grid">
            <label>Disponibilité <span class="req">*</span>
                <select name="availability" required<?= $invalid('availability') ?>><?= $options(Validator::AVAILABILITY, 'availability') ?></select><?= $err('availability') ?></label>
            <label>Disponible à partir du<input type="date" name="available_from" value="<?= $val('available_from') ?>"<?= $invalid('available_from') ?>><?= $err('available_from') ?></label>
            <label>Niveau de français <span class="req">*</span>
                <select name="french_level" required<?= $invalid('french_level') ?>><?= $options($levels, 'french_level') ?></select><?= $err('french_level') ?></label>
            <label>Niveau d'anglais <span class="req">*</span>
                <select name="english_level" required<?= $invalid('english_level') ?>><?= $options($levels, 'english_level') ?></select><?= $err('english_level') ?></label>
        </div>
    </fieldset>

    <label class="consent"><input type="checkbox" name="consent" value="1"<?= !empty($old['consent']) ? ' checked' : '' ?>>
        J'accepte que SKULLVI traite ces informations dans le seul but d'étudier ma candidature. <span class="req">*</span></label>
    <?= $err('consent') ?>

    <button type="submit" class="btn primary">Envoyer ma candidature</button>
</form>
<?php layout_end();
