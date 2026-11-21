<?php

namespace App\Services;

use App\Models\UserPageAccess;

/**
 * Politique d'acces aux pages, par UTILISATEUR.
 *
 * SERVICE DE REFERENCE : c'est la source UNIQUE du catalogue des pages et de
 * la visibilite d'une page pour un utilisateur donne. La sidebar et le
 * backend consomment tous les deux ce service : ils ne peuvent donc pas
 * diverger.
 *
 * REGLE FONDAMENTALE
 *   La politique est RESTRICTIVE : elle peut retirer un acces normalement
 *   accorde (role, droit individuel `acces_*`, abonnement OSAT), mais elle
 *   ne peut jamais en ACCORDER un qui est refuse ailleurs.
 *
 * SEMANTIQUE « ABSENCE DE BLOCAGE = ACCES CONSERVE »
 *   La table `user_page_access` ne stocke que les blocages. Un utilisateur
 *   sans ligne conserve tous ses acces. C'est ce qui garantit qu'un
 *   deploiement ne modifie aucun acces existant.
 *
 * PERIMETRE
 *   Le blocage est INDIVIDUEL : il porte sur un utilisateur precis, sans
 *   toucher a son role (`utilisateurs.role`), a son role metier hotel
 *   (`utilisateurs.hotel_role`) ni a ses droits `acces_*`.
 *
 *   La boutique n'est PAS stockee : elle decoule de `utilisateurs.shop_id`.
 *   Une restriction suit donc l'utilisateur s'il change de boutique.
 *
 * SUPER_ADMIN
 *   Le `super_admin` n'est JAMAIS bloque : il doit pouvoir administrer les
 *   blocages de tous les utilisateurs, sur toutes les boutiques.
 *
 * FAIL-CLOSED
 *   Tout cas indefini est REFUSE : page inconnue, utilisateur introuvable,
 *   erreur SQL (toutes les pages masquees).
 *
 * LECTURE DU ROLE EN BASE
 *   Le role est relu depuis la LIGNE `utilisateurs`, pas depuis
 *   `$_SESSION['role']` : un changement de role est ainsi pris en compte
 *   immediatement, sans reconnexion.
 *
 * Migration : migrations/2026_11_18_user_page_access.sql
 */
final class UserPageAccessService
{
    public const FAMILY_GENERAL = 'general';
    public const FAMILY_HOTEL   = 'hotel';

    /**
     * Catalogue des pages configurables.
     *
     * Les cles sont STABLES : elles ne doivent pas etre renommees apres mise
     * en production (elles sont stockees en base).
     *
     * @var array<string, array{label: string, family: string}>
     */
    private const CATALOG = [
        // ── Pages generales (12) ───────────────────────────────────────────
        'caisse'        => ['label' => 'Caisse / POS',           'family' => self::FAMILY_GENERAL],
        'recharges'     => ['label' => 'Recharges',               'family' => self::FAMILY_GENERAL],
        'produits'      => ['label' => 'Produits',                'family' => self::FAMILY_GENERAL],
        'historique'    => ['label' => 'Historique',              'family' => self::FAMILY_GENERAL],
        'pressing'      => ['label' => 'Pressing',                'family' => self::FAMILY_GENERAL],
        'analytics'     => ['label' => 'Analytics',               'family' => self::FAMILY_GENERAL],
        'paie'          => ['label' => 'Paie',                    'family' => self::FAMILY_GENERAL],
        'rapports'      => ['label' => 'Rapports',                'family' => self::FAMILY_GENERAL],
        'utilisateurs'  => ['label' => 'Utilisateurs',            'family' => self::FAMILY_GENERAL],
        'categories'    => ['label' => 'Categories',              'family' => self::FAMILY_GENERAL],
        'taxes'         => ['label' => 'Taxes',                   'family' => self::FAMILY_GENERAL],
        'parametres'    => ['label' => 'Parametres',              'family' => self::FAMILY_GENERAL],

        // ── Pages Hotel (9) ───────────────────────────────────────────────
        'hotel_tableau_bord' => ['label' => 'Tableau de bord',       'family' => self::FAMILY_HOTEL],
        'hotel_calendrier'   => ['label' => 'Calendrier',            'family' => self::FAMILY_HOTEL],
        'hotel_reception'    => ['label' => 'Reception',             'family' => self::FAMILY_HOTEL],
        'hotel_etages'       => ['label' => 'Etages / Housekeeping', 'family' => self::FAMILY_HOTEL],
        'hotel_chambres'     => ['label' => 'Chambres',              'family' => self::FAMILY_HOTEL],
        'hotel_reservations' => ['label' => 'Reservations',          'family' => self::FAMILY_HOTEL],
        'hotel_guests'       => ['label' => 'Clients / Guests',      'family' => self::FAMILY_HOTEL],
        'hotel_facturation'  => ['label' => 'Facturation',           'family' => self::FAMILY_HOTEL],
        'hotel_roles'        => ['label' => 'Gestion des roles',     'family' => self::FAMILY_HOTEL],
    ];


    /**
     * Le catalogue complet.
     *
     * @return array<string, array{label: string, family: string}>
     */
    public static function getCatalog(): array
    {
        return self::CATALOG;
    }

    /**
     * Le catalogue d'UNE famille de pages.
     *
     * Utilisé pour n'afficher que les pages concernées par l'écran courant :
     * les pages POS sur `/pages-bloquees`, les pages Hôtel sur
     * `/hotel/roles`.
     *
     * @return array<string, array{label: string, family: string}>
     */
    public static function getCatalogForFamily(string $family): array
    {
        if (!self::isKnownFamily($family)) {
            return [];
        }

        $catalog = [];
        foreach (self::CATALOG as $pageKey => $definition) {
            if ($definition['family'] === $family) {
                $catalog[$pageKey] = $definition;
            }
        }

        return $catalog;
    }

    /**
     * Les cles de pages d'une famille.
     *
     * @return array<int, string>
     */
    public static function getPageKeys(string $family): array
    {
        if (!self::isKnownFamily($family)) {
            return [];
        }

        $keys = [];
        foreach (self::CATALOG as $pageKey => $definition) {
            if ($definition['family'] === $family) {
                $keys[] = $pageKey;
            }
        }

        return $keys;
    }

    /**
     * Une page est-elle connue du catalogue ?
     */
    public static function isKnownPage(string $pageKey): bool
    {
        return isset(self::CATALOG[$pageKey]);
    }

    /**
     * Une famille est-elle connue ?
     */
    public static function isKnownFamily(string $family): bool
    {
        return in_array($family, [self::FAMILY_GENERAL, self::FAMILY_HOTEL], true);
    }

    /**
     * La page appartient-elle a la famille demandee ?
     */
    public static function isPageInFamily(string $pageKey, string $family): bool
    {
        return self::isKnownPage($pageKey)
            && self::CATALOG[$pageKey]['family'] === $family;
    }

    /**
     * Le libelle d'une page (fallback : la cle).
     */
    public static function getPageLabel(string $pageKey): string
    {
        return self::CATALOG[$pageKey]['label'] ?? $pageKey;
    }

    /**
     * L'utilisateur a-t-il acces a cette page par DEFAUT (hors blocage) ?
     *
     * Cette methode repond a la question : « cette page fait-elle partie de
     * son outil de travail naturel ? ». Elle se base sur les MEMES regles que
     * la sidebar et les gardes backend :
     *   - les droits `utilisateurs.acces_*` (colonnes du modele) ;
     *   - le role applicatif (`admin` / `super_admin` pour les pages
     *     d'administration) ;
     *   - le role metier hotel (`hotel_role`) pour les pages du PMS.
     *
     * Elle sert UNIQUEMENT a l'affichage (flouter les pages hors perimetre
     * dans la modale). Elle n'accorde aucun droit : la politique reste
     * RESTRICTIVE, et un blocage retire toujours l'acces.
     *
     * @param array<string, mixed> $user Ligne `utilisateurs` (avec `role`,
     *        `hotel_role` et les colonnes `acces_*`).
     */
    public static function isPageAvailableByDefault(string $pageKey, array $user): bool
    {
        if (!self::isKnownPage($pageKey)) {
            return false;
        }

        $role = (string)($user['role'] ?? '');
        $hotelRole = (string)($user['hotel_role'] ?? '');

        // Le super_admin a tout.
        if ($role === 'super_admin') {
            return true;
        }

        // ── Pages du module HOTEL ─────────────────────────────────────
        // Elles suivent le role metier hotel (cf. resolveHotelRole()).
        if (str_starts_with($pageKey, 'hotel_')) {
            // Un `admin` est auto-manager de sa boutique.
            if ($role === 'admin') {
                return true;
            }
            if ($hotelRole === 'manager') {
                // Le manager n'a pas la page « gestion des roles » de sa
                // boutique (celle du super_admin) : cf. sidebar.
                return $pageKey !== 'hotel_roles';
            }
            if ($hotelRole === 'receptionniste') {
                return in_array($pageKey, [
                    'hotel_reception',
                    'hotel_reservations',
                    'hotel_etages',
                    'hotel_chambres',
                    'hotel_guests',
                ], true);
            }
            if ($hotelRole === 'gouvernante') {
                return in_array($pageKey, [
                    'hotel_etages',
                    'hotel_chambres',
                    'hotel_guests',
                ], true);
            }
            return false;
        }

        // ── Pages reservees aux administrateurs ─────────────────────
        // `utilisateurs`, `parametres`, `categories`, `taxes` : le controleur
        // redirige les non-admins vers /dashboard.
        // `analytics` : meme regle (redirection si le role n'est pas admin).
        // `rapports` : la page est visible, mais TOUTES ses APIs exigent
        // `requireAdmin()` (cf. ReportController) : sans etre admin, la page
        // est de fait inutilisable — on la considere donc hors perimetre.
        if (in_array($pageKey, [
            'utilisateurs',
            'parametres',
            'categories',
            'taxes',
            'analytics',
            'rapports',
        ], true)) {
            return $role === 'admin';
        }

        // ── Pages pilotees par un droit `acces_*` ─────────────────────
        // Les colonnes sont `acces_<module>` (migrations 2026_11_14 /
        // 2026_11_15) — cf. User::MODULES_ACCES.
        $moduleMap = [
            'caisse'     => 'acces_caisse',
            'produits'   => 'acces_produits',
            'historique' => 'acces_historique',
            'pressing'   => 'acces_pressing',
            'analytics'  => 'acces_analytics',
            'paie'       => 'acces_paie',
        ];
        if (isset($moduleMap[$pageKey])) {
            $column = $moduleMap[$pageKey];
            // Un role de pilotage a un acces permanent a tous les modules.
            if (in_array($role, ['admin', 'super_admin'], true)) {
                return true;
            }
            return !empty($user[$column]);
        }

        // ── Autres pages : accessibles a tous ────────────────────────
        // (`recharges` et `rapports` sont soumis a un abonnement OSAT ou a un
        // droit metier, pas a un role applicatif).
        return true;
    }

    /**
     * Le contexte utilisateur, lu EN BASE.
     *
     * @return array{role: string, shop_id: int|null}|false
     */
    public static function resolveUserContext(int $userId): array|false
    {
        if ($userId <= 0) {
            return false;
        }

        try {
            $row = \App\Core\Database::getInstance()->fetch(
                'SELECT role, shop_id FROM utilisateurs WHERE id = ? LIMIT 1',
                [$userId]
            );
        } catch (\Throwable $e) {
            error_log('[UserPageAccessService] resolveUserContext error: ' . $e->getMessage());
            return false;
        }

        if (!$row) {
            return false;
        }

        return [
            'role'    => (string)($row['role'] ?? ''),
            'shop_id' => isset($row['shop_id']) ? (int)$row['shop_id'] : null,
        ];
    }

    /**
     * Le `super_admin` est-il l'utilisateur donne ?
     *
     * Lu EN BASE : un super_admin n'est jamais bloque, quelle que soit la
     * ligne de session.
     */
    public static function isSuperAdmin(int $userId): bool
    {
        $context = self::resolveUserContext($userId);
        if ($context === false) {
            return false;
        }

        return $context['role'] === 'super_admin';
    }

    // ── Lecture ──────────────────────────────────────────────────────────

    /**
     * Les pages bloquees d'un utilisateur.
     *
     * @return array<int, string> Les cles de pages bloquees.
     */
    public function blockedPageKeys(int $userId): array
    {
        try {
            return (new UserPageAccess())->blockedPageKeysForUser($userId);
        } catch (\Throwable $e) {
            error_log('[UserPageAccessService] blockedPageKeys error: ' . $e->getMessage());
            // Fail-closed : on ne peut pas savoir ce qui est bloque, donc on
            // considere que TOUT est bloque.
            return array_keys(self::CATALOG);
        }
    }

    /**
     * La carte de visibilite d'un utilisateur : pageKey => visible.
     *
     * C'est la carte consommee par la sidebar ET par la garde backend : les
     * deux ne peuvent donc pas diverger.
     *
     * @return array<string, bool>
     */
    public function getVisibilityMap(int $userId): array
    {
        // Le super_admin n'est jamais bloque.
        if (self::isSuperAdmin($userId)) {
            return array_fill_keys(array_keys(self::CATALOG), true);
        }

        // Utilisateur inconnu : fail-closed (tout masque).
        $context = self::resolveUserContext($userId);
        if ($context === false) {
            return array_fill_keys(array_keys(self::CATALOG), false);
        }

        $blocked = $this->blockedPageKeys($userId);

        $map = [];
        foreach (self::CATALOG as $pageKey => $_definition) {
            $map[$pageKey] = !in_array($pageKey, $blocked, true);
        }

        return $map;
    }

    /**
     * Une page est-elle accessible a cet utilisateur ?
     *
     * Le role et la boutique sont relus EN BASE : un changement de role ou de
     * boutique est ainsi pris en compte immediatement.
     */
    public function isPageAllowedForUser(string $pageKey, int $userId): bool
    {
        // Page inconnue : refuse (fail-closed).
        if (!self::isKnownPage($pageKey)) {
            return false;
        }

        // Utilisateur inconnu : refuse (fail-closed).
        $context = self::resolveUserContext($userId);
        if ($context === false) {
            return false;
        }

        // Le super_admin n'est jamais bloque.
        if ($context['role'] === 'super_admin') {
            return true;
        }

        $blocked = $this->blockedPageKeys($userId);

        return !in_array($pageKey, $blocked, true);
    }

    /**
     * Une page est-elle accessible a cet utilisateur ?
     *
     * Variante explicite : le role est fourni par l'appelant (lu en base).

    // ── Ecriture ─────────────────────────────────────────────────────────

    /**
     * Bloque ou debloque UNE page pour UN utilisateur.
     *
     * Le changement est AUDITE : on ne journalise que les differences (une
     * page deja bloquee n'est pas re-auditee a chaque appel).
     *
     * @param bool $blocked true = bloquer, false = debloquer.
     * @return bool true si l'ecriture a reussi.
     */
    public function setPageBlocked(int $userId, string $pageKey, bool $blocked, ?int $createdBy = null): bool
    {
        if ($userId <= 0 || !self::isKnownPage($pageKey)) {
            error_log('[UserPageAccessService] setPageBlocked - cible invalide');
            return false;
        }

        // Le super_admin n'est jamais bloque.
        if (self::isSuperAdmin($userId)) {
            return false;
        }

        $model = new UserPageAccess();

        // Etat actuel : on ne journalise que la DIFFERENCE.
        $wasBlocked = in_array($pageKey, $this->blockedPageKeys($userId), true);

        try {
            if ($blocked) {
                $model->create($userId, $pageKey, $createdBy);
            } else {
                $model->delete($userId, $pageKey);
            }
        } catch (\Throwable $e) {
            error_log('[UserPageAccessService] setPageBlocked error: ' . $e->getMessage());
            return false;
        }

        if ($wasBlocked !== $blocked) {
            $this->logChange($userId, $pageKey, $blocked, $wasBlocked, $createdBy);
        }

        return true;
    }

    /**
     * Remplace l'ensemble des blocages d'un utilisateur pour une famille de
     * pages donnee.
     *
     * Le payload attendu est :
     *   [pageKey => bool] ou `true` = page bloquee, `false` = page visible.
     *
     * Seules les PAGES DE LA FAMILLE sont concernees : un blocage sur une
     * page d'une autre famille n'est ni lu, ni modifie, ni supprime.
     *
     * @param array<string, bool> $changes
     * @return bool true si les blocages ont ete enregistres.
     */
    public function updateBlockedPages(int $userId, string $family, array $changes, ?int $createdBy = null): bool
    {
        if ($userId <= 0) {
            return false;
        }

        // Le super_admin n'est jamais bloque.
        if (self::isSuperAdmin($userId)) {
            return false;
        }

        $validation = self::validateChanges($family, $changes);
        if (!$validation['success']) {
            error_log('[UserPageAccessService] updateBlockedPages refuse: ' . ($validation['message'] ?? ''));
            return false;
        }

        $familyPageKeys = self::getPageKeys($family);

        // Etat actuel, pour calculer les differences.
        $current = [];
        foreach ($this->blockedPageKeys($userId) as $pageKey) {
            $current[$pageKey] = true;
        }

        // Blocages a conserver : les pages decochees (blocked = true).
        $blockedToKeep = [];
        foreach ($changes as $pageKey => $blocked) {
            $pageKey = (string)$pageKey;
            if ($blocked === true) {
                $blockedToKeep[] = $pageKey;
            }
        }

        // Ecriture atomique des blocages de la famille.
        $model = new UserPageAccess();
        $ok = $model->replaceForUserAndPages(
            $userId,
            $blockedToKeep,
            $createdBy,
            $familyPageKeys
        );

        if (!$ok) {
            return false;
        }

        // Audit : uniquement les DIFFERENCES (une page deja bloquee n'est pas
        // re-auditee a chaque sauvegarde).
        foreach ($familyPageKeys as $pageKey) {
            $previous = isset($current[$pageKey]);
            $now = in_array($pageKey, $blockedToKeep, true);

            if ($previous === $now) {
                continue;
            }

            $this->logChange($userId, $pageKey, $now, $previous, $createdBy);
        }

        return true;
    }

    /**
     * Valide un payload de changement de blocages.
     *
     * @param array<string, bool> $changes
     * @return array{success: bool, message: string, errors?: array<int, string>}
     */
    public static function validateChanges(string $family, array $changes): array
    {
        if (!self::isKnownFamily($family)) {
            return [
                'success' => false,
                'message' => 'Famille inconnue.',
                'errors'  => ['Famille « ' . $family . ' » inconnue.'],
            ];
        }

        if (empty($changes)) {
            return [
                'success' => false,
                'message' => 'Aucun changement fourni.',
                'errors'  => ['Aucun changement fourni.'],
            ];
        }

        $errors = [];

        foreach ($changes as $pageKey => $blocked) {
            $pageKey = (string)$pageKey;

            if (!self::isKnownPage($pageKey)) {
                $errors[] = 'Page inconnue : ' . $pageKey;
                continue;
            }

            if (!self::isPageInFamily($pageKey, $family)) {
                $errors[] = 'La page « ' . $pageKey . ' » n\'appartient pas a la famille « ' . $family . ' ».';
                continue;
            }

            if (!is_bool($blocked)) {
                $errors[] = 'Valeur invalide pour la page « ' . $pageKey . ' » (un booleen est attendu).';
            }
        }

        if (!empty($errors)) {
            return [
                'success' => false,
                'message' => 'Changements invalides.',
                'errors'  => $errors,
            ];
        }

        return ['success' => true, 'message' => 'OK'];
    }

    /**
     * Journalise un changement de blocage.
     */
    private function logChange(
        int $userId,
        string $pageKey,
        bool $blocked,
        bool $previousBlocked,
        ?int $createdBy
    ): void {
        try {
            // L'entite de l'audit est l'UTILISATEUR bloque : le journal
            // permet de retracer « qui a retire quel acces a qui ».
            (new \App\Models\AuditLog())->log(
                $createdBy !== null && $createdBy > 0 ? $createdBy : null,
                null,
                'user_page_access_update',
                'user_page_access',
                $userId,
                [
                    'page_key'         => $pageKey,
                    'blocked'          => $blocked,
                    'previous_blocked' => $previousBlocked,
                ]
            );
        } catch (\Throwable $e) {
            // L'audit ne doit jamais faire echouer la sauvegarde.
            error_log('[UserPageAccessService] logChange error: ' . $e->getMessage());
        }
    }

    /**
     * Une page est-elle accessible a cet utilisateur ?
     *
     * Variante explicite : le role est fourni par l'appelant (lu en base).
     */
    public function isPageAllowed(string $pageKey, int $userId, string $role): bool
    {
        if (!self::isKnownPage($pageKey)) {
            return false;
        }

        if ($role === 'super_admin') {
            return true;
        }

        return !in_array($pageKey, $this->blockedPageKeys($userId), true);
    }
}
