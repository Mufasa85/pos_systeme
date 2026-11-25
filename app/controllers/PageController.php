<?php

namespace App\Controllers;

//use App\Models\Category;
use App\Models\HotelReservation;
use App\Models\Notification;
use App\Models\Product;
use App\Models\ProductBatch;
use App\Models\Sale;
use App\Models\ServiceType;
use App\Models\Settings;
use App\Models\Shop;
use App\Models\User;

class PageController extends Controller
{
    public function __construct()
    {
        // Pas de redirection automatique dans le constructeur
        // Chaque méthode vérifie la session si nécessaire
    }

    private function render($view, $data = [])
    {
        // Vérifier la session AVANT d'afficher la page
        if (!isset($_SESSION['user_id'])) {
            // Redirection vers login
            $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
            $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
            header('Location: ' . $protocol . '://' . $host . '/');
            exit;
        }

        // Vérifier que la boutique de l'utilisateur est toujours active
        // (si non, requireActiveShop() détruit la session et affiche la 403)
        $this->requireActiveShop();

        $page = $view;
        // Charger le nom du magasin et le type de service pour toutes les pages
        $settingsModel = new Settings();
        $shopModel = new Shop();
        $serviceTypeModel = new ServiceType();
        $shopId = $this->getShopId();

        // Get shop name from shops table instead of settings
        $storeName = 'Mon Magasin'; // default
        $serviceType = 'Caisse'; // default
        // Statut d'homologation DGI de la boutique : masque le lien "Taxes"
        // dans la navbar quand le magasin n'est pas homologue. Par defaut
        // (super_admin sans boutique, info indisponible) on affiche le lien.
        $storeHomologation = true;
        $shop = null;
        if ($shopId) {
            $shop = $shopModel->findById($shopId);
            if ($shop) {
                $storeName = $shop['nom'] ?? 'Mon Magasin';
                $storeHomologation = !empty($shop['homologation']);
                if (!empty($shop['service_type_id'])) {
                    $serviceTypeData = $serviceTypeModel->findById($shop['service_type_id']);
                    if ($serviceTypeData) {
                        $serviceType = $serviceTypeData['name'];
                    }
                }
            }
        }
        $data['storeHomologation'] = $storeHomologation;
        // Accès à la fonctionnalité « Extras » (imputation hôtel depuis la caisse).
        // Configuré par boutique via `shops.acces_extras` (coché par le
        // super_admin dans /shops) — indépendant du feature flag API « hotel ».
        // Calculé ici car la boutique est déjà chargée : évite une requête
        // SQL supplémentaire dans le header et dans la vue caisse.
        $data['shopHasExtrasAccess'] = $shopId
            ? (bool)($shop['acces_extras'] ?? false)
            : false;
        // Droits d'accès aux modules du POS — par utilisateur.
        // (`utilisateurs.acces_*`, migrations 2026_11_14 / 2026_11_15).
        //
        // Calculés ici pour que la sidebar masque le lien du module quand le
        // droit est refusé : l'utilisateur ne doit jamais cliquer sur un lien
        // visible qui ne peut aboutir qu'à une redirection (règle du projet).
        //
        // Source de vérité : User::canAccessModule() — identique à la garde
        // backend (PageController::requireModulePageAccess()), donc le lien
        // visible et l'accès réel ne peuvent pas diverger.
        $moduleAccess = [];
        foreach (array_keys(\App\Models\User::MODULES_ACCES) as $module) {
            $moduleAccess[$module] = $this->isModuleAccessAllowed($module);
        }
        $data['canAccessCaisse'] = $moduleAccess['caisse'];
        $data['moduleAccess']     = $moduleAccess;
        // Politique d'accès aux pages (super_admin) — par UTILISATEUR.
        //
        // La politique est RESTRICTIVE : elle retire un accès normalement
        // accordé, elle n'en accorde jamais un.
        //
        // Source de vérité : UserPageAccessService — identique à la garde
        // backend (Router::policyAllows()), donc le lien visible et l'accès
        // réel ne peuvent pas diverger.
        $pagePolicyService = new \App\Services\UserPageAccessService();
        $policyUserId = (int)($_SESSION['user_id'] ?? 0);

        if ($policyUserId > 0) {
            // Utilisateur authentifié : la carte est calculée depuis ses
            // blocages individuels. Le super_admin n'est jamais bloqué et
            // l'utilisateur inconnu est fail-closed : le service s'en charge.
            $data['pageVisibility'] = $pagePolicyService->getVisibilityMap($policyUserId);
        } else {
            // Pas de session : l'authentification reste prioritaire.
            $data['pageVisibility'] = array_fill_keys(
                array_keys($pagePolicyService::getCatalog()),
                true
            );
        }
        // Company info (super_admin) pour l'affichage du nom entreprise dans le layout
        if ($this->isSuperAdmin()) {
            try {
                $companyInfoModel = new \App\Models\CompanyInfo();
                $companyInfo = $companyInfoModel->get();
                $data['companyInfo'] = $companyInfo;
                $data['companyName'] = $companyInfo['name'] ?? 'Mon Entreprise';
            } catch (\Exception $e) {
                $data['companyInfo'] = ['name' => 'Mon Entreprise'];
                $data['companyName'] = 'Mon Entreprise';
            }
        } else {
            // éviter les undefined variables dans le layout
            $data['companyInfo'] = ['name' => null];
            $data['companyName'] = null;
        }

        $data['storeName'] = $storeName;
        // Pour le super_admin, forcer le type de service affiché à 'Caisse' (brut)
        $data['serviceType'] = $this->isSuperAdmin() ? 'Caisse' : $serviceType;

        $data['currentRole'] = $_SESSION['role'] ?? '';
        $data['currentShopId'] = $shopId;

        // Current user profile image
        $data['currentUserProfileImage'] = null;
        if (isset($_SESSION['user_id'])) {
            $userModel = new User();
            $currentUser = $userModel->exist($_SESSION['user_id']);
            if ($currentUser && !empty($currentUser['profile_image'])) {
                $data['currentUserProfileImage'] = $currentUser['profile_image'];
            }
        }

        // Notifications non lues

        try {
            $notifModel = new Notification();
            $data['unreadNotifications'] = $notifModel->getUnreadCount($_SESSION['user_id']);
        } catch (\Exception $e) {
            $data['unreadNotifications'] = 0;
        }

        // Pré-charger le cache d'accès aux modules payants pour la sidebar.
        // Le warmup charge TOUS les flags (snelregideso, paie, hotel,
        // pressing) en une seule requête API, donc on l'appelle une seule
        // fois ici.
        try {
            $rechargeAccessService = new \App\Services\RechargeAccessService();
            $rechargeAccessService->warmupCache();
        } catch (\Exception $e) {
            // Ne pas bloquer l'affichage si le warmup échoue
            error_log('RechargeAccess warmup error: ' . $e->getMessage());
        }

        extract($data);
        require_once dirname(__DIR__) . DIRECTORY_SEPARATOR . 'views/layout/header.php';
        require_once dirname(__DIR__) . DIRECTORY_SEPARATOR . 'views/' . $view . '.php';
        require_once  dirname(__DIR__) . DIRECTORY_SEPARATOR . 'views/layout/footer.php';
    }

    public function dashboard()
    {
        $saleModel = new Sale();
        $productModel = new Product();
        $shopId = $this->isSuperAdmin() ? null : $this->getShopId();
        $ventes = $saleModel->getAllSales($shopId);

        // ⚠️ Le tableau de bord n'a besoin que d'un NOMBRE de produits et de
        // la liste des stocks faibles. Il NE charge plus tout le catalogue :
        // `getAll()` + `count()` chargeait 6 000 lignes pour afficher un
        // simple compteur (400 ms mesurés). `countFiltered()` fait le même
        // travail en 2,6 ms, et `getLowStock()` remplace le `array_filter()`
        // qui parcourait le catalogue entier en PHP.
        $produitsCompte = $productModel->countFiltered($shopId);
        $stockFaible = $productModel->getLowStock($shopId);

        // ⚠️ Aucune borne de période locale n'est calculée ici : les
        // indicateurs de ventes sont fournis par la DGI (cf.
        // `GET /api/dashboard/dgi-stats`).

        // ⚠️ Les indicateurs « Ventes du jour / Cette semaine / Ce mois »
        // ne sont PLUS calculés ici : ils sont fournis par la DGI (API
        // OSAT) et injectés par `public/assets/js/dashboard.js` à partir de
        // `GET /api/dashboard/dgi-stats`.
        //
        // Seuls le graphique (7 derniers jours) et les alertes restent en
        // local : ils ne sont pas des indicateurs fiscaux officiels.

        // Données pour le graphique des 7 derniers jours
        $chart_data = [];
        for ($i = 6; $i >= 0; $i--) {
            $day = date('Y-m-d', strtotime("-$i days"));
            $chart_data[$day] = ['total' => 0, 'count' => 0];
        }

        foreach ($ventes as $v) {
            $v_date_str = date('Y-m-d', strtotime($v['date']));

            // Graphique - 7 derniers jours
            if (isset($chart_data[$v_date_str])) {
                $chart_data[$v_date_str]['total'] += $v['total'];
                $chart_data[$v_date_str]['count']++;
            }
        }

        // Préparer les données pour Chart.js
        $chart_labels = [];
        $chart_values = [];
        foreach ($chart_data as $date => $data) {
            $chart_labels[] = date('d/m', strtotime($date));
            $chart_values[] = round($data['total'], 2);
        }

        // Multi-boutique stats pour super_admin
        //
        // ⚠️ Aucun MONTANT de vente n'est calculé ici : les chiffres de ventes
        // ne sont affichés que pour la boutique connectée (API DGI). On ne
        // garde donc que l'inventaire (nombre de produits) par boutique.
        $shopStats = [];
        if ($this->isSuperAdmin()) {
            $shopModel = new \App\Models\Shop();
            $allShops = $shopModel->getAll();
            $db = \App\Core\Database::getInstance();
            foreach ($allShops as $shop) {
                $sid = $shop['id'];
                $prodCount = $db->fetch(
                    'SELECT COUNT(*) as c FROM produits WHERE shop_id = ?',
                    [$sid]
                );
                $shopStats[] = [
                    'id' => $sid,
                    'nom' => $shop['nom'],
                    'code' => $shop['code'],
                    'produits' => $prodCount['c'] ?? 0,
                ];
            }
        }

        $batchModel = new ProductBatch();
        // ⚠️ Les alertes sont BORNÉES à 20 lignes. Sans cette borne, le
        // dashboard chargeait TOUS les lots en fin de série : mesuré à
        // 50 000 produits / 85 000 lots, la fenêtre de 90 jours renvoyait
        // 19 382 lignes (502 ms SQL + rendu, 19 382 nœuds DOM).
        // Le total est affiché à côté via les compteurs.
        $expirationAlerts = $batchModel->getExpiringSoon(7, $shopId, 20);
        $expiredAlerts = $batchModel->getExpired($shopId, 20);
        $expirationAlertsTotal = $batchModel->countExpiringSoon(7, $shopId);
        $expiredAlertsTotal = $batchModel->countExpired($shopId);

        $this->render('dashboard', [
            'ventes' => $ventes,
            'produits_compte' => $produitsCompte,
            // ⚠️ Pas de `ventes_jour` / `ventes_semaine` / `ventes_mois` :
            // ces indicateurs proviennent de la DGI et sont injectés par le
            // frontend (cf. `public/assets/js/dashboard.js`).
            'chart_labels' => json_encode($chart_labels),
            'chart_values' => json_encode($chart_values),
            'stock_faible' => $stockFaible,
            'expiration_alerts' => $expirationAlerts,
            'expired_alerts' => $expiredAlerts,
            'expiration_alerts_total' => $expirationAlertsTotal,
            'expired_alerts_total' => $expiredAlertsTotal,
            'shopStats' => $shopStats,
            'isSuperAdmin' => $this->isSuperAdmin(),
        ]);
    }

    public function caisse()
    {
        // ── Contrôle d'accès au point de vente ───────────────────────────
        // Le droit est porté par `utilisateurs.acces_caisse` (migration
        // 2026_11_14_user_caisse_access.sql), réglé par le manager depuis
        // /hotel/roles. La gouvernante (et, si le manager le souhaite, la
        // réceptionniste) peut ainsi être retirée du poste de vente.
        //
        // On NE renvoie pas une 403 : la caisse n'est pas une page « interdite »,
        // c'est un poste de travail. On redirige vers la page hôtel du rôle
        // effectif, pour que la personne atterrit sur SON outil (mobile pour
        // la gouvernante) au lieu d'une page d'erreur.
        if (!$this->requireCaissePageAccess()) {
            return;
        }

        $categoryModel = new \App\Models\Category();
        $shopId = $this->isSuperAdmin() ? null : $this->getShopId();
        $categories = $categoryModel->all($shopId);

        // Charger les types de clients pour le select
        $typeClientModel = new \App\Models\TypeClient();
        $clientTypes = $typeClientModel->getAll();

        $this->render('caisse', [
            'categories' => $categories,
            'clientTypes' => $clientTypes,
        ]);
    }

    /**
     * Vérifie que l'utilisateur a le droit d'ouvrir la page /caisse.
     *
     * Retourne true si l'accès est accordé. Sinon, redirige vers la page
     * hôtel correspondant au rôle effectif et retourne false (le caller
     * doit alors stopper son traitement).
     *
     * La source de vérité est `User::canAccessCaisse()` — identique à celle
     * utilisée par la garde API (`Controller::requireCaisseAccess()`) : le
     * lien visible et l'accès réel ne peuvent pas diverger.
     */
    private function requireCaissePageAccess(): bool
    {
        if ($this->isCaisseAccessAllowed()) {
            return true;
        }

        // Page de repli : la page hôtel du rôle effectif si elle existe,
        // sinon le tableau de bord.
        $hr = $this->resolveHotelRole();
        $target = '/dashboard';
        if ($hr === 'gouvernante') {
            $target = '/hotel/etages';
        } elseif ($hr === 'receptionniste') {
            $target = '/hotel/reception';
        } elseif (in_array($hr, ['manager', 'admin', 'super_admin'], true)) {
            $target = '/hotel/tableau-bord';
        }

        $_SESSION['flash_error'] = 'La caisse n\'est pas accessible avec votre rôle.';

        $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
        $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
        header('Location: ' . $protocol . '://' . $host . $target);
        exit;
    }

    /**
     * Vérifie que l'utilisateur a le droit d'ouvrir une page de module POS.
     *
     * Même principe que `requireCaissePageAccess()` mais générique : le
     * droit est porté par la colonne `utilisateurs.acces_<module>`.
     *
     * @param string $module Clé de \App\Models\User::MODULES_ACCES.
     */
    private function requireModulePageAccess(string $module): bool
    {
        if ($this->isModuleAccessAllowed($module)) {
            return true;
        }

        $_SESSION['flash_error'] = 'Ce module n\'est pas accessible avec votre rôle.';

        $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
        $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
        header('Location: ' . $protocol . '://' . $host . '/dashboard');
        exit;
    }

    public function recharges()
    {
        // Vérifier l'accès via l'API externe configurable
        $accessService = new \App\Services\RechargeAccessService();
        if (!$accessService->canAccess()) {
            http_response_code(403);
            require_once dirname(__DIR__) . DIRECTORY_SEPARATOR . 'views/403.php';
            exit;
        }

        $categoryModel = new \App\Models\Category();
        $shopId = $this->isSuperAdmin() ? null : $this->getShopId();
        $categories = $categoryModel->all($shopId);
        $typeClientModel = new \App\Models\TypeClient();
        $clientTypes = $typeClientModel->getAll();

        $this->render('recharges', [
            'categories' => $categories,
            'clientTypes' => $clientTypes,
        ]);
    }

    public function produits()
    {
        // Droit d'accès au module Produits (`utilisateurs.acces_produits`).
        // Un utilisateur retiré de la gestion du catalogue par le manager
        // n'y a plus accès.
        $this->requireModulePageAccess('produits');

        $productModel = new Product();
        $categoryModel = new \App\Models\Category();
        $taxModel = new \App\Models\Tax();

        $shopId = $this->isSuperAdmin() ? null : $this->getShopId();
        // Super admin peut voir les produits des boutiques désactivées via ?include_inactive=1
        $includeInactive = $this->isSuperAdmin() && !empty($_GET['include_inactive']);

        // ── PAGINATION SERVEUR ──────────────────────────────────────────
        // ⚠️ La page NE charge plus tout le catalogue : à 50 000 produits,
        // `getAll()` renvoyait 3,2 Mo de JSON et 116 Mo de HTML, et le
        // filtre parcourait 50 000 <tr> à chaque frappe (150 000
        // querySelector). Mesuré : la requête catalogue seule prenait
        // 5 883 – 6 676 ms.
        $limit  = (int)($_GET['limit'] ?? 50);
        $offset = (int)($_GET['offset'] ?? 0);
        $search = $this->sanitaze($_GET['search'] ?? '');
        $catFilter = $_GET['category'] ?? '';
        $shopFilter = !empty($_GET['shop_id']) ? (int)$_GET['shop_id'] : null;

        // Pour le super_admin, le filtre « boutique » est prioritaire sur
        // la boutique de session (comme dans `getAllFiltered()`).
        $effectiveShopId = $shopFilter ?: $shopId;

        $page = $productModel->getPage(
            [
                'search'   => $search,
                'category' => $catFilter,
                'shop_id'  => $shopFilter,
            ],
            $limit,
            $offset,
            $effectiveShopId,
            $includeInactive
        );

        $produits = $page['rows'];
        $categories = $categoryModel->all($shopId);
        $taxes = $taxModel->getAll();

        // Boutiques pour le filtre super_admin
        $shops = [];
        if ($this->isSuperAdmin()) {
            $shopModel = new \App\Models\Shop();
            $shops = $shopModel->getAll();
        }

        $this->render('produits', [
            'produits' => $produits,
            'total'    => $page['total'],
            'limit'    => $page['limit'],
            'offset'   => $page['offset'],
            'search'   => $search,
            'category' => $catFilter,
            'shopFilter' => $shopFilter,
            'categories' => $categories,
            'taxes' => $taxes,
            'shops' => $shops,
            'includeInactive' => $includeInactive,
        ]);
    }

    public function utilisateurs()
    {
        if (!$this->isAdmin() && !$this->isSuperAdmin()) {
            $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
            $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
            header('Location: ' . $protocol . '://' . $host . '/dashboard');
            exit;
        }
        $userModel = new User();
        $callerRole = $_SESSION['role'] ?? 'vendeur';
        $shopId = $this->isSuperAdmin() ? null : $this->getShopId();
        $utilisateurs = $userModel->all($shopId, $callerRole);

        // Charger les boutiques pour le super_admin
        $shops = [];
        if ($this->isSuperAdmin()) {
            $shopModel = new \App\Models\Shop();
            $shops = $shopModel->getAll();
        }

        $this->render('utilisateurs', ['utilisateurs' => $utilisateurs, 'shops' => $shops]);
    }

    public function otpCodes()
    {
        if (!$this->isSuperAdmin()) {
            $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
            $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
            header('Location: ' . $protocol . '://' . $host . '/dashboard');
            exit;
        }
        $this->render('otp-codes');
    }

    public function monProfil()
    {
        if (!isset($_SESSION['user_id'])) {
            $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
            $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
            header('Location: ' . $protocol . '://' . $host . '/');
            exit;
        }
        $userModel = new User();
        $currentUser = $userModel->exist($_SESSION['user_id']);
        $this->render('mon-profil', ['currentUser' => $currentUser]);
    }

    // ============================================================================
    // Module Hôtel (PMS) — méthodes ajoutées le 2026-10-15
    // Référence : docs/PLAN_HOTEL_V1.md
    // ============================================================================

    /**
     * Routeur /hotel : redirige selon le rôle hôtel effectif de l'utilisateur.
     * - super_admin            → /hotel/tableau-bord
     * - admin (manager auto)   → /hotel/tableau-bord
     * - manager explicite      → /hotel/tableau-bord
     * - receptionniste         → /hotel/reception
     * - gouvernante            → /hotel/etages
     * - sans rôle hôtel        → /hotel/reception par défaut
     */
    public function hotel()
    {
        $this->requireHotelApiAccess();
        $hr = $this->resolveHotelRole();
        $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
        $host = $_SERVER['HTTP_HOST'] ?? 'localhost';

        $target = $this->resolveHotelRedirectTarget($hr);

        if ($target !== null) {
            header('Location: ' . $protocol . '://' . $host . $target);
            exit;
        }

        // Aucune page Hôtel autorisée pour cet utilisateur : 403 explicite
        // plutôt qu'une redirection vers une page bloquée.
        http_response_code(403);
        require_once dirname(__DIR__) . DIRECTORY_SEPARATOR . 'views' . DIRECTORY_SEPARATOR . '403.php';
        exit;
    }

    /**
     * Choisit la page Hôtel vers laquelle rediriger, en tenant compte de la
     * politique du super_admin.
     *
     * ⚠️ On ne doit JAMAIS rediriger vers une page bloquée : l'utilisateur
     * atterrirait sur un 403.
     *
     * @param string $hotelRole Rôle hôtel effectif.
     * @return string|null L'URL de redirection, ou null si aucune page
     *                     Hôtel n'est autorisée.
     */
    private function resolveHotelRedirectTarget(string $hotelRole): ?string
    {
        $candidates = [
            'super_admin'    => ['hotel_tableau_bord', 'hotel_reception', 'hotel_etages'],
            'admin'          => ['hotel_tableau_bord', 'hotel_reception', 'hotel_etages'],
            'manager'        => ['hotel_tableau_bord', 'hotel_reception', 'hotel_etages'],
            'gouvernante'    => ['hotel_etages', 'hotel_reception', 'hotel_tableau_bord'],
            'receptionniste' => ['hotel_reception', 'hotel_tableau_bord', 'hotel_etages'],
        ];

        $urls = [
            'hotel_tableau_bord' => '/hotel/tableau-bord',
            'hotel_reception'    => '/hotel/reception',
            'hotel_etages'       => '/hotel/etages',
        ];

        $userId = (int)($_SESSION['user_id'] ?? 0);
        $policyService = new \App\Services\UserPageAccessService();

        foreach ($candidates[$hotelRole] ?? $candidates['receptionniste'] as $pageKey) {
            if ($userId <= 0 || $policyService->isPageAllowedForUser($pageKey, $userId)) {
                return $urls[$pageKey];
            }
        }

        return null;
    }

    /**
     * Vue principale du réceptionniste : grille des chambres + check-in/out.
     * Sprint 2 remplira cette page. Sprint 1 = squelette.
     */
    public function hotelReception()
    {
        $this->requireHotelApiAccess();
        $this->requireHotelAccess(['super_admin', 'admin', 'manager', 'receptionniste']);
        $this->render('hotel-reception', []);
    }

    /**
     * Vue mobile de la gouvernante : liste des chambres à nettoyer.
     * Sprint 3 remplira cette page. Sprint 1 = squelette.
     */
    public function hotelEtages()
    {
        $this->requireHotelApiAccess();
        $this->requireHotelAccess(['super_admin', 'admin', 'manager', 'gouvernante', 'receptionniste']);
        $this->render('hotel-etages', []);
    }

    /**
     * Tableau de bord du manager : KPI du jour, séjours, housekeeping.
     * Sprint 4 remplira cette page. Sprint 1 = squelette.
     */
    public function hotelDashboard()
    {
        $this->requireHotelApiAccess();
        $this->requireHotelAccess(['super_admin', 'admin', 'manager']);
        $this->render('hotel-tableau-bord', []);
    }

    /**
     * Calendrier manager — vue semaine des séjours.
     * Réservé aux managers (et super_admin). Réceptionniste et gouvernante
     * n'y ont pas accès — ils utilisent leurs pages dédiées.
     *
     * Query : ?from=YYYY-MM-DD (jour de départ de la semaine affichée).
     *         Par défaut = aujourd'hui.
     * Le rendu serveur hydrate la première vue (chambres + séjours qui
     * chevauchent la semaine) ; le JS rafraîchi ensuite toutes les 60 s
     * en fetchant /api/hotel/calendrier?from=...
     */
    public function hotelCalendrier()
    {
        $this->requireHotelApiAccess();
        $this->requireHotelAccess(['super_admin', 'manager']);

        // Plage affichée : [from, from+6] (7 jours). Default = aujourd'hui.
        $from = isset($_GET['from']) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $_GET['from'])
            ? $_GET['from']
            : date('Y-m-d');
        // Borne supérieure inclusive : 7 jours = indices 0..6
        $to   = date('Y-m-d', strtotime($from . ' +6 days'));

        $resModel   = new HotelReservation();
        $chambres   = $resModel->allRooms();                          // 10 simples + 10 doubles triées
        $sejours    = $resModel->sejoursSurPlage($from, $to);         // séjours qui chevauchent la semaine

        $this->render('hotel-calendrier', [
            'calFrom'    => $from,
            'calTo'      => $to,
            'calChambres' => $chambres,
            'calSejours' => $sejours,
        ]);
    }

    /**
     * Gestion des chambres — état, prix, type, notes.
     * Visible par tous les rôles hôtel (manager, réceptionniste, gouvernante).
     * Les permissions fines (qui peut modifier l'état) seront ajoutées au sprint contenu.
     */
    public function hotelChambres()
    {
        $this->requireHotelApiAccess();
        $this->requireHotelAccess(['super_admin', 'manager', 'receptionniste', 'gouvernante']);
        $this->render('hotel-chambres', []);
    }

    /**
     * Page Réservations hôtel — manager + réceptionniste (et super_admin).
     * La page ne charge que la coquille HTML — le contenu vient de
     * fetch() vers /api/hotel/reservations (HotelReservationController).
     */
    public function hotelReservations()
    {
        $this->requireHotelApiAccess();
        $appRole  = $_SESSION['role'] ?? '';
        $hotelRole = $_SESSION['hotel_role'] ?? null;
        $allowed =
            $appRole === 'super_admin'
            || $appRole === 'admin'  // = manager auto
            || in_array($hotelRole, ['manager', 'receptionniste'], true);
        if (!$allowed) {
            $_SESSION['flash_error'] = 'Accès réservé au manager et à la réceptionniste.';
            $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
            $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
            header('Location: ' . $protocol . '://' . $host . '/dashboard');
            exit;
        }
        $this->render('hotel-reservations', []);
    }

    /**
     * Page Guests hôtel — manager + réceptionniste (et super_admin).
     * Lecture pour tous les rôles hôtel, écriture pour manager seulement
     * (géré par le contrôleur API).
     */
    public function hotelGuests()
    {
        $this->requireHotelApiAccess();
        $appRole  = $_SESSION['role'] ?? '';
        $hotelRole = $_SESSION['hotel_role'] ?? null;
        $allowed =
            $appRole === 'super_admin'
            || $appRole === 'admin'  // = manager auto
            || in_array($hotelRole, ['manager', 'receptionniste', 'gouvernante'], true);
        if (!$allowed) {
            $_SESSION['flash_error'] = 'Accès réservé au personnel hôtel.';
            $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
            $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
            header('Location: ' . $protocol . '://' . $host . '/dashboard');
            exit;
        }
        $this->render('hotel-guests', []);
    }

    /**
     * Page "Gestion des rôles hôtel" : le manager assigne chaque
     * vendeur de SA boutique à un rôle métier hôtel (manager /
     * réceptionniste / gouvernante) ou aucun. Super_admin a accès
     * à toutes les boutiques via le sélecteur en haut.
     */
    public function hotelRoles()
    {
        $this->requireHotelApiAccess();
        $this->requireHotelAccess(['super_admin', 'admin', 'manager']);
        $userModel = new User();

        // Boutique ciblée : super_admin peut switcher via ?shop_id=,
        // admin/manager est figé sur sa boutique.
        // Même résolution que les endpoints d'écriture (resolveTargetShopId)
        // pour que la boutique AFFICHÉE soit toujours celle MODIFIÉE.
        $targetShopId = $this->resolveTargetShopId(
            isset($_GET['shop_id']) && $_GET['shop_id'] !== '' ? $_GET['shop_id'] : null
        );

        $hotelUsers = $targetShopId ? $userModel->allForShopWithHotelRole($targetShopId) : [];

        // ── Droits d'accès aux modules du POS ───────────────────────────
        // La colonne « Accès modules POS » (utilisateurs.acces_*) est
        // RÉSERVÉE aux administrateurs POS : `admin` (auto-manager de sa
        // boutique) et `super_admin`.
        //
        // Un manager (vendeur avec hotel_role='manager') continue de gérer
        // les RÔLES hôtel de son équipe, mais ne peut ni VOIR ni MODIFIER
        // les droits d'accès aux modules : c'est une décision de niveau
        // boutique, prise par l'administrateur POS.
        $canManageModuleAccess = $this->isAdmin() || $this->isSuperAdmin();

        // ── Pages bloquées par UTILISATEUR ────────────────────────────────
        // Le blocage est une décision de niveau SUPER_ADMIN : il retire un
        // accès à un utilisateur précis, sans toucher à son rôle ni à ses
        // droits `acces_*`.
        //
        // Un manager (vendeur avec hotel_role='manager') continue de gérer
        // les RÔLES hôtel de son équipe, mais ne voit PAS cette colonne :
        // c'est cohérent avec la colonne « Accès modules POS », qui est
        // elle-même réservée aux administrateurs POS.
        $canManagePageAccess = $this->isSuperAdmin();

        // Pages bloquées, par utilisateur : [userId => [pageKey, ...]].
        // Le service est la source de vérité (même que la garde backend).
        //
        // Sur CETTE page, on ne propose que les pages de la FAMILLE HÔTEL :
        // les pages POS se gèrent depuis l'écran `/pages-bloquees`. Chaque
        // écran a ainsi sa propre modale, avec les pages qui le concernent.
        $blockedPagesByUser = [];
        $defaultAccessByUser = [];
        if ($canManagePageAccess && $hotelUsers) {
            $pageAccessService = new \App\Services\UserPageAccessService();
            $hotelPageKeys = $pageAccessService::getPageKeys(
                \App\Services\UserPageAccessService::FAMILY_HOTEL
            );
            foreach ($hotelUsers as $_u) {
                $__uid = (int)$_u['id'];
                $blockedPagesByUser[$__uid] = array_values(array_intersect(
                    $pageAccessService->blockedPageKeys($__uid),
                    $hotelPageKeys
                ));
                // Pages accessibles par DEFAUT (hors blocage) : elles servent
                // à flouter celles qui sont hors de son périmètre.
                $defaultAccessByUser[$__uid] = [];
                foreach ($hotelPageKeys as $__key) {
                    $defaultAccessByUser[$__uid][$__key] =
                        $pageAccessService::isPageAvailableByDefault($__key, $_u);
                }
            }
        }

        $this->render('hotel-roles', [
            'hotelUsers'      => $hotelUsers,
            'targetShopId'    => $targetShopId,
            'allShops'        => $this->isSuperAdmin() ? $this->listAllShops() : [],
            'currentShopName' => $this->resolveShopName($targetShopId),
            'canManageModuleAccess' => $canManageModuleAccess,
            'canManagePageAccess'   => $canManagePageAccess,
            'blockedPagesByUser'    => $blockedPagesByUser,
            'defaultAccessByUser'   => $defaultAccessByUser,
            // Catalogue limité aux pages HÔTEL (voir commentaire ci-dessus).
            'pageCatalog'           => \App\Services\UserPageAccessService::getCatalogForFamily(
                \App\Services\UserPageAccessService::FAMILY_HOTEL
            ),
        ]);
    }

    /**
     * Résout la boutique cible d'une écriture (/hotel/roles).
     *
     * ⚠️ BUG FIX 2026-11-14 : on ne se base PLUS sur `$_SESSION['shop_id']`
     * seul. Un `super_admin` n'a AUCUNE boutique en session (`shop_id` est
     * NULL au login), alors que la page /hotel/roles lui permet de choisir
     * la boutique affichée via `?shop_id=`. Résultat : toute écriture
     * échouait avec « Boutique non déterminée ».
     *
     * Ordre de résolution :
     *   1. `shop_id` explicite (POST) → validé ;
     *   2. `shop_id` de la session (admin / manager) ;
     *   3. sinon 0 (boutique indéterminée).
     *
     * Sécurité multi-tenant : un `admin` / `manager` ne peut écrire QUE
     * dans SA boutique ; le `shop_id` fourni est ignoré s'il ne correspond
     * pas. Le `super_admin`, lui, agit sur toutes les boutiques.
     *
     * @param mixed $requestedShopId Valeur reçue (POST).
     * @return int 0 = boutique indéterminée.
     */
    private function resolveTargetShopId($requestedShopId): int
    {
        $sessionShopId = (int)($_SESSION['shop_id'] ?? 0);

        // ── super_admin : il choisit la boutique affichée dans l'UI ──
        if ($this->isSuperAdmin()) {
            $requested = (int)$requestedShopId;
            if ($requested > 0) {
                return $requested;
            }
            // Repli : la boutique de session, sinon la 1re boutique ayant
            // des utilisateurs (même logique que hotelRoles()).
            if ($sessionShopId > 0) {
                return $sessionShopId;
            }
            $userModel = new User();
            return (int)($userModel->firstShopIdWithUsers() ?? 0);
        }

        // ── admin / manager : périmètre strict = sa boutique ──
        // Le `shop_id` fourni est ignoré (anti-évasion multi-tenant).
        return $sessionShopId;
    }

    /**
     * POST /api/hotel/role/update : met à jour le rôle hôtel d'un user.
     * Vérifie que :
     * - le caller est super_admin OU manager de la boutique
     * - l'user cible appartient à la boutique (scope multi-tenant)
     */
    public function apiHotelUpdateRole()
    {
        if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
            header('HTTP/1.1 405 Method Not Allowed');
            return;
        }
        $hr = $this->resolveHotelRole();
        if (!in_array($hr, ['super_admin', 'admin', 'manager'], true)) {
            header('HTTP/1.1 403 Forbidden');
            echo json_encode(['success' => false, 'message' => 'Accès refusé']);
            return;
        }

        $userId = (int)($_POST['user_id'] ?? 0);
        $newRole = $_POST['hotel_role'] ?? null;
        if ($newRole === '' || $newRole === 'null') {
            $newRole = null;
        }

        $targetShopId = $this->resolveTargetShopId($_POST['shop_id'] ?? null);
        if (!$targetShopId) {
            header('HTTP/1.1 400 Bad Request');
            echo json_encode(['success' => false, 'message' => 'Boutique non déterminée']);
            return;
        }

        $userModel = new User();
        $ok = $userModel->setHotelRole($userId, $newRole, $targetShopId);
        header('Content-Type: application/json');
        echo json_encode([
            'success' => $ok,
            'message' => $ok ? 'Rôle hôtel mis à jour' : 'Refusé (hors scope ou rôle invalide)',
        ]);
    }

    /**
     * POST /api/hotel/caisse-access/update : met à jour le droit d'accès à
     * un module du POS pour un utilisateur.
     *
     * ⚠️ ACCÈS RÉSERVÉ AUX ADMINISTRATEURS POS (`admin` = auto-manager de
     * la boutique, et `super_admin`). Un manager (vendeur avec
     * `hotel_role='manager'`) gère les RÔLES hôtel de son équipe mais ne
     * peut PAS modifier les droits d'accès aux modules : c'est une décision
     * de niveau boutique, prise par l'administrateur POS.
     *
     * Ce contrôle est côté SERVEUR : même si la colonne était visible dans
     * l'UI, un manager ne pourrait pas la modifier en appelant l'API.
     *
     * Vérifie que :
     * - le caller est admin POS ou super_admin ;
     * - l'user cible appartient à la boutique (scope multi-tenant, appliqué
     *   par `User::setModuleAccess()`).
     *
     * Body : user_id (int), module (caisse|produits|historique|pressing|
     *        analytics|paie), acces (0|1).
     *
     * ⚠️ Le paramètre `acces_caisse` est accepté en ALIAS de `acces` pour
     * compatibilité avec l'ancienne version de l'UI (case « Accès caisse »
     * qui n'envoyait que `acces_caisse`).
     */
    public function apiHotelUpdateCaisseAccess()
    {
        if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
            header('HTTP/1.1 405 Method Not Allowed');
            return;
        }

        // ── Garde administrateur POS ────────────────────────────────────
        // `isAdmin()` couvre `admin` ET `super_admin` ; on exige
        // explicitement le rôle applicatif (et NON le rôle métier hôtel).
        if (!$this->isAdmin() && !$this->isSuperAdmin()) {
            header('HTTP/1.1 403 Forbidden');
            echo json_encode([
                'success' => false,
                'message' => 'Accès réservé aux administrateurs de la boutique.',
            ]);
            return;
        }

        $userId = (int)($_POST['user_id'] ?? 0);
        if ($userId <= 0) {
            header('HTTP/1.1 400 Bad Request');
            echo json_encode(['success' => false, 'message' => 'user_id requis']);
            return;
        }

        // Module ciblé : `module` (nouveau) ou `acces_caisse` (alias).
        $module = (string)($_POST['module'] ?? '');
        if ($module === '' && isset($_POST['acces_caisse'])) {
            $module = 'caisse';
        }
        if (!array_key_exists($module, User::MODULES_ACCES)) {
            header('HTTP/1.1 400 Bad Request');
            echo json_encode([
                'success' => false,
                'message' => 'Module inconnu (attendu : ' . implode(', ', array_keys(User::MODULES_ACCES)) . ')',
            ]);
            return;
        }

        // Le droit est binaire : toute valeur non nulle signifie « autorisé ».
        $raw = $_POST['acces'] ?? $_POST['acces_' . $module] ?? null;
        $granted = !empty($raw) && $raw !== '0' && $raw !== 'false';

        $targetShopId = $this->resolveTargetShopId($_POST['shop_id'] ?? null);
        if (!$targetShopId) {
            header('HTTP/1.1 400 Bad Request');
            echo json_encode(['success' => false, 'message' => 'Boutique non déterminée']);
            return;
        }

        $userModel = new User();
        $ok = $userModel->setModuleAccess($module, $userId, $granted, $targetShopId);
        header('Content-Type: application/json');
        echo json_encode([
            'success' => $ok,
            'message' => $ok
                ? ($granted ? 'Accès « ' . $module . ' » accordé' : 'Accès « ' . $module . ' » retiré')
                : 'Refusé (hors scope ou rôle non modifiable)',
        ]);
    }

    /**
     * Écran « Pages bloquées » (super_admin).
     *
     * Permet de retirer l'accès à une page à un utilisateur PRÉCIS, sans
     * toucher à son rôle (`role`), à son rôle métier hôtel (`hotel_role`) ni
     * à ses droits `acces_*`.
     *
     * ⚠️ Réservé au `super_admin` : c'est une décision de niveau plateforme.
     * Un admin ou un manager (vendeur avec `hotel_role='manager'`) n'a pas
     * accès à cet écran — le contrôle est côté serveur, pas seulement dans
     * la sidebar.
     *
     * La boutique ciblée est choisie via `?shop_id=` (le super_admin agit
     * sur toutes les boutiques).
     */
    public function pagesBloquees()
    {
        // ⚠️ ACCÈS RÉSERVÉ AU `super_admin` : la réponse est une 403 (et non
        // une redirection silencieuse), comme pour toute page hors politique.
        // On NE fait PAS de `exit` : la méthode retourne simplement, ce qui
        // la rend testable et laisse le script se terminer normalement.
        if (!$this->isSuperAdmin()) {
            http_response_code(403);
            require_once dirname(__DIR__) . DIRECTORY_SEPARATOR . 'views' . DIRECTORY_SEPARATOR . '403.php';
            return;
        }

        // Boutique ciblée : le super_admin choisit via ?shop_id=.
        $targetShopId = $this->resolveTargetShopId(
            isset($_GET['shop_id']) && $_GET['shop_id'] !== '' ? $_GET['shop_id'] : null
        );

        $this->render('pages-bloquees', $this->buildPagesBloqueesData($targetShopId));
    }

    /**
     * Donnees de l'écran « Pages bloquées ».
     *
     * Isolé du rendu pour rester testable : `render()` inclut le layout avec
     * `require_once`, donc le HTML ne peut pas être inspecté plusieurs fois
     * dans un meme process.
     *
     * @return array<string, mixed>
     */
    public function buildPagesBloqueesData(int $targetShopId): array
    {
        $pageUsers = $targetShopId
            ? (new User())->allForShopForPageAccess($targetShopId)
            : [];

        // Blocages : une seule requête pour TOUS les utilisateurs (pas de
        // N+1). On ne garde que les lignes des utilisateurs affichés.
        //
        // Sur CET écran, on ne propose que les pages de la FAMILLE GÉNÉRALE
        // (POS) : les pages Hôtel se gèrent depuis `/hotel/roles`.
        $generalPageKeys = \App\Services\UserPageAccessService::getPageKeys(
            \App\Services\UserPageAccessService::FAMILY_GENERAL
        );

        $blockedByUser = [];
        $defaultAccessByUser = [];
        if ($pageUsers) {
            $allBlocked = (new \App\Models\UserPageAccess())->allBlockedByUser();
            foreach ($pageUsers as $u) {
                $uid = (int)$u['id'];
                $blockedByUser[$uid] = array_values(array_intersect(
                    $allBlocked[$uid] ?? [],
                    $generalPageKeys
                ));
                // Pages accessibles par DEFAUT (hors blocage) : elles servent
                // à flouter celles qui sont hors de son périmètre.
                $defaultAccessByUser[$uid] = [];
                foreach ($generalPageKeys as $key) {
                    $defaultAccessByUser[$uid][$key] =
                        \App\Services\UserPageAccessService::isPageAvailableByDefault($key, $u);
                }
            }
        }

        return [
            'pageUsers'     => $pageUsers,
            'pageShopId'    => $targetShopId,
            'pageShopName'  => $this->resolveShopName($targetShopId),
            'pageAllShops'  => $this->listAllShops(),
            'blockedByUser' => $blockedByUser,
            'defaultAccessByUser' => $defaultAccessByUser,
            // Catalogue limité aux pages POS (voir commentaire ci-dessus).
            'pagesCatalog'  => \App\Services\UserPageAccessService::getCatalogForFamily(
                \App\Services\UserPageAccessService::FAMILY_GENERAL
            ),
        ];
    }

    /**
     * POST /api/user/page-access/update : bloque ou debloque l'acces a UNE
     * page pour UN utilisateur.
     *
     * ⚠️ ACCÈS RÉSERVÉ AU `super_admin` : c'est lui qui retire un accès à un
     * utilisateur précis, sans toucher à son rôle (`role`), à son rôle métier
     * hôtel (`hotel_role`) ni à ses droits `acces_*`.
     *
     * Un manager (vendeur avec `hotel_role='manager'`) continue de gérer les
     * RÔLES hôtel de son équipe mais ne peut PAS bloquer une page : c'est
     * une décision de niveau super_admin.
     *
     * Sécurité multi-tenant : l'utilisateur ciblé DOIT appartenir à la
     * boutique attendue, sinon l'écriture est refusée.
     *
     * Body : user_id (int), page_key (string), blocked (0|1), shop_id (int).
     */
    public function apiUserPageAccessUpdate()
    {
        if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
            header('HTTP/1.1 405 Method Not Allowed');
            return;
        }

        // Garde de rôle : le super_admin SEUL peut bloquer une page.
        if (!$this->isSuperAdmin()) {
            header('HTTP/1.1 403 Forbidden');
            echo json_encode([
                'success' => false,
                'message' => 'Accès refusé — super_admin requis',
            ]);
            return;
        }

        $userId = (int)($_POST['user_id'] ?? 0);
        if ($userId <= 0) {
            header('HTTP/1.1 400 Bad Request');
            echo json_encode(['success' => false, 'message' => 'user_id requis']);
            return;
        }

        $pageKey = (string)($_POST['page_key'] ?? '');
        if (!\App\Services\UserPageAccessService::isKnownPage($pageKey)) {
            header('HTTP/1.1 400 Bad Request');
            echo json_encode([
                'success' => false,
                'message' => 'Page inconnue : ' . $pageKey,
            ]);
            return;
        }

        // Le droit est binaire : toute valeur non nulle signifie « bloqué ».
        $raw = $_POST['blocked'] ?? null;
        $blocked = !empty($raw) && $raw !== '0' && $raw !== 'false';

        // Boutique cible : le super_admin agit sur toutes les boutiques, mais
        // l'utilisateur DOIT appartenir à la boutique visée (anti-évasion).
        $targetShopId = $this->resolveTargetShopId($_POST['shop_id'] ?? null);
        if (!$targetShopId) {
            header('HTTP/1.1 400 Bad Request');
            echo json_encode(['success' => false, 'message' => 'Boutique non déterminée']);
            return;
        }

        $targetUser = (new User())->exist($userId);
        if (!$targetUser) {
            header('HTTP/1.1 404 Not Found');
            echo json_encode(['success' => false, 'message' => 'Utilisateur introuvable']);
            return;
        }
        if ((int)($targetUser['shop_id'] ?? 0) !== $targetShopId) {
            header('HTTP/1.1 403 Forbidden');
            echo json_encode([
                'success' => false,
                'message' => 'Utilisateur hors de la boutique ciblée',
            ]);
            return;
        }

        $creatorId = (int)($_SESSION['user_id'] ?? 0);
        $service = new \App\Services\UserPageAccessService();
        $ok = $service->setPageBlocked($userId, $pageKey, $blocked, $creatorId > 0 ? $creatorId : null);

        header('Content-Type: application/json');
        echo json_encode([
            'success' => $ok,
            'message' => $ok
                ? ($blocked
                    ? 'Accès à la page « ' . $pageKey . ' » retiré'
                    : 'Accès à la page « ' . $pageKey . ' » rétabli')
                : 'Refusé (super_admin non bloquable ou écriture impossible)',
        ]);
    }

    /**
     * Endpoint JSON pour la page /hotel/calendrier.
     * Renvoie les chambres + séjours qui chevauchent la semaine
     * débutant à ?from=YYYY-MM-DD (par défaut : aujourd'hui).
     *
     * Accès : manager + super_admin (réceptionniste et gouvernante n'ont pas
     * accès au calendrier global — ils utilisent Réception / Étages).
     */
    public function apiHotelCalendrier()
    {
        $hr = $this->resolveHotelRole();
        if (!in_array($hr, ['super_admin', 'manager'], true)) {
            header('HTTP/1.1 403 Forbidden');
            echo json_encode(['success' => false, 'message' => 'Accès refusé']);
            return;
        }

        $from = isset($_GET['from']) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $_GET['from'])
            ? $_GET['from']
            : date('Y-m-d');
        $to = date('Y-m-d', strtotime($from . ' +6 days'));

        $resModel = new HotelReservation();
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode([
            'success'  => true,
            'data'     => [
                'from'      => $from,
                'to'        => $to,
                'chambres'  => $resModel->allRooms(),
                'sejours'   => $resModel->sejoursSurPlage($from, $to),
            ],
            'server_time' => date('Y-m-d H:i:s'),
        ]);
    }

    /**
     * Résout le rôle hôtel effectif d'un utilisateur pour cette session.
     * Priorité :
     *   - super_admin (rôle app)  → 'super_admin'
     *   - admin (rôle app)        → 'manager' (auto-promu pour sa boutique)
     *   - vendeur + hotel_role    → renvoie hotel_role
     *   - sinon                   → 'aucun'
     */
    private function resolveHotelRole(): string
    {
        $app = $_SESSION['role'] ?? '';
        $hotel = $_SESSION['hotel_role'] ?? null;

        if ($app === 'super_admin') {
            return 'super_admin';
        }
        if ($app === 'admin') {
            return 'manager';
        }

        if (in_array($hotel, ['manager', 'receptionniste', 'gouvernante'], true)) {
            return $hotel;
        }
        return 'aucun';
    }

    /**
     * Vérifie que l'utilisateur courant a accès au module hôtel
     * avec un rôle effectif qui matche la liste autorisée.
     */
    private function requireHotelAccess(array $allowedRoles)
    {
        $hr = $this->resolveHotelRole();
        if (!in_array($hr, $allowedRoles, true)) {
            $_SESSION['flash_error'] = 'Accès au module hôtel non autorisé pour votre rôle.';
            $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
            $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
            header('Location: ' . $protocol . '://' . $host . '/dashboard');
            exit;
        }
    }

    /**
     * Sprint 7 — 2026-10-19 : vérifie l'accès au module hôtel via l'API
     * externe (flag `hotel` retourné par /dgi/option_client/feedback.php).
     * Pattern aligné sur /recharges et /payroll : si l'API refuse, on
     * affiche la page 403.php (au lieu de rediriger vers /dashboard, comme
     * pour requireHotelAccess qui ne dépend que du rôle métier local).
     *
     * NB : le contrôle d'accès API (canAccessHotel) est INDÉPENDANT du
     * contrôle de rôle (requireHotelAccess) — les deux s'enchaînent :
     *   1. D'abord l'API externe (ce helper) : "avez-vous souscrit au
     *      module hôtel ?"
     *   2. Puis le rôle métier (requireHotelAccess) : "votre rôle permet-il
     *      d'accéder à CETTE page hôtel ?"
     * Si les deux checks passent, l'accès est accordé. Si l'un des deux
     * échoue, l'accès est refusé (403 API, 302 → /dashboard pour le rôle).
     */
    private function requireHotelApiAccess()
    {
        $accessService = new \App\Services\RechargeAccessService();
        if (!$accessService->canAccessHotel()) {
            http_response_code(403);
            require_once dirname(__DIR__) . DIRECTORY_SEPARATOR . 'views/403.php';
            exit;
        }
    }

    /**
     * Liste toutes les boutiques (super_admin uniquement).
     */
    private function listAllShops(): array
    {
        if (!$this->isSuperAdmin()) {
            return [];
        }
        try {
            $shopModel = new \App\Models\Shop();
            return $shopModel->getAll() ?? [];
        } catch (\Throwable $e) {
            return [];
        }
    }

    private function resolveShopName(int $shopId): string
    {
        if (!$shopId) {
            return '—';
        }
        try {
            $shopModel = new \App\Models\Shop();
            $shop = $shopModel->findById($shopId);
            return $shop['nom'] ?? "Boutique #$shopId";
        } catch (\Throwable $e) {
            return "Boutique #$shopId";
        }
    }

    public function historique()
    {
        // Droit d'accès au module Historique (`utilisateurs.acces_historique`).
        $this->requireModulePageAccess('historique');

        $saleModel = new Sale();
        $shopId = $this->isSuperAdmin() ? null : $this->getShopId();
        $ventes = $saleModel->getAllSales($shopId);
        $this->render('historique', ['ventes' => $ventes]);
    }

    /**
     * Page des LOTS — liste de tous les lots de la boutique, triés par
     * péremption.
     *
     * ⚠️ La page est une COQUILLE HTML : le contenu vient de fetch() vers
     *    /api/product-batches/page (pagination keyset côté serveur).
     *
     *    Server-side, comme la page /produits : à 50 000 produits /
     *    85 000 lots, rendre TOUS les lots produisait 60 Mo de HTML et
     *    un DOM de 85 000 <tr>.
     */
    public function produitsLots()
    {
        // Droit d'accès au module Produits (même page que /produits).
        $this->requireModulePageAccess('produits');

        $this->render('produits-lots', []);
    }

    /**
     * Page des alertes de péremption (tous les lots).
     *
     * Le dashboard n'affiche que les 20 alertes les plus urgentes (borné
     * pour ne pas charger des milliers de lignes). Cette page montre la
     * liste COMPLÈTE, paginée.
     *
     * Pagination par OFFSET : le nombre de lots par boutique reste
     * raisonnable (des centaines, pas des dizaines de milliers), donc
     * l'OFFSET est acceptable ici. La page /produits/lots, qui liste
     * les 85 000 lots du catalogue, utilise le KEYSET à la place.
     */
    public function alertesLots()
    {
        $this->requireModulePageAccess('produits');

        $page = max(1, (int)($_GET['page'] ?? 1));
        $perPage = 50;
        $offset = ($page - 1) * $perPage;

        // Filtres : 'expired' (périmés), 'soon' (périment bientôt) ou
        // les deux par défaut.
        $filtre = (string)($_GET['filtre'] ?? 'all');
        $days = max(1, min(365, (int)($_GET['days'] ?? 30)));

        $shopId = $this->isSuperAdmin() ? ($_GET['shop_id'] ?? null) : $this->getShopId();
        $batchModel = new ProductBatch();

        $rows = [];
        $total = 0;

        if ($filtre === 'expired') {
            $rows = $batchModel->getExpired($shopId, $perPage, $offset);
            $total = $batchModel->countExpired($shopId);
        } elseif ($filtre === 'soon') {
            $rows = $batchModel->getExpiringSoon($days, $shopId, $perPage, $offset);
            $total = $batchModel->countExpiringSoon($days, $shopId);
        } else {
            // Les deux : on concatène les périmés puis les proches.
            $expired = $batchModel->getExpired($shopId, $perPage, $offset);
            $rows = $expired;
            $total = $batchModel->countExpired($shopId) + $batchModel->countExpiringSoon($days, $shopId);
        }

        $this->render('alertes-lots', [
            'lots' => $rows,
            'total' => $total,
            // ⚠️ La clé s'appelle 'pageNum' et non 'page' : `render()`
            // injecte déjà $page = nom de la vue ('alertes-lots'), et les
            // deux se chevaucheraient.
            'pageNum' => $page,
            'perPage' => $perPage,
            'pages' => (int)ceil(max(1, $total) / $perPage),
            'filtre' => $filtre,
            'days' => $days,
        ]);
    }

    /**
     * Page Pressing — suivi des commandes de pressing.
     *
     * La page ne charge que la coquille HTML : le contenu vient de fetch()
     * vers /api/pressing/* (PressingController).
     *
     * L'accès est contrôlé par le flag `pressing` de l'API OSAT
     * (`RechargeAccessService::canAccessPressing()`), exactement comme
     * `/recharges` et `/hotel` : pas de réglage par boutique.
     */
    public function pressing()
    {
        // 1. Feature flag API `pressing` (inchangé — abonnement OSAT).
        if (!$this->requireShopPressingAccess(false)) {
            return;
        }
        // 2. Droit d'accès par utilisateur (`utilisateurs.acces_pressing`).
        //    Les deux contrôles s'AJOUTENT : le flag dit « la boutique a
        //    souscrit », le droit dit « CET utilisateur a le droit ».
        $this->requireModulePageAccess('pressing');

        $this->render('pressing', []);
    }

    /**
     * Page de facturation hôtel.
     * Le séjour à facturer est passé en query param : /hotel/facturation?sejour_id=X
     * (sinon on affiche un sélecteur de séjour).
     */
    public function hotelFacturation()
    {
        $this->requireHotelApiAccess();
        $this->render('hotel-facturation', []);
    }

    public function analytics()
    {
        if (!$this->isAdmin() && !$this->isSuperAdmin()) {
            $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
            $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
            header('Location: ' . $protocol . '://' . $host . '/dashboard');
            exit;
        }
        // Droit d'accès par utilisateur (`utilisateurs.acces_analytics`).
        // ⚠️ Redondant pour les rôles de pilotage (déjà bloqués par le
        // contrôle admin ci-dessus et exonérés de tout droit), mais utile
        // si la règle d'accès à Analytics évolue.
        $this->requireModulePageAccess('analytics');

        $saleModel = new Sale();
        $saleDetailModel = new \App\Models\SaleDetail();
        $productModel = new Product();
        $userModel = new User();
        $categoryModel = new \App\Models\Category();
        $clientModel = new \App\Models\Client();

        // Gestion du filtre shop_id (super_admin)
        $filteredShopId = null;
        if ($this->isSuperAdmin() && isset($_GET['shop_id']) && $_GET['shop_id'] !== '') {
            $filteredShopId = (int)$_GET['shop_id'];
        }

        $shopId = $this->isSuperAdmin() ? $filteredShopId : $this->getShopId();
        $ventes = $saleModel->getAllSales($shopId);
        $produits = $productModel->getAll($shopId);
        $categories = $categoryModel->all($shopId);
        $users = $userModel->all($shopId);
        $clients = $clientModel->getAll($shopId);

        // Périodes
        $today = date('Y-m-d');
        $weekStart = date('Y-m-d 00:00:00', strtotime('-6 days'));
        $monthStart = date('Y-m-01 00:00:00');
        $yearStart = date('Y-01-01 00:00:00');
        $yearEnd = date('Y-12-31 23:59:59');

        $stats = [
            'today' => ['total' => 0, 'count' => 0],
            'week' => ['total' => 0, 'count' => 0],
            'month' => ['total' => 0, 'count' => 0],
            'year' => ['total' => 0, 'count' => 0],
            'all_time' => ['total' => 0, 'count' => 0],
        ];

        $salesByDay = [];
        $salesByMonth = [];
        $salesByHour = [];
        $salesBySeller = [];
        $revenueByCategory = [];
        $productQuantities = [];
        $clientSales = [];
        $rechargeVsProducts = ['products' => 0, 'recharges' => 0];

        $categoryNames = [];
        foreach ($categories as $cat) {
            $categoryNames[$cat['id']] = $cat['category'];
        }

        foreach ($ventes as $v) {
            $vDate = strtotime($v['date']);
            $vDateStr = date('Y-m-d', $vDate);
            $vMonthStr = date('Y-m', $vDate);
            $vHour = (int) date('H', $vDate);
            $total = (float) $v['total'];
            $sellerId = $v['vendeur_id'];
            $sellerName = $v['nom_vendeur'] ?? 'Inconnu';
            $clientId = $v['client_id'];
            $isRecharge = !empty($v['service']);

            // Stats globales
            $stats['all_time']['total'] += $total;
            $stats['all_time']['count']++;
            if ($vDateStr === $today) {
                $stats['today']['total'] += $total;
                $stats['today']['count']++;
            }
            if ($vDate >= strtotime($weekStart)) {
                $stats['week']['total'] += $total;
                $stats['week']['count']++;
            }
            if ($vDate >= strtotime($monthStart)) {
                $stats['month']['total'] += $total;
                $stats['month']['count']++;
            }
            if ($vDate >= strtotime($yearStart) && $vDate <= strtotime($yearEnd)) {
                $stats['year']['total'] += $total;
                $stats['year']['count']++;
            }

            // Recharge vs produits
            if ($isRecharge) {
                $rechargeVsProducts['recharges'] += $total;
            } else {
                $rechargeVsProducts['products'] += $total;
            }

            // Par jour
            $salesByDay[$vDateStr] = ($salesByDay[$vDateStr] ?? 0) + $total;
            // Par mois
            $salesByMonth[$vMonthStr] = ($salesByMonth[$vMonthStr] ?? 0) + $total;
            // Par heure
            $salesByHour[$vHour] = ($salesByHour[$vHour] ?? 0) + $total;
            // Par vendeur
            if (!isset($salesBySeller[$sellerId])) {
                $salesBySeller[$sellerId] = ['name' => $sellerName, 'total' => 0, 'count' => 0];
            }
            $salesBySeller[$sellerId]['total'] += $total;
            $salesBySeller[$sellerId]['count']++;

            // Par client
            if ($clientId) {
                $clientSales[$clientId] = ($clientSales[$clientId] ?? 0) + $total;
            }

        }

        // Détails des ventes pour les produits et catégories
        foreach ($ventes as $v) {
            $totalSale = (float) $v['total'];
            $details = $saleDetailModel->getBySaleId($v['id']);
            if (empty($details)) {
                continue;
            }
            foreach ($details as $d) {
                $pid = $d['produit_id'];
                $qty = (float) $d['quantite'];
                $lineTotal = (float) $d['prix'] * $qty;
                $productName = $d['produit_nom'] ?? 'Produit #' . $pid;
                $categoryId = 0;
                foreach ($produits as $p) {
                    if ($p['id'] == $pid) {
                        $categoryId = $p['category_id'];
                        break;
                    }
                }
                $categoryName = $categoryNames[$categoryId] ?? 'Non catégorisé';

                if (!isset($productQuantities[$pid])) {
                    $productQuantities[$pid] = ['name' => $productName, 'qty' => 0, 'revenue' => 0];
                }
                $productQuantities[$pid]['qty'] += $qty;
                $productQuantities[$pid]['revenue'] += $lineTotal;

                if (!isset($revenueByCategory[$categoryName])) {
                    $revenueByCategory[$categoryName] = 0;
                }
                $revenueByCategory[$categoryName] += $lineTotal;
            }
        }

        // Trier les top produits
        uasort($productQuantities, function ($a, $b) {
            return $b['revenue'] <=> $a['revenue'];
        });
        $topProducts = array_slice($productQuantities, 0, 10, true);

        // Trier les vendeurs
        uasort($salesBySeller, function ($a, $b) {
            return $b['total'] <=> $a['total'];
        });
        $topSellers = array_slice($salesBySeller, 0, 10, true);

        // Trier les clients
        arsort($clientSales);
        $topClients = array_slice($clientSales, 0, 10, true);
        $topClientsNames = [];
        foreach ($clients as $c) {
            if (isset($topClients[$c['id']])) {
                $topClientsNames[$c['id']] = $c['nom_client'];
            }
        }

        // Données pour les 30 derniers jours
        $dailyLabels = [];
        $dailyValues = [];
        for ($i = 29; $i >= 0; $i--) {
            $day = date('Y-m-d', strtotime("-$i days"));
            $dailyLabels[] = date('d/m', strtotime($day));
            $dailyValues[] = round($salesByDay[$day] ?? 0, 2);
        }

        // Données pour les 12 derniers mois
        $monthlyLabels = [];
        $monthlyValues = [];
        $monthNames = ['01' => 'Jan', '02' => 'Fév', '03' => 'Mar', '04' => 'Avr', '05' => 'Mai', '06' => 'Juin', '07' => 'Juil', '08' => 'Août', '09' => 'Sep', '10' => 'Oct', '11' => 'Nov', '12' => 'Déc'];
        for ($i = 11; $i >= 0; $i--) {
            $month = date('Y-m', strtotime("-$i months"));
            $monthlyLabels[] = ($monthNames[substr($month, 5, 2)] ?? substr($month, 5, 2)) . ' ' . substr($month, 0, 4);
            $monthlyValues[] = round($salesByMonth[$month] ?? 0, 2);
        }

        // Heures de vente
        $hourlyLabels = [];
        $hourlyValues = [];
        for ($i = 0; $i < 24; $i++) {
            $hourlyLabels[] = sprintf('%02dh', $i);
            $hourlyValues[] = round($salesByHour[$i] ?? 0, 2);
        }

        // Calculs KPI
        $averageBasket = $stats['all_time']['count'] > 0 ? $stats['all_time']['total'] / $stats['all_time']['count'] : 0;
        $bestDay = !empty($salesByDay) ? array_search(max($salesByDay), $salesByDay) : null;
        $bestDayAmount = $bestDay ? $salesByDay[$bestDay] : 0;
        $bestDayLabel = $bestDay ? date('d/m/Y', strtotime($bestDay)) : '-';
        $activeClients = count($clientSales);
        $totalClients = count($clients);
        $customerRate = $totalClients > 0 ? round(($activeClients / $totalClients) * 100, 1) : 0;
        $uniqueDays = count($salesByDay);
        $averageDailySales = $uniqueDays > 0 ? $stats['all_time']['total'] / $uniqueDays : 0;

        // Best seller
        $bestSeller = !empty($topSellers) ? reset($topSellers) : null;
        $bestSellerName = $bestSeller ? $bestSeller['name'] : '-';
        $bestSellerAmount = $bestSeller ? $bestSeller['total'] : 0;

        // Best product
        $bestProduct = !empty($topProducts) ? reset($topProducts) : null;
        $bestProductName = $bestProduct ? $bestProduct['name'] : '-';
        $bestProductRevenue = $bestProduct ? $bestProduct['revenue'] : 0;

        // Stock
        $stockAlerts = array_filter($produits, function ($p) {
            return $p['stock'] <= $p['stock_minimum'];
        });
        $stockOut = count(array_filter($produits, function ($p) {
            return (int) $p['stock'] === 0;
        }));

        $this->render('analytics', [
            'ventes' => $ventes,
            'produits' => $produits,
            'categories' => $categories,
            'users' => $users,
            'clients' => $clients,
            'stats' => $stats,
            'salesByDay' => $salesByDay,
            'salesByMonth' => $salesByMonth,
            'salesByHour' => $salesByHour,
            'salesBySeller' => $salesBySeller,
            'topSellers' => $topSellers,
            'topProducts' => $topProducts,
            'topClients' => $topClients,
            'topClientsNames' => $topClientsNames,
            'revenueByCategory' => $revenueByCategory,
            'rechargeVsProducts' => $rechargeVsProducts,
            'dailyLabels' => json_encode($dailyLabels),
            'dailyValues' => json_encode($dailyValues),
            'monthlyLabels' => json_encode($monthlyLabels),
            'monthlyValues' => json_encode($monthlyValues),
            'hourlyLabels' => json_encode($hourlyLabels),
            'hourlyValues' => json_encode($hourlyValues),
            'categoryLabels' => json_encode(array_keys($revenueByCategory)),
            'categoryValues' => json_encode(array_values($revenueByCategory)),
            'sellerLabels' => json_encode(array_values(array_map(function ($s) {
                return $s['name'];
            }, $topSellers))),
            'sellerValues' => json_encode(array_values(array_map(function ($s) {
                return $s['total'];
            }, $topSellers))),
            'rechargeValues' => json_encode([$rechargeVsProducts['products'], $rechargeVsProducts['recharges']]),
            'averageBasket' => $averageBasket,
            'averageDailySales' => $averageDailySales,
            'bestDayLabel' => $bestDayLabel,
            'bestDayAmount' => $bestDayAmount,
            'bestSellerName' => $bestSellerName,
            'bestSellerAmount' => $bestSellerAmount,
            'bestProductName' => $bestProductName,
            'bestProductRevenue' => $bestProductRevenue,
            'activeClients' => $activeClients,
            'totalClients' => $totalClients,
            'customerRate' => $customerRate,
            'stockAlerts' => $stockAlerts,
            'stockOut' => $stockOut,
            'shops' => $this->isSuperAdmin() ? (new \App\Models\Shop())->getAll() : [],
        ]);
    }

    public function parametres()
    {
        if (!$this->isAdmin() && !$this->isSuperAdmin()) {
            $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
            $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
            header('Location: ' . $protocol . '://' . $host . '/dashboard');
            exit;
        }
        $data = [];
        if ($this->isSuperAdmin()) {
            $data['shops'] = (new \App\Models\Shop())->getAll();
        }
        $this->render('parametres', $data);
    }

    public function taxes()
    {
        if (!$this->isAdmin() && !$this->isSuperAdmin()) {
            $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
            $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
            header('Location: ' . $protocol . '://' . $host . '/dashboard');
            exit;
        }
        $taxModel = new \App\Models\Tax();
        $taxes = $taxModel->getAll();
        $this->render('taxes', ['taxes' => $taxes]);
    }

    public function categories()
    {
        if (!$this->isAdmin() && !$this->isSuperAdmin()) {
            $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
            $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
            header('Location: ' . $protocol . '://' . $host . '/dashboard');
            exit;
        }
        $productModel = new Product();
        $categoryModel = new \App\Models\Category();

        $shopId = $this->isSuperAdmin() ? null : $this->getShopId();

        // Get all categories from database
        $dbCategories = $categoryModel->all($shopId);

        // Get all products to count by category
        $produits = $productModel->getAll($shopId);
        $categoryCounts = [];

        foreach ($produits as $p) {
            $cat = $p['categorie'];
            if (!isset($categoryCounts[$cat])) {
                $categoryCounts[$cat] = 0;
            }
            $categoryCounts[$cat]++;
        }

        // Build categories array from database with product counts
        $categories = [];
        $colors = ['#0B5E88', '#8B5E3C', '#5E8B3C', '#3C8B8B', '#8B3C5E', '#5E3C8B', '#8B8B3C'];
        $colorIndex = 0;

        foreach ($dbCategories as $c) {
            $categories[] = [
                'id' => $c['id'],
                'nom' => $c['category'],
                'couleur' => $c['couleur'] ?? $colors[$colorIndex % count($colors)],
                'nombre_produits' => $categoryCounts[$c['category']] ?? 0,
            ];
            $colorIndex++;
        }

        $this->render('categories', ['categories' => $categories]);
    }

    public function shops()
    {
        if (!$this->isSuperAdmin()) {
            $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
            $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
            header('Location: ' . $protocol . '://' . $host . '/dashboard');
            exit;
        }
        $shopModel = new \App\Models\Shop();
        $shops = $shopModel->getAll();
        $this->render('shops', ['shops' => $shops]);
    }


    public function scanner()
    {
        // Vérifier la session
        if (!isset($_SESSION['user_id'])) {
            $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
            $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
            header('Location: ' . $protocol . '://' . $host . '/');
            exit;
        }

        // Pour le scanner, on charge une page spéciale sans le layout principal
        $settingsModel = new Settings();
        $storeName = $settingsModel->get('store_name') ?? 'Mon Magasin';
        $baseUrl = $this->getBaseUrl();

        // Rendre la page scanner directement (sans header/footer du layout)
        require_once dirname(__DIR__) . DIRECTORY_SEPARATOR . 'views/scanner.php';
    }

    // Nouveau scanner propre
    public function newScanner()
    {
        // Vérifier la session
        if (!isset($_SESSION['user_id'])) {
            $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
            $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
            header('Location: ' . $protocol . '://' . $host . '/');
            exit;
        }

        // Charger la nouvelle page scanner
        require_once dirname(__DIR__) . DIRECTORY_SEPARATOR . 'views/new-scanner.php';
    }

    public function payroll($params)
    {
        if (!isset($_SESSION['user_id'])) {
            $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
            $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
            header('Location: ' . $protocol . '://' . $host . '/');
            exit;
        }

        // Vérifier l'accès via l'API externe (flag paie)
        $accessService = new \App\Services\RechargeAccessService();
        if (!$accessService->canAccessPayroll()) {
            http_response_code(403);
            require_once dirname(__DIR__) . DIRECTORY_SEPARATOR . 'views/403.php';
            exit;
        }

        // Droit d'accès par utilisateur (`utilisateurs.acces_paie`).
        // Les deux contrôles s'AJOUTENT : le flag dit « la boutique a
        // souscrit au module paie », le droit dit « CET utilisateur y a
        // accès ». C'est ce second contrôle qui permet de retirer la
        // receptionniste ou la gouvernante du module paie.
        $this->requireModulePageAccess('paie');

        $isAdmin = $this->isAdmin() || $this->isSuperAdmin();
        $view = $params['view'] ?? 'index';

        if ($isAdmin) {
            $allowed = ['index','employees','employee_form','contracts','periods','attendance','payslips','payslip_detail','payslip_pdf','payments','timeclock','reports','settings'];
            if (!in_array($view, $allowed)) {
                $view = 'index';
            }
        } else {
            $allowed = ['mypayslips'];
            if (!in_array($view, $allowed)) {
                $view = 'mypayslips';
            }
        }

        // `$page` reste 'payroll' (c'est lui qui pilote l'inclusion du CSS et
        // l'affichage du bloc dans le header). On transmet en plus la VUE
        // courante (`payrollView`) pour que la sidebar puisse mettre en
        // évidence la sous-page active du dropdown Paie.
        $data = ['page' => 'payroll', 'payrollView' => $view];
        $this->render('payroll/' . $view, $data);
    }

    private function getBaseUrl()
    {
        $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
        $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
        return $protocol . '://' . $host;
    }

    public function rapports()
    {
        $data = ['page' => 'rapports'];
        if ($this->isSuperAdmin()) {
            $data['shops'] = (new \App\Models\Shop())->getAll();
        }
        $this->render('rapports', $data);
    }

    public function rapportTicket()
    {
        if (!isset($_SESSION['user_id'])) {
            $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
            $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
            header('Location: ' . $protocol . '://' . $host . '/');
            exit;
        }
        require_once dirname(__DIR__) . DIRECTORY_SEPARATOR . 'views/rapport-ticket.php';
    }
}
