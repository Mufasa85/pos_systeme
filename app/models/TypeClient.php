<?php

namespace App\Models;

use App\Core\Database;

class TypeClient
{
    private $db;
    private $table = 'type_client';

    public function __construct()
    {
        $this->db = Database::getInstance();
    }

    /**
     * Récupérer tous les types de clients
     */
    public function getAll()
    {
        $sql = "SELECT * FROM {$this->table} ORDER BY code ASC";
        return $this->db->query($sql);
    }

    /**
     * Récupérer un type de client par ID
     */
    public function findById($id)
    {
        $sql = "SELECT * FROM {$this->table} WHERE id = ?";
        $result = $this->db->query($sql, [$id]);
        return $result ? $result[0] : null;
    }

    /**
     * Récupérer un type de client par code
     */
    public function findByCode($code)
    {
        $sql = "SELECT * FROM {$this->table} WHERE code = ?";
        $result = $this->db->query($sql, [$code]);
        return $result ? $result[0] : null;
    }

    /**
     * Créer un nouveau type de client
     */
    public function create($data)
    {
        $sql = "INSERT INTO {$this->table} 
                (code, description) 
                VALUES (?, ?)";

        return $this->db->query($sql, [
            $data['code'],
            $data['description'] ?? null
        ]);
    }

    /**
     * Mettre à jour un type de client
     */
    public function update($id, $data)
    {
        $sql = "UPDATE {$this->table} 
                SET code = ?, description = ? 
                WHERE id = ?";

        return $this->db->query($sql, [
            $data['code'],
            $data['description'] ?? null,
            $id
        ]);
    }

    /**
     * Supprimer un type de client
     */
    public function delete($id)
    {
        $sql = "DELETE FROM {$this->table} WHERE id = ?";
        return $this->db->query($sql, [$id]);
    }
}
