<?php

namespace App\Controllers;

use App\Models\PressingPhoto;
use App\Models\PressingDepot;
use App\controllers\Controller;

class PressingPhotoController extends Controller
{
    public function index($params)
    {
        if (!$this->requireAuth()) return;

        $depotId = is_array($params) ? ($params['id'] ?? null) : $params;
        $depotId = (int)$depotId;

        $depotModel = new PressingDepot();
        $shopId = $this->isSuperAdmin() ? null : $this->getShopId();
        if (!$depotModel->findById($depotId, $shopId)) {
            $this->status(404)->json(['error' => 'Dépôt introuvable']);
            return;
        }

        $model = new PressingPhoto();
        $this->json(['success' => true, 'photos' => $model->getByDepot($depotId)]);
    }

    public function create($params)
    {
        if (!$this->requireAuth()) return;

        $depotId = is_array($params) ? ($params['id'] ?? null) : $params;
        $depotId = (int)$depotId;

        $depotModel = new PressingDepot();
        $shopId = $this->isSuperAdmin() ? null : $this->getShopId();
        $depot = $depotModel->findById($depotId, $shopId);
        if (!$depot) {
            $this->status(404)->json(['error' => 'Dépôt introuvable']);
            return;
        }

        if (!isset($_FILES['photo']) || $_FILES['photo']['error'] !== UPLOAD_ERR_OK) {
            $this->status(400)->json(['error' => 'Aucune photo reçue']);
            return;
        }

        $uploadDir = dirname(__DIR__, 2) . '/public/assets/img/pressing/';
        if (!is_dir($uploadDir)) {
            mkdir($uploadDir, 0755, true);
        }

        $extension = strtolower(pathinfo($_FILES['photo']['name'], PATHINFO_EXTENSION));
        $allowed = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
        if (!in_array($extension, $allowed)) {
            $extension = 'jpg';
        }

        $fileName = 'pressing_' . $depotId . '_' . time() . '.' . $extension;
        $filePath = $uploadDir . $fileName;

        if (!move_uploaded_file($_FILES['photo']['tmp_name'], $filePath)) {
            $this->status(500)->json(['error' => 'Échec de l\'enregistrement']);
            return;
        }

        $input = $_POST;
        $model = new PressingPhoto();
        $id = $model->create([
            'depot_id'   => $depotId,
            'article_id' => !empty($input['article_id']) ? (int)$input['article_id'] : null,
            'chemin'     => 'assets/img/pressing/' . $fileName,
            'type'       => in_array($input['type'] ?? '', ['etat_initial', 'final']) ? $input['type'] : 'etat_initial',
        ]);

        $this->logAudit('create', 'pressing_photo', $id, ['depot_id' => $depotId]);
        $this->json(['success' => true, 'id' => $id, 'chemin' => 'assets/img/pressing/' . $fileName]);
    }

    public function delete($params)
    {
        if (!$this->requireAdmin()) return;

        $id = is_array($params) ? ($params['id'] ?? null) : $params;
        $id = (int)$id;

        $model = new PressingPhoto();
        $model->delete($id);
        $this->logAudit('delete', 'pressing_photo', $id);
        $this->json(['success' => true]);
    }
}
