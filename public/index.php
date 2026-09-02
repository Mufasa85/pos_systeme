<?php

// public/index.php
session_set_cookie_params([
    'httponly' => true,
    'samesite' => 'Lax',
    'secure'   => !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',
]);
session_start();
require dirname(__DIR__) . DIRECTORY_SEPARATOR . 'vendor' . DIRECTORY_SEPARATOR . 'autoload.php';

use App\Core\Router;

// Set base path for AltoRouter before loading routes
//Router::getInstance()->setBasePath('/pos/public');

// Load routes
require dirname(__DIR__) . DIRECTORY_SEPARATOR . 'routes/web.php';
require dirname(__DIR__) . DIRECTORY_SEPARATOR . 'routes/api.php';

Router::matcher();
