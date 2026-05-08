<?php

namespace App\Models;

// app/models/SaleDetail.php

class SaleDetail
{
    private $db;

    public function __construct()
    {
        $this->db = \App\Core\Database::getInstance();
    }

    public function create($data)
    {
        $sql = "INSERT INTO details_vente (vente_id, produit_id, quantite, prix) 
                VALUES (:vente_id, :produit_id, :quantite, :prix)";
        return $this->db->query($sql, [
            ':vente_id'   => $data['vente_id'],
            ':produit_id' => $data['produit_id'],
            ':quantite'   => $data['quantite'],
            ':prix'       => $data['prix']
        ]);
    }
    public function exist($id)
    {
        return $this->db->fetch("SELECT * FROM details_vente WHERE id = ?", [$id]);
    }

    public function getBySaleId($saleId)
    {
        $sql = "SELECT dv.*, p.nom as produit_nom, p.code_barres,
                       t.taux as tax_rate, t.etiquette as tax_etiquette
                FROM details_vente dv 
                LEFT JOIN produits p ON dv.produit_id = p.id 
                LEFT JOIN taxes t ON p.taxe_id = t.id
                WHERE dv.vente_id = ?";
        return $this->db->fetchAll($sql, [$saleId]);
    }
}
