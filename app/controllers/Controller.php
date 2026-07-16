<?php

namespace App\controllers;

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
        header("Content-Type:application/json");
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
        return true;
    }

    protected function requireAdmin()
    {
        if (!$this->requireAuth()) return false;
        if (!$this->isAdmin()) {
            self::status(403)->json(['error' => 'Accès refusé']);
            return false;
        }
        return true;
    }

    protected function requireSuperAdmin()
    {
        if (!$this->requireAuth()) return false;
        if (!$this->isSuperAdmin()) {
            self::status(403)->json(['error' => 'Accès refusé — super_admin requis']);
            return false;
        }
        return true;
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
            error_log("Audit log error: " . $e->getMessage());
        }
    }

    // ── Notification helper ─────────────────────────────────────

    protected function notify($userId, $shopId, $type, $title, $message, $link = null)
    {
        try {
            $notif = new \App\Models\Notification();
            $notif->create($userId, $shopId, $type, $title, $message, $link);
        } catch (\Exception $e) {
            error_log("Notification error: " . $e->getMessage());
        }
    }

    protected function notifyShopAdmins($shopId, $type, $title, $message, $link = null)
    {
        try {
            $notif = new \App\Models\Notification();
            $notif->notifyShopAdmins($shopId, $type, $title, $message, $link);
        } catch (\Exception $e) {
            error_log("Notification error: " . $e->getMessage());
        }
    }

    protected function notifySuperAdmins($type, $title, $message, $link = null)
    {
        try {
            $notif = new \App\Models\Notification();
            $notif->notifySuperAdmins($type, $title, $message, $link);
        } catch (\Exception $e) {
            error_log("Notification error: " . $e->getMessage());
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
