<?php

namespace App\Controllers;

use App\Models\RestaurantTable;
use App\controllers\Controller;

class RestaurantTableController extends Controller
{
    public function index()
    {
        if (!$this->requireAuth()) return;

        $tableModel = new RestaurantTable();
        $shopId = $this->isSuperAdmin() ? null : $this->getShopId();
        $this->json($tableModel->all($shopId));
    }

    public function create()
    {
        if (!$this->requireAdmin()) return;

        $input = json_decode(file_get_contents('php://input'), true) ?? [];
        $numero = $this->sanitaze($input['numero'] ?? '');
        $nom = isset($input['nom']) ? $this->sanitaze($input['nom']) : null;
        $capacite = isset($input['capacite']) ? (int)$input['capacite'] : 4;
        $etat = $this->sanitaze($input['etat'] ?? 'libre');

        $shopId = $this->isSuperAdmin() ? ($input['shop_id'] ?? null) : $this->getShopId();

        if (!$numero) {
            $this->status(400)->json(['error' => 'Numéro de table requis']);
            return;
        }
        if (!$shopId) {
            $this->status(400)->json(['error' => 'Boutique requise']);
            return;
        }
        if ($capacite < 1) {
            $this->status(400)->json(['error' => 'Capacité invalide']);
            return;
        }

        $tableModel = new RestaurantTable();

        if (!in_array($etat, $tableModel->getValidStates())) {
            $etat = 'libre';
        }

        if ($tableModel->existsNumero($numero, $shopId)) {
            $this->status(409)->json(['error' => 'Ce numéro de table existe déjà pour cette boutique']);
            return;
        }

        $id = $tableModel->create([
            'shop_id'  => $shopId,
            'numero'   => $numero,
            'nom'      => $nom,
            'capacite' => $capacite,
            'etat'     => $etat,
        ]);

        $this->logAudit('create', 'restaurant_table', $id, ['numero' => $numero]);
        $this->json(['success' => true, 'id' => $id]);
    }

    public function update($params)
    {
        if (!$this->requireAdmin()) return;

        $id = is_array($params) ? ($params['id'] ?? null) : $params;
        $id = (int)$id;

        if (!$id) {
            $this->status(400)->json(['error' => 'ID table manquant']);
            return;
        }

        $tableModel = new RestaurantTable();
        $shopId = $this->isSuperAdmin() ? null : $this->getShopId();
        $existing = $tableModel->findById($id, $shopId);

        if (!$existing) {
            $this->status(404)->json(['error' => 'Table introuvable']);
            return;
        }

        $input = json_decode(file_get_contents('php://input'), true) ?? [];
        $data = [];

        if (isset($input['numero'])) {
            $numero = $this->sanitaze($input['numero']);
            if ($tableModel->existsNumero($numero, $existing['shop_id'], $id)) {
                $this->status(409)->json(['error' => 'Ce numéro de table existe déjà pour cette boutique']);
                return;
            }
            $data['numero'] = $numero;
        }
        if (isset($input['nom'])) $data['nom'] = $this->sanitaze($input['nom']);
        if (isset($input['capacite'])) $data['capacite'] = (int)$input['capacite'];
        if (isset($input['etat'])) {
            $etat = $this->sanitaze($input['etat']);
            if (!in_array($etat, $tableModel->getValidStates())) {
                $this->status(400)->json(['error' => 'État invalide']);
                return;
            }
            $data['etat'] = $etat;
        }

        $tableModel->update($id, $data);
        $this->logAudit('update', 'restaurant_table', $id, $data);
        $this->json(['success' => true]);
    }

    public function updateState($params)
    {
        if (!$this->requireAuth()) return;

        $id = is_array($params) ? ($params['id'] ?? null) : $params;
        $id = (int)$id;

        if (!$id) {
            $this->status(400)->json(['error' => 'ID table manquant']);
            return;
        }

        $tableModel = new RestaurantTable();
        $shopId = $this->isSuperAdmin() ? null : $this->getShopId();
        $existing = $tableModel->findById($id, $shopId);

        if (!$existing) {
            $this->status(404)->json(['error' => 'Table introuvable']);
            return;
        }

        $input = json_decode(file_get_contents('php://input'), true) ?? [];
        $etat = $this->sanitaze($input['etat'] ?? '');

        if (!in_array($etat, $tableModel->getValidStates())) {
            $this->status(400)->json(['error' => 'État invalide']);
            return;
        }

        $tableModel->updateState($id, $etat);
        $this->logAudit('update_state', 'restaurant_table', $id, ['etat' => $etat]);
        $this->json(['success' => true]);
    }

    public function delete($params)
    {
        if (!$this->requireAdmin()) return;

        $id = is_array($params) ? ($params['id'] ?? null) : $params;
        $id = (int)$id;

        if (!$id) {
            $this->status(400)->json(['error' => 'ID table manquant']);
            return;
        }

        $tableModel = new RestaurantTable();
        $shopId = $this->isSuperAdmin() ? null : $this->getShopId();
        $existing = $tableModel->findById($id, $shopId);

        if (!$existing) {
            $this->status(404)->json(['error' => 'Table introuvable']);
            return;
        }

        $tableModel->delete($id);
        $this->logAudit('delete', 'restaurant_table', $id, ['numero' => $existing['numero']]);
        $this->json(['success' => true]);
    }
}
