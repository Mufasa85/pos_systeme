<?php

namespace App\Controllers;

class Controller
{
    private static $controller;
    private static $SESSION_TIMEOUT = 28800; // 8 heures en secondes

    private static function instance(): self
    {
        if (is_null(self::$controller)) {
            self::$controller = new self();
        }
        return self::$controller;
    }

    /**
     * Assainit une valeur entrante (POST ou JSON) et la renvoie en chaîne.
     *
     * ⚠️ La méthode ne doit JAMAIS lever de TypeError : une valeur absente
     * (`null`), un booléen, un nombre ou un tableau sont normalisés en
     * chaîne. Avant, un simple champ OPTIONNEL non envoyé (ex. `shop_id`)
     * suffisait à produire une page blanche :
     * « sanitaze(): Argument #1 ($input) must be of type string, null given ».
     *
     * @param mixed $input
     */
    protected function sanitaze($input): string
    {
        if (is_string($input)) {
            return strip_tags(htmlspecialchars($input));
        }
        if ($input === null || $input === false) {
            return '';
        }
        if ($input === true) {
            return '1';
        }
        if (is_int($input) || is_float($input)) {
            return strip_tags(htmlspecialchars((string) $input));
        }

        // Tableau ou objet : la structure n'est pas conservée (entrée non
        // conforme au format attendu).
        return '';
    }

    /**
     * Normalise un mot de passe saisi : `trim()` uniquement.
     *
     * ⚠️ Un mot de passe n'est PAS une donnée d'affichage : l'assainissement
     * HTML (`htmlspecialchars`, `strip_tags`) le corrompt (`&` → `&amp;`,
     * `<b` → supprimé) et le désynchronise de la saisie de connexion.
     * La création, la mise à jour et la connexion appliquent donc la même
     * normalisation.
     *
     * @param mixed $password
     */
    protected function sanitizePassword($password): string
    {
        return is_string($password) ? trim($password) : '';
    }

    /**
     * Données de la requête courante : formulaire (POST) OU corps JSON.
     *
     * Le corps n'est lu que si `$_POST` est vide : sur une requête
     * `multipart/form-data` (envoi d'une photo de profil), `php://input`
     * n'est pas disponible.
     *
     * @return array<string, mixed>
     */
    protected function requestInput(): array
    {
        if (!empty($_POST)) {
            return $_POST;
        }

        $raw = @file_get_contents('php://input');
        if (!is_string($raw) || $raw === '') {
            return [];
        }

        $json = json_decode($raw, true);

        return is_array($json) ? $json : [];
    }

    public static function status(int $status)
    {
        \http_response_code($status);
        return self::instance();
    }

    public static function json($array)
    {
        header('Content-Type:application/json');
        echo json_encode($array, JSON_PRETTY_PRINT);
    }

    public function inputs()
    {
        $datas = \file_get_contents('php://input');
        return $datas;
    }

    // ── Multi-shop helpers ──────────────────────────────────────

    protected function getShopId()
    {
        return $_SESSION['shop_id'] ?? null;
    }

    protected function isSuperAdmin()
    {
        return isset($_SESSION['role']) && $_SESSION['role'] === 'super_admin';
    }

    protected function isAdmin()
    {
        return isset($_SESSION['role']) && in_array($_SESSION['role'], ['admin', 'super_admin']);
    }

    protected function isAuthenticated()
    {
        return isset($_SESSION['user_id']);
    }

    protected function requireAuth()
    {
        if (!$this->isAuthenticated()) {
            self::status(403)->json(['success' => false, 'error' => 'Non authentifié']);
            return false;
        }
        // Vérifier expiration de session
        if (isset($_SESSION['last_activity']) && (time() - $_SESSION['last_activity']) > self::$SESSION_TIMEOUT) {
            session_destroy();
            self::status(401)->json(['success' => false, 'error' => 'Session expirée']);
            return false;
        }
        $_SESSION['last_activity'] = time();

        // Vérifier que le shop de l'utilisateur est toujours actif.
        // Cette vérification protège TOUTES les routes (web + api) automatiquement.
        // requireActiveShop() renvoie false seulement si shop OK, sinon exit() directement.
        $this->requireActiveShop();

        return true;
    }

    protected function requireAdmin()
    {
        if (!$this->requireAuth()) {
            return false;
        }
        if (!$this->isAdmin()) {
            self::status(403)->json(['success' => false, 'error' => 'Accès refusé']);
            return false;
        }
        return true;
    }

    protected function requireSuperAdmin()
    {
        if (!$this->isSuperAdmin()) {
            self::status(403)->json(['success' => false, 'error' => 'Accès refusé — super_admin requis']);
            return false;
        }
        return true;
    }

    /**
     * Sprint 7 — 2026-10-19 : garde d'accès au feature flag de l'API OSAT
     * pour les contrôleurs API (Hotel*, Recharges, etc.). Pattern aligné
     * sur requireAuth() : retourne false + status 403 si l'accès est refusé.
     *
     * Usage typique dans un contrôleur API :
     *   public function someAction(): void {
     *       if (!$this->requireAuth() || !$this->requireFeatureApiAccess('hotel')) return;
     *       // ... suite de la logique
     *   }
     *
     * Flags supportés : 'snelregideso' (= canAccess), 'paie' (= canAccessPayroll),
     * 'hotel' (= canAccessHotel).
     */
    protected function requireFeatureApiAccess(string $feature): bool
    {
        $service = new \App\Services\RechargeAccessService();
        $allowed = match ($feature) {
            'snelregideso' => $service->canAccess(),
            'paie'         => $service->canAccessPayroll(),
            'hotel'        => $service->canAccessHotel(),
            default        => true, // feature inconnue → on autorise
        };
        if (!$allowed) {
            self::status(403)->json([
                'success' => false,
                'error'   => "Accès refusé : le feature « {$feature} » n'est pas activé pour cette boutique.",
            ]);
            return false;
        }
        return true;
    }

    /**
     * Vérifie que la boutique courante a accès à la fonctionnalité « Extras »
     * (imputation d'une vente sur un séjour hôtelier depuis la caisse).
     *
     * Ce contrôle REMPLACE le feature flag API « hotel » pour les extras :
     * l'accès est désormais configuré par boutique via `shops.acces_extras`,
     * cochée par le super_admin dans /shops.
     *
     * L'authentification reste obligatoire : ce n'est pas une route anonyme.
     * Un utilisateur non authentifié ou d'une boutique non cochée reçoit un 403.
     *
     * @param bool $json true pour une réponse JSON (API), false pour une page 403
     */
    protected function requireShopExtrasAccess(bool $json = true): bool
    {
        $shopId = $this->getShopId();
        $allowed = (new \App\Models\Shop())->hasExtrasAccess($shopId);

        if (!$allowed) {
            if ($json) {
                self::status(403)->json([
                    'success' => false,
                    'error'   => 'extras_not_allowed',
                    'message' => 'La fonctionnalité Extras n\'est pas activée pour votre boutique.',
                ]);
            } else {
                http_response_code(403);
                require_once dirname(__DIR__) . DIRECTORY_SEPARATOR . 'views' . DIRECTORY_SEPARATOR . '403.php';
                exit;
            }
            return false;
        }

        return true;
    }

    /**
     * Vérifie que l'utilisateur courant a accès à la fonctionnalité « Pressing »
     * (suivi des commandes de pressing).
     *
     * Même mécanisme que « Recharges », « Paie » et « Hôtel » : l'accès est
     * piloté par le flag `pressing` renvoyé par l'API OSAT
     * (`option_client/feedback.php` → `data.pressing`), via
     * `RechargeAccessService::canAccessPressing()`.
     *
     * Règles :
     *   - Flag API vrai → accès accordé.
     *   - Flag API faux / absent / erreur réseau → false (fail-closed),
     *     le caller renvoie un 403.
     *   - L'authentification reste obligatoire : ce n'est pas une route anonyme.
     *
     * @param bool $json true pour une réponse JSON (API), false pour une page 403
     */
    protected function requireShopPressingAccess(bool $json = true): bool
    {
        $accessService = new \App\Services\RechargeAccessService();
        $allowed = $accessService->canAccessPressing();

        if (!$allowed) {
            if ($json) {
                self::status(403)->json([
                    'success' => false,
                    'error'   => 'pressing_not_allowed',
                    'message' => 'La fonctionnalité Pressing n\'est pas activée pour votre boutique.',
                ]);
            } else {
                http_response_code(403);
                require_once dirname(__DIR__) . DIRECTORY_SEPARATOR . 'views' . DIRECTORY_SEPARATOR . '403.php';
                exit;
            }
            return false;
        }

        return true;
    }

    /**
     * Vérifie que l'utilisateur courant a accès à la page /caisse (POS).
     *
     * Le droit est porté par `utilisateurs.acces_caisse` (migration
     * 2026_11_14_user_caisse_access.sql) et se règle par utilisateur depuis
     * /hotel/roles. Il est indépendant du rôle applicatif (`role`) et du
     * rôle métier hôtel (`hotel_role`).
     *
     * Ce contrôle ne concerne QUE le point de vente :
     *   - les recharges Élec/Eau passent par la même route d'écriture de
     *     vente mais ne sont PAS concernées (le caller filtre via
     *     `providerService`, cf. SaleController::create()) ;
     *   - le module Hôtel reste accessible à tous les rôles hôtel.
     *
     * Règles :
     *   - super_admin / admin → toujours autorisé (cf. User::canAccessCaisse()).
     *   - utilisateur inconnu / erreur SQL → refusé (fail-closed).
     *
     * @param bool $json true pour une réponse JSON (API), false pour une page 403
     */
    protected function isCaisseAccessAllowed(): bool
    {
        return $this->isModuleAccessAllowed('caisse');
    }

    /**
     * Vérifie que l'utilisateur courant a accès à un module du POS.
     *
     * Chaque module est porté par une colonne `utilisateurs.acces_*` et se
     * règle par utilisateur depuis /hotel/roles. Ces droits sont
     * INDÉPENDANTS du rôle applicatif, du rôle métier hôtel et des feature
     * flags API OSAT : ils s'AJOUTENT aux contrôles existants.
     *
     * @param string $module Clé de \App\Models\User::MODULES_ACCES.
     */
    protected function isModuleAccessAllowed(string $module): bool
    {
        try {
            return (new \App\Models\User())->canAccessModule($module);
        } catch (\Throwable $e) {
            error_log('isModuleAccessAllowed error: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Garde d'accès à un module du POS pour les routes API.
     *
     * Renvoie false + 403 si l'utilisateur n'a pas le droit d'accéder au
     * module. L'authentification reste obligatoire (requireAuth()).
     *
     * @param string $module Clé de \App\Models\User::MODULES_ACCES.
     * @param bool   $json   true pour une réponse JSON (API), false pour une page 403
     */
    protected function requireModuleAccess(string $module, bool $json = true): bool
    {
        if ($this->isModuleAccessAllowed($module)) {
            return true;
        }

        if ($json) {
            self::status(403)->json([
                'success' => false,
                'error'   => $module . '_not_allowed',
                'message' => 'Ce module n\'est pas accessible avec votre rôle.',
            ]);
        } else {
            http_response_code(403);
            require_once dirname(__DIR__) . DIRECTORY_SEPARATOR . 'views' . DIRECTORY_SEPARATOR . '403.php';
            exit;
        }
        return false;
    }

    /**
     * Garde d'accès au point de vente pour les routes API.
     *
     * Renvoie false + 403 si l'utilisateur n'a pas le droit d'utiliser la
     * caisse. L'authentification reste obligatoire (requireAuth()).
     *
     * @param bool $json true pour une réponse JSON (API), false pour une page 403
     */
    protected function requireCaisseAccess(bool $json = true): bool
    {
        return $this->requireModuleAccess('caisse', $json);
    }

    // ── Politique d'accès aux pages (super_admin) ────────────────────────

    /**
     * Vérifie que l'utilisateur courant peut accéder à une page selon la
     * politique du super_admin.
     *
     * La politique est RESTRICTIVE : elle s'ajoute aux contrôles existants
     * et ne peut qu'en retirer l'accès.
     *
     * @param string $pageKey Clé du catalogue \App\Services\UserPageAccessService.
     */
    protected function isPagePolicyAllowed(string $pageKey): bool
    {
        $userId = (int)($_SESSION['user_id'] ?? 0);
        if ($userId <= 0) {
            return true; // L'authentification reste prioritaire.
        }

        try {
            return (new \App\Services\UserPageAccessService())->isPageAllowedForUser($pageKey, $userId);
        } catch (\Throwable $e) {
            error_log('isPagePolicyAllowed error: ' . $e->getMessage());
            return false; // Fail-closed.
        }
    }

    /**
     * Garde de la politique d'accès aux pages.
     *
     * Renvoie false + 403 si la page est masquée pour l'utilisateur courant.
     *
     * @param string $pageKey Clé du catalogue \App\Services\UserPageAccessService.
     * @param bool   $json   true pour une réponse JSON (API), false pour une page 403
     */
    protected function requirePagePolicy(string $pageKey, bool $json = true): bool
    {
        if ($this->isPagePolicyAllowed($pageKey)) {
            return true;
        }

        if ($json) {
            self::status(403)->json([
                'success' => false,
                'error'   => 'page_not_allowed',
                'message' => "Cette page n'est pas accessible pour votre rôle.",
            ]);
        } else {
            http_response_code(403);
            require_once dirname(__DIR__) . DIRECTORY_SEPARATOR . 'views' . DIRECTORY_SEPARATOR . '403.php';
            exit;
        }

        return false;
    }

    /**
     * Garde pour une lecture partagée par plusieurs pages.
     *
     * L'accès est autorisé si AU MOINS UNE des pages lectrices est
     * accessible : une API partagée ne doit pas être bloquée par la
     * politique d'une seule page.
     *
     * @param string $pageKeysCsv Liste de clés séparées par `|`.
     * @param bool   $json       true pour une réponse JSON (API), false pour une page 403
     */
    protected function requireSharedReadPolicy(string $pageKeysCsv, bool $json = true): bool
    {
        foreach (explode('|', $pageKeysCsv) as $pageKey) {
            $pageKey = trim($pageKey);
            if ($pageKey === '') {
                continue;
            }
            if ($this->isPagePolicyAllowed($pageKey)) {
                return true;
            }
        }

        if ($json) {
            self::status(403)->json([
                'success' => false,
                'error'   => 'page_not_allowed',
                'message' => "Cette page n'est pas accessible pour votre rôle.",
            ]);
        } else {
            http_response_code(403);
            require_once dirname(__DIR__) . DIRECTORY_SEPARATOR . 'views' . DIRECTORY_SEPARATOR . '403.php';
            exit;
        }

        return false;
    }

    /**
     * Résout la clé de page d'une mutation partagée.
     *
     * Le contexte métier (ex. `caisse` vs `recharges`) détermine la page à
     * vérifier. Un contexte absent ou ambigu est REFUSE (fail-closed) : sans
     * contexte, on ne peut pas garantir que la page réellement utilisée est
     * autorisée.
     *
     * @param array<string, mixed> $context
     */
    protected function resolveSharedWritePageKey(string $pageKey, array $context): ?string
    {
        if (!\App\Services\UserPageAccessService::isKnownPage($pageKey)) {
            return null;
        }

        // Le contexte doit confirmer la page : sans lui, on refuse.
        $resolved = $context['page_key'] ?? null;
        if (!is_string($resolved) || $resolved === '' || $resolved !== $pageKey) {
            return null;
        }

        return $resolved;
    }

    /**
     * Vérifie que le shop de l'utilisateur connecté est toujours actif.
     * - super_admin : jamais bloqué (pas lié à un shop)
     * - utilisateur sans shop_id : autorisé
     * - shop désactivé (actif = 0) ou introuvable : session détruite + 403
     *
     * Utilisé par les pages et API pour la « réhydratation » :
     * même un utilisateur déjà connecté est expulsé si son shop est désactivé.
     */
    protected function requireActiveShop(): bool
    {
        // super_admin n'est rattaché à aucun shop, jamais bloqué
        if ($this->isSuperAdmin()) {
            return true;
        }

        $shopId = $this->getShopId();
        if (!$shopId) {
            return true; // pas de shop rattaché => pas de restriction
        }

        try {
            $shop = (new \App\Models\Shop())->findById($shopId);
        } catch (\Exception $e) {
            error_log('requireActiveShop error: ' . $e->getMessage());
            return true; // en cas d'erreur SQL, on n'expulse pas l'utilisateur
        }

        if (!$shop || empty($shop['actif'])) {
            $userId = $_SESSION['user_id'] ?? null;
            // audit avant destruction
            try {
                $audit = new \App\Models\AuditLog();
                $audit->log($userId, $shopId, 'logout', 'shop_disabled', $shopId, [
                    'reason' => 'shop_desactive_pendant_session',
                ]);
            } catch (\Exception $e) {
                // pas bloquant
            }

            // destruction de session puis 403
            $_SESSION = [];
            if (session_status() === PHP_SESSION_ACTIVE) {
                session_destroy();
            }

            http_response_code(403);
            // Permettre à l'appelant API de recevoir du JSON
            $uri = $_SERVER['REQUEST_URI'] ?? '';
            if (strpos($uri, '/api/') === 0) {
                header('Content-Type: application/json');
                echo json_encode([
                    'success' => false,
                    'error'   => 'shop_disabled',
                    'message' => 'Votre boutique a été désactivée. Contactez votre administrateur.',
                ]);
                exit;
            }
            require_once dirname(__DIR__) . DIRECTORY_SEPARATOR . 'views' . DIRECTORY_SEPARATOR . '403.php';
            exit;
        }

        return true;
    }

    /**
     * Variante "douce" de requireActiveShop().
     * Renvoie false au lieu d'appeler exit() si le shop est désactivé.
     * Le contrôleur appelant peut alors choisir :
     *  - soit répondre avec un message JSON 403 explicite
     *  - soit logger une tentative frauduleuse avant de bloquer
     *
     * Ne détruit PAS la session : l'utilisateur reste connecté pour pouvoir
     * continuer à utiliser le front (le polling JS s'occupera de la déconnexion).
     */
    protected function isShopActive(): bool
    {
        if ($this->isSuperAdmin()) {
            return true;
        }
        $shopId = $this->getShopId();
        if (!$shopId) {
            return true;
        }
        try {
            $shop = (new \App\Models\Shop())->findById($shopId);
        } catch (\Exception $e) {
            error_log('isShopActive error: ' . $e->getMessage());
            return true;
        }
        return $shop && !empty($shop['actif']);
    }

    // ── Audit log helper ────────────────────────────────────────

    protected function logAudit($action, $entity, $entityId = null, $details = null)
    {
        try {
            $audit = new \App\Models\AuditLog();
            $audit->log(
                $_SESSION['user_id'] ?? null,
                $this->getShopId(),
                $action,
                $entity,
                $entityId,
                $details
            );
        } catch (\Exception $e) {
            error_log('Audit log error: ' . $e->getMessage());
        }
    }

    // ── Notification helper ─────────────────────────────────────

    protected function notify($userId, $shopId, $type, $title, $message, $link = null)
    {
        try {
            $notif = new \App\Models\Notification();
            $notif->create($userId, $shopId, $type, $title, $message, $link);
        } catch (\Exception $e) {
            error_log('Notification error: ' . $e->getMessage());
        }
    }

    protected function notifyShopAdmins($shopId, $type, $title, $message, $link = null)
    {
        try {
            $notif = new \App\Models\Notification();
            $notif->notifyShopAdmins($shopId, $type, $title, $message, $link);
        } catch (\Exception $e) {
            error_log('Notification error: ' . $e->getMessage());
        }
    }

    protected function notifySuperAdmins($type, $title, $message, $link = null)
    {
        try {
            $notif = new \App\Models\Notification();
            $notif->notifySuperAdmins($type, $title, $message, $link);
        } catch (\Exception $e) {
            error_log('Notification error: ' . $e->getMessage());
        }
    }

    //public function create()
    //{
    //}
    //public function delete()
    //{
    //}
    //public function index()
    //{
    //}
    //public function update()
    //{
    //}
}
