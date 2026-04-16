<?php
// public/index.php

session_start();

require_once '../config/config.php';
require_once '../app/core/Database.php';
require_once '../app/core/Router.php';

$router = new Router();

// Load routes
require_once '../routes/web.php';
require_once '../routes/api.php';

$methode = $_SERVER['REQUEST_METHOD'];
$uri = $_SERVER['REQUEST_URI'];

$router->dispatch($methode, $uri);
