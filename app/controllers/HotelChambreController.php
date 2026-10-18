<?php

namespace App\Controllers;

use App\Models\HotelChambre;
use App\Controllers\Controller;

/**
 * API JSON pour la gestion des chambres hôtel.
 *
 * Routes :
 *   GET    /api/hotel/chambres              → liste
 *   GET    /api/hotel/chambres/[i:id]       → détail
 *   POST   /api/hotel/chambres              → créer
 *   PUT    /api/hotel/chambres/[i:id]       → modifier (tous champs)
 *   POST   /api/hotel/chambres/[i:id]/etat  → changer l'état uniquement
 *
 * Permissions :
 *   - manager (admin app auto + admin assigné) + super_admin : CUD
 *   - receptionniste, gouvernante : GET seulement
 */
class HotelChambreController extends Controller
{
    private function jsonOut($payload, int $status = 200): void
    {
        http_response_code($status);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode($payload, JSON_UNESCAPED_UNICODE);
        exit;
    }

    /**
     * Lecture : tout rôle hôtel peut lister.
     * Écriture : manager uniquement (admin app / super_admin / manager assigné).
     */
    private function guardRead(): bool
    {
        $appRole = $_SESSION['role'] ?? '';
        $hotelRole = $_SESSION['hotel_role'] ?? null;
        if ($appRole === 'super_admin') return true;
        if ($appRole === 'admin') return true;
        if (in_array($hotelRole, ['manager', 'receptionniste', 'gouvernante'], true)) return true;
        return false;
    }

    private function guardWrite(): bool
    {
        $appRole = $_SESSION['role'] ?? '';
        $hotelRole = $_SESSION['hotel_role'] ?? null;
        if ($appRole === 'super_admin') return true;
        if ($appRole === 'admin') return true; // auto-manager
        if ($hotelRole === 'manager') return true;
        return false;
    }

    public function index(): void
    {
        if (!$this->guardRead()) $this->jsonOut(['success' => false, 'message' => 'Accès refusé'], 403);
        $rows = (new HotelChambre())->all();
        // Ajout d'un flag readOnly pour le front (réceptionniste / gouvernante)
        $readOnly = !$this->guardWrite();
        $this->jsonOut(['success' => true, 'read_only' => $readOnly, 'data' => $rows]);
    }

    public function show($id): void
    {
        if (!$this->guardRead()) $this->jsonOut(['success' => false, 'message' => 'Accès refusé'], 403);
        $id = is_array($id) ? (int)($id['id'] ?? 0) : (int)$id;
        $row = (new HotelChambre())->findById($id);
        if (!$row) $this->jsonOut(['success' => false, 'message' => 'Chambre introuvable'], 404);
        $this->jsonOut(['success' => true, 'data' => $row]);
    }

    public function store(): void
    {
        if (!$this->guardWrite()) $this->jsonOut(['success' => false, 'message' => 'Accès refusé'], 403);
        $input = $this->jsonInput();
        foreach (['numero', 'type', 'prix_base'] as $r) {
            if (!isset($input[$r]) || $input[$r] === '') {
                $this->jsonOut(['success' => false, 'message' => "Champ manquant : $r"], 422);
            }
        }
        try {
            $id = (new HotelChambre())->create(
                (string)$input['numero'],
                (string)$input['type'],
                (float)$input['prix_base'],
                isset($input['notes']) ? (string)$input['notes'] : null,
                isset($input['etat']) ? (string)$input['etat'] : 'disponible'
            );
            // La TS n'est pas passée à create() : on l'écrit via update() juste après,
            // pour rester rétro-compatible avec la signature create() existante.
            $needsTsUpdate = array_key_exists('taxe_specifique_type', $input)
                || array_key_exists('taxe_specifique_value', $input);
            if ($needsTsUpdate) {
                (new HotelChambre())->update($id, [
                    'taxe_specifique_type' => isset($input['taxe_specifique_type']) ? (string)$input['taxe_specifique_type'] : '%',
                    'taxe_specifique_value' => isset($input['taxe_specifique_value']) ? (float)$input['taxe_specifique_value'] : 0,
                ]);
            }
        } catch (\RuntimeException $e) {
            $this->jsonOut(['success' => false, 'message' => $e->getMessage()], 409);
        }
        $this->jsonOut(['success' => true, 'data' => (new HotelChambre())->findById($id)], 201);
    }

    public function update($id): void
    {
        if (!$this->guardWrite()) $this->jsonOut(['success' => false, 'message' => 'Accès refusé'], 403);
        $id = is_array($id) ? (int)($id['id'] ?? 0) : (int)$id;
        $input = $this->jsonInput();
        try {
            (new HotelChambre())->update($id, $input);
        } catch (\RuntimeException $e) {
            $this->jsonOut(['success' => false, 'message' => $e->getMessage()], 409);
        }
        $this->jsonOut(['success' => true, 'data' => (new HotelChambre())->findById($id)]);
    }

    /**
     * Changement d'état rapide depuis la liste (sans ouvrir le drawer).
     */
    public function setEtat($id): void
    {
        if (!$this->guardWrite()) $this->jsonOut(['success' => false, 'message' => 'Accès refusé'], 403);
        $id = is_array($id) ? (int)($id['id'] ?? 0) : (int)$id;
        $input = $this->jsonInput();
        if (empty($input['etat'])) {
            $this->jsonOut(['success' => false, 'message' => 'etat requis'], 422);
        }
        try {
            (new HotelChambre())->setEtat($id, (string)$input['etat']);
        } catch (\RuntimeException $e) {
            $this->jsonOut(['success' => false, 'message' => $e->getMessage()], 409);
        }
        $this->jsonOut(['success' => true, 'data' => (new HotelChambre())->findById($id)]);
    }

    private function jsonInput(): array
    {
        $raw = file_get_contents('php://input');
        if ($raw) {
            $j = json_decode($raw, true);
            if (is_array($j)) return $j;
        }
        return $_POST;
    }
}
