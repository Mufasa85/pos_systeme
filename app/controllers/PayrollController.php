<?php

namespace App\Controllers;

use App\Models\PayrollAllowance;
use App\Models\PayrollContributionRate;
use App\Models\PayrollDeduction;
use App\Models\PayrollPaymentMethod;
use App\Models\PayrollPayslip;
use App\Models\PayrollPeriod;
use App\Models\PayrollSeniorityBand;
use App\Services\PayrollCalculator;

class PayrollController extends Controller
{
    public function periods()
    {
        if (!$this->requireAdmin()) {
            return;
        }
        $shopId = $this->getShopId();
        $super  = $this->isSuperAdmin();
        $this->json((new PayrollPeriod())->all($shopId, $super));
    }

    public function createPeriod()
    {
        if (!$this->requireAdmin()) {
            return;
        }
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
        if (!$this->requireAdmin()) {
            return;
        }
        $id = $params['id'] ?? null;
        if (!$id) {
            $this->status(400)->json(['error' => 'ID manquant']);
            return;
        }

        $data = $this->getJsonOrPost();
        if (!$this->isSuperAdmin() && isset($data['shop_id'])) {
            unset($data['shop_id']);
        }

        $ok = (new PayrollPeriod())->update($id, $data);
        $this->json(['success' => (bool)$ok]);
    }

    public function deletePeriod($params)
    {
        if (!$this->requireAdmin()) {
            return;
        }
        $id = $params['id'] ?? null;
        if (!$id) {
            $this->status(400)->json(['error' => 'ID manquant']);
            return;
        }

        $ok = (new PayrollPeriod())->delete($id);
        $this->json(['success' => (bool)$ok]);
    }

    public function calculate($params)
    {
        if (!$this->requireAdmin()) {
            return;
        }
        $id = $params['id'] ?? null;
        if (!$id) {
            $this->status(400)->json(['error' => 'ID période manquant']);
            return;
        }

        // Garde-fou : une période clôturée ou validée n'est plus recalculable.
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

    public function validatePeriod($params)
    {
        if (!$this->requireAdmin()) {
            return;
        }
        $id = $params['id'] ?? null;
        if (!$id) {
            $this->status(400)->json(['error' => 'ID période manquant']);
            return;
        }

        $period = (new PayrollPeriod())->findById($id, $this->getShopId(), $this->isSuperAdmin());
        if (!$period) {
            $this->status(404)->json(['error' => 'Période introuvable']);
            return;
        }

        // Une période déjà validée ou clôturée ne peut pas être revalidée.
        if (in_array($period['status'] ?? '', ['validated', 'closed'], true)) {
            $this->status(409)->json([
                'error' => 'Cette période est déjà ' . $period['status'] . '.',
            ]);
            return;
        }

        // La validation verrouille les bulletins de la période : ils passent
        // tous à `validated` (les bulletins déjà payés restent `paid`).
        $payslipModel = new PayrollPayslip();
        foreach ($payslipModel->findByPeriod($id, $this->getShopId()) as $p) {
            if (($p['status'] ?? '') === 'calculated') {
                $payslipModel->updateStatus($p['id'], 'validated');
            }
        }

        (new PayrollPeriod())->markValidated($id);
        $this->json(['success' => true]);
    }

    public function closePeriod($params)
    {
        if (!$this->requireAdmin()) {
            return;
        }
        $id = $params['id'] ?? null;
        if (!$id) {
            $this->status(400)->json(['error' => 'ID période manquant']);
            return;
        }

        $period = (new PayrollPeriod())->findById($id, $this->getShopId(), $this->isSuperAdmin());
        if (!$period) {
            $this->status(404)->json(['error' => 'Période introuvable']);
            return;
        }

        if (($period['status'] ?? '') === 'closed') {
            $this->status(409)->json(['error' => 'Cette période est déjà clôturée.']);
            return;
        }

        (new PayrollPeriod())->markClosed($id);
        $this->json(['success' => true]);
    }

    /**
     * Paramètres de calcul de la paie (table `payroll_settings`).
     *
     * GET  /api/payroll/settings-params → tous les paramètres résolus
     * POST /api/payroll/settings-params → met à jour les paramètres
     */
    public function settingsParams()
    {
        if (!$this->requireAdmin()) {
            return;
        }

        $shopId = $this->getShopId();
        $settings = new \App\Models\PayrollSettings();

        $this->json([
            'success'  => true,
            'mode'     => $settings->get('calculation_mode', $shopId),
            'values'   => $settings->resolved($shopId),
            'rows'     => $settings->all($shopId),
        ]);
    }

    public function updateSettingsParams()
    {
        if (!$this->requireAdmin()) {
            return;
        }

        $data = $this->getJsonOrPost();
        $shopId = $this->getShopId();

        if (empty($data['values']) || !is_array($data['values'])) {
            $this->status(400)->json(['error' => 'values obligatoire (objet clé → valeur)']);
            return;
        }

        $settings = new \App\Models\PayrollSettings();
        $known = array_keys(\App\Models\PayrollSettings::DEFAULTS);

        foreach ($data['values'] as $key => $value) {
            // Seules les clés déclarées sont acceptées : on n'écrit jamais
            // une clé arbitraire dans la table.
            if (!in_array($key, $known, true)) {
                continue;
            }
            $settings->save((string)$key, (string)$value, $shopId);
        }

        $this->json([
            'success' => true,
            'values'  => $settings->resolved($shopId),
        ]);
    }

    public function parameters()
    {
        if (!$this->requireAdmin()) {
            return;
        }
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
        if (!$this->requireAdmin()) {
            return;
        }
        $this->json((new PayrollPaymentMethod())->all($this->getShopId()));
    }

    public function createPaymentMethod()
    {
        if (!$this->requireAdmin()) {
            return;
        }
        $data = $this->getJsonOrPost();
        $data['shop_id'] = $this->getShopId();
        $id = (new PayrollPaymentMethod())->create($data);
        $this->status(201)->json(['success' => (bool)$id, 'id' => $id]);
    }

    public function updatePaymentMethod($params)
    {
        if (!$this->requireAdmin()) {
            return;
        }
        $id = $params['id'] ?? null;
        if (!$id) {
            $this->status(400)->json(['error' => 'ID manquant']);
            return;
        }

        $data = $this->getJsonOrPost();
        if (!$this->isSuperAdmin() && isset($data['shop_id'])) {
            unset($data['shop_id']);
        }

        $ok = (new PayrollPaymentMethod())->update($id, $data);
        $this->json(['success' => (bool)$ok]);
    }

    public function deletePaymentMethod($params)
    {
        if (!$this->requireAdmin()) {
            return;
        }
        $id = $params['id'] ?? null;
        if (!$id) {
            $this->status(400)->json(['error' => 'ID manquant']);
            return;
        }

        $ok = (new PayrollPaymentMethod())->delete($id);
        $this->json(['success' => (bool)$ok]);
    }

    public function allowances()
    {
        if (!$this->requireAdmin()) {
            return;
        }
        $this->json((new PayrollAllowance())->all($this->getShopId()));
    }

    public function createAllowance()
    {
        if (!$this->requireAdmin()) {
            return;
        }
        $data = $this->getJsonOrPost();
        $data['shop_id'] = $this->getShopId();
        $id = (new PayrollAllowance())->create($data);
        $this->status(201)->json(['success' => (bool)$id, 'id' => $id]);
    }

    public function updateAllowance($params)
    {
        if (!$this->requireAdmin()) {
            return;
        }
        $id = $params['id'] ?? null;
        if (!$id) {
            $this->status(400)->json(['error' => 'ID manquant']);
            return;
        }

        $data = $this->getJsonOrPost();
        if (!$this->isSuperAdmin() && isset($data['shop_id'])) {
            unset($data['shop_id']);
        }

        $ok = (new PayrollAllowance())->update($id, $data);
        $this->json(['success' => (bool)$ok]);
    }

    public function deleteAllowance($params)
    {
        if (!$this->requireAdmin()) {
            return;
        }
        $id = $params['id'] ?? null;
        if (!$id) {
            $this->status(400)->json(['error' => 'ID manquant']);
            return;
        }

        $ok = (new PayrollAllowance())->delete($id);
        $this->json(['success' => (bool)$ok]);
    }

    public function deductions()
    {
        if (!$this->requireAdmin()) {
            return;
        }
        $this->json((new PayrollDeduction())->all($this->getShopId()));
    }

    public function createDeduction()
    {
        if (!$this->requireAdmin()) {
            return;
        }
        $data = $this->getJsonOrPost();
        $data['shop_id'] = $this->getShopId();
        $id = (new PayrollDeduction())->create($data);
        $this->status(201)->json(['success' => (bool)$id, 'id' => $id]);
    }

    public function updateDeduction($params)
    {
        if (!$this->requireAdmin()) {
            return;
        }
        $id = $params['id'] ?? null;
        if (!$id) {
            $this->status(400)->json(['error' => 'ID manquant']);
            return;
        }

        $data = $this->getJsonOrPost();
        if (!$this->isSuperAdmin() && isset($data['shop_id'])) {
            unset($data['shop_id']);
        }

        $ok = (new PayrollDeduction())->update($id, $data);
        $this->json(['success' => (bool)$ok]);
    }

    public function deleteDeduction($params)
    {
        if (!$this->requireAdmin()) {
            return;
        }
        $id = $params['id'] ?? null;
        if (!$id) {
            $this->status(400)->json(['error' => 'ID manquant']);
            return;
        }

        $ok = (new PayrollDeduction())->delete($id);
        $this->json(['success' => (bool)$ok]);
    }

    public function contributions()
    {
        if (!$this->requireAdmin()) {
            return;
        }
        $this->json((new PayrollContributionRate())->all($this->getShopId()));
    }

    public function createContribution()
    {
        if (!$this->requireAdmin()) {
            return;
        }
        $data = $this->getJsonOrPost();
        $data['shop_id'] = $this->getShopId();
        $id = (new PayrollContributionRate())->create($data);
        $this->status(201)->json(['success' => (bool)$id, 'id' => $id]);
    }

    public function updateContribution($params)
    {
        if (!$this->requireAdmin()) {
            return;
        }
        $id = $params['id'] ?? null;
        if (!$id) {
            $this->status(400)->json(['error' => 'ID manquant']);
            return;
        }

        $data = $this->getJsonOrPost();
        if (!$this->isSuperAdmin() && isset($data['shop_id'])) {
            unset($data['shop_id']);
        }

        $ok = (new PayrollContributionRate())->update($id, $data);
        $this->json(['success' => (bool)$ok]);
    }

    public function deleteContribution($params)
    {
        if (!$this->requireAdmin()) {
            return;
        }
        $id = $params['id'] ?? null;
        if (!$id) {
            $this->status(400)->json(['error' => 'ID manquant']);
            return;
        }

        $ok = (new PayrollContributionRate())->delete($id);
        $this->json(['success' => (bool)$ok]);
    }

    public function seniority()
    {
        if (!$this->requireAdmin()) {
            return;
        }
        $this->json((new PayrollSeniorityBand())->all($this->getShopId()));
    }

    public function createSeniority()
    {
        if (!$this->requireAdmin()) {
            return;
        }
        $data = $this->getJsonOrPost();
        $data['shop_id'] = $this->getShopId();
        $id = (new PayrollSeniorityBand())->create($data);
        $this->status(201)->json(['success' => (bool)$id, 'id' => $id]);
    }

    public function updateSeniority($params)
    {
        if (!$this->requireAdmin()) {
            return;
        }
        $id = $params['id'] ?? null;
        if (!$id) {
            $this->status(400)->json(['error' => 'ID manquant']);
            return;
        }

        $data = $this->getJsonOrPost();
        if (!$this->isSuperAdmin() && isset($data['shop_id'])) {
            unset($data['shop_id']);
        }

        $ok = (new PayrollSeniorityBand())->update($id, $data);
        $this->json(['success' => (bool)$ok]);
    }

    public function deleteSeniority($params)
    {
        if (!$this->requireAdmin()) {
            return;
        }
        $id = $params['id'] ?? null;
        if (!$id) {
            $this->status(400)->json(['error' => 'ID manquant']);
            return;
        }

        $ok = (new PayrollSeniorityBand())->delete($id);
        $this->json(['success' => (bool)$ok]);
    }

    private function getJsonOrPost()
    {
        $raw = file_get_contents('php://input');
        return (!empty($raw)) ? (json_decode($raw, true) ?: $_POST) : $_POST;
    }
}
