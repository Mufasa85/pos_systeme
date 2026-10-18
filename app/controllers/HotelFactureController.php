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
     * Calcule la taxe spécifique unitaire d'une chambre, alignée sur
     * posCart.calculateSpecificTaxUnit() (cf. app.js ligne ~1015) :
     *   - type 'CDF' : retourne la valeur fixe (par nuitée)
     *   - type '%'   : retourne prix_nuit × (value / 100)
     *   - type/value invalide ou vide : retourne 0 (pas de TS)
     *
     * Utilisée par preview() ET store() pour que les deux vues s'accordent
     * au centime près (le serveur ne fait pas confiance au client, cf. note
     * dans store() sur le recalcul).
     */
    private function computeTaxeSpecifiqueUnit(?string $type, $value, float $prixNuit): float
    {
        $type = (string)$type;
        $val  = (float)$value;
        if (!in_array($type, ['%', 'CDF'], true) || $val <= 0) return 0.0;
        return $type === 'CDF' ? $val : $prixNuit * ($val / 100);
    }

    /**
     * Génère le numero_facture officiel pour la facture hôtel.
     * Délègue à Sale::generateInvoiceNumber() pour rester sur le compteur
     * annuel officiel (format `AAAA/xxxxxx`) partagé avec /caisse. C'est
     * important pour l'export comptable et les exports DGI multi-boutique :
     * la facture hôtel est une vente comme une autre dans `ventes`.
     */
    private function generateNumeroFacture(): string
    {
        return (new \App\Models\Sale())->generateInvoiceNumber();
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

        // ── Garde-fou global (Sprint 6 — 2026-10-18) ──────────────────────────
        // Toute PDOException (ex: colonne manquante) sortait en page HTML
        // d'erreur fatale PHP (status 200 par défaut sous certains setups),
        // ce qui faisait apparaître "Erreur 200" côté UI. On intercepte ici
        // pour renvoyer un JSON propre avec un status 500 et un message
        // lisible. Le log_error() aide au diagnostic côté serveur.
        try {
            $this->buildPreviewData($sejourId);
        } catch (\Throwable $e) {
            error_log('[hotel-fac preview] ' . $e->getMessage() . ' @ ' . $e->getFile() . ':' . $e->getLine());
            $this->jsonOut([
                'success' => false,
                'message' => 'Erreur lors du calcul de la prévisualisation : ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Construit le payload de prévisualisation pour un séjour donné.
     * Méthode extraite de preview() pour pouvoir l'envelopper dans un
     * try/catch global (cf. note dans preview()). Renvoie via jsonOut()
     * (donc exit) en cas de succès.
     *
     * Throw n'importe quel \Throwable pour les erreurs attendues :
     * PDOException, RuntimeException, etc. Le caller (preview()) traduit
     * en JSON 500 lisible.
     */
    private function buildPreviewData(int $sejourId): void
    {
        $db = \App\Core\Database::getInstance();

        // Séjour + guest + client + chambre
        $sejour = $db->fetch(
            "SELECT s.*,
                    g.id AS guest_id, g.nom AS guest_nom, g.prenom AS guest_prenom,
                    g.telephone AS guest_tel, g.client_id AS guest_client_id,
                    c.id AS chambre_id, c.numero AS chambre_numero, c.type AS chambre_type, c.prix_base AS chambre_prix_base,
                    c.taxe_specifique_type  AS chambre_taxe_specifique_type,
                    c.taxe_specifique_value AS chambre_taxe_specifique_value,
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

        // Taxe spécifique chambre (Sprint 6 — 2026-10-18) : alignée sur le
        // pattern produit (cf. posCart.calculateSpecificTaxUnit). On lit
        // explicitement les colonnes de la chambre liée au séjour — le séjour
        // ne stocke pas la TS car celle-ci est une caractéristique de la
        // chambre (pas du séjour client).
        $tsType  = $sejour['chambre_taxe_specifique_type']  ?? null;
        $tsValue = $sejour['chambre_taxe_specifique_value'] ?? null;
        // Si le SELECT ci-dessus n'a pas ramené les colonnes (jointure plus
        // haut), on retombe sur un fetch direct. En pratique la jointure
        // l'a ramené — c'est juste une sécurité.
        if ($tsType === null || $tsValue === null) {
            $chRow = $db->fetch(
                'SELECT taxe_specifique_type, taxe_specifique_value
                 FROM hotel_chambres WHERE id = :cid',
                [':cid' => (int)$sejour['chambre_id']]
            );
            if ($chRow) {
                $tsType  = $chRow['taxe_specifique_type'];
                $tsValue = $chRow['taxe_specifique_value'];
            }
        }

        $nuits = [];
        $totalNuits = 0.0;
        $totalTaxeSpecifique = 0.0;
        for ($d = clone $start; $d < $end; $d->modify('+1 day')) {
            // TS appliquée par nuitée, sur le prix de la nuit (avant remise)
            $unitTs = $this->computeTaxeSpecifiqueUnit($tsType, $tsValue, $prixParNuit);
            $nuits[] = [
                'date'        => $d->format('Y-m-d'),
                'prix'        => $prixParNuit,
                'ts_unitaire' => round($unitTs, 2),
            ];
            $totalNuits += $prixParNuit;
            $totalTaxeSpecifique += $unitTs;
        }
        $totalTaxeSpecifique = round($totalTaxeSpecifique, 2);
        $remiseNuits = round($totalNuits * $remisePct / 100, 2);
        // La TS ne subit PAS la remise pct du séjour (alignement intentionnel :
        // la remise % est une négociation commerciale sur le prix de la nuit,
        // pas sur les taxes associées — cohérent avec les pratiques hôtelières).
        $totalNuitsApresRemise = round($totalNuits - $remiseNuits, 2);
        // Total séjour = prix nuit net + TS (toutes deux dans `nuits` côté UI).
        $totalNuitsAvecTS = round($totalNuitsApresRemise + $totalTaxeSpecifique, 2);

        // Extras : on lit DEUX sources pour rester compatible avec les deux flux
        // d'imputation :
        //   1. `ventes` filtrées par sejour_id + invoice_type='EX' (flux historique :
        //      la vente POS passe par SaleController::create qui force EX).
        //   2. `hotel_extras` (flux Sprint 5 — l'extra est créé à la sélection du
        //      séjour dans le picker via POST /api/hotel/sejours/[id]/extras-from-cart,
        //      SANS vente POS. vente_id IS NULL pour ces lignes).
        //
        // Pour la source 2 on regroupe par date pour faire une "card" par jour (à
        // défaut d'une vraie vente). Best-effort : on extrait "Nx Nom" de la
        // description pour retrouver qté/nom côté UI.
        //
        // NOTE — Réparation 2026-10-18 : la requête lisait `p.tax_rate` qui
        // n'existe PAS dans `produits`. Le taux vit dans `taxes.taux` via la
        // FK `produits.taxe_id`. La requête foire avec une PDOException qui
        // n'était pas interceptée → l'UI recevait un status 200 avec page
        // HTML d'erreur PHP → message "Erreur 200" incompréhensible pour
        // l'utilisateur. Voir HotelFactureController::preview() try/catch
        // plus bas pour la protection ajoutée.
        $extrasRows = $db->fetchAll(
            "SELECT v.id AS vente_id, v.date, v.total, v.numero_facture,
                    d.produit_id, d.quantite AS d_quantite, d.prix AS d_prix,
                    d.remise_type AS d_remise_type, d.remise_value AS d_remise_value,
                    d.taxe_specifique_type AS d_ts_type, d.taxe_specifique_value AS d_ts_value,
                    p.nom AS produit_nom,
                    t.taux AS produit_tax_rate
             FROM ventes v
             INNER JOIN details_vente d ON d.vente_id = v.id
             LEFT JOIN produits p ON p.id = d.produit_id
             LEFT JOIN taxes   t ON t.id = p.taxe_id
             WHERE v.sejour_id = :sid AND v.invoice_type = 'EX'
             ORDER BY v.date ASC, d.id ASC",
            [':sid' => $sejourId]
        );
        // Source 2 — hotel_extras créés via le picker "Extras" de la caisse.
        // On récupère les snapshots TS/TVA stockés sur la ligne (Sprint 6 —
        // 2026-10-18) pour pouvoir recalculer HT/TS/TVA sans dépendre d'un
        // parsing fragile de la description. Jointure LEFT sur produits/taxes
        // pour les lignes legacy (produit_id NULL).
        $hotelExtrasRows = $db->fetchAll(
            "SELECT he.id, he.description, he.montant, he.date_consommation, he.vente_id,
                    he.produit_id, he.quantite, he.prix_unitaire,
                    he.remise_type, he.remise_value,
                    he.taxe_specifique_type, he.taxe_specifique_value,
                    he.tax_rate,
                    p.nom AS produit_nom,
                    t.taux AS produit_tax_rate_db
             FROM hotel_extras he
             LEFT JOIN produits p ON p.id = he.produit_id
             LEFT JOIN taxes   t ON t.id = p.taxe_id
             WHERE he.sejour_id = :sid
             ORDER BY he.date_consommation ASC, he.id ASC",
            [':sid' => $sejourId]
        );
        // Regroupement côté PHP par vente, pour préserver l'ordre chronologique
        // et donner à l'UI une structure facile à rendre (une card par vente,
        // liste de produits à l'intérieur).
        $extrasParVente = [];
        foreach ($extrasRows as $row) {
            $vid = (int)$row['vente_id'];
            if (!isset($extrasParVente[$vid])) {
                $extrasParVente[$vid] = [
                    'vente_id'    => $vid,
                    'numero'      => (string)$row['numero_facture'],
                    'date'        => (string)$row['date'],
                    'montant'     => (float)$row['total'],
                    'description' => 'Extra #' . $vid . ' — ' . $row['numero_facture'],
                    'produits'    => [],
                ];
            }
            $extrasParVente[$vid]['produits'][] = [
                'produit_id'           => (int)$row['produit_id'],
                'nom'                  => (string)($row['produit_nom'] ?? ('Produit #' . (int)$row['produit_id'])),
                'quantite'             => (float)$row['d_quantite'],
                'prix_unitaire'        => (float)$row['d_prix'],
                'remise_type'          => (string)($row['d_remise_type'] ?? '%'),
                'remise_value'         => (float)($row['d_remise_value'] ?? 0),
                'taxe_specifique_type' => (string)($row['d_ts_type'] ?? '%'),
                'taxe_specifique_value'=> (float)($row['d_ts_value'] ?? 0),
                'tax_rate'             => (float)($row['produit_tax_rate'] ?? 0),
            ];
        }
        // Regroupement des hotel_extras créés via le picker (source 2).
        // Pour les lignes avec vente_id IS NULL, on les apparie en un seul
        // "groupe picker" par date (granularité à la journée). Avec les
        // snapshots TS/TVA/remise de la migration 2026_10_18, on n'a plus
        // besoin de parser la description : on lit directement les colonnes
        // produit_id, quantite, prix_unitaire, tax_rate, taxe_specifique_*.
        // Fallback sur parsing de la description UNIQUEMENT pour les lignes
        // legacy où produit_id est NULL.
        foreach ($hotelExtrasRows as $row) {
            $dateKey = substr((string)$row['date_consommation'], 0, 10);
            $vid = (int)($row['vente_id'] ?? 0);
            $key = $vid > 0 ? (string)$vid : ('picker-' . $dateKey . '-' . $row['id']);
            if (!isset($extrasParVente[$key])) {
                $extrasParVente[$key] = [
                    'vente_id'    => $vid > 0 ? $vid : null,
                    'numero'      => $vid > 0 ? '' : 'Extras caisse',
                    'date'        => (string)$row['date_consommation'],
                    'montant'     => 0.0,
                    'description' => $vid > 0
                        ? ('Extra #' . $vid)
                        : 'Extras saisis depuis la caisse',
                    'produits'    => [],
                ];
            }
            // Snapshot : qte, prix_unitaire, TS, remise, taux TVA
            $produitId    = (int)($row['produit_id'] ?? 0);
            $qty          = isset($row['quantite']) && $row['quantite'] !== null ? (float)$row['quantite'] : null;
            $prixUnit     = isset($row['prix_unitaire']) && $row['prix_unitaire'] !== null ? (float)$row['prix_unitaire'] : null;
            $tsType       = $row['taxe_specifique_type'] ?? '%';
            $tsValue      = (float)($row['taxe_specifique_value'] ?? 0);
            $rType        = $row['remise_type'] ?? '%';
            $rValue       = (float)($row['remise_value'] ?? 0);
            $taxRate      = (float)($row['tax_rate'] ?? $row['produit_tax_rate_db'] ?? 0);
            $nom          = (string)($row['produit_nom'] ?? '');
            if ($nom === '') {
                // Legacy : on retombe sur le parsing de la description
                $desc = (string)$row['description'];
                $cleanDesc = preg_replace('/^Ch\.\s*\S+\s*[—–-]\s*/u', '', $desc) ?: $desc;
                $qtyParsed = null;
                if (preg_match('/^(\d+)\s*[x×]\s*(.+)$/u', $cleanDesc, $m)) {
                    $qtyParsed = (int)$m[1];
                    $nom = trim($m[2]);
                }
                if ($qty === null) $qty = (float)($qtyParsed ?? 1);
                if ($prixUnit === null) $prixUnit = (float)$row['montant'] / max(1, (float)($qty ?? 1));
            } else {
                if ($qty === null) $qty = 1;
                if ($prixUnit === null) $prixUnit = (float)$row['montant'] / max(1, (float)$qty);
            }

            $extrasParVente[$key]['produits'][] = [
                'produit_id'           => $produitId,
                'nom'                  => $nom,
                'quantite'             => $qty,
                'prix_unitaire'        => $prixUnit,
                'remise_type'          => $rType,
                'remise_value'         => $rValue,
                'taxe_specifique_type' => $tsType,
                'taxe_specifique_value'=> $tsValue,
                'tax_rate'             => $taxRate,
            ];
            $extrasParVente[$key]['montant'] += (float)$row['montant'];
        }
        // Tri chronologique pour l'affichage
        uasort($extrasParVente, function ($a, $b) {
            return strcmp((string)$a['date'], (string)$b['date']);
        });
        $extras = array_values($extrasParVente);
        $totalExtras = array_sum(array_column($extras, 'montant'));

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
        // total_final intègre les nuits (avec TS) + extras.
        // Note : avec la TS, l'ancien "total_ht" (= nuitsAprèsRemise) reste
        // exposé tel quel ; le nouveau `total_avec_ts` est la somme à
        // facturer côté "nuits". L'UI additionne : nuits.total_avec_ts + extras.
        $totalBrut     = round($totalNuitsAvecTS + $totalExtras, 2);
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
                'total_ht'           => $totalNuitsApresRemise,
                'taxe_specifique_total' => $totalTaxeSpecifique,
                'total_avec_ts'      => $totalNuitsAvecTS,
                'remise_pct'         => $remisePct,
                'remise_montant'     => $remiseNuits,
                'lignes'             => $nuits,
                'nb_nuits'           => $nbNuits,
                'taxe_specifique_type'  => $tsType,
                'taxe_specifique_value' => (float)$tsValue,
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

        // Taxe spécifique chambre (Sprint 6 — 2026-10-18) :
        // on lit explicitement la TS de la chambre (pas stockée sur le séjour,
        // c'est une propriété de la chambre) et on calcule le total une fois
        // pour toutes les nuits. Aligné sur computeTaxeSpecifiqueUnit() utilisé
        // par preview() — voir la note dans cette méthode.
        $chRow = $db->fetch(
            'SELECT taxe_specifique_type, taxe_specifique_value
             FROM hotel_chambres WHERE id = :cid',
            [':cid' => (int)$sejour['chambre_id']]
        );
        $chTsType  = $chRow['taxe_specifique_type']  ?? '%';
        $chTsValue = $chRow['taxe_specifique_value'] ?? 0;
        $unitTs = $this->computeTaxeSpecifiqueUnit($chTsType, $chTsValue, $prixParNuit);
        $totalTaxeSpecifique = round($unitTs * $nbNuits, 2);

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
        // Total = nuits (après remise) + TS chambre + extras. La TS n'est PAS
        // affectée par la remise pct (cf. commentaire identique dans preview()).
        $totalFinal = round($totalNuitsNet + $totalTaxeSpecifique + $totalExtras, 2);
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

        // dgi_data fourni par le front (validation DGI déjà faite côté client)
        // On le persiste dans ventes pour permettre la réimpression / duplicata
        // via /api/service-bill (cf. facture.php + InvoiceController existant).
        $dgiData = isset($input['dgi_data']) && is_array($input['dgi_data']) ? $input['dgi_data'] : [];

        // ── Transaction ──
        $pdo = $db->getConnection();
        try {
            // ── Garde-fou : pas de double facture active pour un même séjour
            // (UNIQUE (sejour_id) sur hotel_factures) — on refuse avec un
            // message clair au lieu de laisser MySQL crasher en 1062.
            $existing = $db->fetch(
                "SELECT id, numero_facture, statut FROM hotel_factures
                 WHERE sejour_id = :sid AND statut != 'annulee'
                 LIMIT 1",
                [':sid' => $sejourId]
            );
            if ($existing) {
                $this->jsonOut([
                    'success'  => false,
                    'message'  => 'Une facture existe déjà pour ce séjour : ' . $existing['numero_facture']
                                 . '. Annulez-la avant d\'en émettre une nouvelle.',
                ], 409);
            }

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
                'comment'        => 'Facture hôtel séjour #' . $sejourId . (!empty($input['notes']) ? ' — ' . $input['notes'] : ''),
                'service'        => null,
                // --- Champs DGI (persistés pour permettre duplicata & impression A4) ---
                'dateDGI'    => $dgiData['dateDGI'] ?? null,
                'qrCode'     => $dgiData['qrCode'] ?? null,
                'codeDEFDGI' => $dgiData['codeDEFDGI'] ?? null,
                'counters'   => $dgiData['counters'] ?? null,
                'nim'        => $dgiData['nim'] ?? null,
            ]);

            // 1bis. INSERT details_vente — une ligne par nuitée (Sprint 6 — 2026-10-18).
            //
            // Objectif : la TS chambre est portée au niveau détail, comme la TS
            // produit l'est déjà sur la caisse POS (cf. app.js calculateItemTTC).
            // C'est ce qui permet à la vue facture.php (calculant $specificTaxUnit
            // par ligne, voir app/views/facture.php:118) d'afficher la TS chambre
            // automatiquement sur la facture imprimée.
            //
            // Pour une chambre, `produit_id` est NULL (pas de produit stocké) :
            // le libellé "Nuitée chambre N°X — JJ/MM" est généré côté vue via
            // COALESCE / fallback. Si les triggers FK de details_vente.produit_id
            // n'acceptent pas NULL, on retombe sur produit_id=0 (sentinelle).
            // On évite de gérer la remise ici — remise gérée au niveau séjour.
            $saleDetailModel = new \App\Models\SaleDetail();
            for ($i = 0; $i < $nbNuits; $i++) {
                $saleDetailModel->create([
                    'vente_id'             => $saleId,
                    'produit_id'           => null,
                    'quantite'             => 1,
                    'prix'                 => $prixParNuit,
                    'remise_type'          => '%',
                    'remise_value'         => 0,
                    'taxe_specifique_type' => $chTsType,
                    'taxe_specifique_value' => $chTsValue,
                ]);
            }

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