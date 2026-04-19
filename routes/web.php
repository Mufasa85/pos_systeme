<?php

use App\Core\Router;
use App\Controllers\AuthController;
use App\Controllers\PageController;

Router::get("/", [AuthController::class,'showLogin']);
Router::post("/login", [AuthController::class, 'login']);
Router::post("/logout", [AuthController::class, 'logout']);

Router::get("/dashboard", [PageController::class, 'dashboard']);
Router::get("/caisse", [PageController::class, 'caisse']);
Router::get("/produits", [PageController::class, 'produits']);
Router::get("/utilisateurs", [PageController::class, 'utilisateurs']);
Router::get("/historique", [PageController::class, 'historique']);
Router::get("/categories", [PageController::class, 'categories']);
Router::get("/parametres", [PageController::class, 'parametres']);
/*
$router->get('/', 'AuthController@showLogin');
$router->post('/login', 'AuthController@login');
$router->get('/logout', 'AuthController@logout');

$router->get('/dashboard', 'PageController@dashboard');
$router->get('/caisse', 'PageController@caisse');
$router->get('/produits', 'PageController@produits');
$router->get('/utilisateurs', 'PageController@utilisateurs');
$router->get('/historique', 'PageController@historique');
$router->get('/parametres', 'PageController@parametres');

$router->post('/delete/user', 'UserController@delete');*/

