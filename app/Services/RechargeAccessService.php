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
     *
     * BUG FIX 2026-10-20 (round 2) — retour au comportement STRICT :
     * Le module hôtel doit être aussi strict que /recharges et /payroll.
     * Un fail-open (hotel => null) avait été posé en round 1 parce
     * qu'un admin + hotel_role=manager recevait 403 ; mais l'exigence
     * est que l'accès au module reste conditionné au flag API, à
     * l'identique des autres modules payants. Simplement, le bouton
     * Hôtel est MASQUÉ dans le header quand le flag est absent/false
     * (cf. header.php → $showHotel), pour que l'utilisateur ne tombe
     * pas sur une page 403 en cliquant un lien visible.
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
     *
     * BUG FIX 2026-10-20 (round 2) — comportement STRICT :
     * Sémantique identique à canAccess() / canAccessPayroll() :
     *   - flag `hotel` truthy               → accès accordé
     *   - flag `hotel` falsy / absent / null → accès refusé (403)
     *
     * Le rôle métier (requireHotelAccess / guardRead / guardWrite) reste
     * le 2e filtre, inchangé. Le bouton Hôtel est masqué dans header.php
     * quand le flag est faux, pour éviter un 403 en cliquant un lien
     * visible.
     */
    public function canAccessHotel(): bool
    {
        return $this->getFeatureFlag('hotel');
    }
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
     * BUG FIX 2026-10-20 (round 2) — helper de debug.
     * Retourne les flags bruts (sans interprétation) + l'état du cache,
     * pour diagnostiquer un refus d'accès sans ouvrir les logs serveur.
     * Utilisé par GET /api/recharge-access → champ `debug_flags`.
     */
    public function getFlagsForDebug(): array
    {
        $this->ensureSessionStarted();
        $cached = $_SESSION[self::SESSION_KEY] ?? null;
        $flags  = $this->getAllFlags();
        return [
            'flags'          => $flags,
            'from_cache'     => ($cached !== null && isset($cached['expires_at']) && $cached['expires_at'] > time()),
            'cache_expires_in' => ($cached && isset($cached['expires_at'])) ? max(0, (int)$cached['expires_at'] - time()) : 0,
            'nif_found'      => !empty($this->getNif()),
        ];
    }

    /**
     * Récupère TOUS les flags depuis le cache (ou via fetch si expiré).
     * Sprint 7 — 2026-10-19 : factorisé pour canAccessHotel() qui doit
     * distinguer "flag absent" (autoriser par défaut) de "flag=false" (refuser).
     *
     * BUG FIX 2026-10-20 (round 3) — auto-purge du cache "legacy" :
     * L'API OSAT a mis en place le flag `hotel` progressivement. Un cache
     * rempli par une version antérieure de l'API contient
     * `flags['hotel'] = null` (clé présente, valeur absente de l'API).
     * Avec le TTL de 300s, ce cache "legacy" pouvait geler l'accès pendant
     * 5 minutes après le déploiement du nouveau backend OSAT → 403
     * inexpliqué alors que l'API renvoie bien `hotel: 1`.
     *
     * Correctif : si `flags['hotel']` n'est pas un booléen (donc null
     * = l'API ne l'expose pas encore), on considère le cache comme
     * PÉRIMÉ et on force un re-fetch immédiat, même si le TTL n'est pas
     * atteint. Dès que l'API expose `hotel`, la valeur est un booléen
     * et le cache redevient valide normalement.
     */
    private function getAllFlags(): array
    {
        $this->ensureSessionStarted();
        $cached = $_SESSION[self::SESSION_KEY] ?? null;

        if ($cached !== null && isset($cached['expires_at']) && $cached['expires_at'] > time()) {
            $flags = $cached['flags'] ?? [];
            // Cache "legacy" : hotel n'est pas un booléen → on invalide.
            if (!is_bool($flags['hotel'] ?? null)) {
                error_log('[FeatureAccessService] Cache legacy détecté (hotel non-booléen) → re-fetch immédiat');
                return $this->fetchFromApi();
            }
            return $flags;
        }
        return $this->fetchFromApi();
    }

    /**
     * Pré-charge le cache sans bloquer si déjà en cache.
     * Même logique que getAllFlags() : un cache "legacy" (hotel non-booléen)
     * est considéré comme périmé → re-fetch immédiat.
     */
    public function warmupCache(): void
    {
        $this->ensureSessionStarted();

        $cached = $_SESSION[self::SESSION_KEY] ?? null;
        if ($cached !== null && isset($cached['expires_at']) && $cached['expires_at'] > time()) {
            $flags = $cached['flags'] ?? [];
            if (is_bool($flags['hotel'] ?? null)) {
                return;   // cache valide et complet → rien à faire
            }
            // Cache legacy → on repasse par fetchFromApi()
            error_log('[FeatureAccessService] warmup : cache legacy (hotel non-booléen) → re-fetch');
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
