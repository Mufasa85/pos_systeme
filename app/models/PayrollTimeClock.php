<?php

namespace App\Models;

class PayrollTimeClock
{
    private $db;

    public function __construct()
    {
        $this->db = \App\Core\Database::getInstance();
    }

    public function findByEmployeeAndPeriod($employeeId, $periodId, $shopId = null)
    {
        $sql = 'SELECT * FROM payroll_time_clock_events
                WHERE employee_id = ? AND payroll_period_id = ?';
        $params = [$employeeId, $periodId];
        if ($shopId) {
            $sql .= ' AND shop_id = ?';
            $params[] = $shopId;
        }
        $sql .= ' ORDER BY event_at ASC';
        return $this->db->fetchAll($sql, $params);
    }

    public function findByPeriod($periodId, $shopId = null)
    {
        $sql = 'SELECT t.*, u.nom_complet
                FROM payroll_time_clock_events t
                JOIN payroll_employees pe ON t.employee_id = pe.id
                JOIN utilisateurs u ON pe.user_id = u.id
                WHERE t.payroll_period_id = ?';
        $params = [$periodId];
        if ($shopId) {
            $sql .= ' AND t.shop_id = ?';
            $params[] = $shopId;
        }
        $sql .= ' ORDER BY u.nom_complet ASC, t.event_at ASC';
        return $this->db->fetchAll($sql, $params);
    }

    public function create($data)
    {
        $sql = 'INSERT INTO payroll_time_clock_events
                (shop_id, employee_id, event_type, event_at, source, verify_mode, device_sn,
                 latitude, longitude, in_zone, identity_verified, validated, import_batch_id)
                VALUES
                (:shop_id, :employee_id, :event_type, :event_at, :source, :verify_mode, :device_sn,
                 :latitude, :longitude, :in_zone, :identity_verified, :validated, :import_batch_id)';
        $this->db->query($sql, [
            ':shop_id'          => $data['shop_id'] ?? null,
            ':employee_id'      => $data['employee_id'] ?? null,
            ':event_type'       => $data['event_type'] ?? 'UNKNOWN',
            ':event_at'         => $data['event_at'] ?? null,
            ':source'           => $data['source'] ?? 'manual',
            ':verify_mode'      => $data['verify_mode'] ?? null,
            ':device_sn'        => $data['device_sn'] ?? null,
            ':latitude'         => $data['latitude'] ?? null,
            ':longitude'        => $data['longitude'] ?? null,
            ':in_zone'          => $data['in_zone'] ?? null,
            ':identity_verified' => $data['identity_verified'] ?? 0,
            ':validated'        => $data['validated'] ?? 1,
            ':import_batch_id'  => $data['import_batch_id'] ?? null,
        ]);
        return $this->db->lastInsertId();
    }

    public function update($id, $data)
    {
        $fields = [];
        $params = [':id' => $id];
        $allowed = ['shop_id','employee_id','event_type','event_at','source','verify_mode','device_sn','latitude','longitude','in_zone','identity_verified','validated','import_batch_id'];
        foreach ($allowed as $field) {
            if (isset($data[$field])) {
                $fields[] = "$field = :$field";
                $params[":$field"] = $data[$field];
            }
        }
        if (empty($fields)) {
            return false;
        }
        $sql = 'UPDATE payroll_time_clock_events SET ' . implode(', ', $fields) . ' WHERE id = :id';
        return $this->db->execute($sql, $params);
    }

    public function delete($id)
    {
        return $this->db->execute('DELETE FROM payroll_time_clock_events WHERE id = ?', [$id]);
    }

    public function bulkInsert($rows)
    {
        $created = 0;
        foreach ($rows as $row) {
            if ($this->create($row)) {
                $created++;
            }
        }
        return $created;
    }
}
