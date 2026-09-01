<?php

namespace App\Models;

class PayrollPayslipLine
{
    private $db;

    public function __construct()
    {
        $this->db = \App\Core\Database::getInstance();
    }

    public function forPayslip($payslipId)
    {
        return $this->db->fetchAll(
            'SELECT * FROM payroll_payslip_lines WHERE payslip_id = ? ORDER BY sort_order ASC, id ASC',
            [$payslipId]
        );
    }

    public function create($data)
    {
        $sql = 'INSERT INTO payroll_payslip_lines
                (payslip_id, code, label, type, quantity, rate, amount, sort_order)
                VALUES
                (:payslip_id, :code, :label, :type, :quantity, :rate, :amount, :sort_order)';
        $this->db->query($sql, [
            ':payslip_id' => $data['payslip_id'] ?? null,
            ':code'       => $data['code'] ?? null,
            ':label'      => $data['label'] ?? null,
            ':type'       => $data['type'] ?? 'earning',
            ':quantity'   => $data['quantity'] ?? null,
            ':rate'       => $data['rate'] ?? null,
            ':amount'     => $data['amount'] ?? 0,
            ':sort_order' => $data['sort_order'] ?? 0,
        ]);
        return $this->db->lastInsertId();
    }

    public function bulkInsert($payslipId, $lines)
    {
        $this->deleteByPayslip($payslipId);
        $created = 0;
        foreach ($lines as $sort => $line) {
            $line['payslip_id'] = $payslipId;
            $line['sort_order'] = $line['sort_order'] ?? $sort;
            if ($this->create($line)) {
                $created++;
            }
        }
        return $created;
    }

    public function deleteByPayslip($payslipId)
    {
        return $this->db->execute('DELETE FROM payroll_payslip_lines WHERE payslip_id = ?', [$payslipId]);
    }

    public function delete($id)
    {
        return $this->db->execute('DELETE FROM payroll_payslip_lines WHERE id = ?', [$id]);
    }

    public function totalsByPayslip($payslipId)
    {
        $rows = $this->db->fetchAll(
            'SELECT type, COALESCE(SUM(amount), 0) AS total
             FROM payroll_payslip_lines
             WHERE payslip_id = ?
             GROUP BY type',
            [$payslipId]
        );
        $totals = ['earning' => 0, 'deduction' => 0, 'employer' => 0];
        foreach ($rows as $row) {
            $totals[$row['type']] = $row['total'];
        }
        return $totals;
    }

    public function contributionsByPeriod($periodId, $shopId = null)
    {
        $sql = "SELECT pl.code, pl.label, COALESCE(SUM(pl.amount), 0) AS total
                FROM payroll_payslip_lines pl
                JOIN payroll_payslips pp ON pl.payslip_id = pp.id
                WHERE pl.type = 'employer' AND pp.payroll_period_id = ?";
        $params = [$periodId];
        if ($shopId) {
            $sql .= ' AND pp.shop_id = ?';
            $params[] = $shopId;
        }
        $sql .= ' GROUP BY pl.code, pl.label ORDER BY pl.code';
        return $this->db->fetchAll($sql, $params);
    }
}
