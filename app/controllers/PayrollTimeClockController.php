<?php

namespace App\Controllers;

use App\controllers\Controller;
use App\Models\PayrollTimeClock;
use App\Models\PayrollPeriod;
use App\Services\PayrollImportService;

class PayrollTimeClockController extends Controller
{
    public function forPeriod($params)
    {
        if (!$this->requireAdmin()) return;
        $periodId = $params['id'] ?? null;
        if (!$periodId) { $this->status(400)->json(['error' => 'ID période manquant']); return; }

        $this->json((new PayrollTimeClock())->findByPeriod($periodId, $this->getShopId()));
    }

    public function create()
    {
        if (!$this->requireAdmin()) return;
        $data = $this->getJsonOrPost();
        $data['shop_id'] = $this->getShopId();

        $id = (new PayrollTimeClock())->create($data);
        $this->status(201)->json(['success' => (bool)$id, 'id' => $id]);
    }

    public function update($params)
    {
        if (!$this->requireAdmin()) return;
        $id = $params['id'] ?? null;
        if (!$id) { $this->status(400)->json(['error' => 'ID manquant']); return; }

        $ok = (new PayrollTimeClock())->update($id, $this->getJsonOrPost());
        $this->json(['success' => (bool)$ok]);
    }

    public function delete($params)
    {
        if (!$this->requireAdmin()) return;
        $id = $params['id'] ?? null;
        if (!$id) { $this->status(400)->json(['error' => 'ID manquant']); return; }

        $ok = (new PayrollTimeClock())->delete($id);
        $this->json(['success' => (bool)$ok]);
    }

    public function import()
    {
        if (!$this->requireAdmin()) return;
        if (empty($_FILES['file']) || $_FILES['file']['error'] !== UPLOAD_ERR_OK) {
            $this->status(400)->json(['error' => 'Fichier requis']);
            return;
        }

        $tmp = $_FILES['file']['tmp_name'];
        try {
            $service = new PayrollImportService();
            $result = $service->importCsv($this->getShopId(), $tmp, $_SESSION['user_id'] ?? null);
            $this->json(['success' => true, 'import' => $result]);
        } catch (\Throwable $e) {
            $this->status(500)->json(['error' => $e->getMessage()]);
        }
    }

    private function getJsonOrPost()
    {
        $raw = file_get_contents('php://input');
        return (!empty($raw)) ? (json_decode($raw, true) ?: $_POST) : $_POST;
    }
}
