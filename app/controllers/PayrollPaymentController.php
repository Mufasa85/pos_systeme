<?php

namespace App\Controllers;

use App\Models\PayrollPayment;
use App\Models\PayrollPayslip;
use App\Models\PayrollPeriod;

class PayrollPaymentController extends Controller
{
    public function forPayslip($params)
    {
        if (!$this->requireAdmin()) {
            return;
        }
        $payslipId = $params['id'] ?? null;
        if (!$payslipId) {
            $this->status(400)->json(['error' => 'ID payslip manquant']);
            return;
        }

        $this->json((new PayrollPayment())->findByPayslip($payslipId));
    }

    public function create()
    {
        if (!$this->requireAdmin()) {
            return;
        }
        $data = $this->getJsonOrPost();
        if (empty($data['payslip_id']) || empty($data['amount']) || empty($data['paid_at'])) {
            $this->status(400)->json(['error' => 'payslip_id, amount et paid_at obligatoires']);
            return;
        }

        $data['shop_id'] = $this->getShopId();
        $data['created_by'] = $_SESSION['user_id'] ?? null;
        $data['status'] = 'paid';

        $payslip = (new PayrollPayslip())->findById($data['payslip_id'], $this->getShopId());
        if (!$payslip) {
            $this->status(404)->json(['error' => 'Bulletin introuvable']);
            return;
        }

        $id = (new PayrollPayment())->create($data);
        if (!$id) {
            $this->status(500)->json(['error' => 'Erreur création']);
            return;
        }

        $this->markPayslipIfPaid($payslip['id']);
        $this->closePeriodIfAllPaid($payslip['payroll_period_id']);
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

        $payment = (new PayrollPayment())->findById($id, $this->getShopId());
        if (!$payment) {
            $this->status(404)->json(['error' => 'Paiement introuvable']);
            return;
        }

        $data = $this->getJsonOrPost();
        if (!$this->isSuperAdmin() && isset($data['shop_id'])) {
            unset($data['shop_id']);
        }

        $ok = (new PayrollPayment())->update($id, $data);
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

        $payment = (new PayrollPayment())->findById($id, $this->getShopId());
        if (!$payment) {
            $this->status(404)->json(['error' => 'Paiement introuvable']);
            return;
        }

        $ok = (new PayrollPayment())->delete($id);
        $this->json(['success' => (bool)$ok]);
    }

    private function markPayslipIfPaid($payslipId)
    {
        $paid = (new PayrollPayment())->totalPaidByPayslip($payslipId);
        $payslip = (new PayrollPayslip())->findById($payslipId);
        if ($payslip && (float)$paid >= (float)$payslip['net_amount']) {
            (new PayrollPayslip())->updateStatus($payslipId, 'paid');
        }
    }

    private function closePeriodIfAllPaid($periodId)
    {
        $shopId = $this->getShopId();
        $payslips = (new PayrollPayslip())->findByPeriod($periodId, $shopId);
        if (empty($payslips)) {
            return;
        }

        $allPaid = true;
        foreach ($payslips as $p) {
            if ($p['status'] !== 'paid') {
                $allPaid = false;
                break;
            }
        }

        if ($allPaid) {
            (new PayrollPeriod())->markClosed($periodId);
        }
    }

    private function getJsonOrPost()
    {
        $raw = file_get_contents('php://input');
        return (!empty($raw)) ? (json_decode($raw, true) ?: $_POST) : $_POST;
    }
}
