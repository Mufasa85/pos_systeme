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
    //
    // Inclut les factures HÔTEL (ventes.est_hotel = 1) : leur `invoice_type`
    // est le type fiscal réel (FV, EV, FT, FA, EA, ET) depuis la migration
    // 2026_11_05.
    //
    // ⚠️ Les EXTRA hôtel (ventes.invoice_type = 'EX') sont EXCLUS : ce sont
    // des consommations internes déjà incluses dans le total de la facture
    // hôtel. Les compter ici double-compterait le CA (cf. getSalesByTaxGroup
    // et getPaymentsParsed, même règle).
    public function getSalesByInvoiceType($shopId, $startDate, $endDate)
    {
        $sql = 'SELECT COALESCE(v.invoice_type, "FV") as invoice_type,
                       COUNT(v.id) as invoice_count,
                       COALESCE(SUM(v.sous_total_ht), 0) as total_ht,
                       COALESCE(SUM(v.tva), 0) as total_tax,
                       COALESCE(SUM(v.total), 0) as total_ttc
                FROM ventes v
                WHERE v.date BETWEEN ? AND ? AND (? IS NULL OR v.shop_id = ?)
                  AND (v.invoice_type IS NULL OR v.invoice_type != "EX")
                GROUP BY v.invoice_type ORDER BY v.invoice_type';
        return $this->db->fetchAll($sql, [$startDate, $endDate, $shopId, $shopId]);
    }

    // ── Ventes par groupe de taxation ───────────────────────────
    //
    // Le taux de TVA est lu sur la LIGNE DE DÉTAIL (`dv.tax_rate`, snapshot
    // figé à la facturation — migration 2026_11_06) et on ne retombe sur la
    // taxe du produit (`t.taux`) que si le snapshot est absent (lignes
    // antérieures à la migration).
    //
    // Pourquoi c'est nécessaire : les NUITÉES d'hôtel n'ont pas de produit
    // (`produit_id = NULL` — une chambre n'est pas un produit stockable).
    // Avant, `LEFT JOIN produits` → `t.taux = NULL` → ces lignes étaient
    // TOTALEMENT ABSENTES du rapport (ni montant, ni compteur).
    //
    // Le CA des nuitées est `quantite × prix` (le prix HT de la nuitée) ; la
    // taxe est calculée sur cette base, comme pour les produits.
    //
    // ⚠️ Les EXTRA hôtel (ventes.invoice_type = 'EX') sont EXCLUS de ce
    // décompte : ils sont déjà inclus dans le total des factures hôtel
    // (`ventes.est_hotel = 1`). Les compter ici reviendrait à compter le CA
    // deux fois. Ils restent visibles dans le bloc dédié « Extras » du
    // rapport hôtel.
    public function getSalesByTaxGroup($shopId, $startDate, $endDate)
    {
        $sql = 'SELECT COALESCE(dv.tax_groupe, t.groupe_taxe, "Groupe B") as groupe_taxe,
                       COALESCE(dv.tax_etiquette, t.etiquette, "B") as etiquette,
                       COALESCE(dv.tax_rate, t.taux, 0) as taux,
                       COUNT(DISTINCT v.id) as invoice_count,
                       COALESCE(SUM(dv.quantite * dv.prix), 0) as total_ht,
                       COALESCE(SUM(dv.quantite * dv.prix * COALESCE(dv.tax_rate, t.taux, 0) / 100), 0) as total_tax,
                       COALESCE(SUM(dv.quantite * dv.prix * (1 + COALESCE(dv.tax_rate, t.taux, 0) / 100)), 0) as total_ttc
                FROM details_vente dv
                INNER JOIN ventes v ON dv.vente_id = v.id
                LEFT JOIN produits p ON dv.produit_id = p.id
                LEFT JOIN taxes t ON p.taxe_id = t.id
                WHERE v.date BETWEEN ? AND ? AND (? IS NULL OR v.shop_id = ?)
                  AND (v.invoice_type IS NULL OR v.invoice_type != "EX")
                GROUP BY COALESCE(dv.tax_groupe, t.groupe_taxe, "Groupe B"),
                         COALESCE(dv.tax_etiquette, t.etiquette, "B"),
                         COALESCE(dv.tax_rate, t.taux, 0)
                ORDER BY groupe_taxe';
        return $this->db->fetchAll($sql, [$startDate, $endDate, $shopId, $shopId]);
    }

    // ── HÔTEL : CA par chambre ──────────────────────────────────
    //
    // Bloc dédié au CA hôtelier, ventilé par CHAMBRE. Il ne porte que sur
    // les NUITÉES (lignes de détail `ligne_type = 'nuit'` des factures hôtel).
    //
    // Les extras ne sont PAS ici : ils ont leur propre bloc (voir
    // getHotelExtrasReport) et sont déjà inclus dans le total des factures
    // hôtel — les compter ici reviendrait à double-compter.
    public function getHotelReportByRoom($shopId, $startDate, $endDate)
    {
        $sql = 'SELECT c.id as chambre_id,
                       c.numero as chambre_numero,
                       c.type as chambre_type,
                       COUNT(DISTINCT v.id) as nb_factures,
                       COALESCE(SUM(dv.quantite), 0) as nb_nuitees,
                       COALESCE(SUM(dv.quantite * dv.prix), 0) as total_ht,
                       COALESCE(SUM(dv.quantite * dv.prix * COALESCE(dv.tax_rate, 0) / 100), 0) as total_tax,
                       COALESCE(SUM(dv.quantite * dv.prix * (1 + COALESCE(dv.tax_rate, 0) / 100)), 0) as total_ttc
                FROM details_vente dv
                INNER JOIN ventes v ON dv.vente_id = v.id
                INNER JOIN hotel_chambres c ON c.id = dv.chambre_id
                WHERE v.date BETWEEN ? AND ? AND (? IS NULL OR v.shop_id = ?)
                  AND v.est_hotel = 1
                  AND dv.ligne_type = "nuit"
                GROUP BY c.id, c.numero, c.type
                ORDER BY c.numero';
        return $this->db->fetchAll($sql, [$startDate, $endDate, $shopId, $shopId]);
    }

    // ── HÔTEL : ventilation nuitées / extras ────────────────────
    //
    // Détaille ce qui compose le CA d'une facture hôtel :
    //   - les NUITÉES (montant HT des lignes `ligne_type = 'nuit'`) ;
    //   - les EXTRA (montant HT des lignes `ligne_type = 'extra'`).
    //
    // ⚠️ Les extras sont affichés pour information (la direction peut les
    // isoler), mais ils sont DÉJÀ inclus dans le total des factures hôtel
    // (`ventes.est_hotel = 1`) : ils ne doivent pas être additionnés une
    // seconde fois au CA consolidé.
    public function getHotelBreakdown($shopId, $startDate, $endDate)
    {
        // Nuitées : HT des lignes de nuit des factures hôtel.
        $nuites = $this->db->fetch(
            'SELECT COUNT(DISTINCT v.id) as nb_factures,
                    COALESCE(SUM(dv.quantite), 0) as nb_nuitees,
                    COALESCE(SUM(dv.quantite * dv.prix), 0) as total_ht,
                    COALESCE(SUM(dv.quantite * dv.prix * COALESCE(dv.tax_rate, 0) / 100), 0) as total_tax
             FROM details_vente dv
             INNER JOIN ventes v ON dv.vente_id = v.id
             WHERE v.date BETWEEN ? AND ? AND (? IS NULL OR v.shop_id = ?)
               AND v.est_hotel = 1
               AND dv.ligne_type = "nuit"',
            [$startDate, $endDate, $shopId, $shopId]
        ) ?? [];

        // Extras : lignes de détail `ligne_type = 'extra'` des factures hôtel.
        //
        // ⚠️ SOURCE UNIQUE : on lit les lignes de la FACTURE HÔTEL, pas les
        // ventes 'EX' ni la table `hotel_extras`. Les extras sont désormais
        // matérialisés en lignes `details_vente` au moment de la facturation
        // (cf. HotelFactureController::store()) : c'est la même source que
        // celle du « Par groupe de taxation » et de l'A-rapport → une seule
        // vérité, aucun double compte possible.
        $extras = $this->db->fetch(
            'SELECT COUNT(*) as nb_lignes,
                    COALESCE(SUM(dv.quantite * dv.prix), 0) as total_ht,
                    COALESCE(SUM(dv.quantite * dv.prix * COALESCE(dv.tax_rate, 0) / 100), 0) as total_tax
             FROM details_vente dv
             INNER JOIN ventes v ON dv.vente_id = v.id
             WHERE v.date BETWEEN ? AND ? AND (? IS NULL OR v.shop_id = ?)
               AND v.est_hotel = 1
               AND dv.ligne_type = "extra"',
            [$startDate, $endDate, $shopId, $shopId]
        ) ?? [];

        // Total des factures hôtel (nuitées + extras consolidés).
        $factures = $this->db->fetch(
            'SELECT COUNT(*) as nb_factures,
                    COALESCE(SUM(v.sous_total_ht), 0) as total_ht,
                    COALESCE(SUM(v.tva), 0) as total_tax,
                    COALESCE(SUM(v.total), 0) as total_ttc
             FROM ventes v
             WHERE v.date BETWEEN ? AND ? AND (? IS NULL OR v.shop_id = ?)
               AND v.est_hotel = 1',
            [$startDate, $endDate, $shopId, $shopId]
        ) ?? [];

        return [
            'factures' => [
                'nb_factures' => (int)($factures['nb_factures'] ?? 0),
                'total_ht'    => (float)($factures['total_ht'] ?? 0),
                'total_tax'   => (float)($factures['total_tax'] ?? 0),
                'total_ttc'   => (float)($factures['total_ttc'] ?? 0),
            ],
            'nuites' => [
                'nb_nuitees' => (float)($nuites['nb_nuitees'] ?? 0),
                'total_ht'   => (float)($nuites['total_ht'] ?? 0),
                'total_tax'  => (float)($nuites['total_tax'] ?? 0),
            ],
            // ⚠️ Informationnel : déjà inclus dans le total des factures hôtel.
            'extras' => [
                'nb_lignes' => (int)($extras['nb_lignes'] ?? 0),
                'total_ht'  => (float)($extras['total_ht'] ?? 0),
                'total_tax' => (float)($extras['total_tax'] ?? 0),
            ],
        ];
    }

    // ── HÔTEL : extras par produit ──────────────────────────────
    //
    // Détail des extras hôtel (bar, room service...). Affiché pour
    // information : ces montants sont DÉJÀ inclus dans les factures hôtel.
    //
    // Source : les lignes `ligne_type = 'extra'` des factures hôtel (même
    // source que le « Par groupe de taxation » → une seule vérité).
    public function getHotelExtrasReport($shopId, $startDate, $endDate)
    {
        $sql = 'SELECT COALESCE(p.nom, dv.libelle, "Extra") as article_nom,
                       COALESCE(SUM(dv.quantite), 0) as quantite,
                       COALESCE(SUM(dv.quantite * dv.prix), 0) as total_ht,
                       COALESCE(SUM(dv.quantite * dv.prix * COALESCE(dv.tax_rate, t.taux, 0) / 100), 0) as total_tax
                FROM details_vente dv
                INNER JOIN ventes v ON dv.vente_id = v.id
                LEFT JOIN produits p ON p.id = dv.produit_id
                LEFT JOIN taxes t ON t.id = p.taxe_id
                WHERE v.date BETWEEN ? AND ? AND (? IS NULL OR v.shop_id = ?)
                  AND v.est_hotel = 1
                  AND dv.ligne_type = "extra"
                GROUP BY COALESCE(p.nom, dv.libelle, "Extra")
                ORDER BY total_ht DESC';
        return $this->db->fetchAll($sql, [$startDate, $endDate, $shopId, $shopId]);
    }

    //
    // ⚠️ Les EXTRA hôtel (ventes.invoice_type = 'EX') sont EXCLUS : leur
    // encaissement est déjà compté dans les paiements de la facture hôtel
    // (qui porte le montant total TTC facturé au client). Les compter aussi
    // ici ferait apparaître le même euro deux fois dans le total encaissé.
    public function getPaymentsParsed($shopId, $startDate, $endDate)
    {
        $sql = 'SELECT v.payments FROM ventes v
                WHERE v.date BETWEEN ? AND ? AND (? IS NULL OR v.shop_id = ?)
                  AND v.payments IS NOT NULL AND v.payments != ""
                  AND (v.invoice_type IS NULL OR v.invoice_type != "EX")';
        return $this->db->fetchAll($sql, [$startDate, $endDate, $shopId, $shopId]);
    }

    // ── Réductions commerciales ────────────────────────────────
    //
    // ⚠️ Les EXTRA hôtel (`invoice_type = 'EX'`) sont EXCLUS : leur remise
    // est déjà incluse dans la remise de la facture hôtel consolidée.
    // Les compter ici ferait apparaître la remise deux fois.
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
                WHERE v.date BETWEEN ? AND ? AND (? IS NULL OR v.shop_id = ?) AND dv.remise_value > 0
                  AND (v.invoice_type IS NULL OR v.invoice_type != "EX")';
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
    //
    // Deux sources :
    //   1. les PRODUITS du catalogue (ventes POS) ;
    //   2. les NUITÉES D'HÔTEL (lignes `ligne_type = 'nuit'` des factures
    //      hôtel) — agrégées par chambre.
    //
    // ⚠️ Les nuitées n'ont pas de produit (`produit_id = NULL` : une chambre
    // n'est pas un produit stockable) : sans cette seconde source, l'A-rapport
    // ne voyait AUCUNE nuité hôtel, alors qu'elles constituent l'essentiel du
    // CA de l'hôtel.
    //
    // Les extras hôtel (`invoice_type = 'EX'`) sont EXCLUS : ils sont déjà
    // inclus dans le total des factures hôtel (cf. règle de non-double-compte).
    public function getArticlesReport($shopId, $startDate, $endDate)
    {
        $sqlProduits = 'SELECT
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
                         t.taux, t.groupe_taxe, t.etiquette, c.id, c.category';

        // Nuitées d'hôtel : agrégées par chambre, présentées comme des
        // « articles » de la catégorie Hôtel.
        $sqlNuites = 'SELECT
                    NULL as id,
                    CONCAT("CH-", c.numero) as article_code,
                    CONCAT("Nuitée chambre ", c.numero) as article_name,
                    AVG(dv.prix) as unit_price,
                    NULL as stock_quantity,
                    COALESCE(dv.tax_rate, 0) as tax_rate,
                    COALESCE(dv.tax_groupe, "Groupe B") as groupe_taxe,
                    COALESCE(dv.tax_etiquette, "B") as tax_etiquette,
                    NULL as category_id,
                    "Hôtel" as category_name,
                    COALESCE(SUM(dv.quantite), 0) as quantite_vendue,
                    0 as quantite_retournee,
                    COALESCE(SUM(dv.quantite * dv.prix), 0) as montant_collecte
                FROM details_vente dv
                INNER JOIN ventes v ON dv.vente_id = v.id
                INNER JOIN hotel_chambres c ON c.id = dv.chambre_id
                WHERE dv.ligne_type = "nuit"
                  AND v.est_hotel = 1
                  AND v.date BETWEEN ? AND ?
                  AND (? IS NULL OR v.shop_id = ?)
                GROUP BY c.id, c.numero, dv.tax_rate, dv.tax_groupe, dv.tax_etiquette';

        // Extras d'hôtel : agrégés par produit, dans la catégorie
        // « Hôtel - Extras ». Ce sont les LIGNES de la facture hôtel
        // (`ligne_type = 'extra'`) → même source que le « Par groupe de
        // taxation » → les produits consommés en extra apparaissent
        // enfin dans l'A-rapport.
        $sqlExtras = 'SELECT
                    NULL as id,
                    COALESCE(p.code_barres, CONCAT("EX-", dv.produit_id)) as article_code,
                    COALESCE(p.nom, dv.libelle, "Extra") as article_name,
                    AVG(dv.prix) as unit_price,
                    NULL as stock_quantity,
                    COALESCE(dv.tax_rate, t.taux, 0) as tax_rate,
                    COALESCE(dv.tax_groupe, t.groupe_taxe, "Groupe B") as groupe_taxe,
                    COALESCE(dv.tax_etiquette, t.etiquette, "B") as tax_etiquette,
                    NULL as category_id,
                    "Hôtel - Extras" as category_name,
                    COALESCE(SUM(dv.quantite), 0) as quantite_vendue,
                    0 as quantite_retournee,
                    COALESCE(SUM(dv.quantite * dv.prix), 0) as montant_collecte
                FROM details_vente dv
                INNER JOIN ventes v ON dv.vente_id = v.id
                LEFT JOIN produits p ON p.id = dv.produit_id
                LEFT JOIN taxes t ON t.id = p.taxe_id
                WHERE dv.ligne_type = "extra"
                  AND v.est_hotel = 1
                  AND v.date BETWEEN ? AND ?
                  AND (? IS NULL OR v.shop_id = ?)
                GROUP BY dv.produit_id, COALESCE(p.code_barres, CONCAT("EX-", dv.produit_id)),
                         COALESCE(p.nom, dv.libelle, "Extra"),
                         dv.tax_rate, dv.tax_groupe, dv.tax_etiquette';

        $sql = $sqlProduits . ' ORDER BY c.category, p.nom';
        $params = [
            $startDate, $endDate, $shopId, $shopId,
            $startDate, $endDate, $shopId, $shopId,
            $startDate, $endDate, $shopId, $shopId,
            $shopId, $shopId,
        ];

        $articles = $this->db->fetchAll($sql, $params);

        // Second jeu : les nuitées d'hôtel + les extras d'hôtel. On les
        // fusionne et on trie par catégorie puis par nom.
        $nuites = $this->db->fetchAll($sqlNuites, [$startDate, $endDate, $shopId, $shopId]);
        $extras = $this->db->fetchAll($sqlExtras, [$startDate, $endDate, $shopId, $shopId]);

        if ($nuites || $extras) {
            $articles = array_merge($articles, $nuites, $extras);
            usort($articles, static function ($a, $b) {
                $catA = (string)($a['category_name'] ?? '');
                $catB = (string)($b['category_name'] ?? '');
                if ($catA !== $catB) {
                    return strcmp($catA, $catB);
                }
                return strcmp((string)($a['article_name'] ?? ''), (string)($b['article_name'] ?? ''));
            });
        }

        return $articles;
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
