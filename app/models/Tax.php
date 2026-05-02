<?php

namespace App\Models;

use App\Core\Database;

class Tax
{
    private $db;
    private $table = 'taxes';

    public function __construct()
    {
        $this->db = Database::getInstance();
    }

    /**
     * Récupérer toutes les taxes
     */
    public function getAll()
    {
        $sql = "SELECT * FROM {$this->table} ORDER BY groupe_taxe ASC";
        return $this->db->query($sql);
    }

    /**
     * Récupérer une taxe par ID
     */
    public function findById($id)
    {
        $sql = "SELECT * FROM {$this->table} WHERE id = ?";
        $result = $this->db->query($sql, [$id]);
        return $result ? $result[0] : null;
    }

    /**
     * Récupérer une taxe par groupe
     */
    public function findByGroupe($groupe)
    {
        $sql = "SELECT * FROM {$this->table} WHERE groupe_taxe = ?";
        $result = $this->db->query($sql, [$groupe]);
        return $result ? $result[0] : null;
    }

    /**
     * Récupérer le taux de taxe par ID
     */
    public function getRate($id)
    {
        $tax = $this->findById($id);
        return $tax ? $tax['taux'] : 0;
    }

    /**
     * Créer une nouvelle taxe
     */
    public function create($data)
    {
        $sql = "INSERT INTO {$this->table} 
                (groupe_taxe, etiquette, description, taux) 
                VALUES (?, ?, ?, ?)";

        return $this->db->query($sql, [
            $data['groupe_taxe'],
            $data['etiquette'],
            $data['description'] ?? null,
            $data['taux'] ?? 0
        ]);
    }

    /**
     * Mettre à jour une taxe
     */
    public function update($id, $data)
    {
        $sql = "UPDATE {$this->table} 
                SET groupe_taxe = ?, etiquette = ?, description = ?, taux = ? 
                WHERE id = ?";

        return $this->db->query($sql, [
            $data['groupe_taxe'],
            $data['etiquette'],
            $data['description'] ?? null,
            $data['taux'] ?? 0,
            $id
        ]);
    }

    /**
     * Supprimer une taxe
     */
    public function delete($id)
    {
        $sql = "DELETE FROM {$this->table} WHERE id = ?";
        return $this->db->query($sql, [$id]);
    }
}
