<?php

namespace App\Models;

class PayrollEmployee
{
    private $db;

    public function __construct()
    {
        $this->db = \App\Core\Database::getInstance();
    }

    public function all($shopId = null, $superAdmin = false)
    {
        $sql = "SELECT pe.*, u.nom_complet, u.email, u.telephone, u.nom_utilisateur
                FROM payroll_employees pe
                JOIN utilisateurs u ON pe.user_id = u.id";
        $params = [];
        if (!$superAdmin && $shopId) {
            $sql .= " WHERE pe.shop_id = ?";
            $params[] = $shopId;
        }
        $sql .= " ORDER BY u.nom_complet ASC";
        return $this->db->fetchAll($sql, $params);
    }

    public function findById($id, $shopId = null, $superAdmin = false)
    {
        $sql = "SELECT pe.*, u.nom_complet, u.email, u.telephone, u.nom_utilisateur
                FROM payroll_employees pe
                JOIN utilisateurs u ON pe.user_id = u.id
                WHERE pe.id = ?";
        $params = [$id];
        if (!$superAdmin && $shopId) {
            $sql .= " AND pe.shop_id = ?";
            $params[] = $shopId;
        }
        return $this->db->fetch($sql, $params);
    }

    public function findByUserId($userId)
    {
        return $this->db->fetch("SELECT * FROM payroll_employees WHERE user_id = ?", [$userId]);
    }

    public function create($data)
    {
        $sql = "INSERT INTO payroll_employees
                (user_id, shop_id, matricule, device_user_id, hire_date, iban, cnss_number, direction, job_title, sitaf, tax_dependents, cat_leg, cat_prof, ier_rate, department_id, job_category_id, status)
                VALUES
                (:user_id, :shop_id, :matricule, :device_user_id, :hire_date, :iban, :cnss_number, :direction, :job_title, :sitaf, :tax_dependents, :cat_leg, :cat_prof, :ier_rate, :department_id, :job_category_id, :status)";
        $this->db->query($sql, $this->params($data));
        return $this->db->lastInsertId();
    }

    public function createForVendor($userId, $shopId, $matricule = null)
    {
        if ($this->findByUserId($userId)) {
            return false;
        }
        if (empty($matricule)) {
            $matricule = 'EMP-' . (int)$userId;
        }
        return $this->create([
            'user_id'   => $userId,
            'shop_id'   => $shopId,
            'matricule' => $matricule,
            'hire_date' => date('Y-m-d'),
        ]);
    }

    public function update($id, $data)
    {
        $fields = [];
        $params = [':id' => $id];
        $allowed = ['user_id','shop_id','matricule','device_user_id','hire_date','iban','cnss_number','direction','job_title','sitaf','tax_dependents','cat_leg','cat_prof','ier_rate','department_id','job_category_id','status'];
        foreach ($allowed as $field) {
            if (isset($data[$field])) {
                $fields[] = "$field = :$field";
                $params[":$field"] = $data[$field];
            }
        }
        if (empty($fields)) return false;
        $sql = "UPDATE payroll_employees SET " . implode(", ", $fields) . " WHERE id = :id";
        return $this->db->execute($sql, $params);
    }

    public function delete($id)
    {
        return $this->db->execute("DELETE FROM payroll_employees WHERE id = ?", [$id]);
    }

    public function vendorsWithoutEmployee($shopId, $role = 'vendeur')
    {
        $sql = "SELECT u.id, u.nom_complet, u.email, u.telephone
                FROM utilisateurs u
                LEFT JOIN payroll_employees pe ON pe.user_id = u.id
                WHERE u.shop_id = ? AND u.role = ? AND pe.id IS NULL
                ORDER BY u.nom_complet ASC";
        return $this->db->fetchAll($sql, [$shopId, $role]);
    }

    private function params($data)
    {
        return [
            ':user_id'          => $data['user_id']          ?? null,
            ':shop_id'          => $data['shop_id']          ?? null,
            ':matricule'        => $data['matricule']        ?? null,
            ':device_user_id'   => $data['device_user_id']   ?? null,
            ':hire_date'        => $data['hire_date']        ?? null,
            ':iban'             => $data['iban']             ?? null,
            ':cnss_number'      => $data['cnss_number']      ?? null,
            ':direction'        => $data['direction']        ?? null,
            ':job_title'        => $data['job_title']        ?? null,
            ':sitaf'            => $data['sitaf']            ?? 0,
            ':tax_dependents'   => $data['tax_dependents']   ?? 0,
            ':cat_leg'          => $data['cat_leg']          ?? null,
            ':cat_prof'         => $data['cat_prof']         ?? null,
            ':ier_rate'         => $data['ier_rate']         ?? 0,
            ':department_id'    => $data['department_id']    ?? null,
            ':job_category_id'  => $data['job_category_id']  ?? null,
            ':status'           => $data['status']           ?? 'active',
        ];
    }
}
