<?php

namespace App\Controllers;

use App\Models\HotelReservation;
use App\Models\ProductBatch;

/**
 * Pont Caisse POS ↔ Hôtel : APIs légères pour le picker d'imputation
 * d'une vente sur un séjour en cours.
 *
 * Routes (déclarées dans routes/web.php) :
 *   GET  /api/hotel/sejours/en-cours
 *     → Liste les séjours statut='en_cours' avec chambre + guest.
 *     Utilisé par le modal "Imputer sur la chambre X" de la caisse.
 *
 *   POST /api/hotel/sejours/[i:id]/extras-from-cart
 *     → Impute immédiatement les produits du panier de la caisse sur un séjour.
 *     Crée une ligne dans `hotel_extras` par produit (description + montant).
 *     C'est ce que le bouton "Extras" appelle quand le caissier sélectionne
 *     un séjour dans le picker : l'enregistrement se fait à la sélection,
 *     pas seulement à la validation finale de la vente (cf. Sprint 5 / 2026-10-18).
 *
 *   ⚠️ GESTION DU STOCK (2026-11-04) :
 *     Un extra consomme PHYSIQUEMENT un produit (minibar, room service…) :
 *     le stock est donc contrôlé PUIS déduit au moment de l'imputation,
 *     comme pour une vente POS classique (cf. SaleController::create).
 *     - Contrôle préalable de tous les produits AVANT toute déduction :
 *       si un seul produit est en rupture, rien n'est déduit et rien n'est
 *       écrit (HTTP 409, error='stock_insuffisant').
 *     - Déduction FIFO sur les lots non périmés (ProductBatch::deductStock),
 *       dans la MÊME transaction que l'insertion des hotel_extras :
 *       atomique (rollback complet en cas d'échec).
 *     - Notification « stock faible » aux admins après commit si un produit
 *       passe sous son seuil de réapprovisionnement.
 *
 * Règles :
 *   - Pas de duplication de la logique hôtel : on s'appuie sur
 *     HotelReservation et HotelReservation::sejoursImputablesAuxExtras()
 *     qui réutilise la jointure de chambresAvecSejourActif() (déjà utilisée
 *     par la page Réception).
 *   - ⚠️ Un séjour DÉJÀ FACTURÉ n'est plus imputable : il est retiré de la
 *     liste du picker (`enCours`) ET refusé à l'écriture (`extras-from-cart`
 *     + `SaleController::create`). La facture hôtel peut être émise sans
 *     check-out : le séjour reste alors `en_cours` mais sa note est close.
 *   - Lecture seule pour la liste, accessible à tous les rôles authentifiés
 *     (la caisse sert aussi bien aux vendeurs qu'aux réceptionnistes).
 *   - Écriture (`extras-from-cart`) : authentifié + boutique active (même
 *     protection que SaleController::create).
 *   - Accès à la fonctionnalité « Extras » : contrôlé par la configuration
 *     par boutique `shops.acces_extras` (cochée par le super_admin dans
 *     /shops), et NON par le feature flag API « hotel ».
 */
class HotelExtrasController extends Controller
{
    /**
     * GET /api/hotel/sejours/en-cours
     *
     * Retourne les séjours actifs (`statut='en_cours'`) avec le minimum
     * d'infos nécessaires au picker : id du séjour, n° de chambre,
     * nom du client.
     *
     * ⚠️ Règle métier : les séjours DÉJÀ FACTURÉS sont EXCLUS de la liste,
     * même s'ils sont toujours `en_cours`. C'est le cas quand la facture
     * hôtel a été émise SANS check-out : le séjour reste en cours mais sa
     * note est close, donc plus aucun extra ne peut lui être imputé.
     *
     * Format de réponse :
     *   { "success": true, "data": [ {id, chambre_numero, guest_nom, guest_prenom, ...}, ... ] }
     */
    public function enCours(): void
    {
        if (!$this->requireAuth()) {
            return;
        }
        // Accès aux Extras : configuration par boutique (`shops.acces_extras`),
        // cochée par le super_admin dans /shops. Ce contrôle remplace le
        // feature flag API « hotel » pour cette fonctionnalité.
        if (!$this->requireShopExtrasAccess()) {
            return;
        }

        // Réutilise la jointure existante chambres + séjour en_cours + guest
        // déjà servie à la page Réception — pas de duplication de SQL.
        // SejoursImputablesAuxExtras() filtre en plus les séjours déjà
        // facturés (le LEFT JOIN `hotel_factures` y est déjà présent).
        $sejours = (new HotelReservation())->sejoursImputablesAuxExtras();

        $this->json([
            'success' => true,
            'count'   => count($sejours),
            'data'    => $sejours,
        ]);
    }

    /**
     * POST /api/hotel/sejours/[i:id]/extras-from-cart
     *
     * Impute immédiatement les produits du panier de la caisse sur un séjour.
     * Crée une ligne dans `hotel_extras` par produit (description + montant).
     * C'est ce que le bouton "Extras" appelle quand le caissier sélectionne
     * un séjour dans le picker : l'enregistrement se fait à la sélection,
     * pas seulement à la validation finale de la vente.
     *
     * Body JSON : { articles: [{ produit_id, nom?, quantite, prix, ... }, ...] }
     * Réponse   : { success, sejour_id, count, total, ids: [int,...] }
     */
    public function extrasFromCart($params = []): void
    {
        if (!$this->requireAuth()) {
            return;
        }
        // Accès aux Extras : configuration par boutique (`shops.acces_extras`),
        // cochée par le super_admin dans /shops. Ce contrôle remplace le
        // feature flag API « hotel » pour cette fonctionnalité.
        if (!$this->requireShopExtrasAccess()) {
            return;
        }
        if (!$this->isShopActive()) {
            $this->status(403)->json([
                'success' => false,
                'error'   => 'shop_disabled',
                'message' => 'Votre boutique a été désactivée. Vous ne pouvez pas imputer d\'extras.',
            ]);
            return;
        }

        // Récupère l'ID du séjour depuis l'URL (/api/hotel/sejours/[i:id]/...)
        $sejourId = 0;
        if (is_array($params)) {
            $sejourId = (int)($params['id'] ?? 0);
        }
        if ($sejourId <= 0) {
            $this->status(422)->json(['success' => false, 'message' => 'sejour_id requis']);
            return;
        }

        // Lecture du body : JSON (appel AJAX) avec repli sur $_POST.
        // Le repli rend la méthode testable en CLI (php://input n'est pas
        // lisible hors contexte HTTP) — même pattern que
        // HotelReservationController::jsonInput().
        $input = $this->jsonInput();
        if (!is_array($input)) {
            $this->status(422)->json(['success' => false, 'message' => 'Payload JSON invalide']);
            return;
        }

        $articles = $input['articles'] ?? [];
        if (!is_array($articles) || count($articles) === 0) {
            $this->status(422)->json([
                'success' => false,
                'message' => 'Panier vide : aucun extra à imputer.',
            ]);
            return;
        }

        $db = \App\Core\Database::getInstance();

        // 1. Vérifier que le séjour existe et n'est pas clôturé
        $sejour = $db->fetch(
            'SELECT s.id, s.statut, c.numero AS chambre_numero
             FROM hotel_sejours s
             INNER JOIN hotel_chambres c ON c.id = s.chambre_id
             WHERE s.id = :id',
            [':id' => $sejourId]
        );
        if (!$sejour) {
            $this->status(404)->json(['success' => false, 'message' => 'Séjour introuvable']);
            return;
        }
        if (in_array($sejour['statut'], ['termine', 'annule'], true)) {
            $this->status(422)->json([
                'success' => false,
                'message' => 'Séjour clôturé (' . $sejour['statut'] . ') — imputation impossible.',
            ]);
            return;
        }
        // ── Séjour déjà facturé : la note est close ────────────────────────
        // Un séjour peut être facturé SANS être clôturé (facture émise sans
        // check-out) : il reste alors `en_cours` mais plus aucun extra ne
        // peut lui être imputé, sinon le total de la facture déjà émise
        // serait faux. Le picker ne liste plus ces séjours (enCours()), mais
        // on re-vérifie ici : c'est le vrai point de contrôle côté écriture.
        $facture = (new HotelReservation())->factureActive($sejourId);
        if ($facture) {
            $this->status(422)->json([
                'success' => false,
                'error'   => 'sejour_deja_facture',
                'message' => 'Ce séjour est déjà facturé (' . $facture['numero_facture']
                             . ') — impossible d\'ajouter un extra.',
            ]);
            return;
        }

        $chambreNumero = (string)($sejour['chambre_numero'] ?? '');
        $userId = (int)($_SESSION['user_id'] ?? 0);

        // 2. Insertion atomique : une ligne hotel_extras par produit distinct
        $createdIds = [];
        $total = 0.0;
        $count = 0;
        try {
            $pdo = $db->getConnection();
            $pdo->beginTransaction();

            // ── Stock : vérification préalable + déduction FIFO ──────────
            // Un extra consomme physiquement un produit (minibar, room
            // service…) : le stock doit être contrôlé ET déduit au moment
            // de l'imputation, exactement comme pour une vente POS classique
            // (cf. SaleController::create). Sans cela, le stock POS reste
            // inchangé alors que la marchandise est déjà consommée.
            //
            // On travaille sur le MÊME PDO que la transaction (Database est
            // un singleton) → la déduction des lots est atomique avec
            // l'insertion des hotel_extras : si l'une des deux échoue, on
            // rollback tout (stock restauré + aucune ligne créée).
            $batchModel = new ProductBatch();

            // 2a. Contrôle préalable de TOUS les produits avant toute
            //     déduction : on ne veut pas déduire le stock du produit A
            //     puis découvrir que le produit B est en rupture.
            $stockErrors = [];
            foreach ($articles as $item) {
                $produitId = (int)($item['produit_id'] ?? 0);
                $qty       = (float)($item['quantite'] ?? 0);
                if ($produitId <= 0 || $qty <= 0) {
                    continue; // ligne invalide : ignorée plus bas (comme avant)
                }

                $available = $batchModel->getAvailableStock($produitId);
                if ($available < $qty) {
                    $productRow = $db->fetch(
                        'SELECT nom FROM produits WHERE id = :id',
                        [':id' => $produitId]
                    );
                    $stockErrors[] = [
                        'produit_id'   => $produitId,
                        'nom'          => (string)($productRow['nom'] ?? ('Produit #' . $produitId)),
                        'demande'      => $qty,
                        'disponible'   => $available,
                    ];
                }
            }

            if (!empty($stockErrors)) {
                $pdo->rollBack();
                $details = [];
                foreach ($stockErrors as $err) {
                    $details[] = sprintf(
                        '%s (demandé : %s, disponible : %s)',
                        $err['nom'],
                        rtrim(rtrim(number_format($err['demande'], 3, ',', ' '), '0'), ','),
                        rtrim(rtrim(number_format($err['disponible'], 3, ',', ' '), '0'), ',')
                    );
                }
                $this->status(409)->json([
                    'success' => false,
                    'error'   => 'stock_insuffisant',
                    'message' => 'Stock insuffisant pour : ' . implode(' ; ', $details),
                    'details' => $stockErrors,
                ]);
                return;
            }

            $stmtInsert = $pdo->prepare(
                'INSERT INTO hotel_extras
                    (sejour_id, vente_id, produit_id, quantite, prix_unitaire,
                     remise_type, remise_value, taxe_specifique_type, taxe_specifique_value,
                     tax_rate, description, montant, date_consommation, user_id)
                 VALUES (:sid, :vid, :pid, :qty, :prix,
                     :rtype, :rval, :tstype, :tsval,
                     :trate, :desc, :montant, NOW(), :uid)'
            );

            foreach ($articles as $item) {
                $produitId = (int)($item['produit_id'] ?? 0);
                $qty       = (float)($item['quantite'] ?? 0);
                $prix      = (float)($item['prix'] ?? 0);
                $nom       = trim((string)($item['nom'] ?? ''));

                // Snapshots TS + TVA + remise du produit (avec fallback sur la
                // table produits si le frontend ne les a pas envoyés). Permet à
                // /hotel/facturation de recalculer le vrai total (HT + TS + TVA)
                // sans dépendre d'un parsing fragile de la description.
                $tsType  = $item['taxe_specifique_type'] ?? null;
                $tsValue = isset($item['taxe_specifique_value']) ? (float)$item['taxe_specifique_value'] : null;
                $rType   = $item['remise_type'] ?? null;
                $rValue  = isset($item['remise_value']) ? (float)$item['remise_value'] : null;
                $tRate   = isset($item['tax_rate']) ? (float)$item['tax_rate'] : null;

                // Normalisation de la convention de remise : l'ancienne
                // convention ('percent' / 'amount') peut encore traîner dans
                // `produits.remise_type` (DEFAULT de la colonne). Or
                // `hotel_extras.remise_type` est un ENUM('%','CDF') → une
                // valeur hors enum provoque une troncature SQL (500).
                // On ramène donc toujours à '%' ou 'CDF' avant l'insertion.
                // ⚠️ Placé APRÈS le fallback sur la table produits : celui-ci
                // peut réinjecter une valeur 'percent' non normalisée.

                if ($produitId > 0 && ($tsType === null || $tsValue === null || $tRate === null)) {
                    $row = $db->fetch(
                        'SELECT p.taxe_specifique_type, p.taxe_specifique_value,
                                p.remise_type, p.remise_value,
                                t.taux AS tax_rate
                         FROM produits p
                         LEFT JOIN taxes t ON t.id = p.taxe_id
                         WHERE p.id = :id',
                        [':id' => $produitId]
                    );
                    if ($row) {
                        if ($tsType === null)  $tsType  = $row['taxe_specifique_type'] ?? '%';
                        if ($tsValue === null) $tsValue = (float)($row['taxe_specifique_value'] ?? 0);
                        if ($rType === null)   $rType   = $row['remise_type'] ?? '%';
                        if ($rValue === null)  $rValue  = (float)($row['remise_value'] ?? 0);
                        if ($tRate === null)   $tRate   = (float)($row['tax_rate'] ?? 0);
                    }
                }

                // Normalisation de la convention de remise : l'ancienne
                // convention ('percent' / 'amount') peut encore traîner dans
                // `produits.remise_type` (DEFAULT de la colonne). Or
                // `hotel_extras.remise_type` est un ENUM('%','CDF') → une
                // valeur hors enum provoque une troncature SQL (500).
                // On ramène donc toujours à '%' ou 'CDF' avant l'insertion.
                // ⚠️ Placé APRÈS le fallback sur la table produits : celui-ci
                // peut réinjecter une valeur 'percent' non normalisée.
                if ($rType === 'amount') {
                    $rType = 'CDF';
                }
                if (!in_array($rType, ['%', 'CDF'], true)) {
                    $rType = '%';
                }

                if ($produitId <= 0 || $qty <= 0) {
                    continue;
                }
                if ($nom === '') {
                    $row = $db->fetch('SELECT nom FROM produits WHERE id = :id', [':id' => $produitId]);
                    $nom = $row['nom'] ?? ('Produit #' . $produitId);
                }

                // 2b. Déduction effective du stock (FIFO sur les lots non
                //     périmés) — dans la MÊME transaction que l'insertion
                //     du hotel_extras. Si la déduction échoue (rupture
                //     survenue entre le contrôle et l'écriture), on
                //     rollback : aucune ligne créée, aucun stock perdu.
                if (!$batchModel->deductStock($produitId, $qty)) {
                    $pdo->rollBack();
                    $this->status(409)->json([
                        'success' => false,
                        'error'   => 'stock_insuffisant',
                        'message' => 'Stock insuffisant pour le produit « ' . $nom . ' ».',
                        'details' => [[
                            'produit_id' => $produitId,
                            'nom'        => $nom,
                            'demande'    => $qty,
                            'disponible' => $batchModel->getAvailableStock($produitId),
                        ]],
                    ]);
                    return;
                }

                $description = ($chambreNumero !== '' ? 'Ch. ' . $chambreNumero . ' — ' : '')
                              . ((int)$qty) . '× ' . $nom;
                $montant = round($qty * $prix, 2);

                $stmtInsert->execute([
                    ':sid'    => $sejourId,
                    ':vid'    => null,
                    ':pid'    => $produitId,
                    ':qty'    => $qty,
                    ':prix'   => $prix,
                    ':rtype'  => $rType,
                    ':rval'   => $rValue,
                    ':tstype' => $tsType,
                    ':tsval'  => $tsValue,
                    ':trate'  => $tRate,
                    ':desc'   => $description,
                    ':montant'=> $montant,
                    ':uid'    => $userId,
                ]);

                $createdIds[] = (int)$pdo->lastInsertId();
                $total += $montant;
                $count++;
            }

            if ($count === 0) {
                $pdo->rollBack();
                $this->status(422)->json([
                    'success' => false,
                    'message' => 'Aucun article valide dans le panier (produit_id ou quantite manquant).',
                ]);
                return;
            }

            $pdo->commit();
        } catch (\Throwable $e) {
            if (isset($pdo) && $pdo->inTransaction()) $pdo->rollBack();
            error_log('[hotel-extras from-cart] ' . $e->getMessage());
            $this->status(500)->json([
                'success' => false,
                'message' => 'Erreur lors de l\'imputation des extras : ' . $e->getMessage(),
            ]);
            return;
        }

        // ── Notification « stock faible » ────────────────────────────────
        // Alerte les admins de la boutique quand un produit passe sous
        // son seuil de réapprovisionnement (même règle que la vente POS
        // classique, cf. SaleController::create). Appelé APRÈS le commit :
        // la notification ne doit pas être envoyée si la transaction a
        // été annulée.
        foreach ($articles as $item) {
            $produitId = (int)($item['produit_id'] ?? 0);
            $qty       = (float)($item['quantite'] ?? 0);
            if ($produitId <= 0 || $qty <= 0) {
                continue;
            }
            $product = (new \App\Models\Product())->findById($produitId);
            if ($product && (float)$product['stock'] <= (float)$product['stock_minimum']) {
                $this->notifyShopAdmins(
                    $this->getShopId(),
                    'stock_low',
                    'Stock faible : ' . $product['nom'],
                    'Le produit "' . $product['nom'] . '" n\'a plus que ' . $product['stock']
                        . ' unités (minimum: ' . $product['stock_minimum'] . ')',
                    '/produits'
                );
            }
        }

        $this->logAudit('create', 'hotel_extras', null, [
            'sejour_id' => $sejourId,
            'count'     => $count,
            'total'     => round($total, 2),
        ]);

        $this->json([
            'success'   => true,
            'sejour_id' => $sejourId,
            'count'     => $count,
            'total'     => round($total, 2),
            'ids'       => $createdIds,
        ]);
    }

    /**
     * Lit le body de la requête : JSON (appel AJAX) avec repli sur $_POST.
     *
     * Le repli sert à rendre la méthode testable en CLI : `php://input` n'est
     * pas lisible hors contexte HTTP, alors que `$_POST` l'est. Même pattern
     * que `HotelReservationController::jsonInput()`.
     *
     * @return array|null Tableau décodé, ou null si le payload est invalide.
     */
    private function jsonInput(): ?array
    {
        $raw = file_get_contents('php://input');
        if ($raw) {
            $decoded = json_decode($raw, true);
            if (is_array($decoded)) {
                return $decoded;
            }
        }
        return !empty($_POST) ? $_POST : null;
    }
}