<?php

namespace App\Controllers;

use App\Models\HotelReservation;
use App\Models\HotelGuest;
use App\Controllers\Controller;

/**
 * Endpoints JSON pour la page Réservations hôtel.
 *
 * Routes :
 *   GET  /api/hotel/reservations                → liste filtrée
 *   GET  /api/hotel/reservations/{id}           → détail
 *   POST /api/hotel/reservations                → créer (guest + séjour)
 *   PUT  /api/hotel/reservations/{id}           → modifier
 *   POST /api/hotel/reservations/{id}/cancel    → annuler
 *   GET  /api/hotel/reservations/available-rooms → chambres dispo sur période
 *   GET  /api/hotel/guests/search?q=...         → recherche guests
 */
class HotelReservationController extends Controller
{
    /** Réponse JSON uniforme */
    private function jsonOut($payload, int $status = 200): void
    {
        http_response_code($status);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode($payload, JSON_UNESCAPED_UNICODE);
        exit;
    }

    /**
     * Garde : seuls manager + réceptionniste + super_admin ont accès.
     * On lit le rôle app et le rôle hôtel en session.
     */
    private function guard(): void
    {
        $appRole  = $_SESSION['role'] ?? '';
        $hotelRole = $_SESSION['hotel_role'] ?? null;
        if ($appRole === 'super_admin') return;
        if ($appRole === 'admin') return; // auto-manager
        if (in_array($hotelRole, ['manager', 'receptionniste'], true)) return;
        $this->jsonOut(['success' => false, 'message' => 'Accès refusé'], 403);
    }

    public function index(): void
    {
        $this->guard();
        $statut = $_GET['statut'] ?? null;
        $df = $_GET['date_from'] ?? null;
        $dt = $_GET['date_to'] ?? null;
        $rows = (new HotelReservation())->list($statut ?: null, $df ?: null, $dt ?: null);
        $this->jsonOut(['success' => true, 'data' => $rows]);
    }

    public function show($id): void
    {
        $this->guard();
        $id = is_array($id) ? (int)($id['id'] ?? 0) : (int)$id;
        $row = (new HotelReservation())->findById($id);
        if (!$row) {
            $this->jsonOut(['success' => false, 'message' => 'Réservation introuvable'], 404);
        }
        $this->jsonOut(['success' => true, 'data' => $row]);
    }

    public function store(): void
    {
        $this->guard();
        $input = $this->jsonInput();

        // Validation minimale
        $required = ['chambre_id', 'date_arrivee', 'date_depart_prevue', 'prix_par_nuit'];
        foreach ($required as $r) {
            if (empty($input[$r])) {
                $this->jsonOut(['success' => false, 'message' => "Champ manquant : $r"], 422);
            }
        }

        // Guest : soit existant (guest_id), soit nouveau (guest.*)
        $guestId = isset($input['guest_id']) ? (int)$input['guest_id'] : 0;
        if (!$guestId) {
            $g = $input['guest'] ?? [];
            $nom = trim($g['nom'] ?? '');
            if ($nom === '') {
                $this->jsonOut(['success' => false, 'message' => 'Nom du guest manquant'], 422);
            }
            $dup = (new HotelGuest())->findPossibleDuplicate(
                $nom,
                $g['telephone'] ?? null,
                $g['piece_identite'] ?? null
            );
            if ($dup) {
                $guestId = (int)$dup['id'];
            } else {
                $guestId = (new HotelGuest())->create(
                    $nom,
                    $g['prenom'] ?? null,
                    $g['telephone'] ?? null,
                    $g['piece_identite'] ?? null,
                    $g['nationalite'] ?? null
                );
            }
        }

        try {
            $newId = (new HotelReservation())->create(
                (int)$input['chambre_id'],
                $guestId,
                $this->normalizeDateTime($input['date_arrivee']),
                $this->normalizeDate($input['date_depart_prevue']),
                (float)$input['prix_par_nuit'],
                (float)($input['remise_pct'] ?? 0),
                $input['notes'] ?? null,
                (int)($_SESSION['user_id'] ?? 0)
            );
        } catch (\RuntimeException $e) {
            $this->jsonOut(['success' => false, 'message' => $e->getMessage()], 409);
        }

        $this->jsonOut([
            'success' => true,
            'data'    => (new HotelReservation())->findById($newId),
        ], 201);
    }

    public function update($id): void
    {
        $this->guard();
        $id = is_array($id) ? (int)($id['id'] ?? 0) : (int)$id;
        $input = $this->jsonInput();
        try {
            (new HotelReservation())->update($id, $input);
        } catch (\RuntimeException $e) {
            $this->jsonOut(['success' => false, 'message' => $e->getMessage()], 409);
        }
        $this->jsonOut([
            'success' => true,
            'data'    => (new HotelReservation())->findById($id),
        ]);
    }

    public function cancel($id): void
    {
        $this->guard();
        $id = is_array($id) ? (int)($id['id'] ?? 0) : (int)$id;
        $existing = (new HotelReservation())->findById($id);
        if (!$existing) {
            $this->jsonOut(['success' => false, 'message' => 'Introuvable'], 404);
        }
        if (in_array($existing['statut'], ['termine', 'annule'], true)) {
            $this->jsonOut(['success' => false, 'message' => 'Déjà clôturée'], 409);
        }
        (new HotelReservation())->cancel($id);
        $this->jsonOut(['success' => true, 'data' => (new HotelReservation())->findById($id)]);
    }

    public function availableRooms(): void
    {
        $this->guard();
        $arrivee = $_GET['date_arrivee'] ?? '';
        $depart  = $_GET['date_depart_prevue'] ?? '';
        if (!$arrivee || !$depart) {
            $this->jsonOut(['success' => false, 'message' => 'Période requise'], 422);
        }
        $exclude = isset($_GET['exclude_reservation_id']) ? (int)$_GET['exclude_reservation_id'] : null;
        $rooms = (new HotelReservation())->availableRooms(
            $this->normalizeDateTime($arrivee),
            $this->normalizeDate($depart),
            $exclude
        );
        $this->jsonOut(['success' => true, 'data' => $rooms]);
    }

    public function searchGuests(): void
    {
        $this->guard();
        $q = trim($_GET['q'] ?? '');
        if ($q === '') {
            $this->jsonOut(['success' => true, 'data' => []]);
        }
        $rows = (new HotelGuest())->search($q);
        $this->jsonOut(['success' => true, 'data' => $rows]);
    }

    /**
     * Lit le body JSON ou $_POST (fallback formulaires non-JS).
     */
    private function jsonInput(): array
    {
        $raw = file_get_contents('php://input');
        if ($raw) {
            $j = json_decode($raw, true);
            if (is_array($j)) return $j;
        }
        return $_POST;
    }

    private function normalizeDateTime(string $s): string
    {
        // accepte "YYYY-MM-DD" ou "YYYY-MM-DDTHH:MM"
        if (strlen($s) === 10) return $s . ' 14:00:00'; // heure d'arrivée par défaut
        return date('Y-m-d H:i:s', strtotime($s));
    }

    private function normalizeDate(string $s): string
    {
        return date('Y-m-d', strtotime($s));
    }
}
