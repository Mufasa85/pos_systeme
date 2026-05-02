<?php

namespace App\Models;

use App\Core\Database;

class Client
{
    private $db;
    private $table = 'clients';

    public function __construct()
    {
        $this->db = Database::getInstance();
    }

    /**
     * Récupérer tous les clients
     */
    public function getAll()
    {
        $sql = "SELECT c.*, tc.code as type_code, tc.description as type_description 
                FROM {$this->table} c
                LEFT JOIN type_client tc ON c.type_client_id = tc.id
                ORDER BY c.nom_client ASC";
        return $this->db->query($sql);
    }

    /**
     * Rechercher un client par numéro de téléphone
     */
    public function findByNumero($numero)
    {
        $sql = "SELECT c.*, tc.code as type_code, tc.description as type_description
                FROM {$this->table} c
                LEFT JOIN type_client tc ON c.type_client_id = tc.id
                WHERE c.numero = ?";
        $result = $this->db->query($sql, [$numero]);
        return $result ? $result[0] : null;
    }

    /**
     * Rechercher un client par ID
     */
    public function findById($id)
    {
        $sql = "SELECT c.*, tc.code as type_code, tc.description as type_description 
                FROM {$this->table} c
                LEFT JOIN type_client tc ON c.type_client_id = tc.id
                WHERE c.id = ?";
        $result = $this->db->query($sql, [$id]);
        return $result ? $result[0] : null;
    }

    /**
     * Créer un nouveau client
     */
    public function create($data)
    {
        // Générer le code client automatiquement
        $lastId = $this->db->query("SELECT MAX(id) as max_id FROM {$this->table}");
        $nextNum = ($lastId && $lastId[0]['max_id']) ? $lastId[0]['max_id'] + 1 : 1;
        $code_client = 'CLI-' . str_pad($nextNum, 3, '0', STR_PAD_LEFT);

        $sql = "INSERT INTO {$this->table} 
                (nom_client, numero, code_client, type_client_id, nif) 
                VALUES (?, ?, ?, ?, ?)";

        $this->db->execute($sql, [
            $data['nom_client'],
            $data['numero'],
            $code_client,
            $data['type_client_id'] ?? 1,
            $data['nif'] ?? ''
        ]);

        return $this->db->lastInsertId();
    }

    /**
     * Mettre à jour un client
     */
    public function update($id, $data)
    {
        $sql = "UPDATE {$this->table} 
                SET nom_client = ?, numero = ?, type_client_id = ? 
                WHERE id = ?";

        return $this->db->query($sql, [
            $data['nom_client'],
            $data['numero'],
            $data['type_client_id'] ?? 1,
            $id
        ]);
    }

    /**
     * Supprimer un client
     */
    public function delete($id)
    {
        $sql = "DELETE FROM {$this->table} WHERE id = ?";
        return $this->db->query($sql, [$id]);
    }

    /**
     * Récupérer les types de clients
     */
    public function getTypes()
    {
        $sql = "SELECT * FROM type_client ORDER BY code ASC";
        return $this->db->query($sql);
    }
}
