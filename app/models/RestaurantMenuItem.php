<?php

namespace App\Models;

class RestaurantMenuItem
{
    private $db;
    private $table = 'restaurant_menu_items';

    public function __construct()
    {
        $this->db = \App\Core\Database::getInstance();
    }

    public function all($shopId = null)
    {
        if ($shopId) {
            return $this->db->fetchAll(
                "SELECT m.*, c.nom as categorie_nom
                 FROM {$this->table} m
                 LEFT JOIN restaurant_categories c ON m.categorie_id = c.id
                 WHERE m.shop_id = ?
                 ORDER BY c.nom ASC, m.nom ASC",
                [$shopId]
            );
        }
        return $this->db->fetchAll(
            "SELECT m.*, c.nom as categorie_nom
             FROM {$this->table} m
             LEFT JOIN restaurant_categories c ON m.categorie_id = c.id
             ORDER BY m.shop_id ASC, c.nom ASC, m.nom ASC"
        );
    }

    public function findById($id, $shopId = null)
    {
        if ($shopId) {
            return $this->db->fetch("SELECT * FROM {$this->table} WHERE id = ? AND shop_id = ?", [$id, $shopId]);
        }
        return $this->db->fetch("SELECT * FROM {$this->table} WHERE id = ?", [$id]);
    }

    public function create($data)
    {
        $sql = "INSERT INTO {$this->table}
                (shop_id, categorie_id, nom, description, image, prix, temps_preparation, disponible)
                VALUES (:shop_id, :categorie_id, :nom, :description, :image, :prix, :temps_preparation, :disponible)";
        $this->db->execute($sql, [
            ':shop_id'           => $data['shop_id'],
            ':categorie_id'      => $data['categorie_id'],
            ':nom'               => $data['nom'],
            ':description'       => $data['description'] ?? null,
            ':image'             => $data['image'] ?? null,
            ':prix'              => $data['prix'],
            ':temps_preparation' => $data['temps_preparation'] ?? 0,
            ':disponible'        => $data['disponible'] ?? 1,
        ]);
        return $this->db->lastInsertId();
    }

    public function update($id, $data)
    {
        $fields = [];
        $params = [':id' => $id];
        $allowed = ['categorie_id', 'nom', 'description', 'image', 'prix', 'temps_preparation', 'disponible'];
        foreach ($allowed as $field) {
            if (isset($data[$field])) {
                $fields[] = "$field = :$field";
                $params[":$field"] = $data[$field];
            }
        }
        if (empty($fields)) return false;

        $sql = "UPDATE {$this->table} SET " . implode(", ", $fields) . " WHERE id = :id";
        return $this->db->execute($sql, $params);
    }

    public function toggleDisponible($id, $disponible)
    {
        return $this->db->execute(
            "UPDATE {$this->table} SET disponible = ? WHERE id = ?",
            [$disponible ? 1 : 0, $id]
        );
    }

    public function delete($id)
    {
        return $this->db->execute("DELETE FROM {$this->table} WHERE id = ?", [$id]);
    }
}
