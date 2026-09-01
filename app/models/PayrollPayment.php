<?php

namespace App\Models;

class PayrollPayment
{
    private $db;

    public function __construct()
    {
        $this->db = \App\Core\Database::getInstance();
    }

    public function findByPeriod($periodId, $shopId = null)
    {
        $sql = 'SELECT pay.*, u.nom_complet
                FROM payroll_payments pay
                JOIN payroll_payslips pp ON pay.payslip_id = pp.id
                JOIN payroll_employees pe ON pp.employee_id = pe.id
                JOIN utilisateurs u ON pe.user_id = u.id
                WHERE pp.payroll_period_id = ?';
        $params = [$periodId];
        if ($shopId) {
            $sql .= ' AND pay.shop_id = ?';
            $params[] = $shopId;
        }
        $sql .= ' ORDER BY pay.paid_at DESC';
        return $this->db->fetchAll($sql, $params);
    }

    public function findByPayslip($payslipId)
    {
        return $this->db->fetchAll(
            'SELECT * FROM payroll_payments WHERE payslip_id = ? ORDER BY paid_at DESC',
            [$payslipId]
        );
    }

    public function findById($id, $shopId = null)
    {
        $sql = 'SELECT * FROM payroll_payments WHERE id = ?';
        $params = [$id];
        if ($shopId) {
            $sql .= ' AND shop_id = ?';
            $params[] = $shopId;
        }
        return $this->db->fetch($sql, $params);
    }

    public function create($data)
    {
        $sql = 'INSERT INTO payroll_payments
                (shop_id, payslip_id, payment_method_id, amount, paid_at, reference, status, created_by)
                VALUES
                (:shop_id, :payslip_id, :payment_method_id, :amount, :paid_at, :reference, :status, :created_by)';
        $this->db->query($sql, [
            ':shop_id'          => $data['shop_id'] ?? null,
            ':payslip_id'       => $data['payslip_id'] ?? null,
            ':payment_method_id' => $data['payment_method_id'] ?? null,
            ':amount'           => $data['amount'] ?? 0,
            ':paid_at'          => $data['paid_at'] ?? null,
            ':reference'        => $data['reference'] ?? null,
            ':status'           => $data['status'] ?? 'pending',
            ':created_by'       => $data['created_by'] ?? null,
        ]);
        return $this->db->lastInsertId();
    }

    public function update($id, $data)
    {
        $fields = [];
        $params = [':id' => $id];
        $allowed = ['shop_id','payslip_id','payment_method_id','amount','paid_at','reference','status','created_by'];
        foreach ($allowed as $field) {
            if (isset($data[$field])) {
                $fields[] = "$field = :$field";
                $params[":$field"] = $data[$field];
            }
        }
        if (empty($fields)) {
            return false;
        }
        $sql = 'UPDATE payroll_payments SET ' . implode(', ', $fields) . ' WHERE id = :id';
        return $this->db->execute($sql, $params);
    }

    public function delete($id)
    {
        return $this->db->execute('DELETE FROM payroll_payments WHERE id = ?', [$id]);
    }

    public function totalPaidByPayslip($payslipId)
    {
        $row = $this->db->fetch(
            "SELECT COALESCE(SUM(amount), 0) AS total FROM payroll_payments
             WHERE payslip_id = ? AND status = 'paid'",
            [$payslipId]
        );
        return $row['total'] ?? 0;
    }
}
