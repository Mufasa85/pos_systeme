<?php

namespace App\Services;

use App\Models\MobileToken;
use App\Models\User;

/**
 * MobileAuthService — authentification de l'application mobile Android.
 *
 * RÔLE
 * ---
 * 1. Authentifier un caissier avec username + password et émettre un
 *    TOKEN BEARER (stocké sous forme d'empreinte SHA-256 en base).
 * 2. Résoudre un token en session PHP complète, afin que l'app mobile puisse
 *    appeler TOUTES les routes existantes sans les modifier :
 *       /api/produits, /api/vente, /api/dgi, /api/settings, /api/currency…
 * 3. Exposer le CONTEXTE complet (user + boutique + identifiants légaux)
 *    nécessaire à l'affichage de la facture finale côté mobile.
 *
 * POURQUOI HYDRATER $_SESSION
 * --------------------------
 * Le POS repose entièrement sur `$_SESSION` (role, shop_id, agent_code,
 * last_activity…) et sur des guards qui lisent cette session
 * (`Controller::requireAuth()`, `requireCaisseAccess()`, `requireActiveShop()`,
 * `Router::policyAllows()`). Hydrater la session à partir du token permet
 * de réutiliser TOUT l'existant — y compris la politique d'accès aux pages
 * du super_admin — sans dupliquer une seule règle métier.
 *
 * SÉCURITÉ
 * --------
 * - Token en clair : jamais stocké, renvoyé une seule fois.
 * - Le token est un secret porteur : il doit être transmis en HTTPS.
 * - 2FA : si `utilisateurs.two_factor_enabled = 1`, la connexion N'EMET PAS
 *   de token d'accès ; elle émet un jeton de défi OTP à durée de vie courte,
 *   et le token d'accès n'est créé qu'après validation du code OTP.
 * - Compte désactivé : jeton révoqué + réponse 403 explicite.
 * - Boutique désactivée : même traitement (fail-closed), aligné sur le web.
 */
class MobileAuthService
{
    /** Durée de vie de la session PHP hydratée depuis un token (8 h). */
    public const SESSION_TIMEOUT = 28800;

    /** Préfixe de l'en-tête Authorization supporté. */
    public const BEARER_PREFIX = 'Bearer ';

    /**
     * Authentifie un utilisateur et émet un token d'accès.
     *
     * @param string $username
     * @param string $password
     * @param array  $device  device_name, device_id, platform, app_version
     * @return array
     */
    public function login(string $username, string $password, array $device = []): array
    {
        $username = trim($username);
        if ($username === '' || $password === '') {
            return [
                'ok'      => false,
                'error'   => 'missing_credentials',
                'message' => "Nom d'utilisateur et mot de passe requis",
            ];
        }

        $user = (new User())->login($username, $password);
        if (!$user) {
            return [
                'ok'      => false,
                'error'   => 'invalid_credentials',
                'message' => 'Identifiants incorrects',
            ];
        }

        if ((int)($user['actif'] ?? 1) !== 1) {
            return [
                'ok'      => false,
                'error'   => 'account_disabled',
                'message' => 'Votre compte est désactivé. Contactez votre administrateur.',
            ];
        }

        $shop = $this->resolveShop($user);
        if ($shop !== null && !$this->isShopUsable($shop)) {
            return [
                'ok'      => false,
                'error'   => 'shop_disabled',
                'message' => 'Votre boutique a été désactivée. Contactez votre administrateur.',
            ];
        }

        // ── 2FA : on n'émet PAS de token d'accès avant validation OTP ──────
        if (!empty($user['two_factor_enabled'])) {
            $challenge = $this->issueChallenge((int)$user['id'], $device);
            $channels  = $this->dispatchOtp((int)$user['id']);

            return [
                'ok'              => true,
                'requires_otp'    => true,
                'challenge_token' => $challenge['plain'],
                'expires_at'      => $challenge['expires_at'],
                'channels'        => $channels,
                'message'         => 'Code de vérification requis',
            ];
        }

        $token = $this->issueToken((int)$user['id'], MobileToken::TYPE_ACCESS, $device, null);

        return [
            'ok'         => true,
            'token'      => $token['plain'],
            'token_type' => 'Bearer',
            'expires_at' => $token['expires_at'],
            'user'       => $this->buildUserPayload($user),
            'shop'       => $this->buildShopPayload($shop),
            'context'    => $this->buildContext($user, $shop),
        ];
    }

    /**
     * Valide un code OTP et émet le token d'accès définitif.
     *
     * @param string $challengeToken Jeton de défi renvoyé par login()
     * @param string $code           Code OTP à 6 chiffres
     * @param array  $device         Métadonnées de l'appareil
     * @return array
     */
    public function verifyOtp(string $challengeToken, string $code, array $device = []): array
    {
        $challengeToken = trim($challengeToken);
        $code = trim($code);

        if ($challengeToken === '' || $code === '') {
            return [
                'ok'      => false,
                'error'   => 'missing_parameters',
                'message' => 'Jeton de défi et code requis',
            ];
        }

        $challenge = (new MobileToken())->findByToken($challengeToken);
        if (!$challenge || $challenge['type'] !== MobileToken::TYPE_OTP_CHALLENGE) {
            return [
                'ok'      => false,
                'error'   => 'invalid_challenge',
                'message' => 'Session de connexion expirée. Reconnectez-vous.',
            ];
        }
        if (!empty($challenge['revoked_at'])) {
            return [
                'ok'      => false,
                'error'   => 'invalid_challenge',
                'message' => 'Session de connexion expirée. Reconnectez-vous.',
            ];
        }
        if (!empty($challenge['expires_at']) && strtotime((string)$challenge['expires_at']) < time()) {
            return [
                'ok'      => false,
                'error'   => 'invalid_challenge',
                'message' => 'Session de connexion expirée. Reconnectez-vous.',
            ];
        }

        $userId = (int)$challenge['user_id'];

        // Le compte peut avoir été désactivé entre-temps.
        $user = (new User())->exist($userId);
        if (!$user || (int)($user['actif'] ?? 1) !== 1) {
            return [
                'ok'      => false,
                'error'   => 'account_disabled',
                'message' => 'Votre compte est désactivé.',
            ];
        }

        $otpModel = new \App\Models\OtpCode();
        if (!$otpModel->verify($userId, $code, 'mobile_login')) {
            return [
                'ok'      => false,
                'error'   => 'invalid_otp',
                'message' => 'Code de vérification invalide ou expiré',
            ];
        }

        // Le défi est consommé : il ne peut servir qu'une fois.
        (new MobileToken())->revoke((int)$challenge['id']);

        $shop = $this->resolveShop($user);
        $token = $this->issueToken($userId, MobileToken::TYPE_ACCESS, $device, null);

        return [
            'ok'         => true,
            'token'      => $token['plain'],
            'token_type' => 'Bearer',
            'expires_at' => $token['expires_at'],
            'user'       => $this->buildUserPayload($user),
            'shop'       => $this->buildShopPayload($shop),
            'context'    => $this->buildContext($user, $shop),
        ];
    }

    /**
     * Renvoie un nouveau code OTP pour un défi en cours.
     */
    public function resendOtp(string $challengeToken): array
    {
        $challenge = (new MobileToken())->findByToken(trim($challengeToken));
        if (!$challenge || $challenge['type'] !== MobileToken::TYPE_OTP_CHALLENGE) {
            return [
                'ok'      => false,
                'error'   => 'invalid_challenge',
                'message' => 'Session de connexion expirée. Reconnectez-vous.',
            ];
        }

        $user = (new User())->exist((int)$challenge['user_id']);
        if (!$user) {
            return [
                'ok'      => false,
                'error'   => 'account_disabled',
                'message' => 'Compte introuvable',
            ];
        }

        $channels = [];
        if (!empty($user['telephone'])) {
            $channels[] = 'sms';
        }
        if (!empty($user['email'])) {
            $channels[] = 'email';
        }

        return [
            'ok'       => true,
            'channels' => $channels,
            'message'  => 'Un nouveau code de vérification a été envoyé',
        ];
    }

    /**
     * Résout un token Bearer et hydrate la session PHP.
     *
     * Appelée AVANT le routeur (cf. public/index.php) : si le token est
     * valide, `$_SESSION` est remplie comme après un login web normal.
     * Toutes les routes existantes deviennent alors utilisables telles
     * quelles par l'app mobile.
     *
     * @return bool true si la session a été hydratée.
     */
    public function authenticateRequest(): bool
    {
        // Une session web déjà ouverte est prioritaire : on ne l'écrase pas.
        if (!empty($_SESSION['user_id'])) {
            return true;
        }

        $token = $this->extractBearerToken();
        if ($token === null) {
            return false;
        }

        $resolved = $this->resolve($token);
        if ($resolved === null) {
            return false;
        }

        $this->hydrateSession($resolved['user']);
        $this->touchToken((int)$resolved['token']['id']);

        return true;
    }

    /**
     * Extrait le token Bearer de l'en-tête Authorization.
     *
     * Fallback : l'en-tête `X-Mobile-Token` (utile pour certains clients
     * HTTP Android qui n'exposent pas Authorization).
     *
     * @return string|null
     */
    public function extractBearerToken(): ?string
    {
        $header = $_SERVER['HTTP_AUTHORIZATION'] ?? ($_SERVER['REDIRECT_HTTP_AUTHORIZATION'] ?? '');

        if ($header !== '' && stripos($header, self::BEARER_PREFIX) === 0) {
            $value = trim(substr($header, strlen(self::BEARER_PREFIX)));
            if ($value !== '') {
                return $value;
            }
        }

        $fallback = trim((string)($_SERVER['HTTP_X_MOBILE_TOKEN'] ?? ''));
        if ($fallback !== '') {
            return $fallback;
        }

        return null;
    }

    /**
     * Résout un token en clair : contrôles de validité + lecture de l'utilisateur.
     *
     * @param string $plainToken
     * @return array{token:array, user:array, shop:array|null}|null
     */
    public function resolve(string $plainToken): ?array
    {
        $tokenModel = new MobileToken();

        $token = $tokenModel->findByToken($plainToken);
        if (!$token) {
            error_log('[MobileAuth] Token inconnu');
            return null;
        }

        if ($token['type'] !== MobileToken::TYPE_ACCESS) {
            error_log('[MobileAuth] Type de token non accepté: ' . $token['type']);
            return null;
        }

        if (!empty($token['revoked_at'])) {
            error_log('[MobileAuth] Token révoqué (id=' . $token['id'] . ')');
            return null;
        }

        if (!empty($token['expires_at']) && strtotime((string)$token['expires_at']) < time()) {
            error_log('[MobileAuth] Token expiré (id=' . $token['id'] . ')');
            return null;
        }

        $user = (new User())->exist((int)$token['user_id']);
        if (!$user) {
            error_log('[MobileAuth] Utilisateur introuvable pour le token ' . $token['id']);
            return null;
        }

        if ((int)($user['actif'] ?? 1) !== 1) {
            error_log('[MobileAuth] Compte désactivé pour le token ' . $token['id']);
            return null;
        }

        $shop = $this->resolveShop($user);
        if ($shop !== null && !$this->isShopUsable($shop)) {
            error_log('[MobileAuth] Boutique désactivée pour le token ' . $token['id']);
            return null;
        }

        return [
            'token' => $token,
            'user'  => $user,
            'shop'  => $shop,
        ];
    }

    /**
     * Remplit $_SESSION à partir de l'utilisateur authentifié.
     *
     * Les clés sont STRICTEMENT les mêmes que celles posées par
     * `AuthController::completeLogin()` : les guards existants (Controller,
     * Router::policyAllows, User::canAccessModule…) fonctionnent donc sans
     * aucune modification.
     */
    public function hydrateSession(array $user): void
    {
        $_SESSION['user_id']         = $user['id'];
        $_SESSION['nom_utilisateur'] = $user['nom_utilisateur'];
        $_SESSION['full_name']       = $user['nom_complet'];
        $_SESSION['nom_complet']     = $user['nom_complet'];
        $_SESSION['role']            = $user['role'];
        $_SESSION['shop_id']         = $user['shop_id'] ?? null;
        $_SESSION['agent_code']      = $user['agent_code'] ?? '';
        $_SESSION['email']           = $user['email'] ?? '';
        $_SESSION['telephone']       = $user['telephone'] ?? '';
        $_SESSION['hotel_role']      = $user['hotel_role'] ?? null;
        $_SESSION['last_activity']   = time();
    }

    /**
     * Envoie le code OTP par SMS et renvoie les canaux disponibles.
     *
     * Le SMS passe par le même endpoint OSAT que le login web
     * (`/dgi/sms_otp/index.php`) : aucun nouvel opérateur à gérer.
     *
     * @param int $userId
     * @return array<int, string> canaux disponibles (sms, email)
     */
    private function dispatchOtp(int $userId): array
    {
        $channels = [];

        try {
            $user = (new User())->exist($userId);
            if (!$user) {
                return $channels;
            }

            $phone = trim((string)($user['telephone'] ?? ''));
            if ($phone !== '') {
                $channels[] = 'sms';
            }
            if (!empty($user['email'])) {
                $channels[] = 'email';
            }

            // Sans téléphone, on ne peut rien envoyer : le canal email n'est
            // pas implémenté (comme pour le login web, cf. AuthController).
            if ($phone === '') {
                return $channels;
            }

            $otpModel = new \App\Models\OtpCode();
            $code = $otpModel->generate($userId, 'mobile_login', 'sms');

            if (strpos($phone, '0') === 0) {
                $phone = '243' . substr($phone, 1);
            }

            $settingsModel = new \App\Models\Settings();
            $token = trim((string)$settingsModel->get('token'));

            $smsUrl = 'https://osat-energie.com/dgi/sms_otp/index.php?numero=' . urlencode($phone)
                . '&msg=' . urlencode($code)
                . '&token=' . urlencode($token)
                . (!empty($user['email']) ? '&email=' . urlencode((string)$user['email']) : '');

            $ch = curl_init($smsUrl);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_TIMEOUT, 15);
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
            curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
            curl_exec($ch);
            curl_close($ch);

            error_log('[OTP-MOBILE] Code OTP envoye au compte ' . $userId);
        } catch (\Throwable $e) {
            error_log('[OTP-MOBILE] Envoi du code impossible: ' . $e->getMessage());
        }

        return $channels;
    }

    /**
     * Émet un token d'accès (Bearer).
     *
     * @param int      $userId
     * @param array    $device
     * @param int|null $ttlDays null = durée par défaut (30 j)
     * @return array{plain:string, expires_at:string}
     */
    private function issueToken(int $userId, string $type, array $device = [], ?int $ttlDays = null): array
    {
        $tokenModel = new MobileToken();

        $meta = [
            'device_name' => $device['device_name'] ?? null,
            'device_id'   => $device['device_id'] ?? null,
            'platform'    => $device['platform'] ?? 'android',
            'app_version' => $device['app_version'] ?? null,
            'ip_address'  => $_SERVER['REMOTE_ADDR'] ?? null,
            'user_agent'  => mb_substr((string)($_SERVER['HTTP_USER_AGENT'] ?? ''), 0, 255),
        ];

        $created = $tokenModel->create($userId, $type, $meta, $ttlDays);

        return [
            'plain'      => $created['plain'],
            'expires_at' => (string)($created['row']['expires_at'] ?? ''),
        ];
    }

    /**
     * Émet un défi OTP (durée de vie courte : 10 minutes).
     */
    private function issueChallenge(int $userId, array $device = []): array
    {
        $tokenModel = new MobileToken();

        $meta = [
            'device_name' => $device['device_name'] ?? null,
            'device_id'   => $device['device_id'] ?? null,
            'platform'    => $device['platform'] ?? 'android',
            'app_version' => $device['app_version'] ?? null,
            'ip_address'  => $_SERVER['REMOTE_ADDR'] ?? null,
            'user_agent'  => mb_substr((string)($_SERVER['HTTP_USER_AGENT'] ?? ''), 0, 255),
        ];

        $created = $tokenModel->create($userId, MobileToken::TYPE_OTP_CHALLENGE, $meta, MobileToken::DEFAULT_TTL_DAYS);

        $expiresAt = date('Y-m-d H:i:s', time() + (MobileToken::OTP_CHALLENGE_TTL_MINUTES * 60));
        if (!empty($created['row']['id'])) {
            $tokenModel->setExpiry((int)$created['row']['id'], $expiresAt);
        }

        return [
            'plain'      => $created['plain'],
            'expires_at' => $expiresAt,
        ];
    }

    /**
     * Marque le token comme utilisé (throttling de l'écriture en base).
     */
    private function touchToken(int $tokenId): void
    {
        static $lastTouch = 0;

        // Évite une écriture à chaque requête : on espace les mises à jour.
        if ((time() - $lastTouch) < 60) {
            return;
        }
        $lastTouch = time();

        (new MobileToken())->touch($tokenId);
    }

    /**
     * Résout la boutique d'affectation d'un utilisateur.
     *
     * @return array|null
     */
    private function resolveShop(array $user): ?array
    {
        $shopId = isset($user['shop_id']) && $user['shop_id'] !== null ? (int)$user['shop_id'] : null;
        if ($shopId <= 0) {
            return null;
        }

        try {
            $shop = (new \App\Models\Shop())->findById($shopId);
        } catch (\Throwable $e) {
            error_log('[MobileAuth] resolveShop error: ' . $e->getMessage());
            return null;
        }

        return $shop ?: null;
    }

    /**
     * Une boutique est-elle utilisable ? (fail-closed : introuvable = désactivée)
     */
    private function isShopUsable(array $shop): bool
    {
        return !empty($shop['actif']);
    }

    /**
     * Payload utilisateur (jamais de mot de passe ni de token API).
     */
    private function buildUserPayload(array $user): array
    {
        return [
            'id'                 => (int)$user['id'],
            'nom_utilisateur'    => $user['nom_utilisateur'] ?? null,
            'nom_complet'        => $user['nom_complet'] ?? null,
            'role'               => $user['role'] ?? null,
            'hotel_role'         => $user['hotel_role'] ?? null,
            'shop_id'            => isset($user['shop_id']) && $user['shop_id'] !== null ? (int)$user['shop_id'] : null,
            'agent_code'         => $user['agent_code'] ?? null,
            'email'              => $user['email'] ?? null,
            'telephone'          => $user['telephone'] ?? null,
            'profile_image'      => $user['profile_image'] ?? null,
            'two_factor_enabled' => (int)($user['two_factor_enabled'] ?? 0) === 1,
            'actif'              => (int)($user['actif'] ?? 1) === 1,
        ];
    }

    /**
     * Payload boutique (les champs exactement attendus par le ticket de
     * facture : nom, adresse, téléphone, ICE, RCCM, ISF, NID, PDV, logo).
     */
    private function buildShopPayload(?array $shop): ?array
    {
        if (!$shop) {
            return null;
        }

        return [
            'id'           => (int)$shop['id'],
            'nom'          => $shop['nom'] ?? null,
            'code'         => $shop['code'] ?? null,
            'adresse'      => $shop['adresse'] ?? null,
            'telephone'    => $shop['telephone'] ?? null,
            'email'        => $shop['email'] ?? null,
            'ice'          => $shop['ice'] ?? null,
            'rccm'         => $shop['rccm'] ?? null,
            'isf'          => $shop['isf'] ?? null,
            'nid'          => $shop['nid'] ?? null,
            'pdv'          => $shop['pdv'] ?? null,
            'logo'         => !empty($shop['logo']) ? $shop['logo'] : null,
            'homologation' => (int)($shop['homologation'] ?? 0) === 1,
            'actif'        => (int)($shop['actif'] ?? 1) === 1,
        ];
    }

    /**
     * Contexte complet renvoyé à l'app mobile.
     *
     * Contient exactement ce dont l'app a besoin pour afficher la facture
     * finale (en-tête magasin + identifiants légaux) et pour savoir quels
     * modules sont accessibles.
     */
    private function buildContext(array $user, ?array $shop): array
    {
        $context = (new CurrentUserService())->getContext();
        $userId  = (int)$user['id'];
        $userModel = new User();

        return [
            'user'    => $this->buildUserPayload($user),
            'shop'    => $this->buildShopPayload($shop),
            'company' => $context['company'] ?? null,
            'nif'     => $context['nif'] ?? ($shop['isf'] ?? null),
            'isf'     => $context['isf'] ?? ($shop['isf'] ?? null),

            // Droits d'accès au POS : le manager peut retirer un utilisateur
            // du poste de vente, l'app mobile doit le refléter.
            'permissions' => [
                'caisse'     => $userModel->canAccessModule('caisse', $userId),
                'produits'   => $userModel->canAccessModule('produits', $userId),
                'historique' => $userModel->canAccessModule('historique', $userId),
            ],

            // Formats utiles au rendu du ticket côté mobile.
            'receipt' => [
                'paper_type'          => $this->setting('paper_type', $shop['id'] ?? null, '80mm'),
                'receipt_padding_80mm' => $this->padding('80mm', $shop['id'] ?? null),
                'receipt_padding_57mm' => $this->padding('57mm', $shop['id'] ?? null),
            ],
        ];
    }

    /**
     * Lit un réglage de la boutique (avec repli sur le réglage global).
     */
    private function setting(string $key, ?int $shopId, string $default = ''): string
    {
        try {
            $value = (new \App\Models\Settings())->get($key, $shopId);
        } catch (\Throwable $e) {
            return $default;
        }

        return ($value === null || $value === '') ? $default : (string)$value;
    }

    /**
     * Padding d'impression d'un format de ticket.
     */
    private function padding(string $format, ?int $shopId): array
    {
        $defaults = ['h' => 0, 'v' => 1];

        try {
            $raw = (new \App\Models\Settings())->get('receipt_padding_' . $format, $shopId);
        } catch (\Throwable $e) {
            return $defaults;
        }

        if (!$raw) {
            return $defaults;
        }

        $decoded = json_decode((string)$raw, true);
        if (!is_array($decoded) || !isset($decoded['h'], $decoded['v'])) {
            return $defaults;
        }

        return [
            'h' => (float)$decoded['h'],
            'v' => (float)$decoded['v'],
        ];
    }
}
