<?php

namespace App\Models;

use App\Core\Database;

class InvoiceType
{
    private $db;
    private $table = 'invoice_types';

    public function __construct()
    {
        $this->db = Database::getInstance();
    }

    /**
     * Récupérer tous les types de factures
     */
    public function getAll()
    {
        $sql = "SELECT * FROM {$this->table} ORDER BY code ASC";
        return $this->db->query($sql);
    }

    /**
     * Récupérer un type de facture par ID
     */
    public function findById($id)
    {
        $sql = "SELECT * FROM {$this->table} WHERE id = ?";
        $result = $this->db->query($sql, [$id]);
        return $result ? $result[0] : null;
    }

    /**
     * Récupérer un type de facture par code
     */
    public function findByCode($code)
    {
        $sql = "SELECT * FROM {$this->table} WHERE code = ?";
        $result = $this->db->query($sql, [$code]);
        return $result ? $result[0] : null;
    }
}
