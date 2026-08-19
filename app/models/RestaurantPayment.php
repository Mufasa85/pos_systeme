<?php

namespace App\Models;

class RestaurantPayment
{
    private $db;
    private $table = 'restaurant_paiements';

    public function __construct()
    {
        $this->db = \App\Core\Database::getInstance();
    }

    public function getByCommande($commandeId)
    {
        return $this->db->fetchAll(
            "SELECT p.*, u.nom as created_by_name\n             FROM {$this->table} p\n             LEFT JOIN utilisateurs u ON p.created_by = u.id\n             WHERE p.commande_id = ?\n             ORDER BY p.created_at ASC",
            [$commandeId]
        );
    }

    public function getTotalPaid($commandeId)
    {
        $result = $this->db->fetch(
            "SELECT COALESCE(SUM(montant), 0) as total FROM {$this->table} WHERE commande_id = ?",
            [$commandeId]
        );
        return (float)($result['total'] ?? 0);
    }

    public function getTotalPaidSplit($splitBillId)
    {
        $result = $this->db->fetch(
            "SELECT COALESCE(SUM(montant), 0) as total FROM {$this->table} WHERE split_bill_id = ?",
            [$splitBillId]
        );
        return (float)($result['total'] ?? 0);
    }

    public function create($data)
    {
        $sql = "INSERT INTO {$this->table}
                (commande_id, split_bill_id, montant, mode_paiement, reference, created_by)
                VALUES (:commande_id, :split_bill_id, :montant, :mode_paiement, :reference, :created_by)";
        $this->db->execute($sql, [
            ':commande_id'   => $data['commande_id'],
            ':split_bill_id' => $data['split_bill_id'] ?? null,
            ':montant'       => $data['montant'],
            ':mode_paiement' => $data['mode_paiement'] ?? 'cash',
            ':reference'     => $data['reference'] ?? null,
            ':created_by'    => $data['created_by'],
        ]);

        $id = $this->db->lastInsertId();
        $this->recalcPaid($data['commande_id'], $data['split_bill_id'] ?? null);
        return $id;
    }

    public function delete($id)
    {
        $payment = $this->db->fetch("SELECT commande_id, split_bill_id FROM {$this->table} WHERE id = ?", [$id]);
        if (!$payment) return false;

        $result = $this->db->execute("DELETE FROM {$this->table} WHERE id = ?", [$id]);
        $this->recalcPaid($payment['commande_id'], $payment['split_bill_id']);
        return $result;
    }

    private function recalcPaid($commandeId, $splitBillId = null)
    {
        $commandeTotal = $this->getTotalPaid($commandeId);
        $this->db->execute(
            "UPDATE restaurant_commandes SET paid_amount = ? WHERE id = ?",
            [$commandeTotal, $commandeId]
        );

        if ($splitBillId) {
            $splitTotal = $this->getTotalPaidSplit($splitBillId);
            $this->db->execute(
                "UPDATE restaurant_split_bills SET paid_amount = ? WHERE id = ?",
                [$splitTotal, $splitBillId]
            );
        }
    }
}
