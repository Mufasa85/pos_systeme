<?php

namespace App\Models;

/**
 * Blocages de pages, par UTILISATEUR.
 *
 * Table : `user_page_access` (migration 2026_11_18).
 *
 * SEMANTIQUE
 *   Cette table ne stocke QUE les blocages : l'ABSENCE de ligne signifie
 *   que l'utilisateur garde son acces normal (role, droits `acces_*`, feature
 *   flags OSAT). C'est ce qui garantit qu'un deploiement ne modifie aucun
 *   acces existant, et que les nouvelles pages restent accessibles par
 *   defaut.
 *
 *   Une restriction est identifiee par le couple (user_id, page_key), unique
 *   en base.
 *
 * PORTEE
 *   Le blocage porte sur un UTILISATEUR, pas sur une boutique ni sur une
 *   audience. La boutique n'est pas stockee : elle decoule de
 *   `utilisateurs.shop_id`. Une restriction suit donc l'utilisateur s'il
 *   change de boutique.
 *
 * SCOPE
 *   Ce modele est volontairement « bete » : il stocke fidelement ce qu'on
 *   lui demande, sans valider le catalogue metier des pages. La validation
 *   des cles de page est du ressort du service `UserPageAccessService`.
 */
class UserPageAccess
{
    private $db;

    public function __construct()
    {
        $this->db = \App\Core\Database::getInstance();
    }

    /**
     * Toutes les lignes de blocage d'un utilisateur.
     *
     * @return array<int, array<string, mixed>>
     */
    public function allForUser(int $userId): array
    {
        if ($userId <= 0) {
            return [];
        }

        return $this->db->fetchAll(
            'SELECT * FROM user_page_access
             WHERE user_id = ?
             ORDER BY page_key',
            [$userId]
        );
    }

    /**
     * Les pages bloquees d'un utilisateur, sous forme de liste de cles.
     *
     * @return array<int, string>
     */
    public function blockedPageKeysForUser(int $userId): array
    {
        if ($userId <= 0) {
            return [];
        }

        $rows = $this->db->fetchAll(
            'SELECT page_key FROM user_page_access WHERE user_id = ?',
            [$userId]
        );

        $keys = [];
        foreach ($rows as $row) {
            $pageKey = (string)($row['page_key'] ?? '');
            if ($pageKey !== '') {
                $keys[] = $pageKey;
            }
        }

        return $keys;
    }

    /**
     * Les blocages d'une page, tous utilisateurs confondus.
     *
     * @return array<int, array<string, mixed>>
     */
    public function allForPage(string $pageKey): array
    {
        if ($pageKey === '') {
            return [];
        }

        return $this->db->fetchAll(
            'SELECT * FROM user_page_access
             WHERE page_key = ?
             ORDER BY user_id',
            [$pageKey]
        );
    }

    /**
     * Les pages bloquees, regroupees par utilisateur.
     *
     * Utilisee par l'ecran « Pages bloquees » (super_admin) : une seule
     * requete suffit a charger l'etat de tous les utilisateurs, au lieu
     * d'une requete par utilisateur.
     *
     * @return array<int, array<int, string>> userId => [pageKey, ...]
     */
    public function allBlockedByUser(): array
    {
        $rows = $this->db->fetchAll(
            'SELECT user_id, page_key FROM user_page_access ORDER BY user_id, page_key'
        );

        $map = [];
        foreach ($rows as $row) {
            $userId = (int)($row['user_id'] ?? 0);
            $pageKey = (string)($row['page_key'] ?? '');
            if ($userId <= 0 || $pageKey === '') {
                continue;
            }
            if (!isset($map[$userId])) {
                $map[$userId] = [];
            }
            $map[$userId][] = $pageKey;
        }

        return $map;
    }



    /**
     * Les utilisateurs bloques sur une page, pour une boutique donnee.
     *
     * La boutique est croisee avec `utilisateurs.shop_id` : une restriction
     * suit l'utilisateur, mais l'affichage reste scope par boutique.
     *
     * @return array<int, int> Les identifiants utilisateurs bloques.
     */
    public function blockedUserIdsForPageAndShop(string $pageKey, int $shopId): array
    {
        if ($pageKey === '' || $shopId <= 0) {
            return [];
        }

        $rows = $this->db->fetchAll(
            'SELECT upa.user_id
             FROM user_page_access upa
             INNER JOIN utilisateurs u ON u.id = upa.user_id
             WHERE upa.page_key = ? AND u.shop_id = ?
             ORDER BY upa.user_id',
            [$pageKey, $shopId]
        );

        $ids = [];
        foreach ($rows as $row) {
            $ids[] = (int)$row['user_id'];
        }

        return $ids;
    }

    /**
     * Cree un blocage.
     *
     * Idempotent : si le blocage existe deja, la ligne est mise a jour
     * (l'auteur) au lieu de lever une exception de contrainte unique.
     *
     * @return int L'identifiant de la ligne concernee.
     */
    public function create(int $userId, string $pageKey, ?int $createdBy = null): int
    {
        $this->db->execute(
            'INSERT INTO user_page_access (user_id, page_key, created_by)
             VALUES (?, ?, ?)
             ON DUPLICATE KEY UPDATE created_by = VALUES(created_by)',
            [$userId, $pageKey, $createdBy]
        );

        return (int)$this->db->lastInsertId();
    }

    /**
     * Supprime un blocage.
     *
     * @return bool true si une ligne a ete supprimee.
     */
    public function delete(int $userId, string $pageKey): bool
    {
        $this->db->execute(
            'DELETE FROM user_page_access WHERE user_id = ? AND page_key = ?',
            [$userId, $pageKey]
        );

        return true;
    }

    /**
     * Remplace l'ensemble des blocages d'un utilisateur pour une famille de
     * pages donnee.
     *
     * Le modele ne connait pas le catalogue des pages : l'appelant lui
     * transmet les cles de la famille concernee. Les blocages portant sur
     * d'autres pages ne sont PAS touches.
     *
     * @param array<int, string> $blockedPageKeys Cles de pages a bloquer.
     * @param array<int, string> $familyPageKeys   Cles de pages de la famille.
     */
    public function replaceForUserAndPages(
        int $userId,
        array $blockedPageKeys,
        ?int $createdBy = null,
        array $familyPageKeys = []
    ): bool {
        $db = $this->db;

        $db->beginTransaction();
        try {
            // 1. Supprimer les blocages existants sur les pages de la
            //    famille (et uniquement celles-ci).
            if (!empty($familyPageKeys)) {
                $placeholders = implode(', ', array_fill(0, count($familyPageKeys), '?'));
                $params = array_merge([$userId], $familyPageKeys);
                $db->execute(
                    "DELETE FROM user_page_access
                     WHERE user_id = ? AND page_key IN ($placeholders)",
                    $params
                );
            }

            // 2. Reinserer les blocages demandes.
            foreach ($blockedPageKeys as $pageKey) {
                $pageKey = (string)$pageKey;
                if ($pageKey === '') {
                    continue;
                }
                $db->execute(
                    'INSERT INTO user_page_access (user_id, page_key, created_by)
                     VALUES (?, ?, ?)
                     ON DUPLICATE KEY UPDATE created_by = VALUES(created_by)',
                    [$userId, $pageKey, $createdBy]
                );
            }

            $db->commit();
        } catch (\Throwable $e) {
            $db->rollBack();
            error_log('[UserPageAccess] replaceForUserAndPages error: ' . $e->getMessage());
            return false;
        }

        return true;
    }
}
