<?php

namespace App\Models;

class PayrollContributionRate
{
    private $db;

    public function __construct()
    {
        $this->db = \App\Core\Database::getInstance();
    }

    public function all($shopId = null)
    {
        if (!$shopId) {
            return $this->db->fetchAll('SELECT * FROM payroll_contribution_rates WHERE shop_id IS NULL ORDER BY code ASC');
        }
        return $this->db->fetchAll(
            'SELECT a.* FROM payroll_contribution_rates a
             WHERE a.shop_id = ? OR (a.shop_id IS NULL AND NOT EXISTS (
                 SELECT 1 FROM payroll_contribution_rates b WHERE b.code = a.code AND b.shop_id = ?
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
        return $this->db->fetch('SELECT * FROM payroll_contribution_rates WHERE id = ?', [$id]);
    }

    public function findByCode($code, $shopId = null)
    {
        if ($shopId) {
            $sql = 'SELECT * FROM payroll_contribution_rates
                    WHERE code = ? AND (shop_id = ? OR shop_id IS NULL)
                    ORDER BY shop_id IS NULL ASC
                    LIMIT 1';
            return $this->db->fetch($sql, [$code, $shopId]);
        }
        return $this->db->fetch('SELECT * FROM payroll_contribution_rates WHERE code = ? AND shop_id IS NULL LIMIT 1', [$code]);
    }

    public function create($data)
    {
        $sql = 'INSERT INTO payroll_contribution_rates
                (shop_id, code, label, employee_rate, employer_rate, is_active)
                VALUES
                (:shop_id, :code, :label, :employee_rate, :employer_rate, :is_active)';
        $this->db->query($sql, [
            ':shop_id'       => $data['shop_id'] ?? null,
            ':code'          => $data['code'] ?? null,
            ':label'         => $data['label'] ?? null,
            ':employee_rate' => $data['employee_rate'] ?? 0,
            ':employer_rate' => $data['employer_rate'] ?? 0,
            ':is_active'     => $data['is_active'] ?? 1,
        ]);
        return $this->db->lastInsertId();
    }

    public function update($id, $data)
    {
        $fields = [];
        $params = [':id' => $id];
        $allowed = ['shop_id','code','label','employee_rate','employer_rate','is_active'];
        foreach ($allowed as $field) {
            if (isset($data[$field])) {
                $fields[] = "$field = :$field";
                $params[":$field"] = $data[$field];
            }
        }
        if (empty($fields)) {
            return false;
        }
        $sql = 'UPDATE payroll_contribution_rates SET ' . implode(', ', $fields) . ' WHERE id = :id';
        return $this->db->execute($sql, $params);
    }

    public function delete($id)
    {
        return $this->db->execute('DELETE FROM payroll_contribution_rates WHERE id = ?', [$id]);
    }
}
