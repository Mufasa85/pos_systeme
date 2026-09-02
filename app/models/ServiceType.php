<?php

namespace App\Models;

class ServiceType
{
    private $db;

    public function __construct()
    {
        $this->db = \App\Core\Database::getInstance();
    }

    public function getAll()
    {
        return $this->db->fetchAll('SELECT * FROM service_types ORDER BY name ASC');
    }

    public function findById($id)
    {
        return $this->db->fetch('SELECT * FROM service_types WHERE id = ?', [$id]);
    }

    public function findByName($name)
    {
        return $this->db->fetch('SELECT * FROM service_types WHERE name = ?', [$name]);
    }

    public function create($data)
    {
        $sql = 'INSERT INTO service_types (name) VALUES (:name)';
        $this->db->query($sql, [':name' => $data['name']]);
        return $this->db->lastInsertId();
    }

    public function update($id, $data)
    {
        $sql = 'UPDATE service_types SET name = :name WHERE id = :id';
        return $this->db->execute($sql, [':name' => $data['name'], ':id' => $id]);
    }

    public function delete($id)
    {
        return $this->db->execute('DELETE FROM service_types WHERE id = ?', [$id]);
    }

    public function exists($name, $excludeId = null)
    {
        if ($excludeId) {
            return $this->db->fetch('SELECT id FROM service_types WHERE name = ? AND id != ?', [$name, $excludeId]);
        }
        return $this->db->fetch('SELECT id FROM service_types WHERE name = ?', [$name]);
    }
}
