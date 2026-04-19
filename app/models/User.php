<?php

namespace App\Models;

// app/models/User.php

class User
{
    private $db;

    public function __construct()
    {
        $this->db = \App\Core\Database::getInstance();
    }

    public function login($username, $password)
    {
        $sql = "SELECT * FROM utilisateurs WHERE nom_utilisateur = :name AND actif = 1";
        $user = $this->db->fetch($sql, [':name' => $username]);

        if ($user && password_verify($password, $user['mot_de_passe'])) {
            return $user;
        }

        return false;
    }

    public function getAllUsers()
    {
        return $this->db->fetchAll("SELECT * FROM utilisateurs");
    }

    public function deleteUser($id)
    {
        $this->db->query("DELETE FROM utilisateurs WHERE id = ?", [$id]);
    }

    public function exist($id)
    {
        return $this->db->fetch("SELECT * FROM utilisateurs WHERE id = ?", [$id]);
    }
}
