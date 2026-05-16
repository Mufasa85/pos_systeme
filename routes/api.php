<?php

// routes/api.php

use App\Controllers\CategoryController;
use App\Controllers\ProductController;
use App\Controllers\SaleController;
use App\Controllers\UserController;
use App\Controllers\SettingsController;
use App\Controllers\ClientController;
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
Router::get("/api/vente/next-invoice", [SaleController::class, 'nextInvoice']);

// Paramètres du système
// Routes pour les clients
Router::get("/api/clients", [ClientController::class, 'index']);
Router::get("/api/client/lookup", [ClientController::class, 'lookup']);
Router::post("/api/client", [ClientController::class, 'create']);

Router::get("/api/settings", [SettingsController::class, 'index']);
Router::post("/api/settings", [SettingsController::class, 'update']);
Router::post("/api/settings/store", [SettingsController::class, 'updateStore']);
Router::post("/api/settings/tax", [SettingsController::class, 'updateTax']);

// Proxy Bill Payment API (OSAT-Energie pour éviter CORS)
// POST vers https://osat-energie.com/snel_regideso/
Router::post("/api/bill-payment", function () {
    header('Access-Control-Allow-Origin: *');
    header('Access-Control-Allow-Methods: POST, OPTIONS');
    header('Access-Control-Allow-Headers: Content-Type');
    header('Content-Type: application/json');

    if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
        http_response_code(200);
        exit;
    }

    $input = json_decode(file_get_contents('php://input'), true);

    $compteur = trim($input['compteur'] ?? '');
    $service = trim($input['service'] ?? '');
    $action = trim($input['action'] ?? 'fetch');

    if ($action === 'fetch') {
        if (empty($compteur) || empty($service)) {
            echo json_encode(['success' => false, 'message' => 'Paramètres manquants']);
            return;
        }

        // Appel API OSAT-Energie via POST
        $osatUrl = 'https://osat-energie.com/snel_regideso/index.php';

        $postData = json_encode([
            'compteur' => $compteur,
            'service' => $service
        ]);

        $ch = curl_init($osatUrl);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $postData);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Content-Type: application/json',
            'Accept: application/json'
        ]);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlError = curl_error($ch);
        curl_close($ch);

        if ($response === false || empty($response)) {
            echo json_encode(['success' => false, 'message' => 'Erreur connexion API OSAT: ' . $curlError]);
            return;
        }

        $data = json_decode($response, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            echo json_encode(['success' => false, 'message' => 'Réponse API invalide', 'raw' => substr($response, 0, 200)]);
            return;
        }

        echo json_encode(['success' => true, 'data' => $data]);
        return;
    }

    if ($action === 'process') {
        // Traitement du paiement (enregistrement en DB)
        $input = $input ?? [];
        echo json_encode(['success' => true, 'message' => 'Paiement enregistré']);
        return;
    }

    echo json_encode(['success' => false, 'message' => 'Action non reconnue']);
});

// Proxy DGI API - GET
Router::get("/api/dgi", function () {
    header('Access-Control-Allow-Origin: *');
    header('Content-Type: application/json');

    $dgiUrl = 'https://osat-energie.com/dgi/';
    $response = @file_get_contents($dgiUrl);

    if ($response === false) {
        echo json_encode([
            'success' => false,
            'message' => 'Erreur de connexion DGI'
        ]);
        return;
    }

    echo $response;
});

// Proxy DGI API - POST uniquement (forward au serveur DGI)
Router::post("/api/dgi", function () {
    header('Access-Control-Allow-Origin: *');
    header('Access-Control-Allow-Methods: POST, OPTIONS');
    header('Access-Control-Allow-Headers: Content-Type');
    header('Content-Type: application/json');

    if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
        http_response_code(200);
        exit;
    }

    $input = json_decode(file_get_contents('php://input'), true);

    if (!$input) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Données invalides']);
        return;
    }

    // Forward vers le serveur DGI
    $dgiUrl = 'https://osat-energie.com/dgi/';
    $postData = json_encode($input);

    $ch = curl_init($dgiUrl);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $postData);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Content-Type: application/json',
        'Content-Length: ' . strlen($postData)
    ]);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 30);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlError = curl_error($ch);
    curl_close($ch);

    if ($response === false || empty($response)) {
        http_response_code(503);
        echo json_encode([
            'success' => false,
            'message' => 'DGI inaccessible'
        ]);
        return;
    }

    // Renvoyer la réponse de DGI directement
    echo $response;
});

// Proxy Service Bill API - POST (pour éviter CORS)
Router::post("/api/service-bill", function () {
    header('Access-Control-Allow-Origin: *');
    header('Access-Control-Allow-Methods: POST, OPTIONS');
    header('Access-Control-Allow-Headers: Content-Type');
    header('Content-Type: application/json');

    if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
        http_response_code(200);
        exit;
    }

    $input = json_decode(file_get_contents('php://input'), true);

    $nfacture = trim($input['nfacture'] ?? '');
    $client_isf = trim($input['client_isf'] ?? '');

    if (empty($nfacture)) {
        echo json_encode(['success' => false, 'message' => 'Paramètre nfacture requis']);
        return;
    }

    // Appel API OSAT-Energie Service Bill via POST
    $osatUrl = 'https://osat-energie.com/dgi/facture.php';

    $postData = 'nfacture=' . urlencode($nfacture) . '&client_isf=' . urlencode($client_isf);

    $ch = curl_init($osatUrl);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $postData);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Content-Type: application/x-www-form-urlencoded',
        'Accept: application/json'
    ]);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 30);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlError = curl_error($ch);
    curl_close($ch);

    if ($response === false || empty($response)) {
        echo json_encode(['success' => false, 'message' => 'Erreur connexion API Service Bill: ' . $curlError]);
        return;
    }

    // Parser la réponse JSON si possible
    $data = json_decode($response, true);
    if (json_last_error() === JSON_ERROR_NONE) {
        echo json_encode(['success' => true, 'data' => $data]);
    } else {
        echo json_encode(['success' => true, 'data' => $response]);
    }
});
