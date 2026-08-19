<?php

namespace App\Models;

class RestaurantFidelite
{
    private $db;
    private $table = 'restaurant_fidelite';

    public function __construct()
    {
        $this->db = \App\Core\Database::getInstance();
    }

    public function all($shopId = null)
    {
        if ($shopId) {
            return $this->db->fetchAll(
                "SELECT f.*, c.nom as client_nom, c.numero as client_telephone\n                 FROM {$this->table} f\n                 LEFT JOIN clients c ON f.client_id = c.id\n                 WHERE f.shop_id = ?\n                 ORDER BY f.points DESC",
                [$shopId]
            );
        }
        return $this->db->fetchAll(
            "SELECT f.*, c.nom as client_nom, s.nom as shop_name\n             FROM {$this->table} f\n             LEFT JOIN clients c ON f.client_id = c.id\n             LEFT JOIN shops s ON f.shop_id = s.id\n             ORDER BY f.shop_id ASC, f.points DESC"
        );
    }

    public function findByClient($clientId, $shopId)
    {
        return $this->db->fetch(
            "SELECT * FROM {$this->table} WHERE client_id = ? AND shop_id = ?",
            [$clientId, $shopId]
        );
    }

    public function addPoints($clientId, $shopId, $montant)
    {
        $regle = $this->db->fetch(
            "SELECT * FROM restaurant_fidelite_regles\n             WHERE shop_id = ? AND actif = 1 AND montant_depense > 0\n             ORDER BY montant_depense DESC LIMIT 1",
            [$shopId]
        );

        $points = 0;
        if ($regle) {
            $points = (int)floor($montant / $regle['montant_depense']) * $regle['points_gagnes'];
        }

        $existing = $this->findByClient($clientId, $shopId);

        if ($existing) {
            $this->db->execute(
                "UPDATE {$this->table} SET points = points + ?, total_depense = total_depense + ?\n                 WHERE client_id = ? AND shop_id = ?",
                [$points, $montant, $clientId, $shopId]
            );
            return $existing['id'];
        }

        $this->db->execute(
            "INSERT INTO {$this->table} (shop_id, client_id, points, total_depense)\n             VALUES (?, ?, ?, ?)",
            [$shopId, $clientId, $points, $montant]
        );
        return $this->db->lastInsertId();
    }

    public function usePoints($clientId, $shopId, $points)
    {
        return $this->db->execute(
            "UPDATE {$this->table} SET points = GREATEST(0, points - ?)\n             WHERE client_id = ? AND shop_id = ? AND points >= ?",
            [$points, $clientId, $shopId, $points]
        );
    }

    public function getSolde($clientId, $shopId)
    {
        $result = $this->findByClient($clientId, $shopId);
        return $result ? (int)$result['points'] : 0;
    }
}
