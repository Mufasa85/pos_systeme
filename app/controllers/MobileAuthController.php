<?php

namespace App\Controllers;

use App\Models\MobileToken;
use App\Models\User;
use App\Services\MobileAuthService;

/**
 * MobileAuthController — API d'authentification de l'application mobile.
 *
 * CONTRAT
 * -------
 * POST /api/mobile/auth/login
 *   body : { username, password, device?: { device_name, device_id, platform, app_version } }
 *   → 200 { success:true, token, token_type:"Bearer", expires_at, user, shop, context }
 *   → 200 { success:true, requires_otp:true, challenge_token, channels }  (2FA)
 *   → 401 { success:false, error, message }
 *
 * POST /api/mobile/auth/verify-otp
 *   body : { challenge_token, code }
 *   → 200 { success:true, token, token_type:"Bearer", expires_at, user, shop, context }
 *
 * POST /api/mobile/auth/resend-otp
 *   body : { challenge_token }
 *
 * POST /api/mobile/auth/logout      (Bearer requis) → révoque le token courant
 * GET  /api/mobile/auth/me          (Bearer requis) → contexte user + boutique
 * GET  /api/mobile/auth/devices      (Bearer requis) → appareils connectés
 * POST /api/mobile/auth/revoke-all   (Bearer requis) → déconnecte tous les appareils
 *
 * ⚠️ Ces routes sont HORS politique de page (Router sans policyKey) : elles
 *   都属于 à l'authentification elle-même, pas à une page. Les routes métier
 *    (/api/vente, /api/produits…) restent, elles, soumises à la politique.
 *
 * RATE LIMITING
 * ------------
 * Les tentatives de connexion sont comptées dans la table `login_attempts`
 * (déjà utilisée par le login web) : 5 échecs ⇒ blocage de 15 minutes.
 */
class MobileAuthController extends Controller
{
    /** Nombre max de tentatives de connexion échouées. */
    private const MAX_LOGIN_ATTEMPTS = 5;

    /** Durée du blocage après dépassement du quota (minutes). */
    private const LOCKOUT_MINUTES = 15;

    private $service;

    public function __construct()
    {
        $this->service = new MobileAuthService();
    }

    /**
     * POST /api/mobile/auth/login
     */
    public function login()
    {
        $input = $this->input();

        $username = trim((string)($input['username'] ?? ''));
        $password = (string)($input['password'] ?? '');
        $device   = is_array($input['device'] ?? null) ? $input['device'] : [];

        if ($username === '' || $password === '') {
            $this->status(400)->json([
                'success' => false,
                'error'   => 'missing_credentials',
                'message' => "Nom d'utilisateur et mot de passe requis",
            ]);
            return;
        }

        if ($this->isRateLimited($username)) {
            $this->status(429)->json([
                'success' => false,
                'error'   => 'rate_limited',
                'message' => 'Trop de tentatives. Reessayez dans ' . self::LOCKOUT_MINUTES . ' minutes.',
            ]);
            return;
        }

        $result = $this->service->login($username, $password, $device);

        if (empty($result['ok'])) {
            $this->recordLoginAttempt($username);

            $this->status(401)->json([
                'success' => false,
                'error'   => $result['error'] ?? 'invalid_credentials',
                'message' => $result['message'] ?? 'Identifiants incorrects',
            ]);
            return;
        }

        // 2FA : on ne renvoie PAS de token d'accès, mais un défi OTP.
        if (!empty($result['requires_otp'])) {
            $this->clearLoginAttempts($username);
            // Note : l'envoi du code OTP est deja fait par MobileAuthService::login().

            $this->json([
                'success'         => true,
                'requires_otp'    => true,
                'challenge_token' => $result['challenge_token'],
                'expires_at'      => $result['expires_at'],
                'channels'        => $result['channels'] ?? [],
                'message'         => 'Code de vérification envoyé',
            ]);
            return;
        }

        $this->clearLoginAttempts($username);
        $this->logAudit('mobile_login', 'utilisateur', (int)($result['user']['id'] ?? 0));

        $this->json([
            'success'    => true,
            'token'      => $result['token'],
            'token_type' => 'Bearer',
            'expires_at' => $result['expires_at'],
            'user'       => $result['user'],
            'shop'       => $result['shop'],
            'context'    => $result['context'],
        ]);
    }

    /**
     * POST /api/mobile/auth/verify-otp
     */
    public function verifyOtp()
    {
        $input = $this->input();

        $challengeToken = trim((string)($input['challenge_token'] ?? ''));
        $code           = trim((string)($input['code'] ?? ''));
        $device         = is_array($input['device'] ?? null) ? $input['device'] : [];

        if ($challengeToken === '' || $code === '') {
            $this->status(400)->json([
                'success' => false,
                'error'   => 'missing_parameters',
                'message' => 'Jeton de défi et code requis',
            ]);
            return;
        }

        $result = $this->service->verifyOtp($challengeToken, $code, $device);

        if (empty($result['ok'])) {
            $this->status(401)->json([
                'success' => false,
                'error'   => $result['error'] ?? 'invalid_otp',
                'message' => $result['message'] ?? 'Code invalide',
            ]);
            return;
        }

        $this->logAudit('mobile_login_otp', 'utilisateur', (int)($result['user']['id'] ?? 0));

        $this->json([
            'success'    => true,
            'token'      => $result['token'],
            'token_type' => 'Bearer',
            'expires_at' => $result['expires_at'],
            'user'       => $result['user'],
            'shop'       => $result['shop'],
            'context'    => $result['context'],
        ]);
    }

    /**
     * POST /api/mobile/auth/resend-otp
     */
    public function resendOtp()
    {
        $input = $this->input();

        $challengeToken = trim((string)($input['challenge_token'] ?? ''));
        if ($challengeToken === '') {
            $this->status(400)->json([
                'success' => false,
                'error'   => 'missing_parameters',
                'message' => 'Jeton de défi requis',
            ]);
            return;
        }

        $result = $this->service->resendOtp($challengeToken);

        if (empty($result['ok'])) {
            $this->status(401)->json([
                'success' => false,
                'error'   => $result['error'] ?? 'invalid_challenge',
                'message' => $result['message'] ?? 'Session expirée',
            ]);
            return;
        }

        $this->json([
            'success'  => true,
            'channels' => $result['channels'] ?? [],
            'message'  => $result['message'] ?? 'Code renvoyé',
        ]);
    }

    /**
     * POST /api/mobile/auth/logout — révoque le token courant.
     */
    public function logout()
    {
        $token = $this->service->extractBearerToken();
        if ($token === null) {
            $this->status(401)->json([
                'success' => false,
                'error'   => 'unauthenticated',
                'message' => 'Authentification requise',
            ]);
            return;
        }

        $tokenModel = new MobileToken();
        $row = $tokenModel->findByToken($token);

        if ($row && $row['type'] === MobileToken::TYPE_ACCESS) {
            $tokenModel->revoke((int)$row['id']);
            $this->logAudit('mobile_logout', 'utilisateur', (int)$row['user_id']);
        }

        $this->json([
            'success' => true,
            'message' => 'Déconnexion réussie',
        ]);
    }

    /**
     * GET /api/mobile/auth/me — contexte complet (user + boutique).
     */
    public function me()
    {
        if (!$this->requireAuth()) {
            return;
        }

        $user = (new User())->exist((int)($_SESSION['user_id'] ?? 0));
        if (!$user) {
            $this->status(404)->json([
                'success' => false,
                'error'   => 'user_not_found',
                'message' => 'Utilisateur introuvable',
            ]);
            return;
        }

        $shop = null;
        $shopId = (int)($_SESSION['shop_id'] ?? 0);
        if ($shopId > 0) {
            $shop = (new \App\Models\Shop())->findById($shopId);
        }

        $context = (new \App\Services\CurrentUserService())->getContext();

        $this->json([
            'success' => true,
            'data'    => [
                'user'    => $context['user'],
                'shop'    => $context['shop'],
                'company' => $context['company'],
                'nif'     => $context['nif'],
                'isf'     => $context['isf'],
            ],
        ]);
    }

    /**
     * GET /api/mobile/auth/devices — appareils connectés.
     */
    public function devices()
    {
        if (!$this->requireAuth()) {
            return;
        }

        $tokens = (new MobileToken())->listForUser((int)($_SESSION['user_id'] ?? 0));

        $this->json([
            'success' => true,
            'data'    => $tokens,
        ]);
    }

    /**
     * POST /api/mobile/auth/revoke-all — déconnecte tous les appareils.
     */
    public function revokeAll()
    {
        if (!$this->requireAuth()) {
            return;
        }

        $userId = (int)($_SESSION['user_id'] ?? 0);
        (new MobileToken())->revokeAllForUser($userId);

        $this->logAudit('mobile_revoke_all', 'utilisateur', $userId);

        $this->json([
            'success' => true,
            'message' => 'Tous les appareils ont été déconnectés',
        ]);
    }


    // ── Helpers ───────────────────────────────────────────────────────────

    /**
     * Lit le corps de la requête (JSON ou form-urlencoded).
     */
    private function input(): array
    {
        $raw = (string)file_get_contents('php://input');
        $json = $raw !== '' ? json_decode($raw, true) : null;

        if (is_array($json) && !empty($json)) {
            return $json;
        }

        return is_array($_POST) ? $_POST : [];
    }

    /**
     * Le compte est-il bloqué pour excès de tentatives ?
     */
    private function isRateLimited(string $username): bool
    {
        try {
            $db = \App\Core\Database::getInstance();
            $result = $db->fetch(
                'SELECT COUNT(*) as attempts FROM login_attempts
                 WHERE username = ? AND attempted_at > DATE_SUB(NOW(), INTERVAL ? MINUTE)',
                [$username, self::LOCKOUT_MINUTES]
            );
        } catch (\Throwable $e) {
            // Erreur SQL : on n'enferme pas l'utilisateur (fail-open sur
            // l'infra, fail-closed sur l'authentification elle-même).
            error_log('[MobileAuth] isRateLimited error: ' . $e->getMessage());
            return false;
        }

        return (int)($result['attempts'] ?? 0) >= self::MAX_LOGIN_ATTEMPTS;
    }

    /**
     * Enregistre une tentative de connexion échouée.
     */
    private function recordLoginAttempt(string $username): void
    {
        try {
            $db = \App\Core\Database::getInstance();
            $db->query(
                'INSERT INTO login_attempts (username, ip_address) VALUES (?, ?)',
                [$username, $_SERVER['REMOTE_ADDR'] ?? '']
            );
        } catch (\Throwable $e) {
            error_log('[MobileAuth] recordLoginAttempt error: ' . $e->getMessage());
        }
    }

    /**
     * Efface les tentatives après une connexion réussie.
     */
    private function clearLoginAttempts(string $username): void
    {
        try {
            $db = \App\Core\Database::getInstance();
            $db->execute('DELETE FROM login_attempts WHERE username = ?', [$username]);
        } catch (\Throwable $e) {
            error_log('[MobileAuth] clearLoginAttempts error: ' . $e->getMessage());
        }
    }
}
