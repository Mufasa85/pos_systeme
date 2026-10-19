<?php

namespace App\Services;

use App\Models\Shop;
use App\Models\CompanyInfo;

/**
 * Service de vérification d'accès aux fonctionnalités
 * via l'API OSAT : https://osat-energie.com/dgi/option_client/feedback.php?nif={nif}
 *
 * Retourne les flags : snelregideso (recharges), paie (paie).
 * Le résultat est mis en cache en session avec un TTL configurable.
 */
class RechargeAccessService
{
    private int $cacheTtl;
    private string $apiBaseUrl;

    private const SESSION_KEY = 'feature_access_cache';
    private const SESSION_KEY_BC = 'recharge_access_cache'; // backward compat for header.php

    /**
     * Flags par défaut (refus) quand l'API échoue ou ne renvoie rien.
     * Sprint 7 — 2026-10-19 : ajout du flag `hotel` pour le module hôtellerie.
     */
    private const DEFAULT_DENIED_FLAGS = [
        'snelregideso' => false,
        'paie'         => false,
        'hotel'        => false,
    ];

    public function __construct(?int $cacheTtl = null)
    {
        $this->cacheTtl = $cacheTtl ?? 300;
        $this->apiBaseUrl = 'https://osat-energie.com/dgi/option_client/feedback.php';
    }

    /**
     * Vérifie l'accès à /recharges (flag snelregideso)
     */
    public function canAccess(): bool
    {
        return $this->getFeatureFlag('snelregideso');
    }

    /**
     * Vérifie l'accès à /payroll (flag paie)
     */
    public function canAccessPayroll(): bool
    {
        return $this->getFeatureFlag('paie');
    }

    /**
     * Vérifie l'accès à /hotel/* (flag hotel).
     * Sprint 7 — 2026-10-19 : ajouté pour permettre à l'API OSAT de
     * contrôler l'accès au module hôtellerie (comme snelregideso et paie).
     * Si l'API ne renvoie pas le flag `hotel`, on l'accorde par défaut
     * (comportement permissif pour ne pas casser les installations où
     * l'API n'a pas encore été mise à jour). Les utilisateurs peuvent
     * aussi être refusés via requireHotelAccess() (rôle métier) sans
     * dépendre de l'API.
     */
    public function canAccessHotel(): bool
    {
        $flags = $this->getAllFlags();
        if (!array_key_exists('hotel', $flags)) {
            // API n'a pas encore exposé le flag hotel → on autorise
            // (le contrôle d'accès métier via requireHotelAccess suffit
            // pour bloquer les rôles non autorisés).
            return true;
        }
        return !empty($flags['hotel']);
    }
/**
     * Récupère un flag depuis le cache ou depuis l'API.
     */
    /**
     * Récupère un flag depuis le cache ou depuis l'API.
     * Délègue à getAllFlags() pour mutualiser la logique de cache.
     */
    private function getFeatureFlag(string $flagName): bool
    {
        $flags = $this->getAllFlags();
        return !empty($flags[$flagName]);
    }

    /**
     * Récupère TOUS les flags depuis le cache (ou via fetch si expiré).
     * Sprint 7 — 2026-10-19 : factorisé pour canAccessHotel() qui doit
     * distinguer "flag absent" (autoriser par défaut) de "flag=false" (refuser).
     */
    private function getAllFlags(): array
    {
        $this->ensureSessionStarted();
        $cached = $_SESSION[self::SESSION_KEY] ?? null;
        if ($cached !== null && isset($cached['expires_at']) && $cached['expires_at'] > time()) {
            return $cached['flags'] ?? [];
        }
        return $this->fetchFromApi();
    }

    /**
     * Pré-charge le cache sans bloquer si déjà en cache.
     */
    public function warmupCache(): void
    {
        $this->ensureSessionStarted();

        $cached = $_SESSION[self::SESSION_KEY] ?? null;
        if ($cached !== null && isset($cached['expires_at']) && $cached['expires_at'] > time()) {
            return;
        }

        $this->fetchFromApi();
    }

    /**
     * Réinitialise le cache (force une re-vérification au prochain appel).
     */
    public static function resetCache(): void
    {
        if (session_status() === PHP_SESSION_ACTIVE) {
            unset($_SESSION[self::SESSION_KEY]);
            unset($_SESSION[self::SESSION_KEY_BC]);
        }
    }

    /**
     * Appelle l'API externe et met en cache les flags.
     * Retourne un tableau associatif des flags.
     * Sprint 7 — 2026-10-19 : ajout du flag `hotel` (lu depuis le payload
     * `data.hotel` de l'API). Si l'API ne le fournit pas encore, canAccessHotel()
     * le considérera comme "absent" et autorisera l'accès (fail-open par
     * compatibilité avec les déploiements où l'API n'a pas été mise à jour).
     */
    private function fetchFromApi(): array
    {
        $nif = $this->getNif();

        if (empty($nif)) {
            error_log('[FeatureAccessService] Aucun NIF trouvé — accès refusé par défaut');
            $this->storeCache(self::DEFAULT_DENIED_FLAGS);
            return self::DEFAULT_DENIED_FLAGS;
        }

        $url = $this->apiBaseUrl . '?nif=' . urlencode($nif);

        try {
            $ch = curl_init();
            curl_setopt_array($ch, [
                CURLOPT_URL => $url,
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_TIMEOUT => 10,
                CURLOPT_CONNECTTIMEOUT => 5,
                CURLOPT_SSL_VERIFYPEER => false,
                CURLOPT_HTTPHEADER => ['Accept: application/json'],
            ]);

            $response = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $curlError = curl_error($ch);
            curl_close($ch);

            if ($response === false || empty($response)) {
                error_log('[FeatureAccessService] Erreur API: ' . $curlError . ' (HTTP ' . $httpCode . ')');
                $this->storeCache(self::DEFAULT_DENIED_FLAGS);
                return self::DEFAULT_DENIED_FLAGS;
            }

            $data = json_decode($response, true);
            if (json_last_error() !== JSON_ERROR_NONE || empty($data['success'])) {
                error_log('[FeatureAccessService] Réponse API invalide');
                $this->storeCache(self::DEFAULT_DENIED_FLAGS);
                return self::DEFAULT_DENIED_FLAGS;
            }

            $payload = $data['data'] ?? [];
            $flags = [
                'snelregideso' => !empty($payload['snelregideso']),
                'paie'         => !empty($payload['paie']),
                // hotel : si l'API ne le fournit pas, on NE le met PAS dans
                // les flags → canAccessHotel() le verra comme "absent" et
                // autorisera (fail-open pour compat ascendante).
                'hotel'        => array_key_exists('hotel', $payload) ? !empty($payload['hotel']) : null,
            ];

            $this->storeCache($flags);
            return $flags;

        } catch (\Exception $e) {
            error_log('[FeatureAccessService] Exception: ' . $e->getMessage());
            $this->storeCache(self::DEFAULT_DENIED_FLAGS);
            return self::DEFAULT_DENIED_FLAGS;
        }
    }

    /**
     * Stocke les flags en session cache.
     */
    private function storeCache(array $flags): void
    {
        $_SESSION[self::SESSION_KEY] = [
            'flags'      => $flags,
            'expires_at' => time() + $this->cacheTtl,
            'checked_at' => time(),
        ];

        // Backward compat : maintient l'ancienne clé pour le header.php existant
        $_SESSION[self::SESSION_KEY_BC] = [
            'granted'    => $flags['snelregideso'] ?? false,
            'expires_at' => time() + $this->cacheTtl,
            'checked_at' => time(),
        ];
    }

    /**
     * Récupère le NIF depuis company_info (super_admin) ou depuis la boutique connectée.
     *
     * Le super_admin a toujours un shop_id en session (rattachement technique), mais ses
     * informations d'entreprise réelles sont stockées dans company_info : il faut donc
     * prioriser cette table pour son rôle plutôt que le NIF de la boutique.
     */
    private function getNif(): ?string
    {
        $role = $_SESSION['role'] ?? null;

        // 1. Super admin : ses informations sont stockées dans company_info
        if ($role === 'super_admin') {
            try {
                $companyInfo = new CompanyInfo();
                $info = $companyInfo->get();
                if ($info && !empty($info['isf'])) {
                    return $info['isf'];
                }
            } catch (\Exception $e) {
                error_log('[FeatureAccessService] Erreur chargement company_info: ' . $e->getMessage());
            }
        }

        // 2. Essayer depuis la boutique
        $shopId = $_SESSION['shop_id'] ?? null;
        if ($shopId) {
            try {
                $shopModel = new Shop();
                $shop = $shopModel->findById($shopId);
                if ($shop && !empty($shop['isf'])) {
                    return $shop['isf'];
                }
            } catch (\Exception $e) {
                error_log('[FeatureAccessService] Erreur chargement shop: ' . $e->getMessage());
            }
        }

        // 3. Fallback final : company_info (au cas où le rôle ne serait pas super_admin
        // mais qu'aucune boutique valide n'est disponible)
        try {
            $companyInfo = new CompanyInfo();
            $info = $companyInfo->get();
            if ($info && !empty($info['isf'])) {
                return $info['isf'];
            }
        } catch (\Exception $e) {
            error_log('[FeatureAccessService] Erreur chargement company_info: ' . $e->getMessage());
        }

        return null;
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
