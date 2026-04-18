<?php

// public/index.php

session_start();
require dirname(__DIR__).DIRECTORY_SEPARATOR.'vendor'.DIRECTORY_SEPARATOR.'autoload.php';

use App\Core\{Database,Router};
use App\Controllers\UserController;

$router = new Router();

// Load routes
require_once '../routes/web.php';
require_once '../routes/api.php';

$methode = $_SERVER['REQUEST_METHOD'];
$uri = $_SERVER['REQUEST_URI'];

$router->dispatch($methode, $uri);
