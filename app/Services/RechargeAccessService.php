<?php

namespace App\Services;

use App\Models\Settings;

/**
 * Service de vérification d'accès à la page /recharges
 * via une API externe configurable.
 *
 * Le résultat est mis en cache en session avec un TTL configurable
 * pour éviter d'appeler l'API externe à chaque requête.
 */
class RechargeAccessService
{
    /** Durée de vie du cache en session (secondes) — 5 minutes par défaut */
    private int $cacheTtl;

    /** URL de l'API externe de vérification d'accès */
    private string $apiUrl;

    /** Clé de session pour le cache */
    private const SESSION_KEY = 'recharge_access_cache';

    public function __construct(?int $cacheTtl = null)
    {
        $this->cacheTtl = $cacheTtl ?? 300; // 5 minutes

        // Charger l'URL depuis les paramètres (Settings) ou utiliser une valeur par défaut
        $this->apiUrl = $this->loadApiUrl();
    }

    /**
     * Vérifie si l'utilisateur actuel a accès à la page /recharges.
     * Utilise le cache session si disponible et encore valide.
     */
    public function canAccess(): bool
    {
        $this->ensureSessionStarted();

        // Vérifier le cache session
        $cached = $_SESSION[self::SESSION_KEY] ?? null;
        if ($cached !== null && isset($cached['expires_at']) && $cached['expires_at'] > time()) {
            return (bool) $cached['granted'];
        }

        // Pas de cache valide → interroger l'API externe
        return $this->checkFromApi();
    }

    /**
     * Réinitialise le cache pour forcer une nouvelle vérification
     * au prochain appel de canAccess().
     */
    public static function resetCache(): void
    {
        if (session_status() === PHP_SESSION_ACTIVE) {
            unset($_SESSION[self::SESSION_KEY]);
        }
    }

    /**
     * Pré-charge le cache d'accès sans bloquer si déjà en cache.
     * Utile pour le warmup dans le layout sans pénalité si déjà connu.
     */
    public function warmupCache(): void
    {
        $this->ensureSessionStarted();

        $cached = $_SESSION[self::SESSION_KEY] ?? null;
        if ($cached !== null && isset($cached['expires_at']) && $cached['expires_at'] > time()) {
            return; // Cache encore valide, nothing to do
        }

        $this->checkFromApi();
    }

    /**
     * Interroge l'API externe et met en cache le résultat.
     */
    private function checkFromApi(): bool
    {
        $userId = $_SESSION['user_id'] ?? null;
        $shopId = $_SESSION['shop_id'] ?? null;
        $role = $_SESSION['role'] ?? 'vendeur';

        $granted = $this->callExternalApi($userId, $shopId, $role);

        // Mettre en cache
        $_SESSION[self::SESSION_KEY] = [
            'granted' => $granted,
            'expires_at' => time() + $this->cacheTtl,
            'checked_at' => time(),
        ];

        return $granted;
    }

    /**
     * Appelle l'API externe configurée pour vérifier les droits.
     *
     * @param int|null $userId
     * @param int|null $shopId
     * @param string   $role
     * @return bool true si l'accès est autorisé, false sinon
     */
    private function callExternalApi(?int $userId, ?int $shopId, string $role): bool
    {
        // Si aucune URL configurée, on refuse l'accès par défaut (sécurité)
        if (empty($this->apiUrl)) {
            error_log('[RechargeAccessService] Aucune API URL configurée — accès refusé par défaut');
            return false;
        }

        $payload = [
            'user_id' => $userId,
            'shop_id' => $shopId,
            'role' => $role,
        ];

        try {
            $ch = curl_init();
            curl_setopt_array($ch, [
                CURLOPT_URL => $this->apiUrl,
                CURLOPT_POST => true,
                CURLOPT_POSTFIELDS => json_encode($payload),
                CURLOPT_HTTPHEADER => [
                    'Content-Type: application/json',
                    'Accept: application/json',
                ],
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_TIMEOUT => 10,
                CURLOPT_CONNECTTIMEOUT => 5,
                CURLOPT_SSL_VERIFYPEER => false,
            ]);

            $response = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $curlError = curl_error($ch);
            curl_close($ch);

            if ($response === false || empty($response)) {
                error_log('[RechargeAccessService] Erreur connexion API: ' . $curlError . ' (HTTP ' . $httpCode . ')');
                return false;
            }

            $data = json_decode($response, true);
            if (json_last_error() !== JSON_ERROR_NONE) {
                error_log('[RechargeAccessService] Réponse API non-JSON reçue');
                return false;
            }

            // Supporte plusieurs formats de réponse
            if (isset($data['allowed'])) {
                return (bool) $data['allowed'];
            }
            if (isset($data['data']['allowed'])) {
                return (bool) $data['data']['allowed'];
            }
            if (isset($data['access'])) {
                return (bool) $data['access'];
            }

            // Si le format est inconnu mais HTTP 200, on autorise
            if ($httpCode === 200) {
                error_log('[RechargeAccessService] Format de réponse API non reconnu, accès autorisé par défaut (HTTP 200)');
                return true;
            }

            error_log('[RechargeAccessService] Réponse API inattendue (HTTP ' . $httpCode . ')');
            return false;

        } catch (\Exception $e) {
            error_log('[RechargeAccessService] Exception: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Charge l'URL de l'API depuis les paramètres système.
     * Cherche d'abord dans la table settings (clé 'recharge_access_api_url'),
     * puis dans une variable d'environnement.
     */
    private function loadApiUrl(): string
    {
        // 1. Essayer depuis la table settings
        try {
            $settingsModel = new Settings();
            $url = $settingsModel->get('recharge_access_api_url');
            if (!empty($url)) {
                return $url;
            }
        } catch (\Exception $e) {
            // La table n'existe peut-être pas encore, ignorer
        }

        // 2. Essayer depuis une variable d'environnement
        $envUrl = getenv('RECHARGE_ACCESS_API_URL');
        if (!empty($envUrl)) {
            return $envUrl;
        }

        // 3. Valeur par défaut (à configurer via l'interface Settings plus tard)
        return '';
    }

    /**
     * Assure que la session est démarrée.
     */
    private function ensureSessionStarted(): void
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
    }
}