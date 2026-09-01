<?php

namespace App\Models;

class PayrollAttendance
{
    private $db;

    public function __construct()
    {
        $this->db = \App\Core\Database::getInstance();
    }

    public function findByPeriod($periodId, $shopId = null)
    {
        $sql = 'SELECT pa.*, u.nom_complet
                FROM payroll_attendance pa
                JOIN payroll_employees pe ON pa.employee_id = pe.id
                JOIN utilisateurs u ON pe.user_id = u.id
                WHERE pa.payroll_period_id = ?';
        $params = [$periodId];
        if ($shopId) {
            $sql .= ' AND pa.shop_id = ?';
            $params[] = $shopId;
        }
        $sql .= ' ORDER BY u.nom_complet ASC';
        return $this->db->fetchAll($sql, $params);
    }

    public function findByEmployeeAndPeriod($employeeId, $periodId)
    {
        return $this->db->fetch(
            'SELECT * FROM payroll_attendance WHERE employee_id = ? AND payroll_period_id = ?',
            [$employeeId, $periodId]
        );
    }

    public function createOrUpdate($data)
    {
        $existing = $this->findByEmployeeAndPeriod($data['employee_id'], $data['payroll_period_id']);
        if ($existing) {
            return $this->update($existing['id'], $data);
        }
        return $this->create($data);
    }

    public function create($data)
    {
        $sql = 'INSERT INTO payroll_attendance
                (shop_id, employee_id, payroll_period_id, worked_days, worked_hours, expected_working_days, paid_days, notes)
                VALUES
                (:shop_id, :employee_id, :payroll_period_id, :worked_days, :worked_hours, :expected_working_days, :paid_days, :notes)';
        $this->db->query($sql, [
            ':shop_id'             => $data['shop_id'] ?? null,
            ':employee_id'         => $data['employee_id'] ?? null,
            ':payroll_period_id'   => $data['payroll_period_id'] ?? null,
            ':worked_days'         => $data['worked_days'] ?? 0,
            ':worked_hours'        => $data['worked_hours'] ?? 0,
            ':expected_working_days' => $data['expected_working_days'] ?? 22.00,
            ':paid_days'           => $data['paid_days'] ?? null,
            ':notes'               => $data['notes'] ?? null,
        ]);
        return $this->db->lastInsertId();
    }

    public function update($id, $data)
    {
        $fields = [];
        $params = [':id' => $id];
        $allowed = ['shop_id','employee_id','payroll_period_id','worked_days','worked_hours','expected_working_days','paid_days','notes'];
        foreach ($allowed as $field) {
            if (isset($data[$field])) {
                $fields[] = "$field = :$field";
                $params[":$field"] = $data[$field];
            }
        }
        if (empty($fields)) {
            return false;
        }
        $sql = 'UPDATE payroll_attendance SET ' . implode(', ', $fields) . ' WHERE id = :id';
        return $this->db->execute($sql, $params);
    }

    public function delete($id)
    {
        return $this->db->execute('DELETE FROM payroll_attendance WHERE id = ?', [$id]);
    }

    public function bulkSave($rows)
    {
        $created = 0;
        foreach ($rows as $row) {
            if ($this->createOrUpdate($row)) {
                $created++;
            }
        }
        return $created;
    }
}
