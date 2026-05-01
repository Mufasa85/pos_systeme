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
        $sql = "SELECT c.*, tc.nom as type_nom 
                FROM {$this->table} c
                LEFT JOIN type_client tc ON c.type_client_id = tc.id
                ORDER BY c.nom ASC";
        return $this->db->query($sql);
    }

    /**
     * Rechercher un client par numéro de téléphone
     */
    public function findByNumero($numero)
    {
        $sql = "SELECT c.*, tc.nom as type_nom, tc.description as type_description
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
        $sql = "SELECT c.*, tc.nom as type_nom 
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
        $code_client = 'CLI-' . str_pad($nextNum, 5, '0', STR_PAD_LEFT);

        $sql = "INSERT INTO {$this->table} 
                (nom, numero, code_client, type_client_id, adresse, email) 
                VALUES (?, ?, ?, ?, ?, ?)";
        
        $result = $this->db->query($sql, [
            $data['nom'],
            $data['numero'],
            $code_client,
            $data['type_client_id'] ?? 1,
            $data['adresse'] ?? null,
            $data['email'] ?? null
        ]);

        return $result ? $this->findById($this->db->lastInsertId()) : null;
    }

    /**
     * Mettre à jour un client
     */
    public function update($id, $data)
    {
        $sql = "UPDATE {$this->table} 
                SET nom = ?, numero = ?, type_client_id = ?, adresse = ?, email = ? 
                WHERE id = ?";
        
        return $this->db->query($sql, [
            $data['nom'],
            $data['numero'],
            $data['type_client_id'] ?? 1,
            $data['adresse'] ?? null,
            $data['email'] ?? null,
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
        $sql = "SELECT * FROM type_client WHERE actif = 1 ORDER BY nom ASC";
        return $this->db->query($sql);
    }
}