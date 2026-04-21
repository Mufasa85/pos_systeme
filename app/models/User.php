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
        $sql = "SELECT * FROM utilisateurs WHERE nom_utilisateur = :name";
        $user = $this->db->fetch($sql, [':name' => $username]);

        if ($user && password_verify($password, $user['mot_de_passe'])) {
            return $user;
        }

        return false;
    }

    public function all()
    {
        return $this->db->fetchAll("SELECT * FROM utilisateurs");
    }

    public function delete($id)
    {
        $this->db->query("DELETE FROM utilisateurs WHERE id = ?", [$id]);
    }

    public function exist($id)
    {
        return $this->db->fetch("SELECT * FROM utilisateurs WHERE id = ?", [$id]);
    }

    // 🔹 Création d'un utilisateur
    public function create($username, $password, $fullname, $role = 'vendeur', $actif = 1)
    {
        $hashedPassword = password_hash($password, PASSWORD_DEFAULT);

        $sql = "INSERT INTO utilisateurs 
                (nom_utilisateur, mot_de_passe, nom_complet, role, actif) 
                VALUES (:username, :password, :fullname, :role, :actif)";

        return $this->db->query($sql, [
            ':username' => $username,
            ':password' => $hashedPassword,
            ':fullname' => $fullname,
            ':role'     => $role,
            ':actif'    => $actif
        ]);
    }

    // 🔹 Mise à jour d'un utilisateur
    public function update($id, $data)
    {
        $fields = [];
        $params = [':id' => $id];

        if (isset($data['nom_utilisateur'])) {
            $fields[] = "nom_utilisateur = :username";
            $params[':username'] = $data['nom_utilisateur'];
        }
        if (isset($data['mot_de_passe'])) {
            $fields[] = "mot_de_passe = :password";
            $params[':password'] = password_hash($data['mot_de_passe'], PASSWORD_BCRYPT);
        }
        if (isset($data['nom_complet'])) {
            $fields[] = "nom_complet = :fullname";
            $params[':fullname'] = $data['nom_complet'];
        }
        if (isset($data['role'])) {
            $fields[] = "role = :role";
            $params[':role'] = $data['role'];
        }
        if (isset($data['actif'])) {
            $fields[] = "actif = :actif";
            $params[':actif'] = $data['actif'];
        }

        if (empty($fields)) {
            return false; // rien à mettre à jour
        }

        $sql = "UPDATE utilisateurs SET " . implode(", ", $fields) . " WHERE id = :id";
        return $this->db->query($sql, $params);
    }
}
