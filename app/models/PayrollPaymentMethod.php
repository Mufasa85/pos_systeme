<?php

namespace App\Models;

class PayrollPaymentMethod
{
    private $db;

    public function __construct()
    {
        $this->db = \App\Core\Database::getInstance();
    }

    public function all($shopId = null)
    {
        $sql = "SELECT * FROM payroll_payment_methods";
        $params = [];
        if ($shopId) {
            $sql .= " WHERE (shop_id = ? OR shop_id IS NULL)";
            $params[] = $shopId;
        }
        $sql .= " ORDER BY label ASC";
        return $this->db->fetchAll($sql, $params);
    }

    public function active($shopId = null)
    {
        $rows = $this->all($shopId);
        return array_values(array_filter($rows, fn($r) => (int)($r['is_active'] ?? 1) === 1));
    }

    public function findById($id)
    {
        return $this->db->fetch("SELECT * FROM payroll_payment_methods WHERE id = ?", [$id]);
    }

    public function findByCode($code, $shopId = null)
    {
        if ($shopId) {
            $sql = "SELECT * FROM payroll_payment_methods
                    WHERE code = ? AND (shop_id = ? OR shop_id IS NULL)
                    ORDER BY shop_id IS NULL ASC
                    LIMIT 1";
            return $this->db->fetch($sql, [$code, $shopId]);
        }
        return $this->db->fetch("SELECT * FROM payroll_payment_methods WHERE code = ? AND shop_id IS NULL LIMIT 1", [$code]);
    }

    public function create($data)
    {
        $sql = "INSERT INTO payroll_payment_methods
                (shop_id, code, label, is_active)
                VALUES
                (:shop_id, :code, :label, :is_active)";
        $this->db->query($sql, [
            ':shop_id'   => $data['shop_id'] ?? null,
            ':code'      => $data['code'] ?? null,
            ':label'     => $data['label'] ?? null,
            ':is_active' => $data['is_active'] ?? 1,
        ]);
        return $this->db->lastInsertId();
    }

    public function update($id, $data)
    {
        $fields = [];
        $params = [':id' => $id];
        $allowed = ['shop_id','code','label','is_active'];
        foreach ($allowed as $field) {
            if (isset($data[$field])) {
                $fields[] = "$field = :$field";
                $params[":$field"] = $data[$field];
            }
        }
        if (empty($fields)) return false;
        $sql = "UPDATE payroll_payment_methods SET " . implode(", ", $fields) . " WHERE id = :id";
        return $this->db->execute($sql, $params);
    }

    public function delete($id)
    {
        return $this->db->execute("DELETE FROM payroll_payment_methods WHERE id = ?", [$id]);
    }
}
