<?php

namespace App\Controllers;

use App\Models\PayrollEmployee;

class PayrollEmployeeController extends Controller
{
    public function index()
    {
        if (!$this->requireAdmin()) {
            return;
        }

        $employee = new PayrollEmployee();
        $shopId = $this->getShopId();
        $superAdmin = $this->isSuperAdmin();

        $this->json($employee->all($shopId, $superAdmin));
    }

    public function show($params)
    {
        if (!$this->requireAdmin()) {
            return;
        }

        $id = $params['id'] ?? null;
        if (!$id) {
            $this->status(400)->json(['error' => 'ID manquant']);
            return;
        }

        $employee = new PayrollEmployee();
        $shopId = $this->getShopId();
        $superAdmin = $this->isSuperAdmin();
        $data = $employee->findById($id, $shopId, $superAdmin);

        if (!$data) {
            $this->status(404)->json(['error' => 'Employé non trouvé']);
            return;
        }

        $this->json($data);
    }

    public function create()
    {
        if (!$this->requireAdmin()) {
            return;
        }

        $raw = file_get_contents('php://input');
        $data = (!empty($raw)) ? json_decode($raw, true) : $_POST;
        if (!is_array($data)) {
            $data = [];
        }

        $data['shop_id'] = $this->isSuperAdmin() ? ($data['shop_id'] ?? $this->getShopId()) : $this->getShopId();

        if (empty($data['user_id']) || empty($data['matricule']) || empty($data['hire_date'])) {
            $this->status(400)->json(['error' => 'user_id, matricule et hire_date sont obligatoires']);
            return;
        }

        $employee = new PayrollEmployee();

        $existing = $employee->findByUserId($data['user_id']);
        if ($existing) {
            $this->status(409)->json(['error' => 'Ce vendeur a déjà une fiche employé']);
            return;
        }

        $id = $employee->create($data);
        if (!$id) {
            $this->status(500)->json(['error' => 'Erreur lors de la création']);
            return;
        }

        $this->status(201)->json(['success' => true, 'id' => $id]);
    }

    public function update($params)
    {
        if (!$this->requireAdmin()) {
            return;
        }

        $id = $params['id'] ?? null;
        if (!$id) {
            $this->status(400)->json(['error' => 'ID manquant']);
            return;
        }

        $employee = new PayrollEmployee();
        $shopId = $this->getShopId();
        $superAdmin = $this->isSuperAdmin();

        if (!$employee->findById($id, $shopId, $superAdmin)) {
            $this->status(404)->json(['error' => 'Employé non trouvé']);
            return;
        }

        $raw = file_get_contents('php://input');
        $data = (!empty($raw)) ? json_decode($raw, true) : $_POST;
        if (!is_array($data)) {
            $data = [];
        }

        if (!$superAdmin && isset($data['shop_id'])) {
            unset($data['shop_id']);
        }

        $ok = $employee->update($id, $data);
        $this->json(['success' => (bool)$ok]);
    }

    public function delete($params)
    {
        if (!$this->requireAdmin()) {
            return;
        }

        $id = $params['id'] ?? null;
        if (!$id) {
            $this->status(400)->json(['error' => 'ID manquant']);
            return;
        }

        $employee = new PayrollEmployee();
        $shopId = $this->getShopId();
        $superAdmin = $this->isSuperAdmin();

        if (!$employee->findById($id, $shopId, $superAdmin)) {
            $this->status(404)->json(['error' => 'Employé non trouvé']);
            return;
        }

        $ok = $employee->delete($id);
        $this->json(['success' => (bool)$ok]);
    }

    public function vendors()
    {
        if (!$this->requireAdmin()) {
            return;
        }

        $shopId = $this->getShopId();
        if (!$shopId) {
            $this->status(400)->json(['error' => 'Aucune boutique assignée']);
            return;
        }

        $employee = new PayrollEmployee();
        $this->json($employee->vendorsWithoutEmployee($shopId));
    }
}
