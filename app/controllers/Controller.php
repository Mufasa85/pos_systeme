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

    protected function sanitaze(string $input): string
    {
        return strip_tags(htmlspecialchars($input));
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
            self::status(403)->json(['error' => 'Non authentifié']);
            return false;
        }
        // Vérifier expiration de session
        if (isset($_SESSION['last_activity']) && (time() - $_SESSION['last_activity']) > self::$SESSION_TIMEOUT) {
            session_destroy();
            self::status(401)->json(['error' => 'Session expirée']);
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
            self::status(403)->json(['error' => 'Accès refusé']);
            return false;
        }
        return true;
    }

    protected function requireSuperAdmin()
    {
        if (!$this->requireAuth()) {
            return false;
        }
        if (!$this->isSuperAdmin()) {
            self::status(403)->json(['error' => 'Accès refusé — super_admin requis']);
            return false;
        }
        return true;
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
