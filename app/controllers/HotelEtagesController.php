<?php

namespace App\Controllers;

use App\Controllers\Controller;
use App\Models\HotelHousekeeping;

/**
 * API JSON pour la page mobile Gouvernante (`/hotel/etages`).
 *
 * Routes (à câbler dans routes/api.php) :
 *   GET   /api/hotel/etages/board                    → liste tâches + stats
 *   POST  /api/hotel/housekeeping/{id}/claim         → s'auto-assigner
 *   POST  /api/hotel/housekeeping/{id}/done          → marquer fait
 *   POST  /api/hotel/housekeeping/{id}/issue          → signaler un problème
 *
 * Permissions (cf. matrice PLAN_HOTEL_V1.md §2) :
 *   - gouvernante        : tout (read + actions sur ses tâches)
 *   - manager / receptionniste / super_admin / admin : lecture seule utile
 *     (utile surtout si on ouvre plus tard la même page au manager
 *     pour superviser le ménage)
 */
class HotelEtagesController extends Controller
{
    private function jsonOut($payload, int $status = 200): void
    {
        http_response_code($status);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode($payload, JSON_UNESCAPED_UNICODE);
        exit;
    }

    /**
     * Droit minimum pour consulter la page.
     * Toute personne ayant un rôle hôtel peut voir.
     * Sprint 7 — 2026-10-19 : check API hôtel avant check rôle.
     */
    private function guardHotel(): bool
    {
        if (!$this->requireFeatureApiAccess('hotel')) return false;
        $appRole   = $_SESSION['role'] ?? '';
        $hotelRole = $_SESSION['hotel_role'] ?? null;
        if ($appRole === 'super_admin') return true;
        if ($appRole === 'admin') return true;
        if (in_array($hotelRole, ['manager', 'receptionniste', 'gouvernante'], true)) return true;
        return false;
    }

    /**
     * Agrégat pour la page mobile :
     *   - tâches visibles par la gouvernante (filtrées par user_id)
     *   - petites stats du jour
     */
    public function board(): void
    {
        if (!$this->guardHotel()) {
            $this->jsonOut(['success' => false, 'message' => 'Accès refusé'], 403);
        }

        $uid = (int)($_SESSION['user_id'] ?? 0);
        $model = new HotelHousekeeping();

        $this->jsonOut([
            'success' => true,
            'data'    => [
                'taches' => $model->tachesPourGouvernante($uid),
                'stats'  => $model->statsDuJour(),
            ],
        ]);
    }

    /**
     * S'auto-assigner une tâche non assignée.
     * Accepte aussi un `chambre_id` pour les chambres "orphelines" (sans tâche).
     */
    public function claim(): void
    {
        if (!$this->guardHotel()) {
            $this->jsonOut(['success' => false, 'message' => 'Accès refusé'], 403);
        }
        $id = (int)($_POST['id'] ?? 0);
        $chambreId = (int)($_POST['chambre_id'] ?? 0);

        // Cas orphelin : on crée une tâche (a_faire → en_cours) et on l'assigne.
        if ($id <= 0 && $chambreId > 0) {
            $uid = (int)($_SESSION['user_id'] ?? 0);
            $model = new HotelHousekeeping();
            // Crée une tâche en_cours assignée à la gouvernante, sans toucher à la chambre.
            $ok = $model->reclamerOrphelin($chambreId, $uid);
            if (!$ok) {
                $this->jsonOut(['success' => false, 'message' => 'Chambre non éligible'], 409);
            }
            $this->jsonOut(['success' => true]);
            return;
        }

        if ($id <= 0) {
            $this->jsonOut(['success' => false, 'message' => 'ID invalide'], 400);
        }
        $uid = (int)($_SESSION['user_id'] ?? 0);
        $model = new HotelHousekeeping();
        $ok = $model->reclamer($id, $uid);

        if (!$ok) {
            $this->jsonOut(['success' => false, 'message' => 'Tâche déjà assignée ou introuvable'], 409);
        }
        $this->jsonOut(['success' => true]);
    }

    /**
     * Marquer une tâche comme terminée.
     * Accepte aussi un `chambre_id` pour les chambres "orphelines".
     */
    public function done(): void
    {
        if (!$this->guardHotel()) {
            $this->jsonOut(['success' => false, 'message' => 'Accès refusé'], 403);
        }
        $id = (int)($_POST['id'] ?? 0);
        $chambreId = (int)($_POST['chambre_id'] ?? 0);

        $uid = (int)($_SESSION['user_id'] ?? 0);
        $model = new HotelHousekeeping();

        if ($id <= 0 && $chambreId > 0) {
            $res = $model->marquerFaitOrpheline($chambreId, $uid);
        } else {
            if ($id <= 0) {
                $this->jsonOut(['success' => false, 'message' => 'ID invalide'], 400);
            }
            $res = $model->marquerFait($id, $uid);
        }

        if (!$res['ok']) {
            $this->jsonOut(['success' => false, 'message' => $res['error']], 409);
        }
        $this->jsonOut(['success' => true]);
    }

    /**
     * Signaler un problème (texte libre).
     * Accepte aussi un `chambre_id` pour les chambres "orphelines".
     */
    public function issue(): void
    {
        if (!$this->guardHotel()) {
            $this->jsonOut(['success' => false, 'message' => 'Accès refusé'], 403);
        }
        $id = (int)($_POST['id'] ?? 0);
        $chambreId = (int)($_POST['chambre_id'] ?? 0);
        $message = trim((string)($_POST['message'] ?? ''));

        if ($message === '') {
            $this->jsonOut(['success' => false, 'message' => 'Message vide'], 400);
        }
        if ($id <= 0 && $chambreId <= 0) {
            $this->jsonOut(['success' => false, 'message' => 'ID invalide'], 400);
        }

        $uid = (int)($_SESSION['user_id'] ?? 0);
        $model = new HotelHousekeeping();

        if ($id <= 0 && $chambreId > 0) {
            $res = $model->signalerProblemeOrphelin($chambreId, $uid, $message);
        } else {
            $res = $model->signalerProbleme($id, $uid, $message);
        }

        if (!$res['ok']) {
            $this->jsonOut(['success' => false, 'message' => $res['error']], 409);
        }
        $this->jsonOut(['success' => true]);
    }
}