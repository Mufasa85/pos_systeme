<?php

namespace App\Models;

class PressingStatusHistory
{
    private $db;
    private $table = 'pressing_status_history';

    public function __construct()
    {
        $this->db = \App\Core\Database::getInstance();
    }

    public function getByDepot($depotId)
    {
        return $this->db->fetchAll(
            "SELECT h.*, u.nom_complet as changed_by_name
             FROM {$this->table} h
             LEFT JOIN utilisateurs u ON h.changed_by = u.id
             WHERE h.depot_id = ?
             ORDER BY h.created_at ASC",
            [$depotId]
        );
    }

    public function create($data)
    {
        $sql = "INSERT INTO {$this->table}
                (depot_id, ancien_statut, nouveau_statut, changed_by)
                VALUES (:depot_id, :ancien_statut, :nouveau_statut, :changed_by)";
        $this->db->execute($sql, [
            ':depot_id'       => $data['depot_id'],
            ':ancien_statut'  => $data['ancien_statut'],
            ':nouveau_statut' => $data['nouveau_statut'],
            ':changed_by'     => $data['changed_by'],
        ]);
        return $this->db->lastInsertId();
    }
}
