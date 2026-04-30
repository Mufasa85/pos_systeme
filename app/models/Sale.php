<?php

namespace App\Models;

// app/models/Sale.php

class Sale
{
    private $db;

    public function __construct()
    {
        $this->db = \App\Core\Database::getInstance();
    }

    public function create($data)
    {
        $sql = "INSERT INTO ventes (numero_facture, sous_total_ht, tva, total, vendeur_id, date, dateDGI, qrCode, codeDEFDGI, counters, nim) 
                VALUES (:numero_facture, :sous_total_ht, :tva, :total, :vendeur_id, :date, :dateDGI, :qrCode, :codeDEFDGI, :counters, :nim)";
        $this->db->query($sql, [
            ':numero_facture' => $data['numero_facture'],
            ':sous_total_ht'  => $data['sous_total_ht'],
            ':tva'            => $data['tva'],
            ':total'          => $data['total'],
            ':vendeur_id'     => $data['vendeur_id'],
            ':date'           => $data['date'],
            ':dateDGI'        => $data['dateDGI'] ?? null,
            ':qrCode'         => $data['qrCode'] ?? null,
            ':codeDEFDGI'     => $data['codeDEFDGI'] ?? null,
            ':counters'       => $data['counters'] ?? null,
            ':nim'            => $data['nim'] ?? null
        ]);
        return $this->db->getConnection()->lastInsertId();
    }

    public function getAllSales()
    {
        $sql = "SELECT v.*, u.nom_complet as nom_vendeur 
                FROM ventes v 
                LEFT JOIN utilisateurs u ON v.vendeur_id = u.id 
                ORDER BY v.date DESC";
        return $this->db->fetchAll($sql);
    }

    public function generateInvoiceNumber()
    {
        // Simple logic : prefixe FAC- et recupère l'id max + 1000
        $max = $this->db->fetch("SELECT MAX(id) as max_id FROM ventes");
        $nextId = ($max['max_id'] ? $max['max_id'] : 0) + 1000;
        return 'FAC-' . str_pad($nextId, 6, '0', STR_PAD_LEFT);
    }
    public function exist($id)
    {
        $sql = "SELECT v.*, u.nom_complet as nom_vendeur 
                FROM ventes v 
                LEFT JOIN utilisateurs u ON v.vendeur_id = u.id 
                WHERE v.id = ?";
        return $this->db->fetch($sql, [$id]);
    }

    /**
     * Find a sale by invoice number (numero_facture)
     */
    public function findByInvoiceNumber($invoiceNumber)
    {
        $sql = "SELECT v.*, u.nom_complet as nom_vendeur 
                FROM ventes v 
                LEFT JOIN utilisateurs u ON v.vendeur_id = u.id 
                WHERE v.numero_facture = ?";
        return $this->db->fetch($sql, [$invoiceNumber]);
    }
}
