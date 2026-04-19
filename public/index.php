<?php

// public/index.php

session_start();
require dirname(__DIR__).DIRECTORY_SEPARATOR.'vendor'.DIRECTORY_SEPARATOR.'autoload.php';

use App\Controllers\UserController;
use App\Core\Router;

// Load routes
require dirname(__DIR__) . DIRECTORY_SEPARATOR .'routes/web.php';
require dirname(__DIR__) . DIRECTORY_SEPARATOR .'routes/api.php' ;

$methode = $_SERVER['REQUEST_METHOD'];
$uri = $_SERVER['REQUEST_URI'];
Router::matcher();
