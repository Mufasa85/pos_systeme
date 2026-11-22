<?php

namespace App\Models;

/**
 * MobileToken — jetons d'authentification de l'application mobile Android.
 *
 * CONTEXTE
 * --------
 * L'app mobile (qui rend /caisse) ne manipule pas de cookie de session PHP :
 * elle s'authentifie avec un TOKEN BEARER renvoyé par
 * `POST /api/mobile/auth/login`.
 *
 * Le token est stocké sous forme d'EMPREINTE SHA-256 uniquement : le token en
 * clair n'est jamais écrit en base. Il n'est renvoyé qu'UNE fois, à la
 * création (cf. MobileAuthService::issueToken()).
 *
 * SÉCURITÉ
 * --------
 * - Token en clair = 64 caractères hexadécimaux (random_bytes(32)).
 * - Base = SHA-256 hexadécimal du token en clair (index UNIQUE).
 * - Révocation unitaire possible (colonne `revoked_at`).
 * - Expiration obligatoire (colonne `expires_at`).
 * - Un utilisateur désactivé (`utilisateurs.actif = 0`) invalide ses jetons
 *   : le contrôle est fait à chaque requête (cf. MobileAuthService::resolve()).
 */
class MobileToken
{
    /** Type de jeton : authentification de l'app. */
    public const TYPE_ACCESS = 'access';

    /** Type de jeton : défi OTP (2FA) lié à une tentative de connexion. */
    public const TYPE_OTP_CHALLENGE = 'otp_challenge';

    /** Durée de vie par défaut d'un token d'accès (30 jours). */
    public const DEFAULT_TTL_DAYS = 30;

    /** Durée de vie d'un défi OTP (10 minutes). */
    public const OTP_CHALLENGE_TTL_MINUTES = 10;

    private $db;

    public function __construct()
    {
        $this->db = \App\Core\Database::getInstance();
    }

    /**
     * Calcule l'empreinte d'un token en clair.
     *
     * @param string $plainToken Token en clair (jamais stocké).
     */
    public static function hash(string $plainToken): string
    {
        return hash('sha256', $plainToken);
    }

    /**
     * Crée un jeton et renvoie le couple [token_en_clair, ligne].
     *
     * @param int    $userId
     * @param string $type       self::TYPE_ACCESS | self::TYPE_OTP_CHALLENGE
     * @param array  $meta       device_name, device_id, platform, app_version, ip_address, user_agent
     * @param int    $ttlDays    Durée de vie en jours.
     * @return array{plain:string, row:array}
     */
    public function create(int $userId, string $type = self::TYPE_ACCESS, array $meta = [], ?int $ttlDays = null): array
    {
        $plainToken = bin2hex(random_bytes(32));

        $ttlDays = $ttlDays ?? self::DEFAULT_TTL_DAYS;
        if ($ttlDays < 1) {
            $ttlDays = self::DEFAULT_TTL_DAYS;
        }

        $sql = 'INSERT INTO mobile_tokens
                    (user_id, type, token_hash, device_name, device_id, platform, app_version,
                     ip_address, user_agent, expires_at)
                VALUES
                    (:user_id, :type, :token_hash, :device_name, :device_id, :platform, :app_version,
                     :ip_address, :user_agent, DATE_ADD(NOW(), INTERVAL :ttl_days DAY))';

        $this->db->query($sql, [
            ':user_id'     => $userId,
            ':type'        => $type,
            ':token_hash'  => self::hash($plainToken),
            ':device_name' => $meta['device_name'] ?? null,
            ':device_id'   => $meta['device_id'] ?? null,
            ':platform'    => $meta['platform'] ?? 'android',
            ':app_version' => $meta['app_version'] ?? null,
            ':ip_address'  => $meta['ip_address'] ?? null,
            ':user_agent'  => $meta['user_agent'] ?? null,
            ':ttl_days'    => $ttlDays,
        ]);

        $row = $this->db->fetch(
            'SELECT * FROM mobile_tokens WHERE token_hash = ? LIMIT 1',
            [self::hash($plainToken)]
        );

        return [
            'plain' => $plainToken,
            'row'   => $row ?: [],
        ];
    }

    /**
     * Relit un jeton à partir de son empreinte.
     *
     * La ligne est relue même si le jeton est révoqué ou expiré : l'appelant
     * (MobileAuthService) décide du sort de la requête et peut renvoyer un
     * message d'erreur précis (« révoqué » vs « expiré »).
     *
     * @param string $plainToken
     * @return array|null
     */
    public function findByToken(string $plainToken): ?array
    {
        try {
            $row = $this->db->fetch(
                'SELECT * FROM mobile_tokens WHERE token_hash = ? LIMIT 1',
                [self::hash($plainToken)]
            );
        } catch (\Throwable $e) {
            error_log('[MobileToken] findByToken error: ' . $e->getMessage());
            return null;
        }

        return $row ?: null;
    }

    /**
     * Marque un jeton comme « utilisé » (last_used_at).
     */
    public function touch(int $tokenId): void
    {
        try {
            $this->db->execute(
                'UPDATE mobile_tokens SET last_used_at = NOW() WHERE id = ?',
                [$tokenId]
            );
        } catch (\Throwable $e) {
            error_log('[MobileToken] touch error: ' . $e->getMessage());
        }
    }

    /**
     * Recale l'expiration d'un jeton (défi OTP : 10 minutes).
     */
    public function setExpiry(int $tokenId, string $expiresAt): void
    {
        $this->db->execute(
            'UPDATE mobile_tokens SET expires_at = ? WHERE id = ?',
            [$expiresAt, $tokenId]
        );
    }

    /**
     * Révoque un jeton (déconnexion d'un appareil).
     */
    public function revoke(int $tokenId): void
    {
        $this->db->execute(
            'UPDATE mobile_tokens SET revoked_at = NOW() WHERE id = ? AND revoked_at IS NULL',
            [$tokenId]
        );
    }

    /**
     * Révoque TOUS les jetons d'un utilisateur (déconnexion globale,
     * changement de mot de passe, désactivation du compte).
     */
    public function revokeAllForUser(int $userId, string $type = self::TYPE_ACCESS): void
    {
        $this->db->execute(
            'UPDATE mobile_tokens SET revoked_at = NOW()
             WHERE user_id = ? AND type = ? AND revoked_at IS NULL',
            [$userId, $type]
        );
    }

    /**
     * Liste les appareils connectés d'un utilisateur.
     *
     * @return array<int, array>
     */
    public function listForUser(int $userId, string $type = self::TYPE_ACCESS): array
    {
        return $this->db->fetchAll(
            'SELECT id, type, device_name, device_id, platform, app_version, ip_address,
                    last_used_at, expires_at, revoked_at, created_at
             FROM mobile_tokens
             WHERE user_id = ? AND type = ?
             ORDER BY created_at DESC',
            [$userId, $type]
        );
    }

    /**
     * Purge les jetons expirés ou révoqués (maintenance).
     */
    public function purgeExpired(): void
    {
        $this->db->execute(
            'DELETE FROM mobile_tokens
             WHERE expires_at < NOW() OR (revoked_at IS NOT NULL AND revoked_at < DATE_SUB(NOW(), INTERVAL 30 DAY))'
        );
    }
}
