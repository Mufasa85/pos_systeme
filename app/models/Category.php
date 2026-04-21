<?php

namespace App\Models;

class Category
{
    private $db;

    public function __construct()
    {
        $this->db = \App\Core\Database::getInstance();
    }

    // Récupérer toutes les catégories
    public function all()
    {
        return $this->db->fetchAll("SELECT * FROM categories");
    }

    // Supprimer une catégorie par son ID
    public function deleteCategory($id)
    {
        $this->db->query("DELETE FROM categories WHERE id = ?", [$id]);
    }

    // Vérifier si une catégorie existe
    public function exist($id)
    {
        return $this->db->fetch("SELECT * FROM categories WHERE id = ?", [$id]);
    }

    // Ajouter une nouvelle catégorie
    public function add($name)
    {
        $sql = "INSERT INTO categories (category) VALUES (:name)";
        return $this->db->query($sql, [':name' => $name]);
    }

    // Mettre à jour une catégorie existante
    public function update($id, $name)
    {
        $sql = "UPDATE categories SET category = :name , updated_at = NOW()  WHERE id = :id";
        return $this->db->query($sql, [':name' => $name, ':id' => $id]);
    }
}
