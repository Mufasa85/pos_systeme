<?php

use App\Core\Router;
use App\Controllers\AuthController;
use App\Controllers\PageController;

Router::get("/", [AuthController::class, 'showLogin']);
Router::post("/login", [AuthController::class, 'login']);
Router::get("/logout", [AuthController::class, 'logout']);
Router::get("/verify-otp", [AuthController::class, 'showVerifyOtp']);
Router::get("/forgot-password", [AuthController::class, 'showForgotPassword']);
Router::get("/reset-password", [AuthController::class, 'showResetPassword']);

Router::get("/dashboard", [PageController::class, 'dashboard']);
Router::get("/caisse", [PageController::class, 'caisse']);
Router::get("/recharges", [PageController::class, 'recharges']);
Router::get("/produits", [PageController::class, 'produits']);
Router::get("/utilisateurs", [PageController::class, 'utilisateurs']);
Router::get("/otp-codes", [PageController::class, 'otpCodes']);
Router::get("/mon-profil", [PageController::class, 'monProfil']);
Router::get("/historique", [PageController::class, 'historique']);
Router::get("/categories", [PageController::class, 'categories']);
Router::get("/taxes", [PageController::class, 'taxes']);
Router::get("/parametres", [PageController::class, 'parametres']);
Router::get("/analytics", [PageController::class, 'analytics']);
Router::get("/shops", [PageController::class, 'shops']);
Router::get("/restaurant/tables", [PageController::class, 'restaurantTables']);
Router::get("/payroll", [PageController::class, 'payroll']);
Router::get("/payroll/[:view]", [PageController::class, 'payroll']);
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

// Restaurant routes
Router::get("/restaurant/menu", [PageController::class, 'restaurantMenu']);
Router::get("/restaurant/commandes", [PageController::class, 'restaurantCommandes']);
Router::get("/restaurant/cuisine", [PageController::class, 'restaurantCuisine']);
Router::get("/restaurant/rapports", [PageController::class, 'restaurantRapports']);

// Pressing routes
Router::get("/pressing/depot", [PageController::class, 'pressingDepot']);
Router::get("/pressing/suivi", [PageController::class, 'pressingSuivi']);
Router::get("/pressing/retrait", [PageController::class, 'pressingRetrait']);
Router::get("/pressing/historique", [PageController::class, 'pressingHistorique']);
Router::get("/pressing/rapports", [PageController::class, 'pressingRapports']);
Router::get("/pressing/admin", [PageController::class, 'pressingAdmin']);
Router::get("/pressing/ticket", [PageController::class, 'pressingTicket']);


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
