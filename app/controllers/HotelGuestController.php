<?php

namespace App\Controllers;

use App\Models\HotelGuest;
use App\Controllers\Controller;

/**
 * API JSON pour la page Guests.
 *
 * Routes :
 *   GET    /api/hotel/guests              → liste paginée + recherche
 *   GET    /api/hotel/guests/[i:id]       → détail + séjours
 *   POST   /api/hotel/guests              → créer (guest libre ou guest lié à un client POS)
 *   PUT    /api/hotel/guests/[i:id]       → mettre à jour
 *   POST   /api/hotel/guests/[i:id]/link-client → lier/délier un client POS à un guest existant
 *   GET    /api/hotel/clients/search?q=   → autocomplete clients POS (pour le modal d'assignation)
 *
 * Permissions :
 *   - tous rôles hôtel : GET
 *   - manager + réceptionniste + super_admin/admin : POST, PUT, link-client
 *     (la réceptionniste a besoin de pouvoir lier un client à un guest
 *     quand le client arrive, sans dépendre du manager)
 *   - pas de DELETE (les guests ont un historique, on ne supprime jamais)
 */
class HotelGuestController extends Controller
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
        // Sprint 7 — 2026-10-19 : check API avant check rôle.
        if (!$this->requireFeatureApiAccess('hotel')) return false;
        $appRole   = $_SESSION['role'] ?? '';
        $hotelRole = $_SESSION['hotel_role'] ?? null;
        if ($appRole === 'super_admin') return true;
        if ($appRole === 'admin') return true;
        if (in_array($hotelRole, ['manager', 'receptionniste', 'gouvernante'], true)) return true;
        return false;
    }

    private function guardWrite(): bool
    {
        // Sprint 7 — 2026-10-19 : check API avant check rôle.
        if (!$this->requireFeatureApiAccess('hotel')) return false;
        $appRole   = $_SESSION['role'] ?? '';
        $hotelRole = $_SESSION['hotel_role'] ?? null;
        if ($appRole === 'super_admin') return true;
        if ($appRole === 'admin') return true;
        // Manager + réceptionniste peuvent créer/éditer un guest et le lier à un client.
        // La gouvernante reste en lecture seule (pas de contact client direct).
        if (in_array($hotelRole, ['manager', 'receptionniste'], true)) return true;
        return false;
    }

    public function index(): void
    {
        if (!$this->guardRead()) $this->jsonOut(['success' => false, 'message' => 'Accès refusé'], 403);

        $page   = max(1, (int)($_GET['page'] ?? 1));
        $limit  = min(100, max(5, (int)($_GET['limit'] ?? 25)));
        $offset = ($page - 1) * $limit;
        $search = $_GET['q'] ?? null;
        if ($search === '') $search = null;

        $model  = new HotelGuest();
        $rows   = $model->listWithStats($limit, $offset, $search);
        $total  = $model->countGuests($search);
        $pages  = (int)ceil($total / $limit);

        $this->jsonOut([
            'success' => true,
            'read_only' => !$this->guardWrite(),
            'data'    => $rows,
            'meta'    => [
                'page'  => $page,
                'limit' => $limit,
                'total' => $total,
                'pages' => $pages,
            ],
        ]);
    }

    public function show($id): void
    {
        if (!$this->guardRead()) $this->jsonOut(['success' => false, 'message' => 'Accès refusé'], 403);
        $id = is_array($id) ? (int)($id['id'] ?? 0) : (int)$id;
        $model = new HotelGuest();
        $row = $model->findById($id);
        if (!$row) $this->jsonOut(['success' => false, 'message' => 'Guest introuvable'], 404);
        $sejours = $model->sejoursPourGuest($id);
        $this->jsonOut([
            'success' => true,
            'read_only' => !$this->guardWrite(),
            'data' => [
                'guest'   => $row,
                'sejours' => $sejours,
            ],
        ]);
    }

    public function store(): void
    {
        if (!$this->guardWrite()) $this->jsonOut(['success' => false, 'message' => 'Accès refusé'], 403);
        $input = $this->jsonInput();
        $nom = trim((string)($input['nom'] ?? ''));
        if ($nom === '') $this->jsonOut(['success' => false, 'message' => 'Nom requis'], 422);

        // Anti-doublon : si on trouve un probable doublon, on renvoie 409 avec l'existant
        $tel = $input['telephone'] ?? null;
        $piece = $input['piece_identite'] ?? null;
        if ($tel === '') $tel = null;
        if ($piece === '') $piece = null;

        $dup = (new HotelGuest())->findPossibleDuplicate($nom, $tel, $piece);
        if ($dup) {
            $this->jsonOut([
                'success' => false,
                'duplicate' => $dup,
                'message'  => 'Un guest existe déjà avec ces informations.',
            ], 409);
        }

        // Option A : lien vers un client POS (optionnel).
        $clientId = isset($input['client_id']) && $input['client_id'] !== '' ? (int)$input['client_id'] : null;
        if ($clientId !== null) {
            if (!(new \App\Models\Client())->findById($clientId)) {
                $this->jsonOut(['success' => false, 'message' => 'Client POS introuvable'], 422);
            }
            // Si ce client a déjà un guest, on le signale plutôt que d'en créer un doublon
            $existing = (new HotelGuest())->findByClientId($clientId);
            if ($existing) {
                $this->jsonOut([
                    'success'  => false,
                    'duplicate'=> $existing,
                    'message'  => 'Ce client a déjà un guest lié (id #' . $existing['id'] . ').',
                ], 409);
            }
        }

        $id = (new HotelGuest())->create(
            $nom,
            $input['prenom'] ?? null,
            $tel,
            $piece,
            $input['nationalite'] ?? null,
            $clientId
        );
        $this->jsonOut([
            'success' => true,
            'data'    => (new HotelGuest())->findById($id),
        ], 201);
    }

    public function update($id): void
    {
        if (!$this->guardWrite()) $this->jsonOut(['success' => false, 'message' => 'Accès refusé'], 403);
        $id = is_array($id) ? (int)($id['id'] ?? 0) : (int)$id;
        $input = $this->jsonInput();
        // Sanitize : champs vides → null (pour ne pas écraser avec chaîne vide)
        foreach (['prenom', 'telephone', 'piece_identite', 'nationalite'] as $k) {
            if (array_key_exists($k, $input) && $input[$k] === '') {
                $input[$k] = null;
            }
        }
        (new HotelGuest())->updateGuest($id, $input);
        $this->jsonOut([
            'success' => true,
            'data'    => (new HotelGuest())->findById($id),
        ]);
    }

    /**
     * POST /api/hotel/guests/[i:id]/link-client
     * Body : {"client_id": 42}  ou  {"client_id": null}  pour délier.
     * Renvoie le guest mis à jour (avec client_code / client_nom si lié).
     */
    public function linkClient($id): void
    {
        if (!$this->guardWrite()) $this->jsonOut(['success' => false, 'message' => 'Accès refusé'], 403);
        $id = is_array($id) ? (int)($id['id'] ?? 0) : (int)$id;
        $guestModel = new HotelGuest();
        $guest = $guestModel->findById($id);
        if (!$guest) $this->jsonOut(['success' => false, 'message' => 'Guest introuvable'], 404);

        $input = $this->jsonInput();
        $raw = array_key_exists('client_id', $input) ? $input['client_id'] : null;
        if ($raw === null || $raw === '') {
            // Délier
            $guestModel->linkClient($id, null);
            $this->jsonOut(['success' => true, 'data' => $guestModel->findById($id)]);
        }
        $clientId = (int)$raw;
        if (!(new \App\Models\Client())->findById($clientId)) {
            $this->jsonOut(['success' => false, 'message' => 'Client POS introuvable'], 422);
        }
        // Le client est-il déjà lié à un AUTRE guest ? On refuse et on renvoie l'existant
        // pour que la réceptionniste puisse l'ouvrir directement.
        $existing = $guestModel->findByClientId($clientId);
        if ($existing && (int)$existing['id'] !== $id) {
            $this->jsonOut([
                'success'   => false,
                'duplicate' => $existing,
                'message'   => 'Ce client est déjà lié au guest #' . $existing['id'] . '.',
            ], 409);
        }
        $guestModel->linkClient($id, $clientId);
        $this->jsonOut(['success' => true, 'data' => $guestModel->findById($id)]);
    }

    /**
     * GET /api/hotel/clients/search?q=...
     * Autocomplete des clients POS pour le modal d'assignation.
     * Renvoie aussi `linked_guest_id` pour pouvoir prévenir "déjà lié" côté UI.
     * Permissions : même lecture que la liste des guests.
     */
    public function searchClients(): void
    {
        if (!$this->guardRead()) $this->jsonOut(['success' => false, 'message' => 'Accès refusé'], 403);
        $q = trim((string)($_GET['q'] ?? ''));
        if (mb_strlen($q) < 1) $this->jsonOut(['success' => true, 'data' => []]);

        $like = '%' . $q . '%';
        // On borne à 20 résultats pour l'autocomplete.
        // NB : PDO en mode natif (EMULATE_PREPARES=false) ne permet pas de
        // réutiliser un même placeholder nommé plusieurs fois dans la requête.
        // On dédouble donc le paramètre sous 3 clés distinctes.
        $rows = \App\Core\Database::getInstance()->fetchAll(
            "SELECT c.id, c.code_client, c.nom_client, c.numero, c.nif, c.adresse,
                    hg.id AS linked_guest_id
             FROM clients c
             LEFT JOIN hotel_guests hg ON hg.client_id = c.id
             WHERE c.nom_client LIKE :k1 OR c.code_client LIKE :k2 OR c.numero LIKE :k3
             ORDER BY c.nom_client ASC
             LIMIT 20",
            [':k1' => $like, ':k2' => $like, ':k3' => $like]
        );
        $this->jsonOut(['success' => true, 'data' => $rows]);
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
