<?php

/*
|--------------------------------------------------------------------------
| Règles de scoring Talent Engine
|--------------------------------------------------------------------------
|
| Toutes les règles métier sont ici, pas dans le code : un responsable peut
| ajuster un barème, incrémenter `version`, puis lancer
| `php artisan talent:rescore` pour recalculer tous les scores.
|
*/

return [

    'version' => '1.0',

    // Compte responsable créé par `php artisan db:seed`.
    'admin' => [
        'email' => env('ADMIN_EMAIL', 'admin@skullvi.test'),
        'password' => env('ADMIN_PASSWORD', 'skullvi-demo'),
    ],

    // Date de démarrage du programme : une disponibilité plus tardive est signalée.
    'program_start_date' => '2026-11-02',

    // Catégories (score minimum inclus). Évaluées de haut en bas.
    'categories' => [
        'prioritaire'   => 80,
        'interessant'   => 65,
        'a_approfondir' => 50,
        'faible'        => 0,
    ],

    'criteria' => [

        // Formation : pertinence du domaine d'études (15 pts).
        'education' => [
            'max' => 15,
            'points_by_relevance' => ['forte' => 15, 'partielle' => 10, 'faible' => 5, 'aucune' => 0],
        ],

        // Compétences recherchées, selon le niveau déclaré (30 pts, plafonné).
        'skills' => [
            'max' => 30,
            'points_by_level' => ['avance' => 5, 'intermediaire' => 3, 'debutant' => 1],
        ],

        // Expérience : paliers en années (20 pts). Lus du plus haut au plus bas.
        'experience' => [
            'max' => 20,
            'tiers' => [3 => 20, 2 => 15, 1 => 10],
            'below_one_year' => 5, // expérience > 0 mais < 1 an
        ],

        // Qualité des réalisations : preuves concrètes (15 pts).
        'projects' => [
            'max' => 15,
            'github' => 5,
            'portfolio' => 4,
            'description_detailed' => 6, // description >= 150 caractères
            'description_short' => 3,    // description >= 50 caractères
        ],

        // Disponibilité (10 pts).
        'availability' => [
            'max' => 10,
            'points_by_type' => ['temps_plein' => 10, 'temps_partiel' => 6],
            'late_start_penalty' => 3, // disponible après le démarrage du programme
        ],

        // Complétude des champs facultatifs (10 pts). Paliers en % lus du plus haut au plus bas.
        'completeness' => [
            'max' => 10,
            'tiers' => [100 => 10, 80 => 8, 60 => 6],
            'below' => 3,
            'fields' => [
                'phone', 'city', 'linkedin_url', 'github_url', 'portfolio_url',
                'institution', 'graduation_year', 'projects_description', 'motivation', 'available_from',
            ],
        ],
    ],

];
