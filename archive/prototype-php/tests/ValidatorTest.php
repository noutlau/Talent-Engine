<?php
declare(strict_types=1);

use TalentEngine\Validator;

$validate = static fn(array $input): array => (new Validator(scoring_config()))->validate($input);

return [
    'une candidature complète est acceptée' => function () use ($validate) {
        [, $errors] = $validate(valid_input());
        assert_same([], $errors);
    },

    'un formulaire vide renvoie une erreur par champ obligatoire' => function () use ($validate) {
        [, $errors] = $validate([]);
        foreach (['first_name', 'last_name', 'email', 'country', 'program', 'education', 'experience_years',
                     'availability', 'french_level', 'english_level', 'motivation', 'consent'] as $field) {
            assert_true(isset($errors[$field]), "erreur attendue sur $field");
        }
        assert_true(!isset($errors['phone']) && !isset($errors['portfolio_url']), 'les champs facultatifs ne doivent pas bloquer');
    },

    'les données sont normalisées (espaces, casse de l\'e-mail, téléphone, virgule décimale)' => function () use ($validate) {
        [$data, $errors] = $validate(valid_input([
            'first_name' => "  Jean   Pierre ", 'email' => ' Jean.P@Example.COM ', 'phone' => '+33 (0)6.12-34-56-78', 'experience_years' => '2,5',
        ]));
        assert_same([], $errors);
        assert_same('Jean Pierre', $data['first_name']);
        assert_same('jean.p@example.com', $data['email']);
        assert_same('+330612345678', $data['phone']);
        assert_same(2.5, $data['experience_years']);
    },

    'un e-mail invalide est refusé' => function () use ($validate) {
        [, $errors] = $validate(valid_input(['email' => 'pas-un-email']));
        assert_true(isset($errors['email']));
    },

    'seuls les liens http(s) sont acceptés (pas de javascript:)' => function () use ($validate) {
        foreach (['javascript:alert(1)', 'ftp://exemple.com', 'github.com/sans-schema'] as $url) {
            [, $errors] = $validate(valid_input(['portfolio_url' => $url]));
            assert_true(isset($errors['portfolio_url']), "le lien $url devrait être refusé");
        }
    },

    'l\'expérience doit être un nombre entre 0 et 40' => function () use ($validate) {
        foreach (['-1', '41', 'trois', ''] as $value) {
            [, $errors] = $validate(valid_input(['experience_years' => $value]));
            assert_true(isset($errors['experience_years']), "la valeur '$value' devrait être refusée");
        }
    },

    'les compétences hors catalogue sont ignorées, le format CSV est accepté' => function () use ($validate) {
        [$data] = $validate(valid_input(['skills' => ['php', 'cobol', 'PHP', '<script>']]));
        assert_same(['php'], $data['skills']);
        [$data] = $validate(valid_input(['skills' => 'php|SQL; git']));
        assert_same(['php', 'sql', 'git'], $data['skills']);
    },

    'un programme ou un niveau de langue inconnu est refusé' => function () use ($validate) {
        [, $errors] = $validate(valid_input(['program' => 'inexistant', 'french_level' => 'Z9']));
        assert_true(isset($errors['program']) && isset($errors['french_level']));
    },

    'une date impossible est refusée' => function () use ($validate) {
        [, $errors] = $validate(valid_input(['available_from' => '2026-02-30']));
        assert_true(isset($errors['available_from']));
    },

    'le consentement est obligatoire' => function () use ($validate) {
        [, $errors] = $validate(valid_input(['consent' => 'non']));
        assert_true(isset($errors['consent']));
    },

    'le champ piège anti-robot bloque la soumission' => function () use ($validate) {
        [, $errors] = $validate(valid_input(['website' => 'http://spam.example']));
        assert_true(isset($errors['_form']));
    },

    'des valeurs de type tableau injectées ne font pas planter la validation' => function () use ($validate) {
        [, $errors] = $validate(valid_input(['first_name' => ['a'], 'email' => ['x' => 'y']]));
        assert_true(isset($errors['first_name']) && isset($errors['email']));
    },
];
