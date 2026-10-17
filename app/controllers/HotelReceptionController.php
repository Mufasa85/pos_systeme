<?php

namespace App\Controllers;

use App\Models\HotelReservation;
use App\Models\HotelChambre;
use App\Controllers\Controller;

/**
 * API JSON pour la page Réception live.
 *
 * Routes :
 *   GET    /api/hotel/reception/board               → agrégat complet (timeline + grille)
 *   POST   /api/hotel/reservations/[id]/check-in    → passe en en_cours
 *   POST   /api/hotel/reservations/[id]/check-out   → passe en termine
 *   POST   /api/hotel/reception/rooms/[id]/cleaning-done → marque la chambre 'disponible'
 *   POST   /api/hotel/reception/rooms/[id]/out-of-service → marque 'hors_service'
 *
 * Permissions :
 *   - manager + réceptionniste (et super_admin) : check-in, check-out, cleaning-done
 *   - manager + super_admin : out-of-service (manipulation plus sensible)
 */
class HotelReceptionController extends Controller
{
    private function jsonOut($payload, int $status = 200): void
    {
        http_response_code($status);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode($payload, JSON_UNESCAPED_UNICODE);
        exit;
    }

    private function guardStaff(): bool
    {
        $appRole   = $_SESSION['role'] ?? '';
        $hotelRole = $_SESSION['hotel_role'] ?? null;
        if ($appRole === 'super_admin') return true;
        if ($appRole === 'admin') return true;
        if (in_array($hotelRole, ['manager', 'receptionniste'], true)) return true;
        return false;
    }

    private function guardManager(): bool
    {
        $appRole   = $_SESSION['role'] ?? '';
        $hotelRole = $_SESSION['hotel_role'] ?? null;
        if ($appRole === 'super_admin') return true;
        if ($appRole === 'admin') return true;
        if ($hotelRole === 'manager') return true;
        return false;
    }

    /**
     * Droit d'émettre des factures hôtel (manager + réceptionniste).
     * La gouvernante ne peut PAS émettre, elle est en lecture seule sur la facturation.
     */
    private function guardWriteFacture(): bool
    {
        $appRole   = $_SESSION['role'] ?? '';
        $hotelRole = $_SESSION['hotel_role'] ?? null;
        if ($appRole === 'super_admin') return true;
        if ($appRole === 'admin') return true;
        if (in_array($hotelRole, ['manager', 'receptionniste'], true)) return true;
        return false;
    }

    /**
     * Agrégat pour la page Réception : 20 chambres avec séjour actif + timeline du jour.
     */
    public function board(): void
    {
        if (!$this->guardStaff()) {
            $this->jsonOut(['success' => false, 'message' => 'Accès refusé'], 403);
        }
        $resModel = new HotelReservation();
        $this->jsonOut([
            'success'        => true,
            'data'           => [
                'chambres'  => $resModel->chambresAvecSejourActif(),
                'timeline'  => $resModel->sejoursAujourdhui(),
            ],
            'capabilities'   => [
                'check_in_out'       => $this->guardStaff(),
                'cleaning_done'      => $this->guardStaff(),
                'out_of_service'     => $this->guardManager(),
                'emit_facture'       => $this->guardWriteFacture(),
            ],
            'server_time'    => date('Y-m-d H:i:s'),
        ]);
    }

    /**
     * Check-in : séjour passe de 'reserve' à 'en_cours'.
     */
    public function checkIn($id): void
    {
        if (!$this->guardStaff()) {
            $this->jsonOut(['success' => false, 'message' => 'Accès refusé'], 403);
        }
        $id = is_array($id) ? (int)($id['id'] ?? 0) : (int)$id;
        $userId = (int)($_SESSION['user_id'] ?? 0);
        try {
            $row = (new HotelReservation())->checkIn($id, $userId);
        } catch (\RuntimeException $e) {
            $this->jsonOut(['success' => false, 'message' => $e->getMessage()], 409);
        }
        $this->jsonOut(['success' => true, 'data' => $row]);
    }

    /**
     * Check-out : séjour passe de 'en_cours' à 'termine'.
     * Note : la chambre passe automatiquement à 'a_nettoyer' via ce flow
     * (housekeeping à enclencher par la réceptionniste/gouvernante).
     */
    public function checkOut($id): void
    {
        if (!$this->guardStaff()) {
            $this->jsonOut(['success' => false, 'message' => 'Accès refusé'], 403);
        }
        $id = is_array($id) ? (int)($id['id'] ?? 0) : (int)$id;
        $userId = (int)($_SESSION['user_id'] ?? 0);
        try {
            $row = (new HotelReservation())->checkOut($id, $userId);
        } catch (\RuntimeException $e) {
            $this->jsonOut(['success' => false, 'message' => $e->getMessage()], 409);
        }

        // Côté chambre : passer en 'a_nettoyer' pour signaler à la gouvernante.
        // Si le séjour est déjà terminé on laisse l'état tel quel.
        if (!empty($row['chambre_id'])) {
            try {
                (new HotelChambre())->setEtat((int)$row['chambre_id'], 'a_nettoyer');
            } catch (\Throwable $e) {
                // non bloquant
            }
        }

        $this->jsonOut(['success' => true, 'data' => $row]);
    }

    /**
     * Marquage "nettoyage terminé" — la chambre redevient disponible.
     */
    public function cleaningDone($id): void
    {
        if (!$this->guardStaff()) {
            $this->jsonOut(['success' => false, 'message' => 'Accès refusé'], 403);
        }
        $id = is_array($id) ? (int)($id['id'] ?? 0) : (int)$id;
        // La gouvernante peut aussi marquer, mais en pratique elle passe par /hotel/etages
        // — ici on autorise le staff à valider depuis la grille Réception.
        try {
            (new HotelChambre())->setEtat($id, 'disponible');
        } catch (\RuntimeException $e) {
            $this->jsonOut(['success' => false, 'message' => $e->getMessage()], 409);
        }
        $this->jsonOut(['success' => true, 'data' => (new HotelChambre())->findById($id)]);
    }

    /**
     * Mise hors service — manager uniquement (action plus sensible).
     */
    public function outOfService($id): void
    {
        if (!$this->guardManager()) {
            $this->jsonOut(['success' => false, 'message' => 'Réservé au manager'], 403);
        }
        $id = is_array($id) ? (int)($id['id'] ?? 0) : (int)$id;
        try {
            (new HotelChambre())->setEtat($id, 'hors_service');
        } catch (\RuntimeException $e) {
            $this->jsonOut(['success' => false, 'message' => $e->getMessage()], 409);
        }
        $this->jsonOut(['success' => true, 'data' => (new HotelChambre())->findById($id)]);
    }
}
