<?php

namespace App\Controllers;

use App\Models\Client;

class ClientController extends Controller
{
    private $clientModel;

    public function __construct()
    {
        $this->clientModel = new Client();
    }

    /**
     * GET /api/clients - Liste tous les clients
     */
    public function index()
    {
        $clients = $this->clientModel->getAll();
        $types = $this->clientModel->getTypes();

        echo json_encode([
            'clients' => $clients,
            'types' => $types
        ]);
    }

    /**
     * GET /api/client/lookup?numero=xxx - Recherche un client par numéro
     */
    public function lookup()
    {
        $numero = $_GET['numero'] ?? '';

        if (empty($numero)) {
            echo json_encode([
                'found' => false,
                'message' => 'Numéro requis'
            ]);
            return;
        }

        $client = $this->clientModel->findByNumero($numero);

        if ($client) {
            echo json_encode([
                'found' => true,
                'client' => $client
            ]);
        } else {
            echo json_encode([
                'found' => false,
                'numero' => $numero
            ]);
        }
    }

    /**
     * POST /api/client - Crée un nouveau client
     */
    public function create()
    {
        header('Content-Type: application/json');
        
        $data = json_decode(file_get_contents('php://input'), true);

        if (empty($data['nom']) || empty($data['numero'])) {
            echo json_encode([
                'success' => false,
                'message' => 'Nom et numéro sont requis'
            ]);
            return;
        }

        // Vérifier si le client existe déjà
        $existing = $this->clientModel->findByNumero($data['numero']);
        if ($existing) {
            echo json_encode([
                'success' => false,
                'message' => 'Ce numéro existe déjà',
                'client' => $existing
            ]);
            return;
        }

        $client = $this->clientModel->create($data);

        if ($client) {
            echo json_encode([
                'success' => true,
                'message' => 'Client créé avec succès',
                'client' => $client
            ]);
        } else {
            echo json_encode([
                'success' => false,
                'message' => 'Erreur lors de la création du client'
            ]);
        }
    }

    /**
     * GET /api/client/types - Liste les types de clients
     */
    public function types()
    {
        $types = $this->clientModel->getTypes();
        echo json_encode($types);
    }
}