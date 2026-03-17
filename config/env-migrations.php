<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Table de suivi des migrations
    |--------------------------------------------------------------------------
    |
    | Nom de la table utilisée pour suivre les migrations d'environnement
    | déjà exécutées.
    |
    */
    'table' => 'env_migrations',

    /*
    |--------------------------------------------------------------------------
    | Chemin des migrations
    |--------------------------------------------------------------------------
    |
    | Répertoire contenant les fichiers de migration d'environnement.
    | Par défaut : database/env-migrations
    |
    */
    'path' => database_path('env-migrations'),

];
