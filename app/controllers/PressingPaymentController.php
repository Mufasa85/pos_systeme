<?php

namespace App\Controllers;

use App\Models\PressingPayment;
use App\Models\PressingDepot;
use App\controllers\Controller;

class PressingPaymentController extends Controller
{
    public function index($params)
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

        $model = new PressingPayment();
        $this->json([
            'success'    => true,
            'payments'   => $model->getByDepot($depotId),
            'paid'       => $depotModel->getPaidAmount($depotId),
            'solde'      => $depotModel->getSolde($depotId),
        ]);
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

        $input = json_decode(file_get_contents('php://input'), true) ?? [];
        $montant = (float)($input['montant'] ?? 0);
        $mode = $this->sanitaze($input['mode_paiement'] ?? 'cash');
        $reference = isset($input['reference']) ? $this->sanitaze($input['reference']) : null;

        $solde = $depotModel->getSolde($depotId);
        if ($montant <= 0 || $montant > $solde) {
            $this->status(400)->json(['error' => 'Montant invalide ou supérieur au solde restant']);
            return;
        }

        $model = new PressingPayment();
        $id = $model->create([
            'depot_id'       => $depotId,
            'montant'        => $montant,
            'mode_paiement'  => $mode,
            'reference'      => $reference,
            'created_by'     => $_SESSION['user_id'],
        ]);

        $this->logAudit('create', 'pressing_payment', $id, ['depot_id' => $depotId, 'montant' => $montant, 'mode' => $mode]);
        $this->json([
            'success'   => true,
            'id'        => $id,
            'paid'      => $depotModel->getPaidAmount($depotId),
            'solde'     => $depotModel->getSolde($depotId),
        ]);
    }

    public function delete($params)
    {
        if (!$this->requireAdmin()) return;

        $id = is_array($params) ? ($params['id'] ?? null) : $params;
        $id = (int)$id;

        $model = new PressingPayment();
        $payment = $model->findById($id);
        if (!$payment) {
            $this->status(404)->json(['error' => 'Paiement introuvable']);
            return;
        }

        $depotModel = new PressingDepot();
        $shopId = $this->isSuperAdmin() ? null : $this->getShopId();
        $depot = $depotModel->findById($payment['depot_id'], $shopId);
        if (!$depot) {
            $this->status(404)->json(['error' => 'Dépôt introuvable']);
            return;
        }

        $model->delete($id);
        $this->logAudit('delete', 'pressing_payment', $id, ['depot_id' => $payment['depot_id']]);
        $this->json([
            'success' => true,
            'paid'    => $depotModel->getPaidAmount($payment['depot_id']),
            'solde'   => $depotModel->getSolde($payment['depot_id']),
        ]);
    }
}
