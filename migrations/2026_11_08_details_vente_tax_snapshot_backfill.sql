-- ============================================================================
-- MIGRATION : Backfill du snapshot de taxation des lignes de détail
-- Date       : 2026-11-08
-- Reference  : correction du rapport « Par groupe de taxation » (ventes POS)
--
-- PROBLÈME
--   `details_vente.tax_rate` a été créée avec `DEFAULT 0.00` (migration
--   2026_11_06) et le POS n'alimentait PAS cette colonne au moment de la
--   vente. MySQL y stockait donc 0 (et non NULL).
--
--   La logique du rapport interprétait ce 0 comme « ligne réellement non
--   taxée » → une vente POS classique d'un produit du GROUPE B (16 %) était
--   affichée avec un taux de 0.00% et une taxe de 0, alors que la facture
--   porte bien une TVA de 16 %.
--
--   Idem pour les NUITÉES D'HÔTEL antérieures : leur snapshot était vide et
--   leur taux de chambre n'était jamais récupéré.
--
-- SOLUTION
--   On remplit le snapshot manquant à partir de la source de vérité de la
--   ligne :
--     - vente POS / extra avec produit → `produits.taxe_id` → `taxes` ;
--     - nuitée d'hôtel (sans produit)     → `hotel_chambres.taxe_id` → `taxes`
--       (via `details_vente.chambre_id`, ou `details_vente.sejour_id` →
--        `hotel_sejours.chambre_id`).
--
--   ⚠️ On ne touche que les lignes SANS snapshot exploitable :
--     - `tax_rate` à 0 (ou NULL) ET aucun groupe renseigné → on remplit.
--     - une ligne déjà figée (tax_rate > 0) n'est jamais modifiée : la
--       facture émise ne doit pas changer si la taxe du produit ou de la
--       chambre a été modifiée depuis.
--     - une ligne explicitement EXONÉRÉE (tax_rate = 0 MAIS groupe
--       renseigné, ex. groupe A) n'est jamais modifiée non plus.
--
-- Idempotent : rejouable sans effet de bord (les lignes déjà remplies ne
-- correspondent plus au WHERE).
-- ============================================================================

SET NAMES utf8mb4;

-- ----------------------------------------------------------------------------
-- 1. Lignes AVEC produit (ventes POS, extras de caisse, extras du picker)
-- ----------------------------------------------------------------------------
-- On prend le groupe de taxation du produit (`produits.taxe_id` → `taxes`).
-- C'est la même chaîne que celle utilisée par `Product::findById()` au
-- moment de la vente, donc le snapshot reconstitué est cohérent avec la
-- facture émise.
UPDATE `details_vente` d
INNER JOIN `produits` p ON p.id = d.produit_id
INNER JOIN `taxes` t ON t.id = p.taxe_id
SET d.tax_rate      = t.taux,
    d.tax_etiquette = t.etiquette,
    d.tax_groupe    = t.groupe_taxe
WHERE (d.tax_rate IS NULL OR d.tax_rate = 0)
  AND (d.tax_etiquette IS NULL OR d.tax_etiquette = '')
  AND (d.tax_groupe IS NULL OR d.tax_groupe = '');

-- ----------------------------------------------------------------------------
-- 2. Lignes de NUITÉES d'hôtel (sans produit)
-- ----------------------------------------------------------------------------
-- La taxe d'une nuitée vient de la CHAMBRE (`hotel_chambres.taxe_id`), pas
-- d'un produit : une chambre n'est pas un produit stockable.
--
-- On retient la chambre par `details_vente.chambre_id` quand il est renseigné
-- (migration 2026_11_05), sinon on passe par le séjour
-- (`details_vente.sejour_id` → `hotel_sejours.chambre_id`).
UPDATE `details_vente` d
INNER JOIN `hotel_chambres` c ON c.id = COALESCE(d.chambre_id, (
        SELECT s2.chambre_id FROM `hotel_sejours` s2 WHERE s2.id = d.sejour_id
    ))
INNER JOIN `taxes` t ON t.id = c.taxe_id
SET d.tax_rate      = t.taux,
    d.tax_etiquette = t.etiquette,
    d.tax_groupe    = t.groupe_taxe
WHERE d.produit_id IS NULL
  AND (d.tax_rate IS NULL OR d.tax_rate = 0)
  AND (d.tax_etiquette IS NULL OR d.tax_etiquette = '')
  AND (d.tax_groupe IS NULL OR d.tax_groupe = '');

-- ----------------------------------------------------------------------------
-- 3. Correction des snapshots FAUX (faux exonérés)
-- ----------------------------------------------------------------------------
-- Cas constaté : la migration 2026_11_07 a figé le groupe du PRODUIT sur
-- des extras dont le taux venait de `hotel_extras.tax_rate` (à 0 par
-- défaut) → on obtenait « groupe A, taux 0 » pour un produit en groupe B
-- (16 %). Le snapshot disait donc « exonéré » alors que la facture, elle,
-- avait bien appliqué la TVA.
--
-- On réaligne ces lignes sur le groupe du produit : c'est la source de
-- vérité de la taxation d'un article du catalogue.
--
-- ⚠️ On ne touche que les lignes dont le groupe CONTREDIT celui du
-- produit. Une ligne dont le groupe est déjà cohérent (même étiquette,
-- même groupe) n'est pas modifiée : son taux à 0 est alors légitime
-- (produit réellement exonéré, groupe A).
UPDATE `details_vente` d
INNER JOIN `produits` p ON p.id = d.produit_id
INNER JOIN `taxes` t ON t.id = p.taxe_id
SET d.tax_rate      = t.taux,
    d.tax_etiquette = t.etiquette,
    d.tax_groupe    = t.groupe_taxe
WHERE d.tax_rate = 0
  AND t.taux > 0
  AND d.tax_etiquette IS NOT NULL
  AND d.tax_etiquette != ''
  AND (d.tax_etiquette <> t.etiquette OR d.tax_groupe <> t.groupe_taxe);

-- ----------------------------------------------------------------------------
-- 4. Les lignes SANS produit et SANS chambre ne peuvent pas être résolues
--    (extras saisis à la main) : on les laisse telles quelles (taux NULL /
--    0). Le rapport les affectera à un groupe par défaut.
-- ----------------------------------------------------------------------------

