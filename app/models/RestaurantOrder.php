<?php

namespace App\Models;

class RestaurantOrder
{
    private $db;
    private $table = 'restaurant_commandes';
    private $detailsTable = 'restaurant_commande_details';
    private $validStatuts = ['ouverte', 'envoyee_cuisine', 'servie', 'payee', 'annulee'];

    public function __construct()
    {
        $this->db = \App\Core\Database::getInstance();
    }

    public function getValidStatuts()
    {
        return $this->validStatuts;
    }

    // ── Commandes ────────────────────────────────────────────────

    public function findOpenByTable($tableId)
    {
        return $this->db->fetch(
            "SELECT * FROM {$this->table} WHERE table_id = ? AND statut IN ('ouverte','envoyee_cuisine') ORDER BY id DESC LIMIT 1",
            [$tableId]
        );
    }

    public function findById($id, $shopId = null)
    {
        if ($shopId) {
            return $this->db->fetch("SELECT * FROM {$this->table} WHERE id = ? AND shop_id = ?", [$id, $shopId]);
        }
        return $this->db->fetch("SELECT * FROM {$this->table} WHERE id = ?", [$id]);
    }

    public function findByIdFull($id, $shopId = null)
    {
        $where = "WHERE c.id = ?";
        $params = [$id];
        if ($shopId) {
            $where .= " AND c.shop_id = ?";
            $params[] = $shopId;
        }
        return $this->db->fetch(
            "SELECT c.*, t.numero as table_numero, t.nom as table_nom, u.nom_complet as serveur_nom
             FROM {$this->table} c
             LEFT JOIN restaurant_tables t ON c.table_id = t.id
             LEFT JOIN utilisateurs u ON c.serveur_id = u.id
             $where",
            $params
        );
    }

    public function create($data)
    {
        $sql = "INSERT INTO {$this->table} (shop_id, table_id, serveur_id, statut)
                VALUES (:shop_id, :table_id, :serveur_id, 'ouverte')";
        $this->db->execute($sql, [
            ':shop_id'    => $data['shop_id'],
            ':table_id'   => $data['table_id'],
            ':serveur_id' => $data['serveur_id'],
        ]);
        return $this->db->lastInsertId();
    }

    public function updateStatut($id, $statut)
    {
        if (!in_array($statut, $this->validStatuts)) return false;
        return $this->db->execute("UPDATE {$this->table} SET statut = ? WHERE id = ?", [$statut, $id]);
    }

    public function setRemise($id, $remise)
    {
        $this->db->execute("UPDATE {$this->table} SET remise = ? WHERE id = ?", [$remise, $id]);
        $this->recalculateTotals($id);
    }

    public function markPaid($id, $venteId)
    {
        return $this->db->execute(
            "UPDATE {$this->table} SET statut = 'payee', vente_id = ? WHERE id = ?",
            [$venteId, $id]
        );
    }

    // ── Lignes de commande ───────────────────────────────────────

    public function getDetails($commandeId)
    {
        return $this->db->fetchAll(
            "SELECT d.*, m.nom as plat_nom, m.temps_preparation
             FROM {$this->detailsTable} d
             LEFT JOIN restaurant_menu_items m ON d.menu_item_id = m.id
             WHERE d.commande_id = ?
             ORDER BY d.id ASC",
            [$commandeId]
        );
    }

    public function findDetailById($detailId)
    {
        return $this->db->fetch("SELECT * FROM {$this->detailsTable} WHERE id = ?", [$detailId]);
    }

    public function addDetail($data)
    {
        $sql = "INSERT INTO {$this->detailsTable} (commande_id, menu_item_id, quantite, prix_unitaire, commentaire)
                VALUES (:commande_id, :menu_item_id, :quantite, :prix_unitaire, :commentaire)";
        $this->db->execute($sql, [
            ':commande_id'   => $data['commande_id'],
            ':menu_item_id'  => $data['menu_item_id'],
            ':quantite'      => $data['quantite'],
            ':prix_unitaire' => $data['prix_unitaire'],
            ':commentaire'   => $data['commentaire'] ?? null,
        ]);
        $id = $this->db->lastInsertId();
        $this->recalculateTotals($data['commande_id']);
        return $id;
    }

    public function updateDetailQuantity($detailId, $quantite, $commandeId)
    {
        $this->db->execute("UPDATE {$this->detailsTable} SET quantite = ? WHERE id = ?", [$quantite, $detailId]);
        $this->recalculateTotals($commandeId);
    }

    public function removeDetail($detailId, $commandeId)
    {
        $this->db->execute("DELETE FROM {$this->detailsTable} WHERE id = ?", [$detailId]);
        $this->recalculateTotals($commandeId);
    }

    // ── Calcul des totaux ────────────────────────────────────────

    public function recalculateTotals($commandeId)
    {
        $row = $this->db->fetch(
            "SELECT COALESCE(SUM(quantite * prix_unitaire), 0) as sous_total
             FROM {$this->detailsTable} WHERE commande_id = ?",
            [$commandeId]
        );
        $sousTotal = (float)($row['sous_total'] ?? 0);

        $order = $this->findById($commandeId);
        $remise = (float)($order['remise'] ?? 0);

        $taxRate = $this->getDefaultTaxRate();
        $taxes = round(($sousTotal - $remise) * ($taxRate / 100), 2);
        $total = round($sousTotal - $remise + $taxes, 2);

        $this->db->execute(
            "UPDATE {$this->table} SET sous_total = ?, taxes = ?, total = ? WHERE id = ?",
            [$sousTotal, $taxes, $total, $commandeId]
        );
    }

    private function getDefaultTaxRate()
    {
        $tax = $this->db->fetch("SELECT taux FROM taxes ORDER BY id ASC LIMIT 1");
        return $tax ? (float)$tax['taux'] : 0;
    }

    // ── Transfert / Fusion ───────────────────────────────────────

    public function transferTable($commandeId, $tableId)
    {
        return $this->db->execute(
            "UPDATE {$this->table} SET table_id = ? WHERE id = ?",
            [$tableId, $commandeId]
        );
    }

    public function merge($fromId, $toId)
    {
        $this->db->execute(
            "UPDATE {$this->detailsTable} SET commande_id = ? WHERE commande_id = ?",
            [$toId, $fromId]
        );

        $fromNumero = $this->db->fetch("SELECT numero FROM {$this->table} WHERE id = ?", [$fromId]);
        $numero = $fromNumero['numero'] ?? $fromId;

        $this->db->execute(
            "UPDATE {$this->table} SET parent_commande_id = ?, merged_from = ?, merged_at = NOW(), statut = 'annulee'\n             WHERE id = ?",
            [$toId, $numero, $fromId]
        );

        $this->recalculateTotals($toId);
        return true;
    }
}
