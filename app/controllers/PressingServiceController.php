<?php

namespace App\Controllers;

use App\Models\PressingService;
use App\controllers\Controller;

class PressingServiceController extends Controller
{
    public function index()
    {
        if (!$this->requireAuth()) return;

        $shopId = $this->isSuperAdmin() ? null : $this->getShopId();
        $model = new PressingService();
        $this->json(['success' => true, 'services' => $model->all($shopId)]);
    }

    public function create()
    {
        if (!$this->requireAdmin()) return;

        $input = json_decode(file_get_contents('php://input'), true) ?? [];
        $shopId = $this->isSuperAdmin() ? ($input['shop_id'] ?? $this->getShopId()) : $this->getShopId();

        $nom = $this->sanitaze($input['nom'] ?? '');
        $description = isset($input['description']) ? $this->sanitaze($input['description']) : null;
        $duree = (int)($input['duree_estimee'] ?? 0);

        if (!$nom) {
            $this->status(400)->json(['error' => 'Nom requis']);
            return;
        }

        $model = new PressingService();
        $id = $model->create([
            'shop_id'       => $shopId,
            'nom'           => $nom,
            'description'   => $description,
            'duree_estimee' => $duree,
        ]);

        $this->logAudit('create', 'pressing_service', $id, ['nom' => $nom]);
        $this->json(['success' => true, 'id' => $id]);
    }

    public function update($params)
    {
        if (!$this->requireAdmin()) return;

        $id = is_array($params) ? ($params['id'] ?? null) : $params;
        $id = (int)$id;

        $input = json_decode(file_get_contents('php://input'), true) ?? [];
        $model = new PressingService();
        $shopId = $this->isSuperAdmin() ? null : $this->getShopId();

        $existing = $model->findById($id, $shopId);
        if (!$existing) {
            $this->status(404)->json(['error' => 'Service introuvable']);
            return;
        }

        $nom = $this->sanitaze($input['nom'] ?? $existing['nom']);
        $description = isset($input['description']) ? $this->sanitaze($input['description']) : $existing['description'];
        $duree = (int)($input['duree_estimee'] ?? $existing['duree_estimee']);

        $model->update($id, [
            'nom'           => $nom,
            'description'   => $description,
            'duree_estimee' => $duree,
        ]);

        $this->logAudit('update', 'pressing_service', $id, ['nom' => $nom]);
        $this->json(['success' => true]);
    }

    public function delete($params)
    {
        if (!$this->requireAdmin()) return;

        $id = is_array($params) ? ($params['id'] ?? null) : $params;
        $id = (int)$id;

        $model = new PressingService();
        $shopId = $this->isSuperAdmin() ? null : $this->getShopId();
        $existing = $model->findById($id, $shopId);
        if (!$existing) {
            $this->status(404)->json(['error' => 'Service introuvable']);
            return;
        }

        $model->delete($id);
        $this->logAudit('delete', 'pressing_service', $id);
        $this->json(['success' => true]);
    }
}
