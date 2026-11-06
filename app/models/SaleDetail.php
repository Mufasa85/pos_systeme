<?php

namespace App\Models;

// app/models/SaleDetail.php

class SaleDetail
{
    private $db;

    public function __construct()
    {
        $this->db = \App\Core\Database::getInstance();
    }

    public function create($data)
    {
        // `sejour_id`, `chambre_id`, `ligne_type` et `libelle` : tracabilité
        // hôtel (migration 2026_11_05). Elles restent NULL / 'produit' pour
        // les ventes POS classiques.
        $sql = 'INSERT INTO details_vente (vente_id, produit_id, quantite, prix, remise_type, remise_value, taxe_specifique_type, taxe_specifique_value, sejour_id, chambre_id, ligne_type, libelle, tax_rate, tax_etiquette, tax_groupe) 
                VALUES (:vente_id, :produit_id, :quantite, :prix, :remise_type, :remise_value, :taxe_specifique_type, :taxe_specifique_value, :sejour_id, :chambre_id, :ligne_type, :libelle, :tax_rate, :tax_etiquette, :tax_groupe)';
        return $this->db->query($sql, [
            ':vente_id'   => $data['vente_id'],
            ':produit_id' => $data['produit_id'],
            ':quantite'   => $data['quantite'],
            ':prix'       => $data['prix'],
            ':remise_type' => $data['remise_type'] ?? '%',
            ':remise_value' => $data['remise_value'] ?? 0,
            ':taxe_specifique_type' => $data['taxe_specifique_type'] ?? '%',
            ':taxe_specifique_value' => $data['taxe_specifique_value'] ?? 0,
            ':sejour_id'  => $data['sejour_id'] ?? null,
            ':chambre_id' => $data['chambre_id'] ?? null,
            ':ligne_type' => $data['ligne_type'] ?? 'produit',
            ':libelle'    => $data['libelle'] ?? null,
            // Snapshot du groupe de taxation (migration 2026_11_06) : sans lui,
            // les lignes SANS produit (nuitées d'hôtel, recharges) disparaissent
            // du rapport « Par groupe de taxation ».
            ':tax_rate'      => $data['tax_rate'] ?? 0,
            ':tax_etiquette' => $data['tax_etiquette'] ?? null,
            ':tax_groupe'    => $data['tax_groupe'] ?? null,
        ]);
    }
    public function exist($id)
    {
        return $this->db->fetch('SELECT * FROM details_vente WHERE id = ?', [$id]);
    }

    public function getBySaleId($saleId)
    {
        // `dv.libelle` est le libellé FIGÉ au moment de la facturation (lignes
        // hôtel). Il est prioritaire sur `produits.nom` : un produit peut être
        // renommé après coup, la facture déjà émise ne doit pas changer.
        //
        // `tax_rate` / `tax_etiquette` : on lit le SNAPSHOT de la ligne en
        // priorité (`dv.tax_rate`), et on ne retombe sur la taxe du produit
        // (`t.taux`) que pour les lignes antérieures à la migration
        // 2026_11_06 (snapshot NULL). Sans ça, une ligne de nuitée
        // (produit_id NULL) perdrait son groupe de taxation à l'affichage.
        $sql = 'SELECT dv.*, p.nom as produit_nom, p.code_barres,
                       COALESCE(dv.tax_rate, t.taux, 0) as tax_rate,
                       COALESCE(dv.tax_etiquette, t.etiquette) as tax_etiquette
                FROM details_vente dv 
                LEFT JOIN produits p ON dv.produit_id = p.id 
                LEFT JOIN taxes t ON p.taxe_id = t.id
                WHERE dv.vente_id = ?';
        return $this->db->fetchAll($sql, [$saleId]);
    }
}
