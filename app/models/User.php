<?php

namespace App\Models;

class User
{
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

    public function delete($id)
    {
        $this->db->query('DELETE FROM utilisateurs WHERE id = ?', [$id]);
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
            return false; // rien à mettre à jour
        }

        $sql = 'UPDATE utilisateurs SET ' . implode(', ', $fields) . ' WHERE id = :id';
        error_log("User model update - SQL: $sql");
        error_log('User model update - params: ' . print_r($params, true));

        try {
            $this->db->execute($sql, $params);
            error_log('User model update - executed successfully');
            return true; // Succès si pas d'exception
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
