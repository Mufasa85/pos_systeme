<?php

require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../config/config.php';

// Assure qu'une base de test est utilisée si aucune variable d'environnement n'est définie
if (false === getenv('DB_NAME')) {
    putenv('DB_NAME=pos_systeme_test');
}
