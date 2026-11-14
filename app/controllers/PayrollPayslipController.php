<?php

namespace App\Controllers;

use App\Models\PayrollContract;
use App\Models\PayrollEmployee;
use App\Models\PayrollPayslip;
use App\Models\PayrollPayslipLine;
use App\Models\PayrollPeriod;
use App\Services\PayrollCalculator;
use App\Services\PayrollPdfService;

class PayrollPayslipController extends Controller
{
    public function forPeriod($params)
    {
        if (!$this->requireAdmin()) {
            return;
        }
        $periodId = $params['id'] ?? null;
        if (!$periodId) {
            $this->status(400)->json(['error' => 'ID période manquant']);
            return;
        }

        $shopId = $this->getShopId();
        $this->json((new PayrollPayslip())->findByPeriod($periodId, $shopId));
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

        $payslip = (new PayrollPayslip())->findById($id, $this->getShopId());
        if (!$payslip) {
            $this->status(404)->json(['error' => 'Bulletin introuvable']);
            return;
        }

        $payslip['lines'] = (new PayrollPayslipLine())->forPayslip($id);
        $this->json($payslip);
    }

    public function calculateOne()
    {
        if (!$this->requireAdmin()) {
            return;
        }
        $data = $this->getJsonOrPost();
        if (empty($data['employee_id']) || empty($data['period_id'])) {
            $this->status(400)->json(['error' => 'employee_id et period_id obligatoires']);
            return;
        }

        // Garde-fou : un bulletin validé / payé, ou une période clôturée,
        // ne peut pas être recalculé (document comptable validé).
        $payslipModel = new PayrollPayslip();
        $existing = $payslipModel->findByEmployeeAndPeriod($data['employee_id'], $data['period_id']);
        $period = (new PayrollPeriod())->findById($data['period_id'], $this->getShopId(), $this->isSuperAdmin());

        $guard = \App\Services\PayrollWorkflow::canRecalculatePayslip($existing, $period);
        if (!$guard['ok']) {
            $this->status(409)->json(['error' => $guard['reason']]);
            return;
        }

        try {
            $calc = new PayrollCalculator();
            $id = $calc->savePayslip($data['employee_id'], $data['period_id'], $this->getShopId());
            $this->json(['success' => true, 'payslip_id' => $id]);
        } catch (\Throwable $e) {
            $this->status(500)->json(['error' => $e->getMessage()]);
        }
    }

    public function calculatePeriod($params)
    {
        if (!$this->requireAdmin()) {
            return;
        }
        $id = $params['id'] ?? null;
        if (!$id) {
            $this->status(400)->json(['error' => 'ID période manquant']);
            return;
        }

        // Garde-fou : une période validée ou clôturée n'est plus recalculable.
        $period = (new PayrollPeriod())->findById($id, $this->getShopId(), $this->isSuperAdmin());
        if (!$period) {
            $this->status(404)->json(['error' => 'Période introuvable']);
            return;
        }

        $guard = \App\Services\PayrollWorkflow::canCalculatePeriod($period);
        if (!$guard['ok']) {
            $this->status(409)->json(['error' => $guard['reason']]);
            return;
        }

        try {
            $calc = new PayrollCalculator();
            $ids = $calc->calculateForPeriod($id, $this->getShopId());
            $this->json(['success' => true, 'payslip_ids' => $ids, 'count' => count($ids)]);
        } catch (\Throwable $e) {
            $this->status(500)->json(['error' => $e->getMessage()]);
        }
    }

    public function validate($params)
    {
        if (!$this->requireAdmin()) {
            return;
        }
        $id = $params['id'] ?? null;
        if (!$id) {
            $this->status(400)->json(['error' => 'ID manquant']);
            return;
        }

        $payslip = (new PayrollPayslip())->findById($id, $this->getShopId());
        if (!$payslip) {
            $this->status(404)->json(['error' => 'Bulletin introuvable']);
            return;
        }

        // Un bulletin déjà payé ne peut pas être « revalidé ».
        if (($payslip['status'] ?? '') === 'paid') {
            $this->status(409)->json(['error' => 'Ce bulletin est déjà payé.']);
            return;
        }

        // Un bulletin déjà validé n'est pas revalidé (idempotent mais sans
        // effet) : on le signale pour éviter une confusion côté UI.
        if (($payslip['status'] ?? '') === 'validated') {
            $this->json(['success' => true, 'message' => 'Ce bulletin était déjà validé.']);
            return;
        }

        (new PayrollPayslip())->updateStatus($id, 'validated');
        $this->json(['success' => true]);
    }

    public function generatePdf($params)
    {
        if (!$this->requireAdmin()) {
            return;
        }
        $id = $params['id'] ?? null;
        if (!$id) {
            $this->status(400)->json(['error' => 'ID manquant']);
            return;
        }

        try {
            $path = (new PayrollPdfService())->generate($id, $this->getShopId());
            $this->json(['success' => true, 'pdf_path' => $path]);
        } catch (\Throwable $e) {
            $this->status(500)->json(['error' => $e->getMessage()]);
        }
    }

    public function streamPdf($params)
    {
        if (!$this->requireAdmin()) {
            return;
        }
        $id = $params['id'] ?? null;
        if (!$id) {
            $this->status(400)->json(['error' => 'ID manquant']);
            return;
        }

        try {
            (new PayrollPdfService())->stream($id, $this->getShopId());
        } catch (\Throwable $e) {
            $this->status(500)->json(['error' => $e->getMessage()]);
        }
    }

    public function canCalculateEmployee($params)
    {
        if (!$this->requireAdmin()) {
            return;
        }
        $id = $params['id'] ?? null;
        $employee = (new PayrollEmployee())->findById($id, $this->getShopId(), $this->isSuperAdmin());
        $contract = $employee ? (new PayrollContract())->findActiveByEmployee($employee['id']) : null;
        $this->json(['ok' => (bool)$contract, 'employee' => $employee, 'contract' => $contract]);
    }

    public function myPayslips()
    {
        if (!$this->requireAuth()) {
            return;
        }
        $userId = $_SESSION['user_id'] ?? null;
        $employee = (new PayrollEmployee())->findByUserId($userId);
        if (!$employee) {
            $this->json([]);
            return;
        }

        $this->json((new PayrollPayslip())->findByEmployee($employee['id']));
    }

    private function getJsonOrPost()
    {
        $raw = file_get_contents('php://input');
        return (!empty($raw)) ? (json_decode($raw, true) ?: $_POST) : $_POST;
    }
}
