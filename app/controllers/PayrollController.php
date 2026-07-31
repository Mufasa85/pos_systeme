<?php

namespace App\Controllers;

use App\controllers\Controller;
use App\Models\PayrollPeriod;
use App\Models\PayrollAllowance;
use App\Models\PayrollDeduction;
use App\Models\PayrollContributionRate;
use App\Models\PayrollSeniorityBand;
use App\Models\PayrollPaymentMethod;
use App\Services\PayrollCalculator;

class PayrollController extends Controller
{
    public function periods()
    {
        if (!$this->requireAdmin()) return;
        $shopId = $this->getShopId();
        $super  = $this->isSuperAdmin();
        $this->json((new PayrollPeriod())->all($shopId, $super));
    }

    public function createPeriod()
    {
        if (!$this->requireAdmin()) return;
        $data = $this->getJsonOrPost();
        $data['shop_id'] = $this->isSuperAdmin() ? ($data['shop_id'] ?? $this->getShopId()) : $this->getShopId();

        if (empty($data['month']) || empty($data['year'])) {
            $this->status(400)->json(['error' => 'month et year obligatoires']);
            return;
        }

        $existing = (new PayrollPeriod())->findByShopMonthYear($data['shop_id'], $data['month'], $data['year']);
        if ($existing) {
            $this->status(409)->json(['error' => 'Cette période existe déjà']);
            return;
        }

        $id = (new PayrollPeriod())->create($data);
        $this->status(201)->json(['success' => true, 'id' => $id]);
    }

    public function updatePeriod($params)
    {
        if (!$this->requireAdmin()) return;
        $id = $params['id'] ?? null;
        if (!$id) { $this->status(400)->json(['error' => 'ID manquant']); return; }

        $data = $this->getJsonOrPost();
        if (!$this->isSuperAdmin() && isset($data['shop_id'])) unset($data['shop_id']);

        $ok = (new PayrollPeriod())->update($id, $data);
        $this->json(['success' => (bool)$ok]);
    }

    public function deletePeriod($params)
    {
        if (!$this->requireAdmin()) return;
        $id = $params['id'] ?? null;
        if (!$id) { $this->status(400)->json(['error' => 'ID manquant']); return; }

        $ok = (new PayrollPeriod())->delete($id);
        $this->json(['success' => (bool)$ok]);
    }

    public function calculate($params)
    {
        if (!$this->requireAdmin()) return;
        $id = $params['id'] ?? null;
        if (!$id) { $this->status(400)->json(['error' => 'ID période manquant']); return; }

        try {
            $calc = new PayrollCalculator();
            $ids = $calc->calculateForPeriod($id, $this->getShopId());
            $this->json(['success' => true, 'payslip_ids' => $ids, 'count' => count($ids)]);
        } catch (\Throwable $e) {
            $this->status(500)->json(['error' => $e->getMessage()]);
        }
    }

    public function validatePeriod($params)
    {
        if (!$this->requireAdmin()) return;
        $id = $params['id'] ?? null;
        if (!$id) { $this->status(400)->json(['error' => 'ID période manquant']); return; }

        (new PayrollPeriod())->markValidated($id);
        $this->json(['success' => true]);
    }

    public function closePeriod($params)
    {
        if (!$this->requireAdmin()) return;
        $id = $params['id'] ?? null;
        if (!$id) { $this->status(400)->json(['error' => 'ID période manquant']); return; }

        (new PayrollPeriod())->markClosed($id);
        $this->json(['success' => true]);
    }

    public function parameters()
    {
        if (!$this->requireAdmin()) return;
        $shopId = $this->getShopId();
        $this->json([
            'allowances'      => (new PayrollAllowance())->all($shopId),
            'deductions'      => (new PayrollDeduction())->all($shopId),
            'contributions'   => (new PayrollContributionRate())->all($shopId),
            'seniority'       => (new PayrollSeniorityBand())->all($shopId),
            'payment_methods' => (new PayrollPaymentMethod())->all($shopId),
        ]);
    }

    public function paymentMethods()
    {
        if (!$this->requireAdmin()) return;
        $this->json((new PayrollPaymentMethod())->all($this->getShopId()));
    }

    public function createPaymentMethod()
    {
        if (!$this->requireAdmin()) return;
        $data = $this->getJsonOrPost();
        $data['shop_id'] = $this->getShopId();
        $id = (new PayrollPaymentMethod())->create($data);
        $this->status(201)->json(['success' => (bool)$id, 'id' => $id]);
    }

    public function updatePaymentMethod($params)
    {
        if (!$this->requireAdmin()) return;
        $id = $params['id'] ?? null;
        if (!$id) { $this->status(400)->json(['error' => 'ID manquant']); return; }

        $data = $this->getJsonOrPost();
        if (!$this->isSuperAdmin() && isset($data['shop_id'])) unset($data['shop_id']);

        $ok = (new PayrollPaymentMethod())->update($id, $data);
        $this->json(['success' => (bool)$ok]);
    }

    public function deletePaymentMethod($params)
    {
        if (!$this->requireAdmin()) return;
        $id = $params['id'] ?? null;
        if (!$id) { $this->status(400)->json(['error' => 'ID manquant']); return; }

        $ok = (new PayrollPaymentMethod())->delete($id);
        $this->json(['success' => (bool)$ok]);
    }

    public function allowances()
    {
        if (!$this->requireAdmin()) return;
        $this->json((new PayrollAllowance())->all($this->getShopId()));
    }

    public function createAllowance()
    {
        if (!$this->requireAdmin()) return;
        $data = $this->getJsonOrPost();
        $data['shop_id'] = $this->getShopId();
        $id = (new PayrollAllowance())->create($data);
        $this->status(201)->json(['success' => (bool)$id, 'id' => $id]);
    }

    public function updateAllowance($params)
    {
        if (!$this->requireAdmin()) return;
        $id = $params['id'] ?? null;
        if (!$id) { $this->status(400)->json(['error' => 'ID manquant']); return; }

        $data = $this->getJsonOrPost();
        if (!$this->isSuperAdmin() && isset($data['shop_id'])) unset($data['shop_id']);

        $ok = (new PayrollAllowance())->update($id, $data);
        $this->json(['success' => (bool)$ok]);
    }

    public function deleteAllowance($params)
    {
        if (!$this->requireAdmin()) return;
        $id = $params['id'] ?? null;
        if (!$id) { $this->status(400)->json(['error' => 'ID manquant']); return; }

        $ok = (new PayrollAllowance())->delete($id);
        $this->json(['success' => (bool)$ok]);
    }

    public function deductions()
    {
        if (!$this->requireAdmin()) return;
        $this->json((new PayrollDeduction())->all($this->getShopId()));
    }

    public function createDeduction()
    {
        if (!$this->requireAdmin()) return;
        $data = $this->getJsonOrPost();
        $data['shop_id'] = $this->getShopId();
        $id = (new PayrollDeduction())->create($data);
        $this->status(201)->json(['success' => (bool)$id, 'id' => $id]);
    }

    public function updateDeduction($params)
    {
        if (!$this->requireAdmin()) return;
        $id = $params['id'] ?? null;
        if (!$id) { $this->status(400)->json(['error' => 'ID manquant']); return; }

        $data = $this->getJsonOrPost();
        if (!$this->isSuperAdmin() && isset($data['shop_id'])) unset($data['shop_id']);

        $ok = (new PayrollDeduction())->update($id, $data);
        $this->json(['success' => (bool)$ok]);
    }

    public function deleteDeduction($params)
    {
        if (!$this->requireAdmin()) return;
        $id = $params['id'] ?? null;
        if (!$id) { $this->status(400)->json(['error' => 'ID manquant']); return; }

        $ok = (new PayrollDeduction())->delete($id);
        $this->json(['success' => (bool)$ok]);
    }

    public function contributions()
    {
        if (!$this->requireAdmin()) return;
        $this->json((new PayrollContributionRate())->all($this->getShopId()));
    }

    public function createContribution()
    {
        if (!$this->requireAdmin()) return;
        $data = $this->getJsonOrPost();
        $data['shop_id'] = $this->getShopId();
        $id = (new PayrollContributionRate())->create($data);
        $this->status(201)->json(['success' => (bool)$id, 'id' => $id]);
    }

    public function updateContribution($params)
    {
        if (!$this->requireAdmin()) return;
        $id = $params['id'] ?? null;
        if (!$id) { $this->status(400)->json(['error' => 'ID manquant']); return; }

        $data = $this->getJsonOrPost();
        if (!$this->isSuperAdmin() && isset($data['shop_id'])) unset($data['shop_id']);

        $ok = (new PayrollContributionRate())->update($id, $data);
        $this->json(['success' => (bool)$ok]);
    }

    public function deleteContribution($params)
    {
        if (!$this->requireAdmin()) return;
        $id = $params['id'] ?? null;
        if (!$id) { $this->status(400)->json(['error' => 'ID manquant']); return; }

        $ok = (new PayrollContributionRate())->delete($id);
        $this->json(['success' => (bool)$ok]);
    }

    public function seniority()
    {
        if (!$this->requireAdmin()) return;
        $this->json((new PayrollSeniorityBand())->all($this->getShopId()));
    }

    public function createSeniority()
    {
        if (!$this->requireAdmin()) return;
        $data = $this->getJsonOrPost();
        $data['shop_id'] = $this->getShopId();
        $id = (new PayrollSeniorityBand())->create($data);
        $this->status(201)->json(['success' => (bool)$id, 'id' => $id]);
    }

    public function updateSeniority($params)
    {
        if (!$this->requireAdmin()) return;
        $id = $params['id'] ?? null;
        if (!$id) { $this->status(400)->json(['error' => 'ID manquant']); return; }

        $data = $this->getJsonOrPost();
        if (!$this->isSuperAdmin() && isset($data['shop_id'])) unset($data['shop_id']);

        $ok = (new PayrollSeniorityBand())->update($id, $data);
        $this->json(['success' => (bool)$ok]);
    }

    public function deleteSeniority($params)
    {
        if (!$this->requireAdmin()) return;
        $id = $params['id'] ?? null;
        if (!$id) { $this->status(400)->json(['error' => 'ID manquant']); return; }

        $ok = (new PayrollSeniorityBand())->delete($id);
        $this->json(['success' => (bool)$ok]);
    }

    private function getJsonOrPost()
    {
        $raw = file_get_contents('php://input');
        return (!empty($raw)) ? (json_decode($raw, true) ?: $_POST) : $_POST;
    }
}
