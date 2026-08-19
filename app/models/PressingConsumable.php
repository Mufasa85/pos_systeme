<?php

namespace App\Models;

class PressingConsumable
{
    private $db;
    private $table = 'pressing_consumables';
    private $usageTable = 'pressing_consumable_usages';

    public function __construct()
    {
        $this->db = \App\Core\Database::getInstance();
    }

    public function all($shopId = null)
    {
        $sql = "SELECT * FROM {$this->table}";
        $params = [];
        if ($shopId) {
            $sql .= " WHERE shop_id = ?";
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
                (shop_id, nom, quantite, unite, stock_minimum)
                VALUES (:shop_id, :nom, :quantite, :unite, :stock_minimum)";
        $this->db->execute($sql, [
            ':shop_id'       => $data['shop_id'],
            ':nom'           => $data['nom'],
            ':quantite'      => $data['quantite'] ?? 0,
            ':unite'         => $data['unite'] ?? 'unité',
            ':stock_minimum' => $data['stock_minimum'] ?? 0,
        ]);
        return $this->db->lastInsertId();
    }

    public function update($id, $data)
    {
        $sql = "UPDATE {$this->table}
                SET nom = :nom, quantite = :quantite, unite = :unite, stock_minimum = :stock_minimum
                WHERE id = :id";
        return $this->db->execute($sql, [
            ':id'            => $id,
            ':nom'           => $data['nom'],
            ':quantite'      => $data['quantite'] ?? 0,
            ':unite'         => $data['unite'] ?? 'unité',
            ':stock_minimum' => $data['stock_minimum'] ?? 0,
        ]);
    }

    public function delete($id)
    {
        return $this->db->execute("DELETE FROM {$this->table} WHERE id = ?", [$id]);
    }

    public function consume($consumableId, $depotId, $quantity, $userId)
    {
        $sql = "INSERT INTO {$this->usageTable}
                (depot_id, consumable_id, quantite, created_by)
                VALUES (:depot_id, :consumable_id, :quantite, :created_by)";
        $this->db->execute($sql, [
            ':depot_id'     => $depotId,
            ':consumable_id' => $consumableId,
            ':quantite'     => $quantity,
            ':created_by'   => $userId,
        ]);

        return $this->db->execute(
            "UPDATE {$this->table} SET quantite = quantite - ? WHERE id = ?",
            [$quantity, $consumableId]
        );
    }

    public function getUsageByDepot($depotId)
    {
        return $this->db->fetchAll(
            "SELECT u.*, c.nom as consumable_nom, c.unite
             FROM {$this->usageTable} u
             LEFT JOIN {$this->table} c ON u.consumable_id = c.id
             WHERE u.depot_id = ?
             ORDER BY u.created_at ASC",
            [$depotId]
        );
    }
}
