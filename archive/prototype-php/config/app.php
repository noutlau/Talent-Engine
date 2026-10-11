<?php
return [
    'db_path'        => __DIR__ . '/../data/talent-engine.sqlite',
    // Mot de passe de l'espace recruteur. À définir via la variable d'environnement en dehors d'une démo.
    'admin_password' => getenv('TE_ADMIN_PASSWORD') ?: 'skullvi-demo',
];
