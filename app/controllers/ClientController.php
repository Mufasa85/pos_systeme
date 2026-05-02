<?php

namespace App\Controllers;

use App\Models\Client;
use App\Models\TypeClient;
use App\Core\Database;

class ClientController
{
    private $clientModel;
    private $typeClientModel;

    public function __construct()
    {
        $this->clientModel = new Client();
        $this->typeClientModel = new TypeClient();
    }

    /**
     * Liste tous les clients
     * GET /api/clients
     */
    public function index()
    {
        header('Content-Type: application/json');

        try {
            $clients = $this->clientModel->getAll();
            echo json_encode($clients);
        } catch (\Exception $e) {
            echo json_encode([
                'success' => false,
                'message' => 'Erreur: ' . $e->getMessage()
            ]);
        }
    }

    /**
     * Rechercher un client par numéro
     * GET /api/client/lookup?numero=...
     */
    public function lookup()
    {
        header('Content-Type: application/json');

        $numero = $_GET['numero'] ?? '';

        if (empty($numero)) {
            echo json_encode([
                'success' => false,
                'message' => 'Le numéro est requis'
            ]);
            return;
        }

        try {
            $client = $this->clientModel->findByNumero($numero);
            echo json_encode([
                'found' => !empty($client),
                'client' => $client
            ]);
        } catch (\Exception $e) {
            echo json_encode([
                'success' => false,
                'message' => 'Erreur: ' . $e->getMessage()
            ]);
        }
    }

    /**
     * Créer un nouveau client
     * POST /api/client
     */
    public function create()
    {
        header('Content-Type: application/json');

        try {
            $input = json_decode(file_get_contents('php://input'), true);

            $nom = $input['nom'] ?? '';
            $numero = $input['numero'] ?? '';
            $typeClientId = $input['type_client_id'] ?? 1;
            $nif = $input['nif'] ?? '';

            if (empty($nom) || empty($numero)) {
                echo json_encode([
                    'success' => false,
                    'message' => 'Le nom et le numéro sont requis'
                ]);
                return;
            }

            // Vérifier si le client existe déjà
            $existing = $this->clientModel->findByNumero($numero);
            if ($existing) {
                echo json_encode([
                    'success' => false,
                    'message' => 'Ce numéro est déjà utilisé',
                    'client' => $existing
                ]);
                return;
            }

            // Créer le client
            $clientId = $this->clientModel->create([
                'nom_client' => $nom,
                'numero' => $numero,
                'type_client_id' => $typeClientId,
                'nif' => $nif
            ]);

            // Récupérer le client créé
            $client = $this->clientModel->findById($clientId);

            echo json_encode([
                'success' => true,
                'message' => 'Client créé avec succès',
                'client' => $client
            ]);
        } catch (\Exception $e) {
            echo json_encode([
                'success' => false,
                'message' => 'Erreur: ' . $e->getMessage()
            ]);
        }
    }

    /**
     * Récupérer tous les types de clients
     * GET /api/client/types
     */
    public function getTypes()
    {
        header('Content-Type: application/json');

        try {
            $types = $this->typeClientModel->getAll();
            echo json_encode($types);
        } catch (\Exception $e) {
            echo json_encode([
                'success' => false,
                'message' => 'Erreur: ' . $e->getMessage()
            ]);
        }
    }

    /**
     * Rechercher un client par numéro de téléphone
     * GET /api/client/search?numero=0816069107
     */
    public function searchByNumero()
    {
        header('Content-Type: application/json');

        $numero = $_GET['numero'] ?? '';

        if (empty($numero)) {
            echo json_encode([
                'success' => false,
                'message' => 'Le numéro de téléphone est requis'
            ]);
            return;
        }

        try {
            $client = $this->clientModel->findByNumero($numero);

            if ($client) {
                echo json_encode([
                    'success' => true,
                    'client' => [
                        'id' => $client['id'],
                        'nom_client' => $client['nom_client'],
                        'numero' => $client['numero'],
                        'code_client' => $client['code_client'],
                        'type_id' => $client['type_client_id'] ?? null,
                        'type_code' => $client['type_code'] ?? '',
                        'type_description' => $client['type_description'] ?? '',
                        'nif' => $client['nif'] ?? ''
                    ]
                ]);
            } else {
                echo json_encode([
                    'success' => false,
                    'message' => 'Client non trouvé'
                ]);
            }
        } catch (\Exception $e) {
            echo json_encode([
                'success' => false,
                'message' => 'Erreur lors de la recherche: ' . $e->getMessage()
            ]);
        }
    }
}
