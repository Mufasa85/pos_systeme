<?php

namespace App\Models;

use App\Core\Database;

class Report
{
    private $db;

    public function __construct()
    {
        $this->db = Database::getInstance();
    }

    // ── Z-rapports ──────────────────────────────────────────────

    public function getLastZReport($shopId)
    {
        $sql = 'SELECT * FROM z_reports WHERE shop_id = ? AND status = "active" ORDER BY issued_at DESC LIMIT 1';
        return $this->db->fetch($sql, [$shopId]);
    }

    public function createZReport($shopId, $periodStart, $periodEnd, $data)
    {
        // Archiver l'ancien Z-rapport actif
        $this->db->execute(
            'UPDATE z_reports SET status = "archived" WHERE shop_id = ? AND status = "active"',
            [$shopId]
        );

        // Calculer le compteur séquentiel
        $counter = $this->db->fetch(
            'SELECT COALESCE(MAX(counter), 0) + 1 as next FROM z_reports WHERE shop_id = ?',
            [$shopId]
        );
        $nextCounter = $counter['next'] ?? 1;

        $sql = 'INSERT INTO z_reports (shop_id, period_start, period_end, counter, total_sales_ht, total_tax, total_ttc, invoice_count, status)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, "active")';
        $this->db->execute($sql, [
            $shopId,
            $periodStart,
            $periodEnd,
            $nextCounter,
            $data['total_ht'] ?? 0,
            $data['total_tax'] ?? 0,
            $data['total_ttc'] ?? 0,
            $data['invoice_count'] ?? 0,
        ]);

        return $this->db->lastInsertId();
    }

    // ── A-rapports ──────────────────────────────────────────────

    public function getLastAReport($shopId)
    {
        $sql = 'SELECT * FROM a_reports WHERE shop_id = ? AND status = "active" ORDER BY issued_at DESC LIMIT 1';
        return $this->db->fetch($sql, [$shopId]);
    }

    public function createAReport($shopId, $periodStart, $periodEnd, $data)
    {
        // Archiver l'ancien A-rapport actif
        $this->db->execute(
            'UPDATE a_reports SET status = "archived" WHERE shop_id = ? AND status = "active"',
            [$shopId]
        );

        // Calculer le compteur séquentiel
        $counter = $this->db->fetch(
            'SELECT COALESCE(MAX(counter), 0) + 1 as next FROM a_reports WHERE shop_id = ?',
            [$shopId]
        );
        $nextCounter = $counter['next'] ?? 1;

        $sql = 'INSERT INTO a_reports (shop_id, period_start, period_end, counter, articles_count, total_quantity_sold, total_quantity_returned, total_amount_collected, status)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, "active")';
        $this->db->execute($sql, [
            $shopId,
            $periodStart,
            $periodEnd,
            $nextCounter,
            $data['articles_count'] ?? 0,
            $data['total_quantity_sold'] ?? 0,
            $data['total_quantity_returned'] ?? 0,
            $data['total_amount_collected'] ?? 0,
        ]);

        return $this->db->lastInsertId();
    }

    // ── Ventes par type de facture ──────────────────────────────

    public function getSalesByInvoiceType($shopId, $startDate, $endDate)
    {
        $sql = 'SELECT COALESCE(v.invoice_type, "FV") as invoice_type,
                       COUNT(v.id) as invoice_count,
                       COALESCE(SUM(v.sous_total_ht), 0) as total_ht,
                       COALESCE(SUM(v.tva), 0) as total_tax,
                       COALESCE(SUM(v.total), 0) as total_ttc
                FROM ventes v
                WHERE v.date BETWEEN ? AND ? AND (? IS NULL OR v.shop_id = ?)
                GROUP BY v.invoice_type ORDER BY v.invoice_type';
        return $this->db->fetchAll($sql, [$startDate, $endDate, $shopId, $shopId]);
    }

    // ── Ventes par groupe de taxation ───────────────────────────

    public function getSalesByTaxGroup($shopId, $startDate, $endDate)
    {
        $sql = 'SELECT t.groupe_taxe, t.etiquette, t.taux,
                       COUNT(DISTINCT v.id) as invoice_count,
                       COALESCE(SUM(dv.quantite * dv.prix), 0) as total_ht,
                       COALESCE(SUM(dv.quantite * dv.prix * t.taux / 100), 0) as total_tax,
                       COALESCE(SUM(dv.quantite * dv.prix * (1 + t.taux / 100)), 0) as total_ttc
                FROM details_vente dv
                INNER JOIN ventes v ON dv.vente_id = v.id
                LEFT JOIN produits p ON dv.produit_id = p.id
                LEFT JOIN taxes t ON p.taxe_id = t.id
                WHERE v.date BETWEEN ? AND ? AND (? IS NULL OR v.shop_id = ?)
                GROUP BY t.groupe_taxe, t.etiquette, t.taux ORDER BY t.groupe_taxe';
        return $this->db->fetchAll($sql, [$startDate, $endDate, $shopId, $shopId]);
    }

    // ── Paiements (JSON parsé côté service) ─────────────────────

    public function getPaymentsParsed($shopId, $startDate, $endDate)
    {
        $sql = 'SELECT v.payments FROM ventes v
                WHERE v.date BETWEEN ? AND ? AND (? IS NULL OR v.shop_id = ?)
                  AND v.payments IS NOT NULL AND v.payments != ""';
        return $this->db->fetchAll($sql, [$startDate, $endDate, $shopId, $shopId]);
    }

    // ── Réductions commerciales ────────────────────────────────

    public function getTotalDiscounts($shopId, $startDate, $endDate)
    {
        $sql = 'SELECT COUNT(DISTINCT v.id) as invoices_with_discount,
                       COALESCE(SUM(
                           CASE WHEN dv.remise_type IN ("%","percent")
                               THEN (dv.prix * dv.quantite * dv.remise_value / 100)
                               ELSE dv.remise_value END
                       ), 0) as total_discount_amount
                FROM details_vente dv
                INNER JOIN ventes v ON dv.vente_id = v.id
                WHERE v.date BETWEEN ? AND ? AND (? IS NULL OR v.shop_id = ?) AND dv.remise_value > 0';
        return $this->db->fetch($sql, [$startDate, $endDate, $shopId, $shopId]);
    }

    // ── Avoirs et annulations ───────────────────────────────────

    public function getCreditNotes($shopId, $startDate, $endDate)
    {
        $sql = 'SELECT v.invoice_type as invoice_type,
                       COUNT(v.id) as invoice_count,
                       COALESCE(SUM(v.sous_total_ht), 0) as total_ht,
                       COALESCE(SUM(v.tva), 0) as total_tax,
                       COALESCE(SUM(v.total), 0) as total_ttc
                FROM ventes v
                WHERE v.date BETWEEN ? AND ? AND v.invoice_type IN ("FA", "EA")
                  AND (? IS NULL OR v.shop_id = ?)
                GROUP BY v.invoice_type';
        return $this->db->fetchAll($sql, [$startDate, $endDate, $shopId, $shopId]);
    }

    // ── Ventes incomplètes ─────────────────────────────────────

    public function getIncompleteSalesCount($shopId, $startDate, $endDate)
    {
        $sql = 'SELECT COUNT(*) as incomplete_count FROM ventes v
                WHERE v.date BETWEEN ? AND ? AND (? IS NULL OR v.shop_id = ?)
                  AND (v.total IS NULL OR v.total = 0 OR v.payments IS NULL OR v.payments = "")';
        $result = $this->db->fetch($sql, [$startDate, $endDate, $shopId, $shopId]);
        return $result['incomplete_count'] ?? 0;
    }

    // ── A-rapport : détail des articles ────────────────────────

    public function getArticlesReport($shopId, $startDate, $endDate)
    {
        $sql = 'SELECT
                    p.id,
                    p.code_barres as article_code,
                    p.nom as article_name,
                    p.prix as unit_price,
                    p.stock as stock_quantity,
                    COALESCE(t.taux, 0) as tax_rate,
                    t.groupe_taxe,
                    t.etiquette as tax_etiquette,
                    c.id as category_id,
                    c.category as category_name,
                    COALESCE(SUM(CASE WHEN v.date BETWEEN ? AND ?
                                        AND (? IS NULL OR v.shop_id = ?)
                                        AND dv.quantite > 0
                                  THEN dv.quantite ELSE 0 END), 0) as quantite_vendue,
                    COALESCE(SUM(CASE WHEN v.date BETWEEN ? AND ?
                                        AND (? IS NULL OR v.shop_id = ?)
                                        AND dv.quantite < 0
                                  THEN ABS(dv.quantite) ELSE 0 END), 0) as quantite_retournee,
                    COALESCE(SUM(CASE WHEN v.date BETWEEN ? AND ?
                                        AND (? IS NULL OR v.shop_id = ?)
                                        AND dv.quantite > 0
                                  THEN (dv.quantite * dv.prix) ELSE 0 END), 0) as montant_collecte
                FROM produits p
                LEFT JOIN categories c ON p.category_id = c.id
                LEFT JOIN taxes t ON p.taxe_id = t.id
                LEFT JOIN details_vente dv ON dv.produit_id = p.id
                LEFT JOIN ventes v ON dv.vente_id = v.id
                WHERE (? IS NULL OR p.shop_id = ?)
                GROUP BY p.id, p.code_barres, p.nom, p.prix, p.stock,
                         t.taux, t.groupe_taxe, t.etiquette, c.id, c.category
                ORDER BY c.category, p.nom';
        return $this->db->fetchAll($sql, [
            $startDate, $endDate, $shopId, $shopId,
            $startDate, $endDate, $shopId, $shopId,
            $startDate, $endDate, $shopId, $shopId,
            $shopId, $shopId,
        ]);
    }

    // ── Infos boutique / société ───────────────────────────────

    public function getShopInfo($shopId)
    {
        if ($shopId) {
            $sql = 'SELECT s.nom, s.isf, s.adresse, s.telephone, s.email, s.ice, s.rccm
                    FROM shops s WHERE s.id = ?';
            return $this->db->fetch($sql, [$shopId]);
        }

        $sql = 'SELECT c.name as nom, c.isf, c.address as adresse, c.phone as telephone, c.email, c.ice, c.rccm
                FROM company_info c WHERE c.id = 1';
        return $this->db->fetch($sql);
    }
}
