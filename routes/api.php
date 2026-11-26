<?php

// routes/api.php

use App\Controllers\AuthController;
use App\Controllers\CaisseBrouillonController;
use App\Controllers\CategoryController;
use App\Controllers\ClientController;
use App\Controllers\CompanyInfoController;
use App\Controllers\DashboardController;
use App\Controllers\DgiSyncController;
use App\Controllers\DgiWebhookController;
use App\Controllers\MobileAuthController;
use App\Controllers\NotificationController;
use App\Controllers\PageController;
use App\Controllers\PayrollAttendanceController;
use App\Controllers\PayrollContractController;
use App\Controllers\PayrollController;
use App\Controllers\PayrollEmployeeController;
use App\Controllers\PayrollPaymentController;
use App\Controllers\PayrollPayslipController;
use App\Controllers\PayrollRecapImportController;
use App\Controllers\PayrollReportController;
use App\Controllers\PayrollTimeClockController;
use App\Controllers\PressingController;
use App\Controllers\ProductBatchController;
use App\Controllers\ProductController;
use App\Controllers\ProductExportController;
use App\Controllers\ProductImportController;
use App\Controllers\ProductRepairController;
use App\Controllers\ReportController;
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

// ── Auth MOBILE (app Android) ───────────────────────────────────────────
// L'app mobile ne manipule pas le cookie de session : elle utilise un TOKEN
// BEARER. Le token est résolu et hydraté en $_SESSION AVANT le routeur
// (cf. public/index.php) : toutes les routes métier existantes
// (/api/produits, /api/vente, /api/dgi, /api/currency…) restent utilisables
// telles quelles par l'app mobile.
//
// ⚠️ Ces routes sont HORS politique de page : elles relèvent de
//    l'authentification, pas d'une page. Les routes métier gardent leur
//    politique ET leur garde de boutique désactivée.
//
// Documentation : docs/API_MOBILE.md
Router::post('/api/mobile/auth/login', [MobileAuthController::class, 'login']);
Router::post('/api/mobile/auth/verify-otp', [MobileAuthController::class, 'verifyOtp']);
Router::post('/api/mobile/auth/resend-otp', [MobileAuthController::class, 'resendOtp']);
Router::post('/api/mobile/auth/logout', [MobileAuthController::class, 'logout']);
Router::get('/api/mobile/auth/me', [MobileAuthController::class, 'me']);
Router::get('/api/mobile/auth/devices', [MobileAuthController::class, 'devices']);
Router::post('/api/mobile/auth/revoke-all', [MobileAuthController::class, 'revokeAll']);

// ── Shops (super_admin only) ─────────────────────────────────
Router::get('/api/shops', [ShopController::class, 'index']);
Router::post('/api/shops', [ShopController::class, 'create']);
Router::put('/api/shops/[i:id]', [ShopController::class, 'update']);
Router::post('/api/shops/update/[i:id]', [ShopController::class, 'update']);
Router::post('/api/shops/delete/[i:id]', [ShopController::class, 'delete']);
Router::get('/api/shops/stats/[i:id]', [ShopController::class, 'stats']);

// Vérification temps réel du statut actif du shop de l'utilisateur connecté.
// Permet la « réhydratation » côté front : si le shop est désactivé pendant
// la session, le client est redirigé vers /logout?reason=shop_disabled.
Router::get('/api/shops/check-active', function () {
    header('Content-Type: application/json');

    if (!isset($_SESSION['user_id'])) {
        http_response_code(401);
        echo json_encode(['success' => false, 'error' => 'Non authentifié']);
        return;
    }

    // super_admin n'est rattaché à aucun shop → toujours actif
    if (($_SESSION['role'] ?? '') === 'super_admin') {
        echo json_encode(['success' => true, 'active' => true, 'reason' => 'super_admin']);
        return;
    }

    $shopId = $_SESSION['shop_id'] ?? null;
    if (!$shopId) {
        echo json_encode(['success' => true, 'active' => true, 'reason' => 'no_shop']);
        return;
    }

    $shop = (new \App\Models\Shop())->findById($shopId);
    $active = $shop && !empty($shop['actif']);
    echo json_encode([
        'success' => true,
        'active'  => $active,
        'shop_id' => $shopId,
        'reason'  => $active ? null : 'shop_disabled',
    ]);
});

// ── Politique d'accès aux pages (super_admin) ────────────────────
// ⚠️ Ces routes sont HORS POLITICE : elles administrent la politique qui
// restreint les autres rôles. Les bloquer rendrait la politique
// ingérable (personne ne pourrait plus la modifier).
//   - POST /api/user/page-access/update → bloque/débloque une page pour un user
Router::post('/api/user/page-access/update', [PageController::class, 'apiUserPageAccessUpdate']);

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
// Contexte complet de l'utilisateur connecté (session + boutique + NIF).
Router::get('/api/me', [UserController::class, 'me']);
Router::post('/api/user/change-password', [UserController::class, 'changePassword']);
Router::post('/api/user/update-profile', [UserController::class, 'updateProfile']);
Router::post('/api/user/upload-profile-image', [UserController::class, 'uploadProfileImage']);

// ── Produits ───────────────────────────────────────────────────────────
// Politique d'accès aux pages (super_admin) : le 4e argument est le mode
// ('exclusive' | 'shared_read' | 'shared_write'). Voir
// docs/superpowers/specs/2026-11-18-shop-page-access-policy-design.md

// LECTURES PARTAGÉES : le catalogue alimente la caisse, le scanner et le
// tableau de bord. Elles restent disponibles si AU MOINS UNE page lectrice
// est accessible (sinon la caisse tomberait en panne).
Router::get('/api/produits', [ProductController::class, 'index'], 'caisse|produits', 'shared_read');
Router::get('/api/produit', [ProductController::class, 'find'], 'caisse|produits', 'shared_read');
// PAGE de produits (pagination serveur) : évite de charger le catalogue
// entier (3,2 Mo de JSON à 50 000 produits). `/api/produits` reste
// inchangée pour le client mobile Android et la caisse.
Router::get('/api/produits/page', [ProductController::class, 'page'], 'caisse|produits', 'shared_read');
// Un produit par id : la caisse l'utilise quand l'article n'est pas encore
// dans le bloc de catalogue chargé (catalogue paginé).
Router::get('/api/produit/[i:id]', [ProductController::class, 'show'], 'caisse|produits', 'shared_read');
// ÉCRITURES : gestion du catalogue = page Produits.
Router::post('/api/produit', [ProductController::class, 'create'], 'produits');
Router::post('/api/produit/update', [ProductController::class, 'update'], 'produits');
Router::post('/api/produit/delete', [ProductController::class, 'delete'], 'produits');

// Import de produits depuis un fichier Excel / CSV
Router::post('/api/produit/import', [ProductImportController::class, 'import'], 'produits');
Router::get('/api/produit/import/template', [ProductImportController::class, 'template'], 'produits');
Router::get('/api/produit/import/categories', [ProductImportController::class, 'categories'], 'produits');
// Mode « par blocs » : l'import d'un gros fichier est découpé en blocs
// de 5 000 lignes, chaque bloc étant une requête HTTP distincte.
// ⚠️ Sans ça, l'import de 50 000 produits se fait TUER par
// `max_execution_time` (120 s) vers le 26 000e produit.
Router::post('/api/produit/import/chunk', [ProductImportController::class, 'chunk'], 'produits');
Router::post('/api/produit/import/abort', [ProductImportController::class, 'abort'], 'produits');

// Export du catalogue produits vers Excel
Router::get('/api/produit/export', [ProductExportController::class, 'export'], 'produits');

// Réparation des codes-barres corrompus par l'import Excel.
// L'import lisait la valeur AFFICHÉE de la cellule (Excel formate les
// codes-barres à 13 chiffres en date : « 8426423308202 » → « 5/17/23070765319 »).
// GET  = rapport (dry-run, aucune écriture)
// POST = applique la réparation, uniquement si confirm=1 est envoyé.
// Réservé au super_admin (écriture en base sur l'ensemble des boutiques).
Router::get('/api/produit/repair-barcodes', [ProductRepairController::class, 'index']);
Router::post('/api/produit/repair-barcodes', [ProductRepairController::class, 'repair']);

Router::get('/api/product-batches', [ProductBatchController::class, 'index'], 'produits');
Router::post('/api/product-batches', [ProductBatchController::class, 'create'], 'produits');
Router::post('/api/product-batches/update', [ProductBatchController::class, 'update'], 'produits');
Router::post('/api/product-batches/delete', [ProductBatchController::class, 'delete'], 'produits');
Router::get('/api/product-batches/alerts', [ProductBatchController::class, 'alerts'], 'produits');
// Page des lots (pagination keyset) — cf. R3/R4.
Router::get('/api/product-batches/page', [ProductBatchController::class, 'page'], 'produits');

// LECTURE PARTAGÉE : les catégories alimentent le catalogue ET la caisse.
Router::get('/api/categories', [CategoryController::class, 'index'], 'caisse|produits|categories', 'shared_read');
Router::post('/api/categories', [CategoryController::class, 'create'], 'categories');
Router::post('/api/categories/update', [CategoryController::class, 'update'], 'categories');

Router::get('/api/users', [UserController::class, 'all'], 'utilisateurs');
Router::post('/api/create/user', [UserController::class, 'create'], 'utilisateurs');
Router::post('/api/update/user', [UserController::class, 'update'], 'utilisateurs');
Router::post('/api/delete/user', [UserController::class, 'delete'], 'utilisateurs');

// Suppression d'une catégorie
Router::post('/api/delete/category', [CategoryController::class, 'delete'], 'categories');

// Suppression d'une vente
Router::post('/api/delete/vente', [SaleController::class, 'delete']);
Router::post('/api/vente', [SaleController::class, 'create']);
Router::get('/api/vente/[i:id]/details', [SaleController::class, 'details']);
Router::get('/api/vente/next-invoice', [SaleController::class, 'nextInvoice']);
Router::get('/api/ventes/archives', [SaleController::class, 'archives']);
Router::get('/api/cloture', [SaleController::class, 'cloture']);

// ============================================================================
// Brouillons de panier (caisse)
// Référence : docs/PLAN_BROUILLONS_CAISSE.md
//
// Le caissier peut sauvegarder son panier en cours puis le reprendre plus
// tard pour relancer la facture. Le brouillon ne déduit JAMAIS le stock et
// ne consomme JAMAIS un numéro de facture : la facturation passe
// exclusivement par POST /api/vente (SaleController::create).
//
// ⚠️ L'URL « /compteur » est déclarée AVANT « /[i:id] » : le routeur
// distingue les motifs littéraux des motifs à paramètre, mais l'ordre
// « lisible » évite toute ambiguïté lors d'une relecture.
// ============================================================================
Router::get('/api/caisse/brouillons', [CaisseBrouillonController::class, 'index']);
Router::get('/api/caisse/brouillons/compteur', [CaisseBrouillonController::class, 'compteur']);
Router::get('/api/caisse/brouillons/[i:id]', [CaisseBrouillonController::class, 'show']);
Router::post('/api/caisse/brouillons', [CaisseBrouillonController::class, 'store']);
Router::post('/api/caisse/brouillons/[i:id]/restore', [CaisseBrouillonController::class, 'restore']);
Router::post('/api/caisse/brouillons/[i:id]/delete', [CaisseBrouillonController::class, 'destroy']);
Router::get('/api/export/ventes', [SaleController::class, 'exportCsv']);

// ── Synchronisation DGI différée ───────────────────────────────────
//
// Cas "update" : la DGI était indisponible au moment de l'émission. OSAT a
// mis la demande en attente de son côté et revient nous chercher la mise à
// jour quand la DGI redevient disponible.
//
//   POST /api/dgi/update       → OSAT pousse la résolution (route principale,
//                                protégée par un secret partagé : X-DGI-Token
//                                ou X-DGI-Signature)
//   POST /api/vente/[id]/dgi-sync → relance manuelle d'une vente (session)
//   GET  /api/dgi/pending       → liste des ventes en attente (admin)
//   POST /api/dgi/sync-all      → relance par lot (admin)
Router::post('/api/dgi/update', [DgiWebhookController::class, 'update']);
Router::post('/api/vente/[i:id]/dgi-sync', [DgiSyncController::class, 'syncSale']);
Router::get('/api/dgi/pending', [DgiSyncController::class, 'pending']);
Router::post('/api/dgi/sync-all', [DgiSyncController::class, 'syncAll']);

// ── Payroll (employés / vendeurs) ───────────────────────────
Router::get('/api/payroll/employees', [PayrollEmployeeController::class, 'index'], 'paie');
Router::get('/api/payroll/employees/vendors', [PayrollEmployeeController::class, 'vendors'], 'paie');
Router::get('/api/payroll/employees/[i:id]', [PayrollEmployeeController::class, 'show'], 'paie');
Router::post('/api/payroll/employees', [PayrollEmployeeController::class, 'create'], 'paie');
Router::post('/api/payroll/employees/update/[i:id]', [PayrollEmployeeController::class, 'update'], 'paie');
Router::post('/api/payroll/employees/delete/[i:id]', [PayrollEmployeeController::class, 'delete'], 'paie');

// ── Payroll contrats ────────────────────────────────────────
// Le salaire de base et le statut « payé / non payé » d'un agent.
// ⚠️ Ces routes n'existaient pas avant le 2026-11-13 : le salaire n'était
// saisissable nulle part et les employés sans contrat actif étaient ignorés
// silencieusement par le calcul (cf. docs/payroll_rules.md § « Points d'attention »).
Router::get('/api/payroll/contracts', [PayrollContractController::class, 'index'], 'paie');
Router::get('/api/payroll/contracts/[i:id]', [PayrollContractController::class, 'show'], 'paie');
Router::post('/api/payroll/contracts', [PayrollContractController::class, 'create'], 'paie');
Router::post('/api/payroll/contracts/update/[i:id]', [PayrollContractController::class, 'update'], 'paie');
Router::post('/api/payroll/contracts/toggle-paid/[i:id]', [PayrollContractController::class, 'togglePaid'], 'paie');
Router::post('/api/payroll/contracts/delete/[i:id]', [PayrollContractController::class, 'delete'], 'paie');

// ── Payroll périodes & paramètres ───────────────────────────
Router::get('/api/payroll/periods', [PayrollController::class, 'periods'], 'paie');
Router::post('/api/payroll/periods', [PayrollController::class, 'createPeriod'], 'paie');
Router::post('/api/payroll/periods/update/[i:id]', [PayrollController::class, 'updatePeriod'], 'paie');
Router::post('/api/payroll/periods/delete/[i:id]', [PayrollController::class, 'deletePeriod'], 'paie');
Router::post('/api/payroll/periods/calculate/[i:id]', [PayrollController::class, 'calculate'], 'paie');
Router::post('/api/payroll/periods/validate/[i:id]', [PayrollController::class, 'validatePeriod'], 'paie');
Router::post('/api/payroll/periods/close/[i:id]', [PayrollController::class, 'closePeriod'], 'paie');
Router::get('/api/payroll/parameters', [PayrollController::class, 'parameters'], 'paie');
// Import du récap de paie Excel (cf. `Recap paie.xlsx`).
Router::post('/api/payroll/recap/preview', [PayrollRecapImportController::class, 'preview'], 'paie');
Router::post('/api/payroll/recap/import', [PayrollRecapImportController::class, 'apply'], 'paie');

// Paramètres de calcul (mode standard / excel_rdc, barème IRT, LOG/TRANS…).
Router::get('/api/payroll/settings-params', [PayrollController::class, 'settingsParams'], 'paie');
Router::post('/api/payroll/settings-params', [PayrollController::class, 'updateSettingsParams'], 'paie');
Router::get('/api/payroll/payment-methods', [PayrollController::class, 'paymentMethods'], 'paie');
Router::post('/api/payroll/payment-methods', [PayrollController::class, 'createPaymentMethod'], 'paie');
Router::post('/api/payroll/payment-methods/update/[i:id]', [PayrollController::class, 'updatePaymentMethod'], 'paie');
Router::post('/api/payroll/payment-methods/delete/[i:id]', [PayrollController::class, 'deletePaymentMethod'], 'paie');

// ── Payroll paramètres (avantages, retenues, cotisations, barème)
Router::get('/api/payroll/allowances', [PayrollController::class, 'allowances'], 'paie');
Router::post('/api/payroll/allowances', [PayrollController::class, 'createAllowance'], 'paie');
Router::post('/api/payroll/allowances/update/[i:id]', [PayrollController::class, 'updateAllowance'], 'paie');
Router::post('/api/payroll/allowances/delete/[i:id]', [PayrollController::class, 'deleteAllowance'], 'paie');

Router::get('/api/payroll/deductions', [PayrollController::class, 'deductions'], 'paie');
Router::post('/api/payroll/deductions', [PayrollController::class, 'createDeduction'], 'paie');
Router::post('/api/payroll/deductions/update/[i:id]', [PayrollController::class, 'updateDeduction'], 'paie');
Router::post('/api/payroll/deductions/delete/[i:id]', [PayrollController::class, 'deleteDeduction'], 'paie');

Router::get('/api/payroll/contributions', [PayrollController::class, 'contributions'], 'paie');
Router::post('/api/payroll/contributions', [PayrollController::class, 'createContribution'], 'paie');
Router::post('/api/payroll/contributions/update/[i:id]', [PayrollController::class, 'updateContribution'], 'paie');
Router::post('/api/payroll/contributions/delete/[i:id]', [PayrollController::class, 'deleteContribution'], 'paie');

Router::get('/api/payroll/seniority', [PayrollController::class, 'seniority'], 'paie');
Router::post('/api/payroll/seniority', [PayrollController::class, 'createSeniority'], 'paie');
Router::post('/api/payroll/seniority/update/[i:id]', [PayrollController::class, 'updateSeniority'], 'paie');
Router::post('/api/payroll/seniority/delete/[i:id]', [PayrollController::class, 'deleteSeniority'], 'paie');

// ── Payroll présences / absences / heures supp ─────────────
Router::get('/api/payroll/attendance/period/[i:id]', [PayrollAttendanceController::class, 'forPeriod'], 'paie');
Router::post('/api/payroll/attendance', [PayrollAttendanceController::class, 'save'], 'paie');
Router::post('/api/payroll/attendance/bulk', [PayrollAttendanceController::class, 'bulkSave'], 'paie');
Router::post('/api/payroll/absence', [PayrollAttendanceController::class, 'saveAbsence'], 'paie');
Router::post('/api/payroll/absence/update/[i:id]', [PayrollAttendanceController::class, 'updateAbsence'], 'paie');
Router::post('/api/payroll/absence/delete/[i:id]', [PayrollAttendanceController::class, 'deleteAbsence'], 'paie');
Router::post('/api/payroll/overtime', [PayrollAttendanceController::class, 'saveOvertime'], 'paie');
Router::post('/api/payroll/overtime/update/[i:id]', [PayrollAttendanceController::class, 'updateOvertime'], 'paie');
Router::post('/api/payroll/overtime/delete/[i:id]', [PayrollAttendanceController::class, 'deleteOvertime'], 'paie');

// ── Payroll bulletins ───────────────────────────────────────
Router::get('/api/payroll/payslips/period/[i:id]', [PayrollPayslipController::class, 'forPeriod'], 'paie');
Router::get('/api/payroll/payslips/my', [PayrollPayslipController::class, 'myPayslips'], 'paie');
Router::get('/api/payroll/payslips/[i:id]', [PayrollPayslipController::class, 'show'], 'paie');
Router::post('/api/payroll/payslips/calculate', [PayrollPayslipController::class, 'calculateOne'], 'paie');
Router::post('/api/payroll/payslips/period/calculate/[i:id]', [PayrollPayslipController::class, 'calculatePeriod'], 'paie');
Router::post('/api/payroll/payslips/validate/[i:id]', [PayrollPayslipController::class, 'validate'], 'paie');
Router::get('/api/payroll/payslips/pdf/[i:id]', [PayrollPayslipController::class, 'streamPdf'], 'paie');
Router::post('/api/payroll/payslips/pdf/[i:id]', [PayrollPayslipController::class, 'generatePdf'], 'paie');

// ── Payroll paiements ───────────────────────────────────────
Router::get('/api/payroll/payments/payslip/[i:id]', [PayrollPaymentController::class, 'forPayslip'], 'paie');
Router::post('/api/payroll/payments', [PayrollPaymentController::class, 'create'], 'paie');
Router::post('/api/payroll/payments/update/[i:id]', [PayrollPaymentController::class, 'update'], 'paie');
Router::post('/api/payroll/payments/delete/[i:id]', [PayrollPaymentController::class, 'delete'], 'paie');

// ── Payroll pointage / import ───────────────────────────────
Router::get('/api/payroll/timeclock/period/[i:id]', [PayrollTimeClockController::class, 'forPeriod'], 'paie');
Router::post('/api/payroll/timeclock', [PayrollTimeClockController::class, 'create'], 'paie');
Router::post('/api/payroll/timeclock/update/[i:id]', [PayrollTimeClockController::class, 'update'], 'paie');
Router::post('/api/payroll/timeclock/delete/[i:id]', [PayrollTimeClockController::class, 'delete'], 'paie');
Router::post('/api/payroll/timeclock/import', [PayrollTimeClockController::class, 'import'], 'paie');

// ── Payroll rapports ────────────────────────────────────────
Router::get('/api/payroll/reports/period/[i:id]', [PayrollReportController::class, 'periodSummary'], 'paie');
Router::get('/api/payroll/reports/csv/[i:id]', [PayrollReportController::class, 'periodCsv'], 'paie');
Router::get('/api/payroll/reports/payments/[i:id]', [PayrollReportController::class, 'payments'], 'paie');
Router::get('/api/payroll/reports/contributions/[i:id]', [PayrollReportController::class, 'contributions'], 'paie');
Router::get('/api/payroll/reports/headcount', [PayrollReportController::class, 'headcount'], 'paie');

// Paramètres du système
// Routes pour les clients
Router::get('/api/clients', [ClientController::class, 'index']);
Router::get('/api/client/lookup', [ClientController::class, 'lookup']);
// Lecture d'un client par ID — utilisée par la reprise d'un brouillon de
// panier (le brouillon ne conserve que l'ID, pas les données sensibles).
Router::get('/api/client/[i:id]', [ClientController::class, 'show']);
Router::get('/api/client/search', [ClientController::class, 'searchByNumero']);
Router::post('/api/client', [ClientController::class, 'create']);
Router::put('/api/client/[i:id]', [ClientController::class, 'update']);
Router::post('/api/client/update/[i:id]', [ClientController::class, 'update']);

Router::get('/api/settings', [SettingsController::class, 'index'], 'parametres');
Router::post('/api/settings', [SettingsController::class, 'update'], 'parametres');
Router::post('/api/settings/store', [SettingsController::class, 'updateStore'], 'parametres');
Router::post('/api/settings/tax', [SettingsController::class, 'updateTax'], 'parametres');
Router::post('/api/settings/theme', [SettingsController::class, 'saveTheme'], 'parametres');
Router::get('/api/settings/theme', [SettingsController::class, 'getTheme'], 'parametres');
// Format d'impression (papier)
Router::post('/api/settings/paper-type', [SettingsController::class, 'updatePaperType'], 'parametres');
Router::get('/api/settings/paper-type', [SettingsController::class, 'getPaperType'], 'parametres');
// Padding d'affichage des articles sur le ticket (57mm / 80mm)
Router::post('/api/settings/receipt-padding', [SettingsController::class, 'updateReceiptPadding'], 'parametres');
Router::get('/api/settings/receipt-padding', [SettingsController::class, 'getReceiptPadding'], 'parametres');
// Logo du magasin (facture imprimée)
Router::post('/api/settings/logo', [SettingsController::class, 'uploadLogo'], 'parametres');
Router::post('/api/settings/logo/delete', [SettingsController::class, 'deleteLogo'], 'parametres');

// Routes pour la gestion des taxes
Router::get('/api/taxes', [TaxController::class, 'index'], 'taxes');
Router::post('/api/taxes', [TaxController::class, 'create'], 'taxes');
Router::post('/api/taxes/update', [TaxController::class, 'update'], 'taxes');
Router::post('/api/taxes/delete', [TaxController::class, 'delete'], 'taxes');

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

    // ⚠️ Protection boutique désactivée : un user dont le shop a été désactivé
    // ne doit pas pouvoir consulter ni payer des factures de service.
    if (($_SESSION['role'] ?? '') !== 'super_admin') {
        $shopId = $_SESSION['shop_id'] ?? null;
        if ($shopId) {
            $shop = (new \App\Models\Shop())->findById($shopId);
            if (!$shop || empty($shop['actif'])) {
                // Audit : tentative d'accès au proxy paiement
                try {
                    (new \App\Models\AuditLog())->log(
                        $_SESSION['user_id'] ?? null,
                        $shopId,
                        'access_blocked',
                        'bill_payment',
                        null,
                        ['reason' => 'shop_desactive', 'ip' => $_SERVER['REMOTE_ADDR'] ?? null]
                    );
                } catch (\Exception $e) { /* noop */
                }

                // Destruction de session pour expulser l'utilisateur
                $_SESSION = [];
                if (session_status() === PHP_SESSION_ACTIVE) {
                    session_destroy();
                }
                http_response_code(403);
                echo json_encode([
                    'success' => false,
                    'error'   => 'shop_disabled',
                    'message' => 'Votre boutique a été désactivée. Accès aux paiements de factures bloqué.',
                ]);
                exit;
            }
        }
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
            // Si la DGI ne renvoie pas déjà store_homologation (facture ancienne
            // enregistrée avant l'ajout de ce champ), on la complète depuis la BDD.
            if (isset($data['data']) && is_array($data['data']) && !isset($data['data']['store_homologation'])) {
                $data['data']['store_homologation'] = $homologation;
            }
            echo json_encode($data);
        } elseif (isset($data['success']) && $data['success'] === false) {
            // Echec explicite de la DGI : on le propage tel quel
            echo json_encode($data);
        } elseif (isset($data['data'])) {
            $facture = $data['data'];
            if (is_array($facture) && !isset($facture['store_homologation'])) {
                $facture['store_homologation'] = $homologation;
            }
            echo json_encode(['success' => true, 'data' => $facture]);
        } else {
            if (is_array($data) && !isset($data['store_homologation'])) {
                $data['store_homologation'] = $homologation;
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

// ── Accès fonctionnalités (vérification statut + reset cache) ──
Router::get('/api/recharge-access', function () {
    if (!requireAuthenticatedSession()) {
        return;
    }
    header('Content-Type: application/json');
    $accessService = new \App\Services\RechargeAccessService();
    echo json_encode([
        'success'          => true,
        'allowed'          => $accessService->canAccess(),
        'payroll_allowed'  => $accessService->canAccessPayroll(),
        // Sprint 7 — 2026-10-19 : ajout du flag `hotel_allowed` pour le
        // module hôtellerie. Le front utilise ce flag pour cacher/afficher
        // les liens hôtel dans la sidebar.
        // BUG FIX 2026-10-20 (round 2) : comportement STRICT — si l'API
        // n'expose pas le flag `hotel` (ou l'API est injoignable),
        // `hotel_allowed` vaut false (aligné sur canAccess() /allowed et
        // payroll_allowed). Le menu Hôtel est alors masqué (header.php).
        'hotel_allowed'    => $accessService->canAccessHotel(),
        // Exposé pour debug : permet de vérifier d'où vient le refus
        // sans ouvrir les logs serveur (commande « Tester l'accès »).
        'debug_flags'      => $accessService->getFlagsForDebug(),
    ]);
});

Router::get('/api/payroll-access', function () {
    if (!requireAuthenticatedSession()) {
        return;
    }
    header('Content-Type: application/json');
    $accessService = new \App\Services\RechargeAccessService();
    echo json_encode([
        'success' => true,
        'allowed' => $accessService->canAccessPayroll(),
    ]);
});

Router::post('/api/feature-access/reset', function () {
    if (!requireAuthenticatedSession()) {
        return;
    }
    header('Content-Type: application/json');
    \App\Services\RechargeAccessService::resetCache();
    echo json_encode([
        'success' => true,
        'message' => 'Cache réinitialisé',
    ]);
});
// ── Rapports Fiscaux (A) ────────────────────────────────
//
// ⚠️ Routes retirées :
// - `/api/reports/z-report` (Z LOCAL) : la clôture de référence est
//   désormais celle de la DGI (`POST /api/reports/dgi/z`). Conserver les deux
//   aurait permis de clôturer deux fois la même période.
// - `/api/reports/x-report/daily` et `/api/reports/x-report/periodic`
//   (X LOCAL) : l'interface ne les expose plus, le X DGI
//   (`GET /api/reports/dgi/x`) fait désormais foi.
Router::get('/api/reports/a-report', [ReportController::class, 'generateAReport'], 'rapports');
Router::get('/api/reports/a-history', [ReportController::class, 'getAReportHistory'], 'rapports');
Router::get('/api/reports/history', [ReportController::class, 'getReportHistory'], 'rapports');
Router::get('/api/reports/print/[i:id]', [ReportController::class, 'printReport'], 'rapports');

// Rapports DGI aggregés fournis par OSAT.
// - X : periode personnalisée, en lecture seule.
// - Z : consultation (lecture seule) de la période à clôturer.
// - POST Z : clôture DGI (persistance + chaînage de la période suivante).
Router::get('/api/reports/dgi/x', [ReportController::class, 'generateDgiXReport'], 'rapports');
Router::get('/api/reports/dgi/z', [ReportController::class, 'generateDgiZReport'], 'rapports');
Router::post('/api/reports/dgi/z', [ReportController::class, 'generateDgiZClosure'], 'rapports');
Router::get('/api/reports/dgi/z-history', [ReportController::class, 'getDgiZHistory'], 'rapports');

// ── Tableau de bord : indicateurs DGI/OSAT (jour, semaine, mois) ──
//
// Remplace les agrégats SQL locaux par les montants officiels DGI.
// ⚠️ 3 périodes = 3 appels provider (~1,7 s chacun) : la page ne doit pas
// attendre, le frontend injecte les valeurs après le rendu.
Router::get('/api/dashboard/dgi-stats', [DashboardController::class, 'dgiStats']);

// ── Module Pressing — suivi des commandes de pressing ───
// Référence : docs/PLAN_PRESSING.md
//
// Portage de l'appli autonome `pressing/` (9 actions `?api=`) vers des
// routes REST. Accès contrôlé par le flag API `pressing` (comme
// /recharges et /hotel) :
// voir Controller::requireShopPressingAccess().
//
Router::get('/api/pressing/stats', [PressingController::class, 'stats'], 'pressing');
Router::get('/api/pressing/commandes', [PressingController::class, 'index'], 'pressing');
Router::get('/api/pressing/commandes/[i:id]', [PressingController::class, 'show'], 'pressing');
Router::post('/api/pressing/commandes', [PressingController::class, 'store'], 'pressing');
Router::post('/api/pressing/commandes/[i:id]/advance-all', [PressingController::class, 'advanceAll'], 'pressing');
Router::post('/api/pressing/commandes/[i:id]/pay', [PressingController::class, 'pay'], 'pressing');
Router::post('/api/pressing/commandes/[i:id]/delete', [PressingController::class, 'destroy'], 'pressing');
Router::post('/api/pressing/articles/[i:id]/advance', [PressingController::class, 'advance'], 'pressing');
Router::post('/api/pressing/articles/[i:id]/recede', [PressingController::class, 'recede'], 'pressing');
Router::post('/api/pressing/facture/lookup', [PressingController::class, 'lookupFacture'], 'pressing');
Router::get('/api/pressing/ventes-sans-commande', [PressingController::class, 'ventesSansCommande'], 'pressing');
