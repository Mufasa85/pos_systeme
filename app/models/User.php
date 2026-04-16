<?php
// app/models/User.php

class User {
    private $db;

    public function __construct() {
        $this->db = Database::getInstance();
    }

    public function login($username, $password) {
        $sql = "SELECT * FROM utilisateurs WHERE nom_utilisateur = :nom_utilisateur AND actif = 1";
        $user = $this->db->fetch($sql, [':nom_utilisateur' => $username]);

        if ($user && password_verify($password, $user['mot_de_passe'])) {
            return $user;
        }

        return false;
    }

    public function getAllUsers() {
        return $this->db->fetchAll("SELECT * FROM utilisateurs");
    }
}
