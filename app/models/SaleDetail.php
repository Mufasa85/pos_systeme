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
}
