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

Router::get('/api/categories', [CategoryController::class,'index']);

Router::post('/api/create/user', [UserController::class,'create']);
Router::post("/api/delete/user", [UserController::class,'delete']);

// Suppression d'une catégorie
Router::post("/api/delete/category", [CategoryController::class, 'delete']);

// Suppression d'une vente
Router::post("/api/delete/vente", [SaleController::class, 'delete']);
Router::post("/api/vente", [SaleController::class, 'create']);
