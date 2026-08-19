<?php

namespace App\Models;

class PressingService
{
    private $db;
    private $table = 'pressing_services';

    public function __construct()
    {
        $this->db = \App\Core\Database::getInstance();
    }

    public function all($shopId = null)
    {
        $sql = "SELECT * FROM {$this->table} WHERE actif = 1";
        $params = [];
        if ($shopId) {
            $sql .= " AND shop_id = ?";
            $params[] = $shopId;
        }
        $sql .= " ORDER BY nom ASC";
        return $this->db->fetchAll($sql, $params);
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
                (shop_id, nom, description, duree_estimee)
                VALUES (:shop_id, :nom, :description, :duree_estimee)";
        $this->db->execute($sql, [
            ':shop_id'       => $data['shop_id'],
            ':nom'           => $data['nom'],
            ':description'   => $data['description'] ?? null,
            ':duree_estimee' => $data['duree_estimee'] ?? 0,
        ]);
        return $this->db->lastInsertId();
    }

    public function update($id, $data)
    {
        $sql = "UPDATE {$this->table}
                SET nom = :nom, description = :description, duree_estimee = :duree_estimee
                WHERE id = :id";
        return $this->db->execute($sql, [
            ':id'            => $id,
            ':nom'           => $data['nom'],
            ':description'   => $data['description'] ?? null,
            ':duree_estimee' => $data['duree_estimee'] ?? 0,
        ]);
    }

    public function delete($id)
    {
        return $this->db->execute("UPDATE {$this->table} SET actif = 0 WHERE id = ?", [$id]);
    }
}
