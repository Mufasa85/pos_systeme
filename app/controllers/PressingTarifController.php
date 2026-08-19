<?php

namespace App\Controllers;

use App\Models\PressingTarif;
use App\controllers\Controller;

class PressingTarifController extends Controller
{
    public function index()
    {
        if (!$this->requireAuth()) return;

        $shopId = $this->isSuperAdmin() ? null : $this->getShopId();
        $serviceId = !empty($_GET['service_id']) ? (int)$_GET['service_id'] : null;

        $model = new PressingTarif();
        $this->json(['success' => true, 'tarifs' => $model->all($shopId, $serviceId)]);
    }

    public function create()
    {
        if (!$this->requireAdmin()) return;

        $input = json_decode(file_get_contents('php://input'), true) ?? [];
        $shopId = $this->isSuperAdmin() ? ($input['shop_id'] ?? $this->getShopId()) : $this->getShopId();

        $serviceId = (int)($input['service_id'] ?? 0);
        $articleType = $this->sanitaze($input['article_type'] ?? '');
        $prix = (float)($input['prix_unitaire'] ?? 0);

        if (!$serviceId || !$articleType || $prix <= 0) {
            $this->status(400)->json(['error' => 'Service, type d\'article et prix requis']);
            return;
        }

        $model = new PressingTarif();
        $id = $model->create([
            'shop_id'       => $shopId,
            'service_id'    => $serviceId,
            'article_type'  => $articleType,
            'prix_unitaire' => $prix,
        ]);

        $this->logAudit('create', 'pressing_tarif', $id, ['service_id' => $serviceId, 'article_type' => $articleType]);
        $this->json(['success' => true, 'id' => $id]);
    }

    public function update($params)
    {
        if (!$this->requireAdmin()) return;

        $id = is_array($params) ? ($params['id'] ?? null) : $params;
        $id = (int)$id;

        $input = json_decode(file_get_contents('php://input'), true) ?? [];
        $model = new PressingTarif();
        $shopId = $this->isSuperAdmin() ? null : $this->getShopId();

        $existing = $model->findById($id, $shopId);
        if (!$existing) {
            $this->status(404)->json(['error' => 'Tarif introuvable']);
            return;
        }

        $serviceId = (int)($input['service_id'] ?? $existing['service_id']);
        $articleType = $this->sanitaze($input['article_type'] ?? $existing['article_type']);
        $prix = (float)($input['prix_unitaire'] ?? $existing['prix_unitaire']);

        if (!$serviceId || !$articleType || $prix <= 0) {
            $this->status(400)->json(['error' => 'Service, type d\'article et prix requis']);
            return;
        }

        $model->update($id, [
            'service_id'    => $serviceId,
            'article_type'  => $articleType,
            'prix_unitaire' => $prix,
        ]);

        $this->logAudit('update', 'pressing_tarif', $id);
        $this->json(['success' => true]);
    }

    public function delete($params)
    {
        if (!$this->requireAdmin()) return;

        $id = is_array($params) ? ($params['id'] ?? null) : $params;
        $id = (int)$id;

        $model = new PressingTarif();
        $shopId = $this->isSuperAdmin() ? null : $this->getShopId();
        $existing = $model->findById($id, $shopId);
        if (!$existing) {
            $this->status(404)->json(['error' => 'Tarif introuvable']);
            return;
        }

        $model->delete($id);
        $this->logAudit('delete', 'pressing_tarif', $id);
        $this->json(['success' => true]);
    }
}
