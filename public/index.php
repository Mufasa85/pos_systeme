<?php

// public/index.php

session_start();
require dirname(__DIR__).DIRECTORY_SEPARATOR.'vendor'.DIRECTORY_SEPARATOR.'autoload.php';

use App\Core\Router;

// Set base path for AltoRouter before loading routes
Router::getInstance()->setBasePath('/pos/public');

// Load routes
require dirname(__DIR__) . DIRECTORY_SEPARATOR .'routes/web.php';
require dirname(__DIR__) . DIRECTORY_SEPARATOR .'routes/api.php';

Router::matcher();
