<?php

namespace App\Controllers;

use App\Models\ServiceType;
use App\controllers\Controller;

class ServiceTypeController extends Controller
{
    public function index()
    {
        if (!$this->requireSuperAdmin() && !$this->requireAdmin()) return;

        $serviceTypeModel = new ServiceType();
        $this->json($serviceTypeModel->getAll());
    }

    public function create()
    {
        if (!$this->requireSuperAdmin() && !$this->requireAdmin()) return;

        $input = json_decode(file_get_contents('php://input'), true);
        $name = $this->sanitaze($input['name'] ?? '');

        if (!$name) {
            $this->status(400)->json(['error' => 'Nom du type de service requis']);
            return;
        }

        $serviceTypeModel = new ServiceType();

        // Vérifier unicité du nom
        if ($serviceTypeModel->exists($name)) {
            $this->status(409)->json(['error' => 'Ce type de service existe déjà']);
            return;
        }

        $id = $serviceTypeModel->create(['name' => $name]);
        $this->logAudit('create', 'service_type', $id, ['name' => $name]);
        $this->json(['success' => true, 'id' => $id]);
    }

    public function update($id)
    {
        if (!$this->requireSuperAdmin() && !$this->requireAdmin()) return;

        if (is_array($id)) $id = $id['id'] ?? null;
        $id = (int)$id;

        if (!$id) {
            $this->status(400)->json(['error' => 'ID type de service manquant']);
            return;
        }

        $input = json_decode(file_get_contents('php://input'), true);
        $name = $this->sanitaze($input['name'] ?? '');

        if (!$name) {
            $this->status(400)->json(['error' => 'Nom du type de service requis']);
            return;
        }

        $serviceTypeModel = new ServiceType();

        $serviceType = $serviceTypeModel->findById($id);
        if (!$serviceType) {
            $this->status(404)->json(['error' => 'Type de service introuvable']);
            return;
        }

        // Vérifier unicité du nom (excluant l'actuel)
        if ($serviceTypeModel->exists($name, $id)) {
            $this->status(409)->json(['error' => 'Ce type de service existe déjà']);
            return;
        }

        $serviceTypeModel->update($id, ['name' => $name]);
        $this->logAudit('update', 'service_type', $id, ['name' => $name]);
        $this->json(['success' => true, 'message' => 'Type de service mis à jour']);
    }

    public function delete($id)
    {
        if (!$this->requireSuperAdmin()) return;

        if (is_array($id)) $id = $id['id'] ?? null;
        $id = (int)$id;

        if (!$id) {
            $this->status(400)->json(['error' => 'ID type de service manquant']);
            return;
        }

        $serviceTypeModel = new ServiceType();
        $serviceType = $serviceTypeModel->findById($id);
        if (!$serviceType) {
            $this->status(404)->json(['error' => 'Type de service introuvable']);
            return;
        }

        $serviceTypeModel->delete($id);
        $this->logAudit('delete', 'service_type', $id, ['name' => $serviceType['name']]);
        $this->json(['success' => true, 'message' => 'Type de service supprimé']);
    }
}
