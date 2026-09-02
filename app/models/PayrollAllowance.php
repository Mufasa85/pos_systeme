<?php

namespace App\Models;

class PayrollAllowance
{
    private $db;

    public function __construct()
    {
        $this->db = \App\Core\Database::getInstance();
    }

    public function all($shopId = null)
    {
        if (!$shopId) {
            return $this->db->fetchAll('SELECT * FROM payroll_allowances WHERE shop_id IS NULL ORDER BY code ASC');
        }
        return $this->db->fetchAll(
            'SELECT a.* FROM payroll_allowances a
             WHERE a.shop_id = ? OR (a.shop_id IS NULL AND NOT EXISTS (
                 SELECT 1 FROM payroll_allowances b WHERE b.code = a.code AND b.shop_id = ?
             ))
             ORDER BY a.code ASC',
            [$shopId, $shopId]
        );
    }

    public function active($shopId = null)
    {
        $rows = $this->all($shopId);
        return array_values(array_filter($rows, fn ($r) => (int)($r['is_active'] ?? 1) === 1));
    }

    public function findById($id)
    {
        return $this->db->fetch('SELECT * FROM payroll_allowances WHERE id = ?', [$id]);
    }

    public function findByCode($code, $shopId = null)
    {
        if ($shopId) {
            $sql = 'SELECT * FROM payroll_allowances
                    WHERE code = ? AND (shop_id = ? OR shop_id IS NULL)
                    ORDER BY shop_id IS NULL ASC
                    LIMIT 1';
            return $this->db->fetch($sql, [$code, $shopId]);
        }
        return $this->db->fetch('SELECT * FROM payroll_allowances WHERE code = ? AND shop_id IS NULL LIMIT 1', [$code]);
    }

    public function create($data)
    {
        $sql = 'INSERT INTO payroll_allowances
                (shop_id, code, label, calculation_type, amount, is_active)
                VALUES
                (:shop_id, :code, :label, :calculation_type, :amount, :is_active)';
        $this->db->query($sql, [
            ':shop_id'         => $data['shop_id'] ?? null,
            ':code'            => $data['code'] ?? null,
            ':label'           => $data['label'] ?? null,
            ':calculation_type' => $data['calculation_type'] ?? 'fixed',
            ':amount'          => $data['amount'] ?? 0,
            ':is_active'       => $data['is_active'] ?? 1,
        ]);
        return $this->db->lastInsertId();
    }

    public function update($id, $data)
    {
        $fields = [];
        $params = [':id' => $id];
        $allowed = ['shop_id','code','label','calculation_type','amount','is_active'];
        foreach ($allowed as $field) {
            if (isset($data[$field])) {
                $fields[] = "$field = :$field";
                $params[":$field"] = $data[$field];
            }
        }
        if (empty($fields)) {
            return false;
        }
        $sql = 'UPDATE payroll_allowances SET ' . implode(', ', $fields) . ' WHERE id = :id';
        return $this->db->execute($sql, $params);
    }

    public function delete($id)
    {
        return $this->db->execute('DELETE FROM payroll_allowances WHERE id = ?', [$id]);
    }
}
