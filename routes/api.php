<?php

// routes/api.php

use App\Core\Router;

Router::get("/api/produits", [ProductController::class, 'index']);
Router::get("/api/produit", [ProductController::class, 'find']);
Router::post("/api/produit", [ProductController::class, 'create']);
Router::post("/api/produit/update", [ProductController::class, 'update']);
Router::post("/api/produit/delete", [ProductController::class, 'delete']);

Router::post("/api/vente", [SaleController::class, 'apiCreate']);
