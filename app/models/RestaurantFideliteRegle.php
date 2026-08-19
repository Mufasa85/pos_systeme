<?php

namespace App\Models;

class RestaurantFideliteRegle
{
    private $db;
    private $table = 'restaurant_fidelite_regles';

    public function __construct()
    {
        $this->db = \App\Core\Database::getInstance();
    }

    public function all($shopId = null)
    {
        if ($shopId) {
            return $this->db->fetchAll(
                "SELECT * FROM {$this->table} WHERE shop_id = ? ORDER BY actif DESC, montant_depense ASC",
                [$shopId]
            );
        }
        return $this->db->fetchAll(
            "SELECT r.*, s.nom as shop_name FROM {$this->table} r\n             LEFT JOIN shops s ON r.shop_id = s.id\n             ORDER BY r.shop_id ASC, r.montant_depense ASC"
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
                (shop_id, montant_depense, points_gagnes, points_requis, remise_points, actif)
                VALUES (:shop_id, :montant_depense, :points_gagnes, :points_requis, :remise_points, :actif)";
        $this->db->execute($sql, [
            ':shop_id'        => $data['shop_id'],
            ':montant_depense' => $data['montant_depense'],
            ':points_gagnes'  => $data['points_gagnes'] ?? 0,
            ':points_requis'  => $data['points_requis'] ?? 0,
            ':remise_points'  => $data['remise_points'] ?? 0,
            ':actif'          => $data['actif'] ?? 1,
        ]);
        return $this->db->lastInsertId();
    }

    public function update($id, $data)
    {
        $allowed = ['montant_depense', 'points_gagnes', 'points_requis', 'remise_points', 'actif'];
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
