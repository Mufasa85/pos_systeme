<?php

namespace App\Models;

class PayrollContract
{
    private $db;

    public function __construct()
    {
        $this->db = \App\Core\Database::getInstance();
    }

    public function all($shopId = null, $superAdmin = false)
    {
        $sql = "SELECT pc.*, pe.shop_id, u.nom_complet
                FROM payroll_contracts pc
                JOIN payroll_employees pe ON pc.employee_id = pe.id
                JOIN utilisateurs u ON pe.user_id = u.id";
        $params = [];
        if (!$superAdmin && $shopId) {
            $sql .= " WHERE pe.shop_id = ?";
            $params[] = $shopId;
        }
        $sql .= " ORDER BY u.nom_complet ASC, pc.start_date DESC";
        return $this->db->fetchAll($sql, $params);
    }

    public function findById($id, $shopId = null, $superAdmin = false)
    {
        $sql = "SELECT pc.*, pe.shop_id
                FROM payroll_contracts pc
                JOIN payroll_employees pe ON pc.employee_id = pe.id
                WHERE pc.id = ?";
        $params = [$id];
        if (!$superAdmin && $shopId) {
            $sql .= " AND pe.shop_id = ?";
            $params[] = $shopId;
        }
        return $this->db->fetch($sql, $params);
    }

    public function findByEmployee($employeeId)
    {
        return $this->db->fetchAll(
            "SELECT * FROM payroll_contracts WHERE employee_id = ? ORDER BY start_date DESC",
            [$employeeId]
        );
    }

    public function findActiveByEmployee($employeeId)
    {
        return $this->db->fetch(
            "SELECT * FROM payroll_contracts
             WHERE employee_id = ? AND is_active = 1
             ORDER BY start_date DESC LIMIT 1",
            [$employeeId]
        );
    }

    public function create($data)
    {
        $sql = "INSERT INTO payroll_contracts
                (employee_id, type, start_date, end_date, base_salary, sursalary, pay_type, currency, is_active)
                VALUES
                (:employee_id, :type, :start_date, :end_date, :base_salary, :sursalary, :pay_type, :currency, :is_active)";
        $this->db->query($sql, $this->params($data));
        return $this->db->lastInsertId();
    }

    public function update($id, $data)
    {
        $fields = [];
        $params = [':id' => $id];
        $allowed = ['employee_id','type','start_date','end_date','base_salary','sursalary','pay_type','currency','is_active'];
        foreach ($allowed as $field) {
            if (isset($data[$field])) {
                $fields[] = "$field = :$field";
                $params[":$field"] = $data[$field];
            }
        }
        if (empty($fields)) return false;
        $sql = "UPDATE payroll_contracts SET " . implode(", ", $fields) . " WHERE id = :id";
        return $this->db->execute($sql, $params);
    }

    public function delete($id)
    {
        return $this->db->execute("DELETE FROM payroll_contracts WHERE id = ?", [$id]);
    }

    private function params($data)
    {
        return [
            ':employee_id' => $data['employee_id'] ?? null,
            ':type'        => $data['type'] ?? 'CDI',
            ':start_date'  => $data['start_date'] ?? null,
            ':end_date'    => $data['end_date'] ?? null,
            ':base_salary' => $data['base_salary'] ?? 0,
            ':sursalary'   => $data['sursalary'] ?? 0,
            ':pay_type'    => $data['pay_type'] ?? 'monthly',
            ':currency'    => $data['currency'] ?? 'XOF',
            ':is_active'   => $data['is_active'] ?? 1,
        ];
    }
}
