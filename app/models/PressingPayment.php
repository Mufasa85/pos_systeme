<?php

namespace App\Models;

class PressingPayment
{
    private $db;
    private $table = 'pressing_paiements';

    public function __construct()
    {
        $this->db = \App\Core\Database::getInstance();
    }

    public function findById($id)
    {
        return $this->db->fetch("SELECT * FROM {$this->table} WHERE id = ?", [$id]);
    }

    public function getByDepot($depotId)
    {
        return $this->db->fetchAll(
            "SELECT p.*, u.nom_complet as created_by_name
             FROM {$this->table} p
             LEFT JOIN utilisateurs u ON p.created_by = u.id
             WHERE p.depot_id = ?
             ORDER BY p.created_at ASC",
            [$depotId]
        );
    }

    public function getTotalPaid($depotId)
    {
        $row = $this->db->fetch(
            "SELECT COALESCE(SUM(montant), 0) as total FROM {$this->table} WHERE depot_id = ?",
            [$depotId]
        );
        return $row ? (float)$row['total'] : 0;
    }

    public function create($data)
    {
        $sql = "INSERT INTO {$this->table}
                (depot_id, montant, mode_paiement, reference, created_by)
                VALUES (:depot_id, :montant, :mode_paiement, :reference, :created_by)";
        $this->db->execute($sql, [
            ':depot_id'       => $data['depot_id'],
            ':montant'        => $data['montant'],
            ':mode_paiement'  => $data['mode_paiement'] ?? 'cash',
            ':reference'      => $data['reference'] ?? null,
            ':created_by'     => $data['created_by'],
        ]);

        $id = $this->db->lastInsertId();
        $this->recalcPaidAmount($data['depot_id']);
        return $id;
    }

    public function delete($id)
    {
        $payment = $this->db->fetch("SELECT depot_id FROM {$this->table} WHERE id = ?", [$id]);
        if (!$payment) return false;
        $result = $this->db->execute("DELETE FROM {$this->table} WHERE id = ?", [$id]);
        $this->recalcPaidAmount($payment['depot_id']);
        return $result;
    }

    private function recalcPaidAmount($depotId)
    {
        $total = $this->getTotalPaid($depotId);
        return $this->db->execute(
            "UPDATE pressing_depots SET paid_amount = ? WHERE id = ?",
            [$total, $depotId]
        );
    }
}
