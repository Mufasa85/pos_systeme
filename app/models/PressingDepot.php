<?php

namespace App\Models;

class PressingDepot
{
    private $db;
    private $table = 'pressing_depots';
    private $validStatuts = ['recu', 'en_lavage', 'en_sechage', 'en_repassage', 'pret', 'livre'];

    public function __construct()
    {
        $this->db = \App\Core\Database::getInstance();
    }

    public function getValidStatuts()
    {
        return $this->validStatuts;
    }

    public function all($shopId = null, $filters = [])
    {
        $where = [];
        $params = [];

        if ($shopId) {
            $where[] = 'd.shop_id = ?';
            $params[] = $shopId;
        }
        if (!empty($filters['statut'])) {
            $where[] = 'd.statut = ?';
            $params[] = $filters['statut'];
        }
        if (!empty($filters['client_id'])) {
            $where[] = 'd.client_id = ?';
            $params[] = $filters['client_id'];
        }
        if (!empty($filters['date_from'])) {
            $where[] = 'DATE(d.date_reception) >= ?';
            $params[] = $filters['date_from'];
        }
        if (!empty($filters['date_to'])) {
            $where[] = 'DATE(d.date_reception) <= ?';
            $params[] = $filters['date_to'];
        }

        $whereSql = $where ? 'WHERE ' . implode(' AND ', $where) : '';

        return $this->db->fetchAll(
            "SELECT d.*, c.nom_client as nom_client, c.numero as client_numero
             FROM {$this->table} d
             LEFT JOIN clients c ON d.client_id = c.id
             $whereSql
             ORDER BY d.date_reception DESC",
            $params
        );
    }

    public function findById($id, $shopId = null)
    {
        $where = 'd.id = ?';
        $params = [$id];
        if ($shopId) {
            $where .= ' AND d.shop_id = ?';
            $params[] = $shopId;
        }
        return $this->db->fetch(
            "SELECT d.*, c.nom_client as nom_client, c.numero as client_numero
             FROM {$this->table} d
             LEFT JOIN clients c ON d.client_id = c.id
             WHERE $where",
            $params
        );
    }

    public function findByNumero($numero)
    {
        return $this->db->fetch(
            "SELECT d.*, c.nom_client as nom_client, c.numero as client_numero
             FROM {$this->table} d
             LEFT JOIN clients c ON d.client_id = c.id
             WHERE d.numero = ?",
            [$numero]
        );
    }

    public function generateNumero()
    {
        $prefix = 'PR-' . date('Ymd') . '-';
        $last = $this->db->fetch(
            "SELECT numero FROM {$this->table} WHERE numero LIKE ? ORDER BY id DESC LIMIT 1",
            [$prefix . '%']
        );
        if ($last && preg_match('/(\d{4})$/', $last['numero'], $m)) {
            $seq = intval($m[1]) + 1;
        } else {
            $seq = 1;
        }
        return $prefix . str_pad($seq, 4, '0', STR_PAD_LEFT);
    }

    public function create($data)
    {
        $sql = "INSERT INTO {$this->table}
                (shop_id, numero, client_id, statut, sous_total, remise, total, paid_amount, qr_code, code_barre, date_prevue, adresse_livraison, date_retour_prevue, created_by)
                VALUES (:shop_id, :numero, :client_id, 'recu', :sous_total, :remise, :total, 0, :qr_code, :code_barre, :date_prevue, :adresse_livraison, :date_retour_prevue, :created_by)";
        $this->db->execute($sql, [
            ':shop_id'           => $data['shop_id'],
            ':numero'            => $data['numero'],
            ':client_id'         => $data['client_id'],
            ':sous_total'        => $data['sous_total'],
            ':remise'            => $data['remise'] ?? 0,
            ':total'             => $data['total'],
            ':qr_code'           => $data['numero'],
            ':code_barre'        => $data['numero'],
            ':date_prevue'       => $data['date_prevue'] ?? null,
            ':adresse_livraison' => $data['adresse_livraison'] ?? null,
            ':date_retour_prevue' => $data['date_retour_prevue'] ?? null,
            ':created_by'        => $data['created_by'],
        ]);
        return $this->db->lastInsertId();
    }

    public function updateStatut($id, $statut, $changedBy = null)
    {
        if (!in_array($statut, $this->validStatuts)) return false;

        $depot = $this->db->fetch("SELECT statut FROM {$this->table} WHERE id = ?", [$id]);
        if (!$depot) return false;

        $params = [$statut, $id];
        $dateField = '';
        if ($statut === 'livre') {
            $dateField = ', date_livraison = NOW()';
        }
        $ok = $this->db->execute(
            "UPDATE {$this->table} SET statut = ? $dateField WHERE id = ?",
            $params
        );

        if ($ok) {
            $userId = $changedBy ?? ($_SESSION['user_id'] ?? null);
            if ($userId) {
                $history = new PressingStatusHistory();
                $history->create([
                    'depot_id'       => $id,
                    'ancien_statut'  => $depot['statut'],
                    'nouveau_statut' => $statut,
                    'changed_by'     => $userId,
                ]);
            }
        }

        return $ok;
    }

    public function markPaid($id, $venteId)
    {
        return $this->db->execute(
            "UPDATE {$this->table} SET vente_id = ?, paid_amount = total WHERE id = ?",
            [$venteId, $id]
        );
    }

    public function isPaid($id)
    {
        $row = $this->db->fetch("SELECT vente_id, paid_amount, total FROM {$this->table} WHERE id = ?", [$id]);
        if (!$row) return false;
        return !empty($row['vente_id']) || (float)$row['paid_amount'] >= (float)$row['total'];
    }

    public function getPaidAmount($id)
    {
        $row = $this->db->fetch("SELECT paid_amount FROM {$this->table} WHERE id = ?", [$id]);
        return $row ? (float)$row['paid_amount'] : 0;
    }

    public function getSolde($id)
    {
        $row = $this->db->fetch("SELECT total, paid_amount FROM {$this->table} WHERE id = ?", [$id]);
        if (!$row) return 0;
        return max(0, (float)$row['total'] - (float)$row['paid_amount']);
    }
}
