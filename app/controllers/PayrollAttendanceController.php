<?php

namespace App\Controllers;

use App\controllers\Controller;
use App\Models\PayrollAttendance;
use App\Models\PayrollAbsence;
use App\Models\PayrollOvertime;
use App\Models\PayrollPeriod;

class PayrollAttendanceController extends Controller
{
    public function forPeriod($params)
    {
        if (!$this->requireAdmin()) return;
        $periodId = $params['id'] ?? null;
        if (!$periodId) { $this->status(400)->json(['error' => 'ID période manquant']); return; }

        $shopId = $this->getShopId();
        $this->json([
            'attendance' => (new PayrollAttendance())->findByPeriod($periodId, $shopId),
            'absences'   => (new PayrollAbsence())->findByPeriod($periodId, $shopId),
            'overtimes'  => (new PayrollOvertime())->findByPeriod($periodId, $shopId),
        ]);
    }

    public function save()
    {
        if (!$this->requireAdmin()) return;
        $data = $this->getJsonOrPost();
        $data['shop_id'] = $this->getShopId();

        $attendance = new PayrollAttendance();
        $ok = $attendance->createOrUpdate($data);
        $this->json(['success' => (bool)$ok, 'id' => $ok]);
    }

    public function bulkSave()
    {
        if (!$this->requireAdmin()) return;
        $data = $this->getJsonOrPost();
        $rows = $data['attendance'] ?? [];
        $shopId = $this->getShopId();
        foreach ($rows as &$row) $row['shop_id'] = $shopId;

        $count = (new PayrollAttendance())->bulkSave($rows);
        $this->json(['success' => true, 'saved' => $count]);
    }

    public function saveAbsence()
    {
        if (!$this->requireAdmin()) return;
        $data = $this->getJsonOrPost();
        $data['shop_id'] = $this->getShopId();

        $id = (new PayrollAbsence())->create($data);
        $this->status(201)->json(['success' => (bool)$id, 'id' => $id]);
    }

    public function updateAbsence($params)
    {
        if (!$this->requireAdmin()) return;
        $id = $params['id'] ?? null;
        if (!$id) { $this->status(400)->json(['error' => 'ID manquant']); return; }

        $ok = (new PayrollAbsence())->update($id, $this->getJsonOrPost());
        $this->json(['success' => (bool)$ok]);
    }

    public function deleteAbsence($params)
    {
        if (!$this->requireAdmin()) return;
        $id = $params['id'] ?? null;
        if (!$id) { $this->status(400)->json(['error' => 'ID manquant']); return; }

        $ok = (new PayrollAbsence())->delete($id);
        $this->json(['success' => (bool)$ok]);
    }

    public function saveOvertime()
    {
        if (!$this->requireAdmin()) return;
        $data = $this->getJsonOrPost();
        $data['shop_id'] = $this->getShopId();

        $id = (new PayrollOvertime())->create($data);
        $this->status(201)->json(['success' => (bool)$id, 'id' => $id]);
    }

    public function updateOvertime($params)
    {
        if (!$this->requireAdmin()) return;
        $id = $params['id'] ?? null;
        if (!$id) { $this->status(400)->json(['error' => 'ID manquant']); return; }

        $ok = (new PayrollOvertime())->update($id, $this->getJsonOrPost());
        $this->json(['success' => (bool)$ok]);
    }

    public function deleteOvertime($params)
    {
        if (!$this->requireAdmin()) return;
        $id = $params['id'] ?? null;
        if (!$id) { $this->status(400)->json(['error' => 'ID manquant']); return; }

        $ok = (new PayrollOvertime())->delete($id);
        $this->json(['success' => (bool)$ok]);
    }

    private function getJsonOrPost()
    {
        $raw = file_get_contents('php://input');
        return (!empty($raw)) ? (json_decode($raw, true) ?: $_POST) : $_POST;
    }
}
