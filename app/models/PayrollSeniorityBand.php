<?php

namespace App\Models;

class PayrollSeniorityBand
{
    private $db;

    public function __construct()
    {
        $this->db = \App\Core\Database::getInstance();
    }

    public function all($shopId = null)
    {
        $sql = 'SELECT * FROM payroll_seniority_bands';
        $params = [];
        if ($shopId) {
            $sql .= ' WHERE (shop_id = ? OR shop_id IS NULL)';
            $params[] = $shopId;
        }
        $sql .= ' ORDER BY min_years ASC';
        return $this->db->fetchAll($sql, $params);
    }

    public function findById($id)
    {
        return $this->db->fetch('SELECT * FROM payroll_seniority_bands WHERE id = ?', [$id]);
    }

    public function findBandForYears($years, $shopId = null)
    {
        $sql = 'SELECT * FROM payroll_seniority_bands
                WHERE ? >= min_years AND (max_years IS NULL OR ? <= max_years)';
        $params = [$years, $years];
        if ($shopId) {
            $sql .= ' AND (shop_id = ? OR shop_id IS NULL)';
            $params[] = $shopId;
        }
        $sql .= ' ORDER BY shop_id IS NULL ASC, percent DESC LIMIT 1';
        return $this->db->fetch($sql, $params);
    }

    public function create($data)
    {
        $sql = 'INSERT INTO payroll_seniority_bands
                (shop_id, min_years, max_years, percent, label)
                VALUES
                (:shop_id, :min_years, :max_years, :percent, :label)';
        $this->db->query($sql, [
            ':shop_id'   => $data['shop_id'] ?? null,
            ':min_years' => $data['min_years'] ?? 0,
            ':max_years' => $data['max_years'] ?? null,
            ':percent'   => $data['percent'] ?? 0,
            ':label'     => $data['label'] ?? null,
        ]);
        return $this->db->lastInsertId();
    }

    public function update($id, $data)
    {
        $fields = [];
        $params = [':id' => $id];
        $allowed = ['shop_id','min_years','max_years','percent','label'];
        foreach ($allowed as $field) {
            if (isset($data[$field])) {
                $fields[] = "$field = :$field";
                $params[":$field"] = $data[$field];
            }
        }
        if (empty($fields)) {
            return false;
        }
        $sql = 'UPDATE payroll_seniority_bands SET ' . implode(', ', $fields) . ' WHERE id = :id';
        return $this->db->execute($sql, $params);
    }

    public function delete($id)
    {
        return $this->db->execute('DELETE FROM payroll_seniority_bands WHERE id = ?', [$id]);
    }
}
