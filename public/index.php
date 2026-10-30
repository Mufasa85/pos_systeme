<?php

// public/index.php
session_set_cookie_params([
    'httponly' => true,
    'samesite' => 'Lax',
    'secure'   => !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',
]);
session_start();
require dirname(__DIR__) . DIRECTORY_SEPARATOR . 'vendor' . DIRECTORY_SEPARATOR . 'autoload.php';

// Configuration (constantes APP_URL, DB_*, DGI_WEBHOOK_TOKEN…).
// Elle est chargée ICI, et pas seulement via App\Core\Database (qui ne
// s'instancie qu'au premier accès BDD) : certaines routes ont besoin de la
// configuration SANS toucher la base — notamment POST /api/dgi/update, qui
// doit lire le secret DGI_WEBHOOK_TOKEN pour authentifier OSAT AVANT toute
// requête SQL. Sans ce require, la constante n'existerait pas au moment du
// contrôle et la route répondrait toujours 401.
//
// require_once : sans harm si Database.php l'a déjà chargée.
require_once dirname(__DIR__) . DIRECTORY_SEPARATOR . 'config' . DIRECTORY_SEPARATOR . 'config.php';

use App\Core\Router;

// Set base path for AltoRouter before loading routes
//Router::getInstance()->setBasePath('/pos/public');

// Load routes
require dirname(__DIR__) . DIRECTORY_SEPARATOR . 'routes/web.php';
require dirname(__DIR__) . DIRECTORY_SEPARATOR . 'routes/api.php';

Router::matcher();
