<?php

namespace App\Models;

class RestaurantCategory
{
    private $db;
    private $table = 'restaurant_categories';

    public function __construct()
    {
        $this->db = \App\Core\Database::getInstance();
    }

    public function all($shopId = null)
    {
        if ($shopId) {
            return $this->db->fetchAll("SELECT * FROM {$this->table} WHERE shop_id = ? ORDER BY nom ASC", [$shopId]);
        }
        return $this->db->fetchAll("SELECT * FROM {$this->table} ORDER BY shop_id ASC, nom ASC");
    }

    public function findById($id, $shopId = null)
    {
        if ($shopId) {
            return $this->db->fetch("SELECT * FROM {$this->table} WHERE id = ? AND shop_id = ?", [$id, $shopId]);
        }
        return $this->db->fetch("SELECT * FROM {$this->table} WHERE id = ?", [$id]);
    }

    public function existsNom($nom, $shopId, $excludeId = null)
    {
        if ($excludeId) {
            return $this->db->fetch(
                "SELECT id FROM {$this->table} WHERE nom = ? AND shop_id = ? AND id != ?",
                [$nom, $shopId, $excludeId]
            );
        }
        return $this->db->fetch("SELECT id FROM {$this->table} WHERE nom = ? AND shop_id = ?", [$nom, $shopId]);
    }

    public function create($data)
    {
        $sql = "INSERT INTO {$this->table} (shop_id, nom, description) VALUES (:shop_id, :nom, :description)";
        $this->db->execute($sql, [
            ':shop_id'     => $data['shop_id'],
            ':nom'         => $data['nom'],
            ':description' => $data['description'] ?? null,
        ]);
        return $this->db->lastInsertId();
    }

    public function update($id, $data)
    {
        $fields = [];
        $params = [':id' => $id];
        foreach (['nom', 'description'] as $field) {
            if (isset($data[$field])) {
                $fields[] = "$field = :$field";
                $params[":$field"] = $data[$field];
            }
        }
        if (empty($fields)) return false;

        $sql = "UPDATE {$this->table} SET " . implode(", ", $fields) . " WHERE id = :id";
        return $this->db->execute($sql, $params);
    }

    public function delete($id)
    {
        return $this->db->execute("DELETE FROM {$this->table} WHERE id = ?", [$id]);
    }
}
