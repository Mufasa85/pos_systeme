<?php

namespace App\Controllers;

use App\Controllers\Controller;

/**
 * API JSON pour la facturation hôtel.
 *
 * Routes :
 *   GET  /api/hotel/factures/preview?sejour_id=X   → agrège séjour + extras + acomptes
 *   POST /api/hotel/factures                       → émet la facture officielle
 *   GET  /api/hotel/factures/[i:id]                → détail d'une facture émise
 *   POST /api/hotel/factures/[i:id]/cancel         → annule (passe statut='annulee')
 *
 * Permissions :
 *   - tous rôles hôtel : GET
 *   - manager + réceptionniste + super_admin/admin : POST, cancel
 *   - gouvernante : lecture seule
 *
 * Notes fiscales :
 *   - Type de facture = HB (Hotel Bill), régime TVA type B hôtellerie
 *     (TVA non collectée ; tva_montant reste à 0).
 *   - La facture vit dans `ventes` (invoice_type='HB') pour réutiliser l'impression
 *     / PDF / QR DGI existants. Le contexte hôtelier (nuit(s), extras, acompte) est
 *     dans la table `hotel_factures` qui pointe sur la vente.
 */
class HotelFactureController extends Controller
{
    private function jsonOut($payload, int $status = 200): void
    {
        http_response_code($status);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode($payload, JSON_UNESCAPED_UNICODE);
        exit;
    }

    private function guardRead(): bool
    {
        $appRole   = $_SESSION['role'] ?? '';
        $hotelRole = $_SESSION['hotel_role'] ?? null;
        if ($appRole === 'super_admin') return true;
        if ($appRole === 'admin') return true;
        if (in_array($hotelRole, ['manager', 'receptionniste', 'gouvernante'], true)) return true;
        return false;
    }

    private function guardWrite(): bool
    {
        $appRole   = $_SESSION['role'] ?? '';
        $hotelRole = $_SESSION['hotel_role'] ?? null;
        if ($appRole === 'super_admin') return true;
        if ($appRole === 'admin') return true;
        // La gouvernante n'émet pas de factures
        if (in_array($hotelRole, ['manager', 'receptionniste'], true)) return true;
        return false;
    }

    /**
     * Génère un numero_facture unique pour les factures hôtel.
     * Format : HB-YYYYMMDD-XXXX (XXXX = compteur quotidien, padded à 4).
     * Robuste : on prend MAX+1 dans la table ventes pour le jour.
     */
    private function generateNumeroFacture(): string
    {
        $today = date('Ymd');
        $prefix = 'HB-' . $today . '-';
        $db = \App\Core\Database::getInstance();
        $row = $db->fetch(
            "SELECT COUNT(*) AS c FROM ventes WHERE numero_facture LIKE :p",
            [':p' => $prefix . '%']
        );
        $next = (int)($row['c'] ?? 0) + 1;
        return $prefix . str_pad((string)$next, 4, '0', STR_PAD_LEFT);
    }

    /**
     * GET /api/hotel/factures/preview?sejour_id=X
     * Agrège séjour + extras (ventes.type='EX') + acomptes (hotel_paiements)
     * → renvoie un JSON détaillé pour pré-remplir la page de facturation.
     */
    public function preview(): void
    {
        if (!$this->guardRead()) $this->jsonOut(['success' => false, 'message' => 'Accès refusé'], 403);

        $sejourId = (int)($_GET['sejour_id'] ?? 0);
        if ($sejourId <= 0) $this->jsonOut(['success' => false, 'message' => 'sejour_id requis'], 422);

        $db = \App\Core\Database::getInstance();

        // Séjour + guest + client + chambre
        $sejour = $db->fetch(
            "SELECT s.*,
                    g.id AS guest_id, g.nom AS guest_nom, g.prenom AS guest_prenom,
                    g.telephone AS guest_tel, g.client_id AS guest_client_id,
                    c.id AS chambre_id, c.numero AS chambre_numero, c.type AS chambre_type, c.prix_base AS chambre_prix_base,
                    cl.code_client AS client_code, cl.nom_client AS client_nom
             FROM hotel_sejours s
             INNER JOIN hotel_guests g ON g.id = s.guest_id
             INNER JOIN hotel_chambres c ON c.id = s.chambre_id
             LEFT JOIN clients cl ON cl.id = g.client_id
             WHERE s.id = :id",
            [':id' => $sejourId]
        );
        if (!$sejour) $this->jsonOut(['success' => false, 'message' => 'Séjour introuvable'], 404);

        // Vérifier qu'une facture non-annulée n'existe pas déjà
        $existing = $db->fetch(
            'SELECT id, vente_id, numero_facture, statut, total_final FROM hotel_factures WHERE sejour_id = :sid',
            [':sid' => $sejourId]
        );

        // Calcul des nuits : nb de nuits entre date_arrivee (jour) et date_depart_prevue (jour)
        $start = new \DateTime(date('Y-m-d', strtotime($sejour['date_arrivee'])));
        $end   = new \DateTime($sejour['date_depart_prevue']);
        if ($end <= $start) {
            // séjour d'un seul jour (départ le même jour)
            $end->modify('+1 day');
        }
        $nbNuits = (int)$start->diff($end)->days;
        $prixParNuit = (float)$sejour['prix_par_nuit'];
        $remisePct   = (float)$sejour['remise_pct'];

        $nuits = [];
        $totalNuits = 0.0;
        for ($d = clone $start; $d < $end; $d->modify('+1 day')) {
            $nuits[] = [
                'date'  => $d->format('Y-m-d'),
                'prix'  => $prixParNuit,
            ];
            $totalNuits += $prixParNuit;
        }
        $remiseNuits = round($totalNuits * $remisePct / 100, 2);
        $totalNuitsApresRemise = round($totalNuits - $remiseNuits, 2);

        // Extras : ventes avec sejour_id=X et invoice_type='EX' (et statut actif)
        $extrasRows = $db->fetchAll(
            "SELECT v.id AS vente_id, v.date, v.total, v.numero_facture,
                    GROUP_CONCAT(d.produit_id, '|', d.quantite, '|', d.prix SEPARATOR ';') AS lignes
             FROM ventes v
             LEFT JOIN details_vente d ON d.vente_id = v.id
             WHERE v.sejour_id = :sid AND v.invoice_type = 'EX'
             GROUP BY v.id
             ORDER BY v.date ASC",
            [':sid' => $sejourId]
        );
        $extras = [];
        $totalExtras = 0.0;
        foreach ($extrasRows as $row) {
            // On ne récupère pas les noms de produits ici (jointure supplémentaire possible)
            // — on remonte le montant global et l'id vente, l'UI pourra faire des lookups.
            $montant = (float)$row['total'];
            $extras[] = [
                'vente_id'       => (int)$row['vente_id'],
                'numero'         => $row['numero_facture'],
                'date'           => $row['date'],
                'montant'        => $montant,
                'description'    => 'Extra #' . $row['vente_id'] . ' — ' . $row['numero_facture'],
            ];
            $totalExtras += $montant;
        }

        // Acomptes
        $acomptesRows = $db->fetchAll(
            'SELECT id, montant, mode_paiement, created_at FROM hotel_paiements WHERE sejour_id = :sid ORDER BY created_at ASC',
            [':sid' => $sejourId]
        );
        $acomptes = array_map(fn($r) => [
            'id'      => (int)$r['id'],
            'montant' => (float)$r['montant'],
            'mode'    => $r['mode_paiement'],
            'date'    => $r['created_at'],
        ], $acomptesRows);
        $acompteTotal = array_sum(array_column($acomptes, 'montant'));

        // Totaux globaux
        $totalBrut     = round($totalNuitsApresRemise + $totalExtras, 2);
        $tvaMontant    = 0.0; // Type B : TVA non collectée
        $totalFinal    = $totalBrut;
        $soldeDu       = round($totalFinal - $acompteTotal, 2);

        $this->jsonOut([
            'success' => true,
            'data'    => [
            'sejour'            => [
                'id'          => (int)$sejour['id'],
                'statut'      => $sejour['statut'],
                'date_arrivee'   => $sejour['date_arrivee'],
                'date_depart_prevue' => $sejour['date_depart_prevue'],
                'guest' => [
                    'id'       => (int)$sejour['guest_id'],
                    'nom'      => $sejour['guest_nom'],
                    'prenom'   => $sejour['guest_prenom'],
                    'telephone'=> $sejour['guest_tel'],
                ],
                'client' => $sejour['client_code'] ? [
                    'id'         => (int)$sejour['guest_client_id'],
                    'code_client'=> $sejour['client_code'],
                    'nom_client' => $sejour['client_nom'],
                ] : null,
                'chambre' => [
                    'numero'   => $sejour['chambre_numero'],
                    'type'     => $sejour['chambre_type'],
                    'prix_base'=> (float)$sejour['chambre_prix_base'],
                ],
            ],
            'nuits' => [
                'total_ht'  => $totalNuitsApresRemise,
                'remise_pct'=> $remisePct,
                'remise_montant' => $remiseNuits,
                'lignes'    => $nuits,
                'nb_nuits'  => $nbNuits,
            ],
            'extras' => [
                'total_ht'  => round($totalExtras, 2),
                'lignes'    => $extras,
            ],
            'acomptes' => $acomptes,
            'tva'      => [
                'type'    => 'B',
                'montant' => $tvaMontant,
                'note'    => "Régime hôtelier — TVA type B non collectée",
            ],
            'total_brut'      => $totalBrut,
            'total_final'     => $totalFinal,
            'acompte_total'   => round($acompteTotal, 2),
            'solde_du'        => $soldeDu,
            'existing_facture' => $existing ? [
                'id'            => (int)$existing['id'],
                'vente_id'      => (int)$existing['vente_id'],
                'numero_facture'=> $existing['numero_facture'],
                'statut'        => $existing['statut'],
                'total_final'   => (float)$existing['total_final'],
            ] : null,
            ],
        ]);
    }

    /**
     * POST /api/hotel/factures
     * Body JSON :
     *   {
     *     sejour_id: int,
     *     remise_pct?: float (par défaut: celle du séjour),
     *     extras_manuels?: [{ description, montant }]   // ajoutés à la volée
     *     paiements?: [{ mode, montant }]               // modes de paiement encaissés
     *     check_out?: bool                              // passer le séjour en 'termine'
     *     notes?: string
     *   }
     *
     * Crée la facture officielle (ventes.invoice_type='HB') et la ligne
     * hotel_factures qui la complète.
     */
    public function store(): void
    {
        if (!$this->guardWrite()) $this->jsonOut(['success' => false, 'message' => 'Accès refusé'], 403);

        $input = json_decode(file_get_contents('php://input'), true);
        if (!is_array($input)) $this->jsonOut(['success' => false, 'message' => 'Payload invalide'], 422);

        $sejourId = (int)($input['sejour_id'] ?? 0);
        if ($sejourId <= 0) $this->jsonOut(['success' => false, 'message' => 'sejour_id requis'], 422);

        $db = \App\Core\Database::getInstance();
        $sejour = $db->fetch(
            "SELECT s.*, g.client_id AS guest_client_id, g.id AS guest_id
             FROM hotel_sejours s
             INNER JOIN hotel_guests g ON g.id = s.guest_id
             WHERE s.id = :id",
            [':id' => $sejourId]
        );
        if (!$sejour) $this->jsonOut(['success' => false, 'message' => 'Séjour introuvable'], 404);

        // Refuser si une facture non-annulée existe déjà
        $existing = $db->fetch(
            "SELECT id FROM hotel_factures WHERE sejour_id = :sid AND statut <> 'annulee'",
            [':sid' => $sejourId]
        );
        if ($existing) {
            $this->jsonOut([
                'success' => false,
                'message' => 'Une facture non annulée existe déjà pour ce séjour (id #' . $existing['id'] . '). Annule-la d\'abord.',
            ], 409);
        }

        // ── Recalcul serveur des totaux (on ne fait pas confiance au client) ──
        $start = new \DateTime(date('Y-m-d', strtotime($sejour['date_arrivee'])));
        $end   = new \DateTime($sejour['date_depart_prevue']);
        if ($end <= $start) $end->modify('+1 day');
        $nbNuits = (int)$start->diff($end)->days;
        $prixParNuit = (float)$sejour['prix_par_nuit'];
        $totalNuitsBrut = $nbNuits * $prixParNuit;

        $remisePct = isset($input['remise_pct']) ? (float)$input['remise_pct'] : (float)$sejour['remise_pct'];
        if ($remisePct < 0) $remisePct = 0;
        if ($remisePct > 100) $remisePct = 100;
        $remiseMontantNuits = round($totalNuitsBrut * $remisePct / 100, 2);
        $totalNuitsNet = round($totalNuitsBrut - $remiseMontantNuits, 2);

        // Extras saisis en caisse
        $extrasRows = $db->fetchAll(
            "SELECT id, total FROM ventes WHERE sejour_id = :sid AND invoice_type = 'EX'",
            [':sid' => $sejourId]
        );
        $totalExtrasCaisse = array_sum(array_map(fn($r) => (float)$r['total'], $extrasRows));

        // Extras saisis à la main depuis la page facturation (ajout direct dans la facture,
        // PAS dans la table ventes — ce sont des one-shot non-stock)
        $extrasManuels = $input['extras_manuels'] ?? [];
        $totalExtrasManuels = 0.0;
        $manuelsNormalises = [];
        if (is_array($extrasManuels)) {
            foreach ($extrasManuels as $e) {
                $desc = trim((string)($e['description'] ?? ''));
                $montant = (float)($e['montant'] ?? 0);
                if ($desc !== '' && $montant > 0) {
                    $manuelsNormalises[] = ['description' => $desc, 'montant' => $montant];
                    $totalExtrasManuels += $montant;
                }
            }
        }

        $totalExtras = round($totalExtrasCaisse + $totalExtrasManuels, 2);
        $totalFinal = round($totalNuitsNet + $totalExtras, 2);
        $tvaMontant = 0.0; // type B

        // Acomptes
        $acompteRow = $db->fetch(
            "SELECT COALESCE(SUM(montant), 0) AS t FROM hotel_paiements WHERE sejour_id = :sid",
            [':sid' => $sejourId]
        );
        $acompteTotal = (float)($acompteRow['t'] ?? 0);
        $soldeDu = round($totalFinal - $acompteTotal, 2);

        // Paiements encaissés à la facturation
        $paiements = $input['paiements'] ?? [];
        if (!is_array($paiements)) $paiements = [];
        $paiementsNormalises = [];
        foreach ($paiements as $p) {
            $mode = (string)($p['mode'] ?? 'cash');
            $montant = (float)($p['montant'] ?? 0);
            if ($montant > 0 && in_array($mode, ['cash', 'mobile_money', 'carte', 'autre'], true)) {
                $paiementsNormalises[] = ['mode' => $mode, 'montant' => $montant];
            }
        }

        $userId = (int)($_SESSION['user_id'] ?? 0);
        $shopId = $_SESSION['shop_id'] ?? null;
        if ($userId <= 0) $this->jsonOut(['success' => false, 'message' => 'Session invalide'], 401);

        // ISF boutique (même logique que la caisse POS)
        $storeIsf = null;
        if ($shopId) {
            $shop = (new \App\Models\Shop())->findById((int)$shopId);
            if ($shop && !empty($shop['isf'])) $storeIsf = trim((string)$shop['isf']);
        }
        if (!$storeIsf) {
            $companyInfo = (new \App\Models\CompanyInfo())->get();
            $storeIsf = !empty($companyInfo['isf']) ? trim((string)$companyInfo['isf']) : null;
        }

        // ── Transaction ──
        $pdo = $db->getConnection();
        try {
            $pdo->beginTransaction();

            $numeroFacture = $this->generateNumeroFacture();
            $clientId = !empty($sejour['guest_client_id']) ? (int)$sejour['guest_client_id'] : null;

            // 1. INSERT ventes (invoice_type='HB')
            $saleId = (new \App\Models\Sale())->create([
                'numero_facture' => $numeroFacture,
                'invoice_type'   => 'HB',
                'client_id'      => $clientId,
                'sejour_id'      => null, // la facture HB n'est PAS un extra
                'sous_total_ht'  => $totalFinal,
                'tva'            => $tvaMontant,
                'total'          => $totalFinal,
                'payments'       => $paiementsNormalises,
                'vendeur_id'     => $userId,
                'shop_id'        => $shopId,
                'store_isf'      => $storeIsf,
                'date'           => date('Y-m-d H:i:s'),
                'comment'        => 'Facture hôtel séjour #' . $sejourId . ($input['notes'] ? ' — ' . $input['notes'] : ''),
                'service'        => null,
            ]);

            // 2. INSERT hotel_factures
            $stmt = $pdo->prepare(
                'INSERT INTO hotel_factures
                    (sejour_id, vente_id, numero_facture, invoice_type,
                     total_nuits, total_extras, remise_pct, remise_montant,
                     tva_type, tva_montant, total_final, acompte_precedent, solde_du,
                     paiements_json, statut, user_id, shop_id, notes)
                 VALUES
                    (:sejour_id, :vente_id, :numero, :type,
                     :total_nuits, :total_extras, :remise_pct, :remise_montant,
                     :tva_type, :tva_montant, :total_final, :acompte, :solde,
                     :paiements_json, :statut, :user_id, :shop_id, :notes)'
            );
            $stmt->execute([
                ':sejour_id'      => $sejourId,
                ':vente_id'       => $saleId,
                ':numero'         => $numeroFacture,
                ':type'           => 'HB',
                ':total_nuits'    => $totalNuitsNet,
                ':total_extras'   => $totalExtras,
                ':remise_pct'     => $remisePct,
                ':remise_montant' => $remiseMontantNuits,
                ':tva_type'       => 'B',
                ':tva_montant'    => $tvaMontant,
                ':total_final'    => $totalFinal,
                ':acompte'        => $acompteTotal,
                ':solde'          => $soldeDu,
                ':paiements_json' => json_encode($paiementsNormalises, JSON_UNESCAPED_UNICODE),
                ':statut'         => 'emise',
                ':user_id'        => $userId,
                ':shop_id'        => $shopId,
                ':notes'          => isset($input['notes']) ? trim((string)$input['notes']) : null,
            ]);
            $factureId = (int)$pdo->lastInsertId();

            // 3. Check-out si demandé
            $doCheckOut = !empty($input['check_out']);
            if ($doCheckOut && $sejour['statut'] === 'en_cours') {
                $stmt = $pdo->prepare(
                    "UPDATE hotel_sejours
                     SET statut = 'termine', date_depart_reelle = NOW(), version = version + 1
                     WHERE id = :id AND statut = 'en_cours'"
                );
                $stmt->execute([':id' => $sejourId]);
                // Chambre → a_nettoyer
                $stmt = $pdo->prepare(
                    "UPDATE hotel_chambres SET etat = 'a_nettoyer' WHERE id = :cid"
                );
                $stmt->execute([':cid' => (int)$sejour['chambre_id']]);
                // Tâche housekeeping à faire
                $stmt = $pdo->prepare(
                    "INSERT INTO hotel_housekeeping (chambre_id, sejour_id, type, statut)
                     VALUES (:cid, :chid, 'nettoyage', 'a_faire')"
                );
                $stmt->execute([':cid' => (int)$sejour['chambre_id'], ':chid' => $sejourId]);
            }

            // 4. Audit log
            $this->logAudit('create', 'hotel_facture', $factureId, [
                'sejour_id' => $sejourId,
                'vente_id'  => $saleId,
                'total'     => $totalFinal,
            ]);

            $pdo->commit();
        } catch (\Exception $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            $this->jsonOut([
                'success' => false,
                'message' => 'Erreur lors de l\'émission de la facture : ' . $e->getMessage(),
            ], 500);
        }

        $this->jsonOut([
            'success'         => true,
            'facture_id'      => $factureId,
            'vente_id'        => $saleId,
            'numero_facture'  => $numeroFacture,
            'total_final'     => $totalFinal,
            'solde_du'        => $soldeDu,
            'check_out_done'  => $doCheckOut,
            // URL d'impression — réutilise InvoiceController existant
            'print_url'       => '/facture/' . $saleId,
        ], 201);
    }

    /**
     * GET /api/hotel/factures/[i:id]
     */
    public function show($id): void
    {
        if (!$this->guardRead()) $this->jsonOut(['success' => false, 'message' => 'Accès refusé'], 403);
        $id = is_array($id) ? (int)($id['id'] ?? 0) : (int)$id;
        $db = \App\Core\Database::getInstance();
        $facture = $db->fetch(
            "SELECT f.*, s.date_arrivee, s.date_depart_prevue, s.statut AS sejour_statut,
                    g.nom AS guest_nom, g.prenom AS guest_prenom,
                    c.numero AS chambre_numero
             FROM hotel_factures f
             INNER JOIN hotel_sejours s ON s.id = f.sejour_id
             INNER JOIN hotel_guests g ON g.id = s.guest_id
             INNER JOIN hotel_chambres c ON c.id = s.chambre_id
             WHERE f.id = :id",
            [':id' => $id]
        );
        if (!$facture) $this->jsonOut(['success' => false, 'message' => 'Facture introuvable'], 404);
        $facture['paiements'] = $facture['paiements_json'] ? json_decode($facture['paiements_json'], true) : [];
        unset($facture['paiements_json']);
        $this->jsonOut(['success' => true, 'data' => $facture]);
    }

    /**
     * POST /api/hotel/factures/[i:id]/cancel
     * Annule (passe statut='annulee'). Ne supprime pas la vente — on garde l'historique.
     * Si on annule la facture, le séjour peut être refacturé.
     */
    public function cancel($id): void
    {
        if (!$this->guardWrite()) $this->jsonOut(['success' => false, 'message' => 'Accès refusé'], 403);
        $id = is_array($id) ? (int)($id['id'] ?? 0) : (int)$id;
        $db = \App\Core\Database::getInstance();
        $facture = $db->fetch('SELECT id, statut FROM hotel_factures WHERE id = :id', [':id' => $id]);
        if (!$facture) $this->jsonOut(['success' => false, 'message' => 'Facture introuvable'], 404);
        if ($facture['statut'] === 'annulee') {
            $this->jsonOut(['success' => false, 'message' => 'Facture déjà annulée'], 409);
        }
        $db->execute(
            "UPDATE hotel_factures SET statut = 'annulee' WHERE id = :id",
            [':id' => $id]
        );
        $this->logAudit('cancel', 'hotel_facture', $id, []);
        $this->jsonOut(['success' => true]);
    }
}