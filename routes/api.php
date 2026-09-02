<?php

// routes/api.php

use App\Controllers\AuthController;
use App\Controllers\CategoryController;
use App\Controllers\ClientController;
use App\Controllers\CompanyInfoController;
use App\Controllers\NotificationController;
use App\Controllers\PayrollAttendanceController;
use App\Controllers\PayrollController;
use App\Controllers\PayrollEmployeeController;
use App\Controllers\PayrollPaymentController;
use App\Controllers\PayrollPayslipController;
use App\Controllers\PayrollReportController;
use App\Controllers\PayrollTimeClockController;
use App\Controllers\ProductBatchController;
use App\Controllers\ProductController;
use App\Controllers\SaleController;
use App\Controllers\ServiceTypeController;
use App\Controllers\SettingsController;
use App\Controllers\ShopController;
use App\Controllers\TaxController;
use App\Controllers\UserController;
use App\Core\Router;
use App\Models\CompanyInfo;
use App\Models\Sale;
use App\Models\Shop;

// Garde d'authentification simple pour les endpoints proxy (évite l'abus par
// des visiteurs non connectés qui utiliseraient le serveur comme relais).
function requireAuthenticatedSession()
{
    if (!isset($_SESSION['user_id'])) {
        http_response_code(401);
        echo json_encode(['success' => false, 'message' => 'Authentification requise']);
        return false;
    }
    return true;
}

// ── Auth API (OTP, forgot password, reset) ──────────────────
Router::get('/api/auth/verify-otp', [AuthController::class, 'verifyOtp']);
Router::get('/api/auth/resend-otp', [AuthController::class, 'resendOtp']);
Router::get('/api/auth/forgot-password', [AuthController::class, 'forgotPassword']);
Router::post('/api/auth/verify-reset-code', [AuthController::class, 'verifyResetCode']);
Router::post('/api/auth/reset-password', [AuthController::class, 'resetPassword']);
Router::get('/api/auth/otp-codes', [AuthController::class, 'getOtpCodes']);

// ── Shops (super_admin only) ─────────────────────────────────
Router::get('/api/shops', [ShopController::class, 'index']);
Router::post('/api/shops', [ShopController::class, 'create']);
Router::put('/api/shops/[i:id]', [ShopController::class, 'update']);
Router::post('/api/shops/update/[i:id]', [ShopController::class, 'update']);
Router::post('/api/shops/delete/[i:id]', [ShopController::class, 'delete']);
Router::get('/api/shops/stats/[i:id]', [ShopController::class, 'stats']);

// ── Service Types (super_admin/admin) ─────────────────────────
Router::get('/api/service-types', [ServiceTypeController::class, 'index']);
Router::post('/api/service-types', [ServiceTypeController::class, 'create']);
Router::post('/api/service-types/update/[i:id]', [ServiceTypeController::class, 'update']);
Router::post('/api/service-types/delete/[i:id]', [ServiceTypeController::class, 'delete']);

// ── Company Info (super_admin only) ───────────────────────────
Router::get('/api/company-info', [CompanyInfoController::class, 'index']);
Router::post('/api/company-info', [CompanyInfoController::class, 'update']);

// ── Notifications ────────────────────────────────────────────
Router::get('/api/notifications', [NotificationController::class, 'index']);
Router::get('/api/notifications/unread', [NotificationController::class, 'unreadCount']);
Router::post('/api/notifications/read/[i:id]', [NotificationController::class, 'markRead']);
Router::post('/api/notifications/read-all', [NotificationController::class, 'markAllRead']);
Router::post('/api/notifications/delete/[i:id]', [NotificationController::class, 'delete']);

// ── Users extra ──────────────────────────────────────────────
Router::post('/api/user/change-password', [UserController::class, 'changePassword']);
Router::post('/api/user/update-profile', [UserController::class, 'updateProfile']);
Router::post('/api/user/upload-profile-image', [UserController::class, 'uploadProfileImage']);

Router::get('/api/produits', [ProductController::class, 'index']);
Router::get('/api/produit', [ProductController::class, 'find']);
Router::post('/api/produit', [ProductController::class, 'create']);
Router::post('/api/produit/update', [ProductController::class, 'update']);
Router::post('/api/produit/delete', [ProductController::class, 'delete']);

Router::get('/api/product-batches', [ProductBatchController::class, 'index']);
Router::post('/api/product-batches', [ProductBatchController::class, 'create']);
Router::post('/api/product-batches/update', [ProductBatchController::class, 'update']);
Router::post('/api/product-batches/delete', [ProductBatchController::class, 'delete']);
Router::get('/api/product-batches/alerts', [ProductBatchController::class, 'alerts']);

Router::get('/api/categories', [CategoryController::class, 'index']);
Router::post('/api/categories', [CategoryController::class, 'create']);
Router::post('/api/categories/update', [CategoryController::class, 'update']);

Router::get('/api/users', [UserController::class, 'all']);
Router::post('/api/create/user', [UserController::class, 'create']);
Router::post('/api/update/user', [UserController::class, 'update']);
Router::post('/api/delete/user', [UserController::class, 'delete']);

// Suppression d'une catégorie
Router::post('/api/delete/category', [CategoryController::class, 'delete']);

// Suppression d'une vente
Router::post('/api/delete/vente', [SaleController::class, 'delete']);
Router::post('/api/vente', [SaleController::class, 'create']);
Router::get('/api/vente/[i:id]/details', [SaleController::class, 'details']);
Router::get('/api/vente/next-invoice', [SaleController::class, 'nextInvoice']);
Router::get('/api/ventes/archives', [SaleController::class, 'archives']);
Router::get('/api/cloture', [SaleController::class, 'cloture']);
Router::get('/api/export/ventes', [SaleController::class, 'exportCsv']);

// ── Payroll (employés / vendeurs) ───────────────────────────
Router::get('/api/payroll/employees', [PayrollEmployeeController::class, 'index']);
Router::get('/api/payroll/employees/vendors', [PayrollEmployeeController::class, 'vendors']);
Router::get('/api/payroll/employees/[i:id]', [PayrollEmployeeController::class, 'show']);
Router::post('/api/payroll/employees', [PayrollEmployeeController::class, 'create']);
Router::post('/api/payroll/employees/update/[i:id]', [PayrollEmployeeController::class, 'update']);
Router::post('/api/payroll/employees/delete/[i:id]', [PayrollEmployeeController::class, 'delete']);

// ── Payroll périodes & paramètres ───────────────────────────
Router::get('/api/payroll/periods', [PayrollController::class, 'periods']);
Router::post('/api/payroll/periods', [PayrollController::class, 'createPeriod']);
Router::post('/api/payroll/periods/update/[i:id]', [PayrollController::class, 'updatePeriod']);
Router::post('/api/payroll/periods/delete/[i:id]', [PayrollController::class, 'deletePeriod']);
Router::post('/api/payroll/periods/calculate/[i:id]', [PayrollController::class, 'calculate']);
Router::post('/api/payroll/periods/validate/[i:id]', [PayrollController::class, 'validatePeriod']);
Router::post('/api/payroll/periods/close/[i:id]', [PayrollController::class, 'closePeriod']);
Router::get('/api/payroll/parameters', [PayrollController::class, 'parameters']);
Router::get('/api/payroll/payment-methods', [PayrollController::class, 'paymentMethods']);
Router::post('/api/payroll/payment-methods', [PayrollController::class, 'createPaymentMethod']);
Router::post('/api/payroll/payment-methods/update/[i:id]', [PayrollController::class, 'updatePaymentMethod']);
Router::post('/api/payroll/payment-methods/delete/[i:id]', [PayrollController::class, 'deletePaymentMethod']);

// ── Payroll paramètres (avantages, retenues, cotisations, barème)
Router::get('/api/payroll/allowances', [PayrollController::class, 'allowances']);
Router::post('/api/payroll/allowances', [PayrollController::class, 'createAllowance']);
Router::post('/api/payroll/allowances/update/[i:id]', [PayrollController::class, 'updateAllowance']);
Router::post('/api/payroll/allowances/delete/[i:id]', [PayrollController::class, 'deleteAllowance']);

Router::get('/api/payroll/deductions', [PayrollController::class, 'deductions']);
Router::post('/api/payroll/deductions', [PayrollController::class, 'createDeduction']);
Router::post('/api/payroll/deductions/update/[i:id]', [PayrollController::class, 'updateDeduction']);
Router::post('/api/payroll/deductions/delete/[i:id]', [PayrollController::class, 'deleteDeduction']);

Router::get('/api/payroll/contributions', [PayrollController::class, 'contributions']);
Router::post('/api/payroll/contributions', [PayrollController::class, 'createContribution']);
Router::post('/api/payroll/contributions/update/[i:id]', [PayrollController::class, 'updateContribution']);
Router::post('/api/payroll/contributions/delete/[i:id]', [PayrollController::class, 'deleteContribution']);

Router::get('/api/payroll/seniority', [PayrollController::class, 'seniority']);
Router::post('/api/payroll/seniority', [PayrollController::class, 'createSeniority']);
Router::post('/api/payroll/seniority/update/[i:id]', [PayrollController::class, 'updateSeniority']);
Router::post('/api/payroll/seniority/delete/[i:id]', [PayrollController::class, 'deleteSeniority']);

// ── Payroll présences / absences / heures supp ─────────────
Router::get('/api/payroll/attendance/period/[i:id]', [PayrollAttendanceController::class, 'forPeriod']);
Router::post('/api/payroll/attendance', [PayrollAttendanceController::class, 'save']);
Router::post('/api/payroll/attendance/bulk', [PayrollAttendanceController::class, 'bulkSave']);
Router::post('/api/payroll/absence', [PayrollAttendanceController::class, 'saveAbsence']);
Router::post('/api/payroll/absence/update/[i:id]', [PayrollAttendanceController::class, 'updateAbsence']);
Router::post('/api/payroll/absence/delete/[i:id]', [PayrollAttendanceController::class, 'deleteAbsence']);
Router::post('/api/payroll/overtime', [PayrollAttendanceController::class, 'saveOvertime']);
Router::post('/api/payroll/overtime/update/[i:id]', [PayrollAttendanceController::class, 'updateOvertime']);
Router::post('/api/payroll/overtime/delete/[i:id]', [PayrollAttendanceController::class, 'deleteOvertime']);

// ── Payroll bulletins ───────────────────────────────────────
Router::get('/api/payroll/payslips/period/[i:id]', [PayrollPayslipController::class, 'forPeriod']);
Router::get('/api/payroll/payslips/my', [PayrollPayslipController::class, 'myPayslips']);
Router::get('/api/payroll/payslips/[i:id]', [PayrollPayslipController::class, 'show']);
Router::post('/api/payroll/payslips/calculate', [PayrollPayslipController::class, 'calculateOne']);
Router::post('/api/payroll/payslips/period/calculate/[i:id]', [PayrollPayslipController::class, 'calculatePeriod']);
Router::post('/api/payroll/payslips/validate/[i:id]', [PayrollPayslipController::class, 'validate']);
Router::get('/api/payroll/payslips/pdf/[i:id]', [PayrollPayslipController::class, 'streamPdf']);
Router::post('/api/payroll/payslips/pdf/[i:id]', [PayrollPayslipController::class, 'generatePdf']);

// ── Payroll paiements ───────────────────────────────────────
Router::get('/api/payroll/payments/payslip/[i:id]', [PayrollPaymentController::class, 'forPayslip']);
Router::post('/api/payroll/payments', [PayrollPaymentController::class, 'create']);
Router::post('/api/payroll/payments/update/[i:id]', [PayrollPaymentController::class, 'update']);
Router::post('/api/payroll/payments/delete/[i:id]', [PayrollPaymentController::class, 'delete']);

// ── Payroll pointage / import ───────────────────────────────
Router::get('/api/payroll/timeclock/period/[i:id]', [PayrollTimeClockController::class, 'forPeriod']);
Router::post('/api/payroll/timeclock', [PayrollTimeClockController::class, 'create']);
Router::post('/api/payroll/timeclock/update/[i:id]', [PayrollTimeClockController::class, 'update']);
Router::post('/api/payroll/timeclock/delete/[i:id]', [PayrollTimeClockController::class, 'delete']);
Router::post('/api/payroll/timeclock/import', [PayrollTimeClockController::class, 'import']);

// ── Payroll rapports ────────────────────────────────────────
Router::get('/api/payroll/reports/period/[i:id]', [PayrollReportController::class, 'periodSummary']);
Router::get('/api/payroll/reports/csv/[i:id]', [PayrollReportController::class, 'periodCsv']);
Router::get('/api/payroll/reports/payments/[i:id]', [PayrollReportController::class, 'payments']);
Router::get('/api/payroll/reports/contributions/[i:id]', [PayrollReportController::class, 'contributions']);
Router::get('/api/payroll/reports/headcount', [PayrollReportController::class, 'headcount']);

// Paramètres du système
// Routes pour les clients
Router::get('/api/clients', [ClientController::class, 'index']);
Router::get('/api/client/lookup', [ClientController::class, 'lookup']);
Router::get('/api/client/search', [ClientController::class, 'searchByNumero']);
Router::post('/api/client', [ClientController::class, 'create']);
Router::put('/api/client/[i:id]', [ClientController::class, 'update']);
Router::post('/api/client/update/[i:id]', [ClientController::class, 'update']);

Router::get('/api/settings', [SettingsController::class, 'index']);
Router::post('/api/settings', [SettingsController::class, 'update']);
Router::post('/api/settings/store', [SettingsController::class, 'updateStore']);
Router::post('/api/settings/tax', [SettingsController::class, 'updateTax']);
Router::post('/api/settings/theme', [SettingsController::class, 'saveTheme']);
Router::get('/api/settings/theme', [SettingsController::class, 'getTheme']);
// Format d'impression (papier)
Router::post('/api/settings/paper-type', [SettingsController::class, 'updatePaperType']);
Router::get('/api/settings/paper-type', [SettingsController::class, 'getPaperType']);
// Padding d'affichage des articles sur le ticket (57mm / 80mm)
Router::post('/api/settings/receipt-padding', [SettingsController::class, 'updateReceiptPadding']);
Router::get('/api/settings/receipt-padding', [SettingsController::class, 'getReceiptPadding']);

// Routes pour la gestion des taxes
Router::get('/api/taxes', [TaxController::class, 'index']);
Router::post('/api/taxes', [TaxController::class, 'create']);
Router::post('/api/taxes/update', [TaxController::class, 'update']);
Router::post('/api/taxes/delete', [TaxController::class, 'delete']);

// Proxy Bill Payment API (OSAT-Energie pour éviter CORS)
// POST vers https://osat-energie.com/snel_regideso/
Router::post('/api/bill-payment', function () {
    header('Access-Control-Allow-Origin: *');
    header('Access-Control-Allow-Methods: POST, OPTIONS');
    header('Access-Control-Allow-Headers: Content-Type');
    header('Content-Type: application/json');

    if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
        http_response_code(200);
        exit;
    }

    if (!requireAuthenticatedSession()) {
        return;
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
            'service' => $service,
        ]);

        $ch = curl_init($osatUrl);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $postData);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Content-Type: application/json',
            'Accept: application/json',
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
Router::get('/api/dgi', function () {
    header('Access-Control-Allow-Origin: *');
    header('Content-Type: application/json');

    if (!requireAuthenticatedSession()) {
        return;
    }

    $dgiUrl = 'https://osat-energie.com/dgi/';
    $response = @file_get_contents($dgiUrl);

    if ($response === false) {
        echo json_encode([
            'success' => false,
            'message' => 'Erreur de connexion DGI',
        ]);
        return;
    }

    echo $response;
});

// Proxy DGI API - POST uniquement (forward au serveur DGI)
Router::post('/api/dgi', function () {
    header('Access-Control-Allow-Origin: *');
    header('Access-Control-Allow-Methods: POST, OPTIONS');
    header('Access-Control-Allow-Headers: Content-Type');
    header('Content-Type: application/json');

    if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
        http_response_code(200);
        exit;
    }

    if (!requireAuthenticatedSession()) {
        return;
    }

    $input = json_decode(file_get_contents('php://input'), true);

    if (!$input) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Données invalides']);
        return;
    }

    $shopId = $_SESSION['shop_id'] ?? null;
    $shop = $shopId ? (new Shop())->findById($shopId) : null;
    $token = trim((string)($shop['token'] ?? ''));

    if ($token === '') {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Token DGI non configuré pour cette boutique']);
        return;
    }

    $input['token'] = $token;

    // Statut d'homologation DGI (RCCM/licence) du magasin : calculé côté
    // serveur à partir de la base de données pour ne pas pouvoir être
    // falsifié par le client, puis injecté/écrasé dans la requête envoyée
    // à la DGI.
    $input['store_homologation'] = (bool)($shop['homologation'] ?? false);

    // Forward vers le serveur DGI
    $dgiUrl = 'https://osat-energie.com/dgi/';
    $postData = json_encode($input);

    $ch = curl_init($dgiUrl);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $postData);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Content-Type: application/json',
        'Content-Length: ' . strlen($postData),
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
            'message' => 'DGI inaccessible',
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

    if (!requireAuthenticatedSession()) {
        return;
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

        $shopId = $_SESSION['shop_id'] ?? null;
        $shop = $shopId ? (new Shop())->findById($shopId) : null;
        $token = trim((string)($shop['token'] ?? ''));

        if ($token === '') {
            http_response_code(500);
            echo json_encode(['success' => false, 'message' => 'Token DGI non configuré pour cette boutique']);
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
Router::get('/api/dgi/sms', $smsHandler);
Router::post('/api/dgi/sms', $smsHandler);

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

    if (!requireAuthenticatedSession()) {
        return;
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

    // La boutique de référence est celle qui a émis la facture, pas celle de la
    // session : un super_admin n'a pas de shop_id et doit pouvoir consulter les
    // factures de toutes les boutiques.
    $sessionShopId = $_SESSION['shop_id'] ?? null;
    $isSuperAdmin = ($_SESSION['role'] ?? '') === 'super_admin';

    $sale = (new Sale())->findByInvoiceNumber($invoice_number);
    $shopId = $sale['shop_id'] ?? $sessionShopId;

    // Isolation multi-boutique : hors super_admin, on refuse les factures d'une autre boutique
    if (!$isSuperAdmin && $shopId && $sessionShopId && $shopId != $sessionShopId) {
        http_response_code(403);
        echo json_encode(['success' => false, 'message' => 'Cette facture ne fait pas partie de votre boutique']);
        return;
    }

    $shopModel = new Shop();
    $shop = $shopId ? $shopModel->findById($shopId) : null;
    $companyInfo = (new CompanyInfo())->get();

    // Statut d'homologation DGI (RCCM/licence) de la boutique émettrice de
    // la facture, calculé côté serveur pour ne pas être falsifiable.
    $homologation = (bool)($shop['homologation'] ?? false);

    // L'ISF de recherche est celui figé sur la vente à sa création : c'est le
    // seul fiable, l'ISF de l'utilisateur qui consulte pouvant être différent
    // (super_admin, autre boutique). Replis pour les ventes antérieures à la
    // migration : boutique émettrice, puis société.
    $saleIsf = trim((string)($sale['store_isf'] ?? ''));
    if ($saleIsf === '') {
        $saleIsf = trim((string)($shop['isf'] ?? ''));
    }
    if ($saleIsf === '') {
        $saleIsf = trim((string)($companyInfo['isf'] ?? ''));
    }

    if ($saleIsf !== '') {
        $store_isf = $saleIsf;
    }

    if (empty($store_isf)) {
        echo json_encode(['success' => false, 'message' => 'Paramètre store_isf requis']);
        return;
    }

    // Le token doit appartenir au même émetteur que l'ISF interrogé : on le
    // résout donc à partir de l'ISF, pas de la session.
    $token = '';
    if (trim((string)($companyInfo['isf'] ?? '')) === $store_isf) {
        $token = trim((string)($companyInfo['token'] ?? ''));
    }
    if ($token === '') {
        $isfShop = $shopModel->findByIsf($store_isf);
        $token = trim((string)($isfShop['token'] ?? ''));
    }
    if ($token === '') {
        $token = trim((string)($shop['token'] ?? ''));
    }
    if ($token === '') {
        $token = trim((string)($companyInfo['token'] ?? ''));
    }

    if ($token === '') {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Token DGI non configuré pour cette boutique']);
        return;
    }

    // Appel API DGI - GET avec query string
    $osatUrl = 'https://osat-energie.com/dgi/facture/?store_isf=' . urlencode($store_isf) . '&invoice_number=' . urlencode($invoice_number) . '&token=' . urlencode($token ?? '');

    $ch = curl_init($osatUrl);
    curl_setopt($ch, CURLOPT_HTTPGET, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Accept: application/json',
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
            if (isset($data['data']) && is_array($data['data'])) {
                $data['data']['homologation'] = $homologation;
            }
            echo json_encode($data);
        } elseif (isset($data['success']) && $data['success'] === false) {
            // Echec explicite de la DGI : on le propage tel quel
            echo json_encode($data);
        } elseif (isset($data['data'])) {
            $facture = $data['data'];
            if (is_array($facture)) {
                $facture['homologation'] = $homologation;
            }
            echo json_encode(['success' => true, 'data' => $facture]);
        } else {
            if (is_array($data)) {
                $data['homologation'] = $homologation;
            }
            echo json_encode(['success' => true, 'data' => $data]);
        }
    } else {
        echo json_encode(['success' => true, 'data' => $response]);
    }
};
Router::get('/api/service-bill', $serviceBillHandler);
Router::post('/api/service-bill', $serviceBillHandler);

// Proxy Currency API - GET (taux de change)
Router::get('/api/currency', function () {
    header('Access-Control-Allow-Origin: *');
    header('Content-Type: application/json');

    if (!requireAuthenticatedSession()) {
        return;
    }

    $currencyUrl = 'https://osat-energie.com/dgi/currency/index.php';
    $response = @file_get_contents($currencyUrl);

    if ($response === false) {
        echo json_encode([
            'success' => false,
            'message' => 'Erreur de connexion Currency API',
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
