-- ============================================================================
-- MIGRATION : Lignes de détail des EXTRAS dans les factures hôtel (backfill)
-- Date       : 2026-11-07
-- Reference  : intégration de l'hôtel dans les rapports fiscaux
--
-- PROBLÈME
--   Les EXTRAS d'une facture hôtel (bar, room service...) n'avaient AUCUNE
--   ligne dans `details_vente` rattachée à la vente hôtel :
--     - les extras de caisse vivaient dans une VENTE SÉPARÉE (`invoice_type='EX'`) ;
--     - les extras du picker vivaient dans `hotel_extras` (vente_id = NULL) ;
--     - les extras saisis à la main n'étaient stockés NULLE PART.
--   Conséquence : les rapports (« Par groupe de taxation » et A-rapport) ne
--   voyaient QUE les nuitées : les produits consommés en extra étaient
--   totalement invisibles.
--
-- SOLUTION
--   À partir de maintenant (cf. HotelFactureController::store()), chaque extra
--   crée une ligne `details_vente` avec `ligne_type = 'extra'` sur la vente
--   hôtel. Cette migration RECONSTRUIT ces lignes pour les factures hôtel
--   DÉJÀ émises, à partir des ventes 'EX' et des lignes `hotel_extras`.
--
-- ⚠️ SOURCE UNIQUE : ces lignes deviennent la SEULE source des rapports pour
-- les extras. Les ventes 'EX' et `hotel_extras` restent les sources de
-- référence (preview + consolidation), mais les rapports les excluent.
--
-- Idempotent : on ne recrée que les lignes ABSENTES (pas de doublon si la
-- migration est rejouée).
-- ============================================================================

SET NAMES utf8mb4;

-- ----------------------------------------------------------------------------
-- 1. Extras de caisse : ventes 'EX' → lignes de la facture hôtel
-- ----------------------------------------------------------------------------
-- On rattache chaque ligne de détail d'une vente 'EX' à la facture hôtel du
-- même séjour (via `hotel_factures.vente_id`).
--
-- Le snapshot de taxe est repris de la ligne d'origine ; à défaut, on
-- remonte au groupe du produit (`produits.taxe_id` → `taxes`).
INSERT INTO `details_vente`
    (vente_id, produit_id, quantite, prix, remise_type, remise_value,
     taxe_specifique_type, taxe_specifique_value,
     sejour_id, chambre_id, ligne_type, libelle,
     tax_rate, tax_etiquette, tax_groupe)
SELECT
    hf.vente_id,
    dx.produit_id,
    dx.quantite,
    dx.prix,
    dx.remise_type,
    dx.remise_value,
    dx.taxe_specifique_type,
    dx.taxe_specifique_value,
    hf.sejour_id,
    s.chambre_id,
    'extra',
    COALESCE(dx.libelle, p.nom, 'Extra'),
    COALESCE(dx.tax_rate, t.taux, 0),
    COALESCE(dx.tax_etiquette, t.etiquette),
    COALESCE(dx.tax_groupe, t.groupe_taxe)
FROM `ventes` vx
INNER JOIN `details_vente` dx ON dx.vente_id = vx.id
INNER JOIN `hotel_factures` hf ON hf.sejour_id = vx.sejour_id
INNER JOIN `hotel_sejours` s ON s.id = hf.sejour_id
LEFT JOIN `produits` p ON p.id = dx.produit_id
LEFT JOIN `taxes` t ON t.id = p.taxe_id
WHERE vx.invoice_type = 'EX'
  AND vx.sejour_id IS NOT NULL
  -- On ne recrée pas une ligne si elle existe déjà (idempotence).
  AND NOT EXISTS (
      SELECT 1 FROM `details_vente` dcheck
      WHERE dcheck.vente_id = hf.vente_id
        AND dcheck.ligne_type = 'extra'
        AND dcheck.produit_id <=> dx.produit_id
        AND dcheck.quantite = dx.quantite
        AND dcheck.prix = dx.prix
  );

-- ----------------------------------------------------------------------------
-- 2. Extras du picker : `hotel_extras` (vente_id IS NULL) → facture hôtel
-- ----------------------------------------------------------------------------
INSERT INTO `details_vente`
    (vente_id, produit_id, quantite, prix, remise_type, remise_value,
     taxe_specifique_type, taxe_specifique_value,
     sejour_id, chambre_id, ligne_type, libelle,
     tax_rate, tax_etiquette, tax_groupe)
SELECT
    hf.vente_id,
    he.produit_id,
    COALESCE(he.quantite, 1),
    COALESCE(he.prix_unitaire, he.montant),
    he.remise_type,
    he.remise_value,
    he.taxe_specifique_type,
    he.taxe_specifique_value,
    hf.sejour_id,
    s.chambre_id,
    'extra',
    COALESCE(p.nom, he.description, 'Extra'),
    COALESCE(he.tax_rate, t.taux, 0),
    COALESCE(t.etiquette),
    COALESCE(t.groupe_taxe)
FROM `hotel_extras` he
INNER JOIN `hotel_factures` hf ON hf.sejour_id = he.sejour_id
INNER JOIN `hotel_sejours` s ON s.id = hf.sejour_id
LEFT JOIN `produits` p ON p.id = he.produit_id
LEFT JOIN `taxes` t ON t.id = p.taxe_id
WHERE he.vente_id IS NULL
  AND NOT EXISTS (
      SELECT 1 FROM `details_vente` dcheck
      WHERE dcheck.vente_id = hf.vente_id
        AND dcheck.ligne_type = 'extra'
        AND dcheck.produit_id <=> he.produit_id
        AND dcheck.quantite = COALESCE(he.quantite, 1)
        AND dcheck.prix = COALESCE(he.prix_unitaire, he.montant)
  );

-- ----------------------------------------------------------------------------
-- 3. Les ventes 'EX' restent en place (historique de l'encaissement)
-- ----------------------------------------------------------------------------
-- On ne supprime RIEN : les ventes 'EX' restent l'historique de l'encaissement
-- (et la source de la preview côté facturation).
--
-- Les rapports s'appuient désormais sur les lignes `details_vente` de la
-- facture hôtel, qui sont FIGÉES au moment de la facturation. C'est
-- volontaire : une facture émise ne doit pas changer si un extra est
-- ensuite modifié côté caisse.
--
-- Conséquence : les rapports EXCLUENT les ventes 'EX' (cf.
-- Report::getSalesByTaxGroup, getPaymentsParsed, getTotalDiscounts) pour
-- éviter de compter deux fois les extras.

