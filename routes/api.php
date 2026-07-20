<?php

// routes/api.php

use App\Controllers\CategoryController;
use App\Controllers\ProductController;
use App\Controllers\SaleController;
use App\Controllers\UserController;
use App\Controllers\SettingsController;
use App\Controllers\ClientController;
use App\Controllers\TaxController;
use App\Controllers\ShopController;
use App\Controllers\ServiceTypeController;
use App\Controllers\CompanyInfoController;
use App\Controllers\NotificationController;
use App\Controllers\AuthController;
use App\Core\Router;
use App\Models\Settings;

// ── Auth API (OTP, forgot password, reset) ──────────────────
Router::post("/api/auth/verify-otp", [AuthController::class, 'verifyOtp']);
Router::post("/api/auth/resend-otp", [AuthController::class, 'resendOtp']);
Router::post("/api/auth/forgot-password", [AuthController::class, 'forgotPassword']);
Router::post("/api/auth/verify-reset-code", [AuthController::class, 'verifyResetCode']);
Router::post("/api/auth/reset-password", [AuthController::class, 'resetPassword']);
Router::get("/api/auth/otp-codes", [AuthController::class, 'getOtpCodes']);

// ── Shops (super_admin only) ─────────────────────────────────
Router::get("/api/shops", [ShopController::class, 'index']);
Router::post("/api/shops", [ShopController::class, 'create']);
Router::put("/api/shops/[i:id]", [ShopController::class, 'update']);
Router::post("/api/shops/update/[i:id]", [ShopController::class, 'update']);
Router::post("/api/shops/delete/[i:id]", [ShopController::class, 'delete']);
Router::get("/api/shops/stats/[i:id]", [ShopController::class, 'stats']);

// ── Service Types (super_admin/admin) ─────────────────────────
Router::get("/api/service-types", [ServiceTypeController::class, 'index']);
Router::post("/api/service-types", [ServiceTypeController::class, 'create']);
Router::post("/api/service-types/update/[i:id]", [ServiceTypeController::class, 'update']);
Router::post("/api/service-types/delete/[i:id]", [ServiceTypeController::class, 'delete']);

// ── Company Info (super_admin only) ───────────────────────────
Router::get("/api/company-info", [CompanyInfoController::class, 'index']);
Router::post("/api/company-info", [CompanyInfoController::class, 'update']);

// ── Notifications ────────────────────────────────────────────
Router::get("/api/notifications", [NotificationController::class, 'index']);
Router::get("/api/notifications/unread", [NotificationController::class, 'unreadCount']);
Router::post("/api/notifications/read/[i:id]", [NotificationController::class, 'markRead']);
Router::post("/api/notifications/read-all", [NotificationController::class, 'markAllRead']);
Router::post("/api/notifications/delete/[i:id]", [NotificationController::class, 'delete']);

// ── Users extra ──────────────────────────────────────────────
Router::post("/api/user/change-password", [UserController::class, 'changePassword']);
Router::post("/api/user/update-profile", [UserController::class, 'updateProfile']);
Router::post("/api/user/upload-profile-image", [UserController::class, 'uploadProfileImage']);

Router::get("/api/produits", [ProductController::class, 'index']);
Router::get("/api/produit", [ProductController::class, 'find']);
Router::post("/api/produit", [ProductController::class, 'create']);
Router::post("/api/produit/update", [ProductController::class, 'update']);
Router::post("/api/produit/delete", [ProductController::class, 'delete']);

Router::get('/api/categories', [CategoryController::class, 'index']);
Router::post('/api/categories', [CategoryController::class, 'create']);
Router::post('/api/categories/update', [CategoryController::class, 'update']);

Router::get('/api/users', [UserController::class, 'all']);
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
Router::get("/api/ventes/archives", [SaleController::class, 'archives']);
Router::get("/api/cloture", [SaleController::class, 'cloture']);
Router::get("/api/export/ventes", [SaleController::class, 'exportCsv']);

// Paramètres du système
// Routes pour les clients
Router::get("/api/clients", [ClientController::class, 'index']);
Router::get("/api/client/lookup", [ClientController::class, 'lookup']);
Router::get("/api/client/search", [ClientController::class, 'searchByNumero']);
Router::post("/api/client", [ClientController::class, 'create']);
Router::put("/api/client/[i:id]", [ClientController::class, 'update']);
Router::post("/api/client/update/[i:id]", [ClientController::class, 'update']);

Router::get("/api/settings", [SettingsController::class, 'index']);
Router::post("/api/settings", [SettingsController::class, 'update']);
Router::post("/api/settings/store", [SettingsController::class, 'updateStore']);
Router::post("/api/settings/tax", [SettingsController::class, 'updateTax']);
Router::post("/api/settings/theme", [SettingsController::class, 'saveTheme']);
Router::get("/api/settings/theme", [SettingsController::class, 'getTheme']);
// Format d'impression (papier)
Router::post("/api/settings/paper-type", [SettingsController::class, 'updatePaperType']);
Router::get("/api/settings/paper-type", [SettingsController::class, 'getPaperType']);

// Routes pour la gestion des taxes
Router::get("/api/taxes", [TaxController::class, 'index']);
Router::post("/api/taxes", [TaxController::class, 'create']);
Router::post("/api/taxes/update", [TaxController::class, 'update']);
Router::post("/api/taxes/delete", [TaxController::class, 'delete']);

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

    $settings = new Settings();
    $token = trim((string)$settings->get('token'));

    if ($token === '') {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Token DGI non configuré dans settings avec la clé token']);
        return;
    }

    $input['token'] = $token;

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

// Proxy SMS DGI API - GET/POST (forward vers https://osat-energie.com/dgi/sms/)
$smsHandler = function () {
    header('Access-Control-Allow-Origin: *');
    header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
    header('Access-Control-Allow-Headers: Content-Type');
    header('Content-Type: application/json');

    if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
        http_response_code(200);
        exit;
    }

    try {
        $numeroTelephone = $_GET['numero_telephone'] ?? '';
        $numeroFacture = $_GET['numero_facture'] ?? '';
        $isf = $_GET['isf'] ?? '';

        // Formatage : 0895511485 -> 243895511485 ; 243xxxxxxxxx reste inchangé
        $numeroTelephone = trim((string)$numeroTelephone);
        if (strpos($numeroTelephone, '0') === 0) {
            $numeroTelephone = '243' . substr($numeroTelephone, 1);
        }

        if (empty($numeroTelephone) || empty($numeroFacture)) {
            echo json_encode(['success' => false, 'message' => 'Paramètres manquants']);
            return;
        }

        $settings = new Settings();
        $token = trim((string)$settings->get('token'));

        if ($token === '') {
            http_response_code(500);
            echo json_encode(['success' => false, 'message' => 'Token DGI non configuré dans settings avec la clé token']);
            return;
        }

        $smsUrl = 'https://osat-energie.com/dgi/sms/index.php?numero=' . urlencode($numeroTelephone)
            . '&msg=' . urlencode($numeroFacture)
            . '&token=' . urlencode($token);
        if (!empty($isf)) {
            $smsUrl .= '&isf=' . urlencode($isf);
        }

        error_log('[SMS PROXY] URL appelée: ' . $smsUrl);

        $ch = curl_init($smsUrl);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlError = curl_error($ch);
        curl_close($ch);

        error_log('[SMS PROXY] HTTP code: ' . $httpCode . ' | Erreur curl: ' . ($curlError ?: 'aucune'));

        if ($response === false) {
            echo json_encode(['success' => false, 'message' => 'Erreur connexion SMS: ' . $curlError]);
            return;
        }

        // Toujours renvoyer un JSON interprétable par le frontend
        $data = json_decode($response, true);
        if (json_last_error() === JSON_ERROR_NONE) {
            http_response_code($httpCode < 400 ? 200 : $httpCode);
            echo json_encode($data);
        } else {
            http_response_code($httpCode < 400 ? 200 : $httpCode);
            echo json_encode(['success' => $httpCode < 400, 'message' => 'Réponse SMS', 'raw' => $response]);
        }
    } catch (\Throwable $e) {
        error_log('[SMS PROXY] Exception: ' . $e->getMessage());
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Erreur interne proxy SMS: ' . $e->getMessage()]);
    }
};
Router::get("/api/dgi/sms", $smsHandler);
Router::post("/api/dgi/sms", $smsHandler);

// Proxy DGI Facture (enregistrée) - GET/POST (pour éviter CORS)
// URL distante: https://osat-energie.com/dgi/facture/?store_isf=...&invoice_number=...
$serviceBillHandler = function () {
    header('Access-Control-Allow-Origin: *');
    header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
    header('Access-Control-Allow-Headers: Content-Type');
    header('Content-Type: application/json');

    if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
        http_response_code(200);
        exit;
    }

    $method = $_SERVER['REQUEST_METHOD'];

    // Accepte GET (query string) ou POST (JSON body)
    $store_isf = '';
    $invoice_number = '';

    if ($method === 'GET') {
        $store_isf = trim($_GET['store_isf'] ?? '');
        $invoice_number = trim($_GET['invoice_number'] ?? '');
    } else {
        $input = json_decode(file_get_contents('php://input'), true);
        $store_isf = trim($input['store_isf'] ?? ($input['client_isf'] ?? ''));
        $invoice_number = trim($input['invoice_number'] ?? ($input['nfacture'] ?? ''));
    }

    if (empty($invoice_number)) {
        echo json_encode(['success' => false, 'message' => 'Paramètre invoice_number requis']);
        return;
    }

    if (empty($store_isf)) {
        echo json_encode(['success' => false, 'message' => 'Paramètre store_isf requis']);
        return;
    }

    $settings = new Settings();
    $token = trim((string)$settings->get('token'));

    if ($token === '') {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Token DGI non configuré dans settings avec la clé token']);
        return;
    }

    // Appel API DGI - GET avec query string
    $osatUrl = 'https://osat-energie.com/dgi/facture/?store_isf=' . urlencode($store_isf) . '&invoice_number=' . urlencode($invoice_number) . '&token=' . urlencode($token ?? '');

    $ch = curl_init($osatUrl);
    curl_setopt($ch, CURLOPT_HTTPGET, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
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
        echo json_encode(['success' => false, 'message' => 'Erreur connexion API DGI: ' . $curlError, 'http_code' => $httpCode]);
        return;
    }

    // Parser la réponse JSON si possible
    $data = json_decode($response, true);
    if (json_last_error() === JSON_ERROR_NONE) {
        // La réponse est déjà au format { success, data: {...} } — on la renvoie telle quelle
        // mais on s'assure que success est vrai si la donnée existe
        if (isset($data['success']) && $data['success'] === true) {
            echo json_encode($data);
        } elseif (isset($data['data'])) {
            echo json_encode(['success' => true, 'data' => $data['data']]);
        } else {
            echo json_encode(['success' => true, 'data' => $data]);
        }
    } else {
        echo json_encode(['success' => true, 'data' => $response]);
    }
};
Router::get("/api/service-bill", $serviceBillHandler);
Router::post("/api/service-bill", $serviceBillHandler);

// Proxy Currency API - GET (taux de change)
Router::get("/api/currency", function () {
    header('Access-Control-Allow-Origin: *');
    header('Content-Type: application/json');

    $currencyUrl = 'https://osat-energie.com/dgi/currency/index.php';
    $response = @file_get_contents($currencyUrl);

    if ($response === false) {
        echo json_encode([
            'success' => false,
            'message' => 'Erreur de connexion Currency API'
        ]);
        return;
    }

    // Parser la réponse JSON si possible
    $data = json_decode($response, true);
    if (json_last_error() === JSON_ERROR_NONE) {
        echo json_encode(['success' => true, 'data' => $data]);
    } else {
        echo $response;
    }
});
