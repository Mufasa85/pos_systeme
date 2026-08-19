<?php

namespace App\Models;

class RestaurantCommandeDetailOption
{
    private $db;
    private $table = 'restaurant_commande_detail_options';

    public function __construct()
    {
        $this->db = \App\Core\Database::getInstance();
    }

    public function getByDetail($commandeDetailId)
    {
        return $this->db->fetchAll(
            "SELECT o.* FROM {$this->table} o\n             WHERE o.commande_detail_id = ?\n             ORDER BY o.id ASC",
            [$commandeDetailId]
        );
    }

    public function create($data)
    {
        $sql = "INSERT INTO {$this->table}
                (commande_detail_id, option_id, nom, prix_supp)
                VALUES (:commande_detail_id, :option_id, :nom, :prix_supp)";
        $this->db->execute($sql, [
            ':commande_detail_id' => $data['commande_detail_id'],
            ':option_id'          => $data['option_id'],
            ':nom'                => $data['nom'],
            ':prix_supp'          => $data['prix_supp'] ?? 0,
        ]);
        return $this->db->lastInsertId();
    }

    public function deleteByDetail($commandeDetailId)
    {
        return $this->db->execute(
            "DELETE FROM {$this->table} WHERE commande_detail_id = ?",
            [$commandeDetailId]
        );
    }

    public function delete($id)
    {
        return $this->db->execute("DELETE FROM {$this->table} WHERE id = ?", [$id]);
    }
}
