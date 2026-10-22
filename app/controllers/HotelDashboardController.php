<?php

namespace App\Controllers;

use App\Controllers\Controller;
use App\Core\Database;

/**
 * API JSON du tableau de bord hôtelier (/hotel/tableau-bord).
 *
 * Route :
 *   GET /api/hotel/dashboard → agrégat KPI + séjours en cours + housekeeping
 *
 * Objectif (Sprint 4) : brancher la page `hotel-tableau-bord.php` (jusqu'ici
 * un squelette avec des KPI « — ») sur les données réelles de la DB.
 *
 * Réponse (data) :
 *   kpi         : chambres_occupees, chambres_libres, chambres_total,
 *                chambres_a_nettoyer, chambres_hors_service,
 *                taux_occupation, housekeeping_attente,
 *                recettes_jour, sejours_factures_jour, extras_jour
 *   sejours     : séjours `en_cours` (chambre + guest), limité à 30
 *   housekeeping: tâches `a_faire` / `en_cours`, limité à 30
 *
 * Permissions : manager + super_admin/admin (lecture seule).
 * Le module hôtel est mono-établissement (pas de shop_id sur les tables
 * hôtel) : aucun filtre boutique n'est appliqué ici.
 */
class HotelDashboardController extends Controller
{
    private function jsonOut($payload, int $status = 200): void
    {
        http_response_code($status);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode($payload, JSON_UNESCAPED_UNICODE);
        exit;
    }

    /** Droit de lecture : manager (hôtelier) + admin/super_admin. */
    private function guardRead(): bool
    {
        // Sprint 7 — 2026-10-19 : check API avant check rôle.
        if (!$this->requireFeatureApiAccess('hotel')) return false;
        $appRole   = $_SESSION['role'] ?? '';
        $hotelRole = $_SESSION['hotel_role'] ?? null;
        if ($appRole === 'super_admin') return true;
        if ($appRole === 'admin') return true;
        if ($hotelRole === 'manager') return true;
        return false;
    }
/**
     * GET /api/hotel/dashboard
     */
    public function index(): void
    {
        if (!$this->guardRead()) {
            $this->jsonOut(['success' => false, 'message' => 'Accès refusé'], 403);
        }

        try {
            $db = Database::getInstance();

            // ── 1. KPI CHAMBRES ──────────────────────────────────────────
            // Total physique et répartition par état. L'occupation est
            // comptée via les séjours `en_cours` (vérité métier) plutôt
            // que via hotel_chambres.etat, qui peut être en retard après
            // un check-out.
            $chambreRow = $db->fetch(
                "SELECT
                    COUNT(*) AS total,
                    SUM(CASE WHEN etat = 'disponible'  THEN 1 ELSE 0 END) AS libres,
                    SUM(CASE WHEN etat = 'a_nettoyer'   THEN 1 ELSE 0 END) AS a_nettoyer,
                    SUM(CASE WHEN etat = 'hors_service' THEN 1 ELSE 0 END) AS hors_service
                 FROM hotel_chambres"
            ) ?: [];

            $chambresTotal       = (int)($chambreRow['total'] ?? 0);
            $chambresLibres      = (int)($chambreRow['libres'] ?? 0);
            $chambresANettoyer   = (int)($chambreRow['a_nettoyer'] ?? 0);
            $chambresHorsService = (int)($chambreRow['hors_service'] ?? 0);

            $occRow = $db->fetch(
                "SELECT COUNT(*) AS n FROM hotel_sejours WHERE statut = 'en_cours'"
            ) ?: [];
            $chambresOccupees = (int)($occRow['n'] ?? 0);

            $tauxOccupation = $chambresTotal > 0
                ? round($chambresOccupees / $chambresTotal * 100, 1)
                : 0.0;
            // ── 2. KPI HOUSEKEEPING ───────────────────────────────────────
            // Tâches non terminées : `a_faire` + `en_cours`.
            $hkRow = $db->fetch(
                "SELECT COUNT(*) AS n
                 FROM hotel_housekeeping
                 WHERE statut IN ('a_faire', 'en_cours')"
            ) ?: [];
            $housekeepingAttente = (int)($hkRow['n'] ?? 0);

            // ── 3. KPI RECETTES DU JOUR ───────────────────────────────────
            // Source : hotel_factures émises aujourd'hui (total_final TTC).
            // Les factures annulées (`statut='annulee'`) sont exclues.
            $recRow = $db->fetch(
                "SELECT COALESCE(SUM(total_final), 0) AS t, COUNT(*) AS n
                 FROM hotel_factures
                 WHERE statut != 'annulee'
                   AND DATE(created_at) = CURDATE()"
            ) ?: [];
            $recettesJour        = round((float)($recRow['t'] ?? 0), 2);
            $sejoursFacturesJour = (int)($recRow['n'] ?? 0);

            // ── 4. KPI EXTRAS DU JOUR ─────────────────────────────────────
            // Source : hotel_extras consommés aujourd'hui. On couvre les
            // deux flux : ventes POS imputées ET lignes du picker
            // (vente_id NULL). Pas de double-compte : chaque ligne de
            // hotel_extras est comptée une seule fois.
            $extrasRow = $db->fetch(
                "SELECT COALESCE(SUM(montant), 0) AS t
                 FROM hotel_extras
                 WHERE DATE(date_consommation) = CURDATE()"
            ) ?: [];
            $extrasJour = round((float)($extrasRow['t'] ?? 0), 2);
            // ── 5. LISTE SÉJOURS EN COURS ─────────────────────────────────
            // Alimenté par le bloc « Séjours en cours » de la page.
            // Volontairement limité (30) : c'est un aperçu, pas un export.
            $sejours = $db->fetchAll(
                "SELECT s.id, s.chambre_id, s.date_arrivee, s.date_depart_prevue,
                        s.statut, s.prix_par_nuit, s.remise_pct,
                        c.numero AS chambre_numero, c.type AS chambre_type,
                        g.nom AS guest_nom, g.prenom AS guest_prenom,
                        g.telephone AS guest_telephone
                 FROM hotel_sejours s
                 INNER JOIN hotel_chambres c ON c.id = s.chambre_id
                 INNER JOIN hotel_guests  g ON g.id = s.guest_id
                 WHERE s.statut = 'en_cours'
                 ORDER BY c.numero ASC
                 LIMIT 30"
            );

            // ── 6. LISTE HOUSEKEEPING EN ATTENTE ──────────────────────────
            // Tâches `a_faire` / `en_cours` (les `fait` et `bloque` sortent).
            $housekeeping = $db->fetchAll(
                "SELECT h.id, h.chambre_id, h.sejour_id, h.type, h.statut,
                        h.date_creation, h.notes,
                        c.numero AS chambre_numero
                 FROM hotel_housekeeping h
                 INNER JOIN hotel_chambres c ON c.id = h.chambre_id
                 WHERE h.statut IN ('a_faire', 'en_cours')
                 ORDER BY h.date_creation ASC
                 LIMIT 30"
            );

            $this->jsonOut([
                'success' => true,
                'data' => [
                    'kpi' => [
                        'chambres_occupees'     => $chambresOccupees,
                        'chambres_libres'       => $chambresLibres,
                        'chambres_total'        => $chambresTotal,
                        'chambres_a_nettoyer'   => $chambresANettoyer,
                        'chambres_hors_service' => $chambresHorsService,
                        'taux_occupation'       => $tauxOccupation,
                        'housekeeping_attente'  => $housekeepingAttente,
                        'recettes_jour'         => $recettesJour,
                        'sejours_factures_jour' => $sejoursFacturesJour,
                        'extras_jour'           => $extrasJour,
                    ],
                    'sejours'      => $sejours,
                    'housekeeping' => $housekeeping,
                    'generated_at' => date('Y-m-d H:i:s'),
                ],
            ]);
        } catch (\Throwable $e) {
            error_log('[hotel-dashboard] ' . $e->getMessage());
            $this->jsonOut([
                'success' => false,
                'message' => 'Erreur lors du chargement du tableau de bord : ' . $e->getMessage(),
            ], 500);
        }
    }
}