<?php

namespace App\Services;

use App\Models\CompanyInfo;
use App\Models\ServiceType;
use App\Models\Settings;
use App\Models\Shop;
use App\Models\User;

/**
 * Service de contexte « utilisateur connecté ».
 *
 * Rassemble en un seul payload JSON toutes les informations disponibles sur
 * la personne authentifiée :
 *   - la session PHP (rôle, boutique, agent_code, expiration…)
 *   - la fiche utilisateur complète (relue en BDD)
 *   - la boutique d'affectation + son type de service
 *   - les identifiants légaux (NIF/ISF, ICE, RCCM, NID, PDV)
 *
 * Résolution automatique du NIF (ISF) — ordre de priorité :
 *   1. super_admin          → company_info.isf (l'entreprise, pas une boutique)
 *   2. utilisateur boutique → shops.isf
 *   3. ancien réglage        → settings.store_isf
 *   4. repli final          → company_info.isf
 *
 * Sécurité : les colonnes sensibles ne sont JAMAIS retournées
 * (`utilisateurs.mot_de_passe`, `shops.token` = secret API OSAT,
 * `company_info.token`).
 */
class CurrentUserService
{
    /**
     * Durée de vie de la session (8 heures), alignée sur Controller::$SESSION_TIMEOUT.
     * Dupliquée volontairement ici pour que le service reste autonome et
     * n'accède pas à un membre d'une autre classe.
     */
    public const SESSION_TIMEOUT = 28800;

    /**
     * Construit le contexte complet de l'utilisateur connecté.
     *
     * @return array
     */
    public function getContext(): array
    {
        $userId = (int)($_SESSION['user_id'] ?? 0);
        $role   = (string)($_SESSION['role'] ?? '');
        $shopId = !empty($_SESSION['shop_id']) ? (int)$_SESSION['shop_id'] : null;

        $user    = $this->getUser($userId);
        $shop    = $this->getShop($shopId);
        $company = $this->getCompanyInfo();
        $nif     = $this->resolveNif($shop, $company, $role);

        $lastActivity = isset($_SESSION['last_activity']) ? (int)$_SESSION['last_activity'] : null;

        return [
            'authenticated' => $userId > 0,

            // ── Session PHP ──────────────────────────────────────────
            'session' => [
                'user_id'         => $userId,
                'nom_utilisateur' => $_SESSION['nom_utilisateur'] ?? null,
                'nom_complet'     => $_SESSION['nom_complet'] ?? ($_SESSION['full_name'] ?? null),
                'role'            => $role,
                'hotel_role'      => $_SESSION['hotel_role'] ?? null,
                'shop_id'         => $shopId,
                'agent_code'      => $_SESSION['agent_code'] ?? null,
                'email'           => $_SESSION['email'] ?? null,
                'telephone'       => $_SESSION['telephone'] ?? null,
                'last_activity'   => $lastActivity,
                'expires_at'      => $lastActivity ? $lastActivity + self::SESSION_TIMEOUT : null,
                'is_super_admin'  => $role === 'super_admin',
                'is_admin'        => in_array($role, ['admin', 'super_admin'], true),
            ],

            // ── Fiche utilisateur (BDD) ─────────────────────────────
            'user' => $user ? [
                'id'                 => (int)$user['id'],
                'nom_utilisateur'    => $user['nom_utilisateur'] ?? null,
                'nom_complet'        => $user['nom_complet'] ?? null,
                'role'               => $user['role'] ?? null,
                'hotel_role'         => $user['hotel_role'] ?? null,
                'shop_id'            => isset($user['shop_id']) && $user['shop_id'] !== null ? (int)$user['shop_id'] : null,
                'agent_code'         => $user['agent_code'] ?? null,
                'email'              => $user['email'] ?? null,
                'telephone'          => $user['telephone'] ?? null,
                'actif'              => (int)($user['actif'] ?? 1) === 1,
                'profile_image'      => $user['profile_image'] ?? null,
                'two_factor_enabled' => (int)($user['two_factor_enabled'] ?? 0) === 1,
            ] : null,

            // ── Boutique d'affectation ──────────────────────────────
            'shop' => $shop ? [
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
                'homologation' => (int)($shop['homologation'] ?? 0) === 1,
                'actif'        => (int)($shop['actif'] ?? 1) === 1,
                'service_type' => $this->getServiceTypeName($shop['service_type_id'] ?? null),
            ] : null,

            // ── Informations société (company_info) ─────────────────
            'company' => $company ? [
                'name'    => $company['name'] ?? null,
                'address' => $company['address'] ?? null,
                'phone'   => $company['phone'] ?? null,
                'email'   => $company['email'] ?? null,
                'ice'     => $company['ice'] ?? null,
                'rccm'    => $company['rccm'] ?? null,
                'isf'     => $company['isf'] ?? null,
                'nid'     => $company['nid'] ?? null,
                'pdv'     => $company['pdv'] ?? null,
            ] : null,

            // ── Identifiants légaux (résolus automatiquement) ──────
            'nif' => $nif,
            'isf' => $nif,   // alias : dans ce projet NIF == ISF
        ];
    }

    // ── Lecture utilisateur ──────────────────────────────────────

    private function getUser(int $userId): ?array
    {
        if ($userId <= 0) {
            return null;
        }
        try {
            return (new User())->exist($userId) ?: null;
        } catch (\Exception $e) {
            error_log('[CurrentUserService] Erreur lecture utilisateur: ' . $e->getMessage());
            return null;
        }
    }

    // ── Lecture boutique ─────────────────────────────────────────

    private function getShop(?int $shopId): ?array
    {
        if (!$shopId) {
            return null;
        }
        try {
            return (new Shop())->findById($shopId) ?: null;
        } catch (\Exception $e) {
            error_log('[CurrentUserService] Erreur lecture boutique: ' . $e->getMessage());
            return null;
        }
    }

    // ── Lecture company_info ─────────────────────────────────────

    private function getCompanyInfo(): ?array
    {
        try {
            return (new CompanyInfo())->get() ?: null;
        } catch (\Exception $e) {
            error_log('[CurrentUserService] Erreur lecture company_info: ' . $e->getMessage());
            return null;
        }
    }

    // ── Type de service de la boutique ───────────────────────────

    private function getServiceTypeName($serviceTypeId): ?string
    {
        if (!$serviceTypeId) {
            return null;
        }
        try {
            $type = (new ServiceType())->findById((int)$serviceTypeId);
            return $type['name'] ?? null;
        } catch (\Exception $e) {
            error_log('[CurrentUserService] Erreur lecture service_type: ' . $e->getMessage());
            return null;
        }
    }

    // ── Résolution automatique du NIF / ISF ───────────────────────

    private function resolveNif(?array $shop, ?array $company, string $role): ?string
    {
        // 1. Super admin : les identifiants de l'ENTREPRISE (company_info)
        if ($role === 'super_admin') {
            if (!empty($company['isf'])) {
                return $company['isf'];
            }
            return !empty($shop['isf']) ? $shop['isf'] : null;
        }

        // 2. Utilisateur de boutique : l'ISF de SA boutique
        if (!empty($shop['isf'])) {
            return $shop['isf'];
        }

        // 3. Ancien réglage par boutique (table settings)
        try {
            $settingsIsf = (new Settings())->get('store_isf', $shop['id'] ?? null);
            if (!empty($settingsIsf)) {
                return $settingsIsf;
            }
        } catch (\Exception $e) {
            error_log('[CurrentUserService] Erreur lecture settings: ' . $e->getMessage());
        }

        // 4. Repli final sur l'entreprise
        return !empty($company['isf']) ? $company['isf'] : null;
    }
}
