<?php

namespace App\Models;

class RestaurantMenu
{
    private $db;
    private $table = 'restaurant_menus';

    public function __construct()
    {
        $this->db = \App\Core\Database::getInstance();
    }

    public function all($shopId = null)
    {
        if ($shopId) {
            return $this->db->fetchAll(
                "SELECT * FROM {$this->table} WHERE shop_id = ? ORDER BY actif DESC, nom ASC",
                [$shopId]
            );
        }
        return $this->db->fetchAll(
            "SELECT m.*, s.nom as shop_name FROM {$this->table} m\n             LEFT JOIN shops s ON m.shop_id = s.id\n             ORDER BY m.shop_id ASC, m.nom ASC"
        );
    }

    public function findById($id, $shopId = null)
    {
        $sql = "SELECT * FROM {$this->table} WHERE id = ?";
        $params = [$id];
        if ($shopId) {
            $sql .= " AND shop_id = ?";
            $params[] = $shopId;
        }
        return $this->db->fetch($sql, $params);
    }

    public function create($data)
    {
        $sql = "INSERT INTO {$this->table}
                (shop_id, nom, description, prix, actif)
                VALUES (:shop_id, :nom, :description, :prix, :actif)";
        $this->db->execute($sql, [
            ':shop_id'    => $data['shop_id'],
            ':nom'        => $data['nom'],
            ':description' => $data['description'] ?? null,
            ':prix'       => $data['prix'] ?? 0,
            ':actif'      => $data['actif'] ?? 1,
        ]);
        return $this->db->lastInsertId();
    }

    public function update($id, $data)
    {
        $allowed = ['nom', 'description', 'prix', 'actif'];
        $fields = [];
        $params = [':id' => $id];

        foreach ($allowed as $field) {
            if (array_key_exists($field, $data)) {
                $fields[] = "$field = :$field";
                $params[":$field"] = $data[$field];
            }
        }

        if (empty($fields)) return false;

        $sql = "UPDATE {$this->table} SET " . implode(', ', $fields) . " WHERE id = :id";
        return $this->db->execute($sql, $params);
    }

    public function delete($id, $shopId = null)
    {
        $sql = "DELETE FROM {$this->table} WHERE id = ?";
        $params = [$id];
        if ($shopId) {
            $sql .= " AND shop_id = ?";
            $params[] = $shopId;
        }
        return $this->db->execute($sql, $params);
    }
}
