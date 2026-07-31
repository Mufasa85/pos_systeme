<?php

namespace App\Models;

class PayrollPeriod
{
    private $db;

    public function __construct()
    {
        $this->db = \App\Core\Database::getInstance();
    }

    public function all($shopId = null, $superAdmin = false)
    {
        $sql = "SELECT * FROM payroll_periods";
        $params = [];
        if (!$superAdmin && $shopId) {
            $sql .= " WHERE shop_id = ?";
            $params[] = $shopId;
        }
        $sql .= " ORDER BY year DESC, month DESC";
        return $this->db->fetchAll($sql, $params);
    }

    public function findById($id, $shopId = null, $superAdmin = false)
    {
        $sql = "SELECT * FROM payroll_periods WHERE id = ?";
        $params = [$id];
        if (!$superAdmin && $shopId) {
            $sql .= " AND shop_id = ?";
            $params[] = $shopId;
        }
        return $this->db->fetch($sql, $params);
    }

    public function findByShopMonthYear($shopId, $month, $year)
    {
        return $this->db->fetch(
            "SELECT * FROM payroll_periods WHERE shop_id = ? AND month = ? AND year = ?",
            [$shopId, $month, $year]
        );
    }

    public function findByStatus($status, $shopId = null)
    {
        $sql = "SELECT * FROM payroll_periods WHERE status = ?";
        $params = [$status];
        if ($shopId) {
            $sql .= " AND shop_id = ?";
            $params[] = $shopId;
        }
        $sql .= " ORDER BY year DESC, month DESC";
        return $this->db->fetchAll($sql, $params);
    }

    public function create($data)
    {
        $sql = "INSERT INTO payroll_periods
                (shop_id, month, year, working_days, status)
                VALUES
                (:shop_id, :month, :year, :working_days, :status)";
        $this->db->query($sql, [
            ':shop_id'      => $data['shop_id'] ?? null,
            ':month'        => $data['month'] ?? null,
            ':year'         => $data['year'] ?? null,
            ':working_days' => $data['working_days'] ?? 22.00,
            ':status'       => $data['status'] ?? 'draft',
        ]);
        return $this->db->lastInsertId();
    }

    public function update($id, $data)
    {
        $fields = [];
        $params = [':id' => $id];
        $allowed = ['shop_id','month','year','working_days','status','calculated_at','validated_at','closed_at'];
        foreach ($allowed as $field) {
            if (isset($data[$field])) {
                $fields[] = "$field = :$field";
                $params[":$field"] = $data[$field];
            }
        }
        if (empty($fields)) return false;
        $sql = "UPDATE payroll_periods SET " . implode(", ", $fields) . " WHERE id = :id";
        return $this->db->execute($sql, $params);
    }

    public function delete($id)
    {
        return $this->db->execute("DELETE FROM payroll_periods WHERE id = ?", [$id]);
    }

    public function markCalculated($id)
    {
        return $this->db->execute(
            "UPDATE payroll_periods SET status = 'calculated', calculated_at = NOW() WHERE id = ?",
            [$id]
        );
    }

    public function markValidated($id)
    {
        return $this->db->execute(
            "UPDATE payroll_periods SET status = 'validated', validated_at = NOW() WHERE id = ?",
            [$id]
        );
    }

    public function markClosed($id)
    {
        return $this->db->execute(
            "UPDATE payroll_periods SET status = 'closed', closed_at = NOW() WHERE id = ?",
            [$id]
        );
    }
}
