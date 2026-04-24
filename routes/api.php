<?php

// routes/api.php

use App\Controllers\CategoryController;
use App\Controllers\ProductController;
use App\Controllers\SaleController;
use App\Controllers\UserController;
use App\Core\Router;

Router::get("/api/produits", [ProductController::class, 'index']);
Router::get("/api/produit", [ProductController::class, 'find']);
Router::post("/api/produit", [ProductController::class, 'create']);
Router::post("/api/produit/update", [ProductController::class, 'update']);
Router::post("/api/produit/delete", [ProductController::class, 'delete']);

Router::get('/api/categories', [CategoryController::class, 'index']);
Router::post('/api/categories', [CategoryController::class, 'create']);
Router::post('/api/categories/update', [CategoryController::class, 'update']);

Router::post('/api/create/user', [UserController::class, 'create']);
Router::post('/api/update/user', [UserController::class, 'update']);
Router::post("/api/delete/user", [UserController::class, 'delete']);

// Suppression d'une catégorie
Router::post("/api/delete/category", [CategoryController::class, 'delete']);

// Suppression d'une vente
Router::post("/api/delete/vente", [SaleController::class, 'delete']);
Router::post("/api/vente", [SaleController::class, 'create']);
Router::get("/api/vente/[i:id]/details", [SaleController::class, 'details']);

// Proxy DGI API avec CORS
Router::get("/api/dgi", function () {
    header('Access-Control-Allow-Origin: *');
    header('Access-Control-Allow-Methods: GET, OPTIONS');
    header('Access-Control-Allow-Headers: Content-Type');
    header('Content-Type: application/json');

    if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
        http_response_code(200);
        exit;
    }

    $dgiUrl = 'https://osat-energie.com/dgi/';
    $response = @file_get_contents($dgiUrl);

    if ($response === false) {
        echo json_encode([
            'success' => false,
            'message' => 'Erreur de connexion DGI'
        ]);
    } else {
        echo $response;
    }
});
