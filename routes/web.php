<?php

use App\Core\Router;
use App\Controllers\AuthController;
use App\Controllers\PageController;

Router::get("/", [AuthController::class, 'showLogin']);
Router::post("/login", [AuthController::class, 'login']);
Router::get("/logout", [AuthController::class, 'logout']);

Router::get("/dashboard", [PageController::class, 'dashboard']);
Router::get("/caisse", [PageController::class, 'caisse']);
Router::get("/produits", [PageController::class, 'produits']);
Router::get("/utilisateurs", [PageController::class, 'utilisateurs']);
Router::get("/historique", [PageController::class, 'historique']);
Router::get("/categories", [PageController::class, 'categories']);
Router::get("/parametres", [PageController::class, 'parametres']);
Router::get("/scanner", [PageController::class, 'scanner']);
Router::get("/new-scanner", [PageController::class, 'newScanner']);
Router::get("/facture/[i:id]", [\App\Controllers\InvoiceController::class, 'show']);
Router::get("/facture", [\App\Controllers\InvoiceController::class, 'showByRef']);
Router::get("/facture-client/[i:id]", [\App\Controllers\InvoiceController::class, 'publicInvoice']);

// API routes for invoice actions
Router::post("/api/facture/[i:id]/send", [\App\Controllers\InvoiceController::class, 'sendInvoice']);
Router::get("/api/facture/[i:id]/pdf", [\App\Controllers\InvoiceController::class, 'downloadPdf']);

// API route for client search
Router::get("/api/client/search", [\App\Controllers\ClientController::class, 'searchByNumero']);
Router::get("/api/client/types", [\App\Controllers\ClientController::class, 'getTypes']);

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
