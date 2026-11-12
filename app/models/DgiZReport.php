<?php

namespace App\Models;

use App\Core\Database;

/**
 * Clôtures de période fiscale DGI (obtenues via l'API OSAT).
 *
 * Distinct de la table `z_reports` (qui suit les Z-rapports LOCAUX du POS) :
 * ici, les montants proviennent de la DGI et servent de référence de
 * synchronisation. Mélanger les deux tables rendrait impossible de savoir
 * quel jeu de chiffres a été présenté à l'administration fiscale.
 */
class DgiZReport
{
    private $db;

    public function __construct()
    {
        $this->db = Database::getInstance();
    }

    /**
     * Dernière clôture DGI active d'une boutique.
     */
    public function getLast(?int $shopId): ?array
    {
        $sql = 'SELECT * FROM dgi_z_reports
                WHERE (? IS NULL OR shop_id = ?) AND status = "active"
                ORDER BY period_end DESC, id DESC LIMIT 1';
        $result = $this->db->fetch($sql, [$shopId, $shopId]);

        return $result ?: null;
    }

    /**
     * Dernière clôture DGI **non vide** (au moins une facture).
     *
     * Une clôture vide ne doit pas servir de point de départ au chaînage :
     * elle ferait repartir la période suivante d'un instant arbitraire et
     * empêcherait de clôturer les ventes suivantes.
     */
    public function getLastWithInvoices(?int $shopId): ?array
    {
        $sql = 'SELECT * FROM dgi_z_reports
                WHERE (? IS NULL OR shop_id = ?) AND status = "active" AND invoice_count > 0
                ORDER BY period_end DESC, id DESC LIMIT 1';
        $result = $this->db->fetch($sql, [$shopId, $shopId]);

        return $result ?: null;
    }

    /**
     * Prochain numéro de clôture pour une boutique.
     */
    public function nextCounter(?int $shopId): int
    {
        $result = $this->db->fetch(
            'SELECT COALESCE(MAX(counter), 0) + 1 as next FROM dgi_z_reports WHERE shop_id = ?',
            [$shopId]
        );

        return (int)($result['next'] ?? 1);
    }

    /**
     * Enregistre une clôture DGI et archive la précédente.
     */
    public function create(?int $shopId, string $periodStart, string $periodEnd, array $totals, ?string $snapshot = null): int
    {
        if ($shopId) {
            $this->db->execute(
                'UPDATE dgi_z_reports SET status = "archived" WHERE shop_id = ? AND status = "active"',
                [$shopId]
            );
        }

        $sql = 'INSERT INTO dgi_z_reports
                (shop_id, counter, period_start, period_end, total_ht, total_tax, total_ts, total_ttc, invoice_count, discount_total, snapshot, status)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, "active")';
        $this->db->execute($sql, [
            $shopId,
            $this->nextCounter($shopId),
            $periodStart,
            $periodEnd,
            $totals['ht'] ?? 0,
            $totals['tva'] ?? 0,
            $totals['ts_total'] ?? 0,
            $totals['ttc'] ?? 0,
            $totals['invoice_count'] ?? 0,
            $totals['discount_total'] ?? 0,
            $snapshot,
        ]);

        return (int)$this->db->lastInsertId();
    }

    /**
     * Historique des clôtures DGI d'une boutique.
     */
    public function getHistory(?int $shopId, int $limit = 50): array
    {
        $sql = 'SELECT dzr.*, s.nom as shop_name
                FROM dgi_z_reports dzr
                LEFT JOIN shops s ON dzr.shop_id = s.id
                WHERE (? IS NULL OR dzr.shop_id = ?)
                ORDER BY dzr.issued_at DESC LIMIT ' . max(1, $limit);

        return $this->db->fetchAll($sql, [$shopId, $shopId]);
    }
}
