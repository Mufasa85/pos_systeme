<?php

namespace App\Models;

class PayrollAbsence
{
    private $db;

    public function __construct()
    {
        $this->db = \App\Core\Database::getInstance();
    }

    public function findByPeriod($periodId, $shopId = null)
    {
        $sql = 'SELECT pa.*, u.nom_complet
                FROM payroll_absences pa
                JOIN payroll_employees pe ON pa.employee_id = pe.id
                JOIN utilisateurs u ON pe.user_id = u.id
                WHERE pa.payroll_period_id = ?';
        $params = [$periodId];
        if ($shopId) {
            $sql .= ' AND pa.shop_id = ?';
            $params[] = $shopId;
        }
        $sql .= ' ORDER BY u.nom_complet ASC, pa.start_date ASC';
        return $this->db->fetchAll($sql, $params);
    }

    public function findByEmployee($employeeId)
    {
        return $this->db->fetchAll(
            'SELECT * FROM payroll_absences WHERE employee_id = ? ORDER BY start_date DESC',
            [$employeeId]
        );
    }

    public function findById($id)
    {
        return $this->db->fetch('SELECT * FROM payroll_absences WHERE id = ?', [$id]);
    }

    public function create($data)
    {
        $sql = 'INSERT INTO payroll_absences
                (shop_id, employee_id, payroll_period_id, type, start_date, end_date, days, is_paid, notes)
                VALUES
                (:shop_id, :employee_id, :payroll_period_id, :type, :start_date, :end_date, :days, :is_paid, :notes)';
        $this->db->query($sql, [
            ':shop_id'           => $data['shop_id'] ?? null,
            ':employee_id'       => $data['employee_id'] ?? null,
            ':payroll_period_id' => $data['payroll_period_id'] ?? null,
            ':type'              => $data['type'] ?? 'unjustified',
            ':start_date'        => $data['start_date'] ?? null,
            ':end_date'          => $data['end_date'] ?? null,
            ':days'              => $data['days'] ?? 0,
            ':is_paid'           => $data['is_paid'] ?? 0,
            ':notes'             => $data['notes'] ?? null,
        ]);
        return $this->db->lastInsertId();
    }

    public function update($id, $data)
    {
        $fields = [];
        $params = [':id' => $id];
        $allowed = ['shop_id','employee_id','payroll_period_id','type','start_date','end_date','days','is_paid','notes'];
        foreach ($allowed as $field) {
            if (isset($data[$field])) {
                $fields[] = "$field = :$field";
                $params[":$field"] = $data[$field];
            }
        }
        if (empty($fields)) {
            return false;
        }
        $sql = 'UPDATE payroll_absences SET ' . implode(', ', $fields) . ' WHERE id = :id';
        return $this->db->execute($sql, $params);
    }

    public function delete($id)
    {
        return $this->db->execute('DELETE FROM payroll_absences WHERE id = ?', [$id]);
    }

    public function totalDaysByEmployeeAndPeriod($employeeId, $periodId)
    {
        $row = $this->db->fetch(
            'SELECT COALESCE(SUM(days), 0) AS total FROM payroll_absences
             WHERE employee_id = ? AND payroll_period_id = ?',
            [$employeeId, $periodId]
        );
        return $row['total'] ?? 0;
    }
}
