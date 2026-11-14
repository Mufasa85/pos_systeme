<?php

namespace App\Models;

class PayrollPayslip
{
    private $db;

    public function __construct()
    {
        $this->db = \App\Core\Database::getInstance();
    }

    public function findByPeriod($periodId, $shopId = null)
    {
        // LEFT JOIN sur `utilisateurs` : depuis la migration
        // 2026_10_25_user_delete_fk_set_null.sql, `payroll_employees.user_id`
        // peut être NULL (employé supprimé). Un INNER JOIN masquerait alors
        // les bulletins de paie de cet employé. Le COALESCE affiche
        // « Utilisateur supprimé » à la place.
        $sql = 'SELECT pp.*, COALESCE(u.nom_complet, \'' . User::DELETED_LABEL . '\') as nom_complet, pe.matricule
                FROM payroll_payslips pp
                JOIN payroll_employees pe ON pp.employee_id = pe.id
                LEFT JOIN utilisateurs u ON pe.user_id = u.id
                WHERE pp.payroll_period_id = ?';
        $params = [$periodId];
        if ($shopId) {
            $sql .= ' AND pp.shop_id = ?';
            $params[] = $shopId;
        }
        $sql .= ' ORDER BY nom_complet ASC';
        return $this->db->fetchAll($sql, $params);
    }

    public function findByEmployeeAndPeriod($employeeId, $periodId)
    {
        return $this->db->fetch(
            'SELECT * FROM payroll_payslips WHERE employee_id = ? AND payroll_period_id = ?',
            [$employeeId, $periodId]
        );
    }

    public function findByEmployee($employeeId)
    {
        return $this->db->fetchAll(
            'SELECT pp.*, p.month, p.year
             FROM payroll_payslips pp
             JOIN payroll_periods p ON pp.payroll_period_id = p.id
             WHERE pp.employee_id = ?
             ORDER BY p.year DESC, p.month DESC',
            [$employeeId]
        );
    }

    public function findById($id, $shopId = null)
    {
        // LEFT JOIN : `payroll_employees.user_id` peut être NULL si l'employé
        // a été supprimé (cf. migration 2026_10_25_user_delete_fk_set_null.sql).
        $sql = 'SELECT pp.*, COALESCE(u.nom_complet, \'' . User::DELETED_LABEL . '\') as nom_complet, pe.matricule, p.month, p.year
                FROM payroll_payslips pp
                JOIN payroll_employees pe ON pp.employee_id = pe.id
                LEFT JOIN utilisateurs u ON pe.user_id = u.id
                JOIN payroll_periods p ON pp.payroll_period_id = p.id
                WHERE pp.id = ?';
        $params = [$id];
        if ($shopId) {
            $sql .= ' AND pp.shop_id = ?';
            $params[] = $shopId;
        }
        return $this->db->fetch($sql, $params);
    }

    public function create($data)
    {
        $sql = 'INSERT INTO payroll_payslips
                (shop_id, employee_id, payroll_period_id, gross_amount, taxable_amount, cnss_base,
                 total_deductions, employer_charges, employer_cost, net_amount, status, pdf_path)
                VALUES
                (:shop_id, :employee_id, :payroll_period_id, :gross_amount, :taxable_amount, :cnss_base,
                 :total_deductions, :employer_charges, :employer_cost, :net_amount, :status, :pdf_path)';
        $this->db->query($sql, [
            ':shop_id'          => $data['shop_id'] ?? null,
            ':employee_id'      => $data['employee_id'] ?? null,
            ':payroll_period_id' => $data['payroll_period_id'] ?? null,
            ':gross_amount'     => $data['gross_amount'] ?? 0,
            ':taxable_amount'   => $data['taxable_amount'] ?? 0,
            ':cnss_base'        => $data['cnss_base'] ?? 0,
            ':total_deductions' => $data['total_deductions'] ?? 0,
            ':employer_charges' => $data['employer_charges'] ?? 0,
            ':employer_cost'    => $data['employer_cost'] ?? 0,
            ':net_amount'       => $data['net_amount'] ?? 0,
            ':status'           => $data['status'] ?? 'draft',
            ':pdf_path'         => $data['pdf_path'] ?? null,
        ]);
        return $this->db->lastInsertId();
    }

    public function createOrUpdate($data)
    {
        $existing = $this->findByEmployeeAndPeriod($data['employee_id'], $data['payroll_period_id']);
        if ($existing) {
            return $this->update($existing['id'], $data) ? $existing['id'] : false;
        }
        return $this->create($data);
    }

    public function update($id, $data)
    {
        $fields = [];
        $params = [':id' => $id];
        $allowed = ['shop_id','employee_id','payroll_period_id','gross_amount','taxable_amount','cnss_base','total_deductions','employer_charges','employer_cost','net_amount','status','pdf_path'];
        foreach ($allowed as $field) {
            if (isset($data[$field])) {
                $fields[] = "$field = :$field";
                $params[":$field"] = $data[$field];
            }
        }
        if (empty($fields)) {
            return false;
        }
        $sql = 'UPDATE payroll_payslips SET ' . implode(', ', $fields) . ' WHERE id = :id';
        return $this->db->execute($sql, $params);
    }

    public function delete($id)
    {
        return $this->db->execute('DELETE FROM payroll_payslips WHERE id = ?', [$id]);
    }

    /**
     * Dernier bulletin verrouillé (validé ou payé) d'un employé.
     *
     * Sert de garde-fou : la fiche de rémunération d'un agent qui a un bulletin
     * validé ou payé ne doit plus être modifiable.
     *
     * @return array|false
     */
    public function findLockedByEmployee($employeeId, $shopId = null)
    {
        $sql = 'SELECT * FROM payroll_payslips
                WHERE employee_id = ? AND status IN (\'validated\', \'paid\')';
        $params = [$employeeId];
        if ($shopId) {
            $sql .= ' AND shop_id = ?';
            $params[] = $shopId;
        }
        $sql .= ' ORDER BY id DESC LIMIT 1';
        return $this->db->fetch($sql, $params);
    }

    /**
     * Compte les bulletins d'une période par statut.
     *
     * @return array<string, int>
     */
    public function countByStatus($periodId, $shopId = null)
    {
        $sql = 'SELECT status, COUNT(*) AS n FROM payroll_payslips
                WHERE payroll_period_id = ?';
        $params = [$periodId];
        if ($shopId) {
            $sql .= ' AND shop_id = ?';
            $params[] = $shopId;
        }
        $sql .= ' GROUP BY status';

        $counts = [];
        foreach ($this->db->fetchAll($sql, $params) as $row) {
            $counts[$row['status']] = (int)$row['n'];
        }
        return $counts;
    }

    public function updateStatus($id, $status)
    {
        return $this->db->execute('UPDATE payroll_payslips SET status = ? WHERE id = ?', [$status, $id]);
    }

    public function totalByPeriod($periodId)
    {
        $row = $this->db->fetch(
            'SELECT COALESCE(SUM(gross_amount),0) AS gross, COALESCE(SUM(net_amount),0) AS net
             FROM payroll_payslips WHERE payroll_period_id = ?',
            [$periodId]
        );
        return $row ?: ['gross' => 0, 'net' => 0];
    }
}
