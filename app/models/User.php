<?php

namespace App\Models;

class User
{
    /**
     * Libellé affiché à la place d'un nom d'utilisateur absent.
     *
     * Depuis la migration 2026_10_25_user_delete_fk_set_null.sql, supprimer un
     * utilisateur détache son nom des lignes liées (ventes, paie, hôtel) sans
     * supprimer l'historique : la colonne FK passe à NULL et le `LEFT JOIN`
     * ne renvoie plus aucun nom. On affiche alors ce libellé.
     */
    public const DELETED_LABEL = 'Utilisateur supprimé';

    private $db;

    public function __construct()
    {
        $this->db = \App\Core\Database::getInstance();
    }

    public function login($username, $password)
    {
        $sql = 'SELECT * FROM utilisateurs WHERE nom_utilisateur = :name';
        $user = $this->db->fetch($sql, [':name' => $username]);

        if ($user && password_verify($password, $user['mot_de_passe'])) {
            return $user;
        }

        return false;
    }

    public function all($shopId = null, $callerRole = null)
    {
        if ($callerRole === 'admin' && $shopId) {
            // Admin voit uniquement les utilisateurs de sa boutique (sauf super_admin)
            return $this->db->fetchAll("SELECT u.*, s.nom AS shop_name FROM utilisateurs u LEFT JOIN shops s ON u.shop_id = s.id WHERE u.shop_id = ? AND u.role != 'super_admin' ORDER BY u.nom_complet ASC", [$shopId]);
        }
        if ($callerRole === 'super_admin' || !$shopId) {
            // Super admin voit tout le monde
            return $this->db->fetchAll('SELECT u.*, s.nom AS shop_name FROM utilisateurs u LEFT JOIN shops s ON u.shop_id = s.id ORDER BY u.nom_complet ASC');
        }
        // Fallback : filtrer par shop_id
        return $this->db->fetchAll('SELECT u.*, s.nom AS shop_name FROM utilisateurs u LEFT JOIN shops s ON u.shop_id = s.id WHERE u.shop_id = ? ORDER BY u.nom_complet ASC', [$shopId]);
    }

    /**
     * Liste des utilisateurs d'une boutique avec leur rôle hôtel.
     * Utilisé par /hotel/roles pour le manager.
     */
    public function allForShopWithHotelRole(int $shopId)
    {
        return $this->db->fetchAll(
            "SELECT u.id, u.nom_utilisateur, u.nom_complet, u.role, u.actif, u.hotel_role,
                    s.nom AS shop_name
             FROM utilisateurs u
             LEFT JOIN shops s ON u.shop_id = s.id
             WHERE u.shop_id = :sid
               AND u.role != 'super_admin'
             ORDER BY u.nom_complet ASC",
            [':sid' => $shopId]
        );
    }

    /**
     * Première boutique ayant au moins un utilisateur (pour le cas super_admin
     * sans boutique par défaut → on choisit la première boutique avec users).
     */
    public function firstShopIdWithUsers(): ?int
    {
        $row = $this->db->fetch(
            'SELECT shop_id FROM utilisateurs WHERE shop_id IS NOT NULL ORDER BY shop_id ASC LIMIT 1'
        );
        return $row ? (int)$row['shop_id'] : null;
    }

    /**
     * Met à jour uniquement le rôle hôtel d'un utilisateur.
     * Vérifie que l'utilisateur appartient bien à la boutique scope (sécurité multi-tenant).
     */
    public function setHotelRole(int $userId, ?string $hotelRole, int $expectedShopId): bool
    {
        $row = $this->db->fetch(
            'SELECT shop_id FROM utilisateurs WHERE id = :id',
            [':id' => $userId]
        );
        if (!$row) return false;
        if ($row['shop_id'] === null) return false; // super_admin exclu
        if ((int)$row['shop_id'] !== $expectedShopId) return false;

        $allowed = [null, 'manager', 'receptionniste', 'gouvernante'];
        if (!in_array($hotelRole, $allowed, true)) return false;

        $this->db->query(
            'UPDATE utilisateurs SET hotel_role = :hr WHERE id = :id',
            [':hr' => $hotelRole, ':id' => $userId]
        );
        return true;
    }

    /**
     * Supprime un utilisateur.
     *
     * Historique conservé : toutes les clés étrangères qui pointent vers
     * `utilisateurs` sont en `ON DELETE SET NULL` (cf. migration
     * 2026_10_25_user_delete_fk_set_null.sql). Supprimer un compte ne doit
     * JAMAIS faire disparaître les factures fiscales, les bulletins de paie
     * ou les lignes du module hôtel : seul l'auteur est détaché (NULL).
     *
     * @return array{success: bool, message?: string}
     */
    public function delete($id)
    {
        $id = (int)$id;
        if ($id <= 0) {
            return ['success' => false, 'message' => 'Identifiant utilisateur invalide.'];
        }

        $pdo = $this->db->getConnection();

        // Si une transaction est déjà ouverte (appel depuis un flux déjà
        // transactionnel), on ne la commit/rollback pas nous-mêmes : on
        // participe à la transaction existante.
        $ownTransaction = !$pdo->inTransaction();
        if ($ownTransaction) {
            $pdo->beginTransaction();
        }

        try {
            // Suppression idempotente : si l'utilisateur n'existe pas, on ne
            // lève pas d'exception (l'API doit rester tolérante).
            $stmt = $pdo->prepare('DELETE FROM utilisateurs WHERE id = ?');
            $stmt->execute([$id]);

            $deleted = $stmt->rowCount();
            if ($deleted === 0) {
                if ($ownTransaction) {
                    $pdo->rollBack();
                }
                return ['success' => false, 'message' => 'Utilisateur inexistant.'];
            }

            if ($ownTransaction) {
                $pdo->commit();
            }

            return ['success' => true];
        } catch (\PDOException $e) {
            if ($ownTransaction && $pdo->inTransaction()) {
                $pdo->rollBack();
            }

            // 1451 = violation de clé étrangère : il reste une contrainte
            // bloquante (schéma non migré). On renvoie un message clair au
            // lieu d'afficher une page blanche avec un Fatal error.
            $sqlState = $e->getCode();
            if ($sqlState === '23000') {
                error_log('User::delete - contrainte FK bloquante pour user_id=' . $id . ' : ' . $e->getMessage());
                return [
                    'success' => false,
                    'message' => "Suppression impossible : cet utilisateur est encore référencé par des données liées. "
                        . 'Vérifiez que la migration 2026_10_25_user_delete_fk_set_null.sql a été appliquée.',
                ];
            }

            error_log('User::delete - erreur SQL (' . $sqlState . ') : ' . $e->getMessage());
            return [
                'success' => false,
                'message' => 'Erreur lors de la suppression de l\'utilisateur.',
            ];
        }
    }

    public function exist($id)
    {
        return $this->db->fetch('SELECT * FROM utilisateurs WHERE id = ?', [$id]);
    }

    // 🔹 Création d'un utilisateur
    public function create($username, $password, $fullname, $role = 'vendeur', $actif = 1, $agentCode = null, $shopId = null, $email = null, $telephone = null)
    {
        $hashedPassword = password_hash($password, PASSWORD_DEFAULT);

        $sql = 'INSERT INTO utilisateurs
                (nom_utilisateur, mot_de_passe, nom_complet, role, shop_id, actif, agent_code, email, telephone)
                VALUES (:username, :password, :fullname, :role, :shop_id, :actif, :agent_code, :email, :telephone)';

        return $this->db->query($sql, [
            ':username'   => $username,
            ':password'   => $hashedPassword,
            ':fullname'   => $fullname,
            ':role'       => $role,
            ':shop_id'    => $shopId,
            ':actif'      => $actif,
            ':agent_code' => $agentCode,
            ':email'      => $email,
            ':telephone'  => $telephone,
        ]);
    }

    // 🔹 Mise à jour d'un utilisateur
    public function update($id, $data)
    {
        $fields = [];
        $params = [':id' => $id];

        if (isset($data['nom_utilisateur'])) {
            $fields[] = 'nom_utilisateur = :username';
            $params[':username'] = $data['nom_utilisateur'];
        }
        if (isset($data['mot_de_passe'])) {
            $fields[] = 'mot_de_passe = :password';
            $params[':password'] = password_hash($data['mot_de_passe'], PASSWORD_BCRYPT);
        }
        if (isset($data['nom_complet'])) {
            $fields[] = 'nom_complet = :fullname';
            $params[':fullname'] = $data['nom_complet'];
        }
        if (isset($data['role'])) {
            $fields[] = 'role = :role';
            $params[':role'] = $data['role'];
        }
        if (isset($data['hotel_role'])) {
            $fields[] = 'hotel_role = :hotel_role';
            $params[':hotel_role'] = $data['hotel_role'];
        }
        if (isset($data['actif'])) {
            $fields[] = 'actif = :actif';
            $params[':actif'] = $data['actif'];
        }
        if (isset($data['agent_code'])) {
            $fields[] = 'agent_code = :agent_code';
            $params[':agent_code'] = $data['agent_code'];
        }
        if (isset($data['shop_id'])) {
            $fields[] = 'shop_id = :shop_id';
            $params[':shop_id'] = $data['shop_id'];
        }
        if (isset($data['email'])) {
            $fields[] = 'email = :email';
            $params[':email'] = $data['email'];
        }
        if (isset($data['telephone'])) {
            $fields[] = 'telephone = :telephone';
            $params[':telephone'] = $data['telephone'];
        }
        if (isset($data['two_factor_enabled'])) {
            $fields[] = 'two_factor_enabled = :two_factor_enabled';
            $params[':two_factor_enabled'] = $data['two_factor_enabled'];
        }
        if (isset($data['profile_image'])) {
            $fields[] = 'profile_image = :profile_image';
            $params[':profile_image'] = $data['profile_image'];
        }

        if (empty($fields)) {
            error_log("User model update - no fields to update for id: $id");
            return false;
        }

        $sql = 'UPDATE utilisateurs SET ' . implode(', ', $fields) . ' WHERE id = :id';
        error_log("User model update - SQL: $sql");
        error_log('User model update - params: ' . print_r($params, true));

        try {
            $this->db->execute($sql, $params);
            error_log('User model update - executed successfully');
            return true;
        } catch (\Exception $e) {
            error_log('User model update - error: ' . $e->getMessage());
            return false;
        }
    }

    public function findByProfileImage($profileImage)
    {
        return $this->db->fetch("SELECT id, shop_id, profile_image FROM utilisateurs WHERE profile_image = :profile_image", [':profile_image' => $profileImage]);
    }

    public function findByEmail($email)
    {
        return $this->db->fetch('SELECT * FROM utilisateurs WHERE email = ? AND actif = 1', [$email]);
    }

    public function findByPhone($phone)
    {
        return $this->db->fetch('SELECT * FROM utilisateurs WHERE telephone = ? AND actif = 1', [$phone]);
    }

    public function updatePassword($id, $newPassword)
    {
        $hashed = password_hash($newPassword, PASSWORD_BCRYPT);
        return $this->db->execute('UPDATE utilisateurs SET mot_de_passe = ? WHERE id = ?', [$hashed, $id]);
    }
}
