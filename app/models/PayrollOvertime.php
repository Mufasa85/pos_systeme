<?php

namespace App\Models;

class PayrollOvertime
{
    private $db;

    public function __construct()
    {
        $this->db = \App\Core\Database::getInstance();
    }

    public function findByPeriod($periodId, $shopId = null)
    {
        $sql = "SELECT po.*, u.nom_complet
                FROM payroll_overtimes po
                JOIN payroll_employees pe ON po.employee_id = pe.id
                JOIN utilisateurs u ON pe.user_id = u.id
                WHERE po.payroll_period_id = ?";
        $params = [$periodId];
        if ($shopId) {
            $sql .= " AND po.shop_id = ?";
            $params[] = $shopId;
        }
        $sql .= " ORDER BY u.nom_complet ASC, po.work_date ASC";
        return $this->db->fetchAll($sql, $params);
    }

    public function findByEmployeeAndPeriod($employeeId, $periodId)
    {
        return $this->db->fetchAll(
            "SELECT * FROM payroll_overtimes
             WHERE employee_id = ? AND payroll_period_id = ?
             ORDER BY work_date ASC",
            [$employeeId, $periodId]
        );
    }

    public function findById($id)
    {
        return $this->db->fetch("SELECT * FROM payroll_overtimes WHERE id = ?", [$id]);
    }

    public function create($data)
    {
        $sql = "INSERT INTO payroll_overtimes
                (shop_id, employee_id, payroll_period_id, work_date, hours, rate_type, multiplier, amount)
                VALUES
                (:shop_id, :employee_id, :payroll_period_id, :work_date, :hours, :rate_type, :multiplier, :amount)";
        $this->db->query($sql, [
            ':shop_id'           => $data['shop_id'] ?? null,
            ':employee_id'       => $data['employee_id'] ?? null,
            ':payroll_period_id' => $data['payroll_period_id'] ?? null,
            ':work_date'         => $data['work_date'] ?? null,
            ':hours'             => $data['hours'] ?? 0,
            ':rate_type'         => $data['rate_type'] ?? 'normal_25',
            ':multiplier'        => $data['multiplier'] ?? 1.25,
            ':amount'            => $data['amount'] ?? null,
        ]);
        return $this->db->lastInsertId();
    }

    public function update($id, $data)
    {
        $fields = [];
        $params = [':id' => $id];
        $allowed = ['shop_id','employee_id','payroll_period_id','work_date','hours','rate_type','multiplier','amount'];
        foreach ($allowed as $field) {
            if (isset($data[$field])) {
                $fields[] = "$field = :$field";
                $params[":$field"] = $data[$field];
            }
        }
        if (empty($fields)) return false;
        $sql = "UPDATE payroll_overtimes SET " . implode(", ", $fields) . " WHERE id = :id";
        return $this->db->execute($sql, $params);
    }

    public function delete($id)
    {
        return $this->db->execute("DELETE FROM payroll_overtimes WHERE id = ?", [$id]);
    }

    public function totalAmountByEmployeeAndPeriod($employeeId, $periodId)
    {
        $row = $this->db->fetch(
            "SELECT COALESCE(SUM(amount), 0) AS total FROM payroll_overtimes
             WHERE employee_id = ? AND payroll_period_id = ?",
            [$employeeId, $periodId]
        );
        return $row['total'] ?? 0;
    }
}
