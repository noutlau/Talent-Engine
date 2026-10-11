<?php
/**
 * Règles de qualification et de scoring.
 *
 * Tout ce qui relève d'une décision "métier" est ici, pas dans le code :
 * un recruteur peut ajuster les poids ou les compétences attendues, puis
 * lancer `php bin/rescore.php` pour recalculer tous les scores.
 */
return [
    // Incrémenter à chaque changement de règles : chaque score stocké garde la version qui l'a produit.
    'version' => '1.0',

    // Seuils de priorisation (score sur 100).
    'thresholds' => [
        'prioritaire' => 70,
        'a_examiner'  => 50,
    ],

    // Poids des critères (doivent totaliser 100, vérifié par les tests).
    'weights' => [
        'skills'       => 30, // compétences techniques attendues
        'background'   => 20, // expérience + formation
        'proof'        => 20, // preuves concrètes : lien GitHub/portfolio, projet réalisé
        'motivation'   => 15, // motivation rédigée
        'availability' => 10, // disponibilité
        'languages'    => 5,  // français / anglais
    ],

    // Catalogue des compétences proposées dans le formulaire.
    'skills' => [
        'javascript' => 'JavaScript',
        'php'        => 'PHP',
        'python'     => 'Python',
        'sql'        => 'SQL / bases de données',
        'git'        => 'Git',
        'html_css'   => 'HTML / CSS',
        'laravel'    => 'Laravel / Symfony',
        'react'      => 'React / Vue',
        'node'       => 'Node.js',
        'api'        => 'API REST',
        'tests'      => 'Tests automatisés',
        'docker'     => 'Docker',
        'data'       => 'Analyse de données',
        'ml'         => 'Machine learning',
        'cloud'      => 'Cloud (AWS, GCP, Azure)',
    ],

    // Un profil de scoring par programme : le même moteur sert plusieurs programmes.
    'programs' => [
        'dev' => [
            'label'            => 'Programme Développeurs SKULLVI',
            'start_date'       => '2026-11-02',
            'core_skills'      => ['javascript', 'php', 'python', 'sql', 'git', 'html_css'],
            'core_expected'    => 3, // nb de compétences clés pour obtenir le maximum (on ne demande pas tout)
            'bonus_skills'     => ['laravel', 'react', 'node', 'api', 'tests', 'docker'],
            'min_french'       => 'B1',
            'min_availability' => 'part_time',
            'motivation_keywords' => ['apprendre', 'équipe', 'projet', 'contribuer', 'skullvi', 'progresser', 'construire', 'curieu', 'autonom'],
        ],
        'data' => [
            'label'            => 'Programme Data & IA SKULLVI',
            'start_date'       => '2026-11-02',
            'core_skills'      => ['python', 'sql', 'data', 'git'],
            'core_expected'    => 3,
            'bonus_skills'     => ['ml', 'cloud', 'api', 'tests', 'docker'],
            'min_french'       => 'B1',
            'min_availability' => 'part_time',
            'motivation_keywords' => ['apprendre', 'équipe', 'données', 'data', 'contribuer', 'skullvi', 'progresser', 'curieu', 'autonom'],
        ],
    ],
];
