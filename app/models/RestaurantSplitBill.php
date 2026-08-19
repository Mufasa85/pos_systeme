<?php

namespace App\Models;

class RestaurantSplitBill
{
    private $db;
    private $table = 'restaurant_split_bills';

    public function __construct()
    {
        $this->db = \App\Core\Database::getInstance();
    }

    public function all($commandeId)
    {
        return $this->db->fetchAll("SELECT * FROM {$this->table} WHERE commande_id = ? ORDER BY id ASC", [$commandeId]);
    }

    public function findById($id)
    {
        return $this->db->fetch("SELECT * FROM {$this->table} WHERE id = ?", [$id]);
    }

    public function create($data)
    {
        $sql = "INSERT INTO {$this->table} (commande_id, label, total, paid_amount)
                VALUES (:commande_id, :label, :total, 0)";
        $this->db->execute($sql, [
            ':commande_id' => $data['commande_id'],
            ':label'       => $data['label'] ?? 'Part',
            ':total'       => $data['total'] ?? 0,
        ]);
        return $this->db->lastInsertId();
    }

    public function update($id, $data)
    {
        $allowed = ['label', 'total', 'paid_amount'];
        $fields = [];
        $params = [':id' => $id];

        foreach ($allowed as $field) {
            if (array_key_exists($field, $data)) {
                $fields[] = "$field = :$field";
                $params[":$field"] = $data[$field];
            }
        }

        if (empty($fields)) {
            return false;
        }

        $sql = "UPDATE {$this->table} SET " . implode(', ', $fields) . " WHERE id = :id";
        return $this->db->execute($sql, $params);
    }

    public function delete($id)
    {
        return $this->db->execute("DELETE FROM {$this->table} WHERE id = ?", [$id]);
    }

    public function recalcTotal($splitBillId)
    {
        $total = $this->db->fetch(
            "SELECT COALESCE(SUM(prix_total), 0) as total\n             FROM restaurant_commande_details\n             WHERE split_bill_id = ?",
            [$splitBillId]
        );
        return $this->update($splitBillId, ['total' => $total['total'] ?? 0]);
    }

    public function getSolde($splitBillId)
    {
        $split = $this->findById($splitBillId);
        if (!$split) return 0;
        return max(0, (float)$split['total'] - (float)$split['paid_amount']);
    }
}
