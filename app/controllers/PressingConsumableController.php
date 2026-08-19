<?php

namespace App\Controllers;

use App\Models\PressingConsumable;
use App\controllers\Controller;

class PressingConsumableController extends Controller
{
    public function index()
    {
        if (!$this->requireAuth()) return;

        $shopId = $this->isSuperAdmin() ? null : $this->getShopId();
        $model = new PressingConsumable();
        $this->json(['success' => true, 'consumables' => $model->all($shopId)]);
    }

    public function create()
    {
        if (!$this->requireAdmin()) return;

        $input = json_decode(file_get_contents('php://input'), true) ?? [];
        $shopId = $this->isSuperAdmin() ? ($input['shop_id'] ?? $this->getShopId()) : $this->getShopId();

        $nom = $this->sanitaze($input['nom'] ?? '');
        $quantite = (float)($input['quantite'] ?? 0);
        $unite = $this->sanitaze($input['unite'] ?? 'unité');
        $minimum = (float)($input['stock_minimum'] ?? 0);

        if (!$nom) {
            $this->status(400)->json(['error' => 'Nom requis']);
            return;
        }

        $model = new PressingConsumable();
        $id = $model->create([
            'shop_id'       => $shopId,
            'nom'           => $nom,
            'quantite'      => $quantite,
            'unite'         => $unite,
            'stock_minimum' => $minimum,
        ]);

        $this->logAudit('create', 'pressing_consumable', $id, ['nom' => $nom]);
        $this->json(['success' => true, 'id' => $id]);
    }

    public function update($params)
    {
        if (!$this->requireAdmin()) return;

        $id = is_array($params) ? ($params['id'] ?? null) : $params;
        $id = (int)$id;

        $input = json_decode(file_get_contents('php://input'), true) ?? [];
        $model = new PressingConsumable();
        $shopId = $this->isSuperAdmin() ? null : $this->getShopId();

        $existing = $model->findById($id, $shopId);
        if (!$existing) {
            $this->status(404)->json(['error' => 'Consommable introuvable']);
            return;
        }

        $model->update($id, [
            'nom'           => $this->sanitaze($input['nom'] ?? $existing['nom']),
            'quantite'      => (float)($input['quantite'] ?? $existing['quantite']),
            'unite'         => $this->sanitaze($input['unite'] ?? $existing['unite']),
            'stock_minimum' => (float)($input['stock_minimum'] ?? $existing['stock_minimum']),
        ]);

        $this->logAudit('update', 'pressing_consumable', $id);
        $this->json(['success' => true]);
    }

    public function delete($params)
    {
        if (!$this->requireAdmin()) return;

        $id = is_array($params) ? ($params['id'] ?? null) : $params;
        $id = (int)$id;

        $model = new PressingConsumable();
        $shopId = $this->isSuperAdmin() ? null : $this->getShopId();
        $existing = $model->findById($id, $shopId);
        if (!$existing) {
            $this->status(404)->json(['error' => 'Consommable introuvable']);
            return;
        }

        $model->delete($id);
        $this->logAudit('delete', 'pressing_consumable', $id);
        $this->json(['success' => true]);
    }

    public function consume($params)
    {
        if (!$this->requireAuth()) return;

        $id = is_array($params) ? ($params['id'] ?? null) : $params;
        $id = (int)$id;

        $input = json_decode(file_get_contents('php://input'), true) ?? [];
        $depotId = (int)($input['depot_id'] ?? 0);
        $quantite = (float)($input['quantite'] ?? 0);

        if (!$depotId || $quantite <= 0) {
            $this->status(400)->json(['error' => 'Dépôt et quantité requis']);
            return;
        }

        $model = new PressingConsumable();
        $shopId = $this->isSuperAdmin() ? null : $this->getShopId();
        $existing = $model->findById($id, $shopId);
        if (!$existing) {
            $this->status(404)->json(['error' => 'Consommable introuvable']);
            return;
        }
        if ($existing['quantite'] < $quantite) {
            $this->status(409)->json(['error' => 'Stock insuffisant']);
            return;
        }

        $model->consume($id, $depotId, $quantite, $_SESSION['user_id']);
        $this->logAudit('consume', 'pressing_consumable', $id, ['depot_id' => $depotId, 'quantite' => $quantite]);
        $this->json(['success' => true, 'reste' => $existing['quantite'] - $quantite]);
    }
}
