<?php

namespace App\Controllers;

use App\Models\PayrollContract;
use App\Models\PayrollEmployee;

/**
 * CRUD des contrats de paie.
 *
 * ⚠️ Historique : jusqu'en novembre 2026, `payroll_contracts` n'avait AUCUN
 * point d'entrée (ni route, ni vue, ni contrôleur). Le salaire de base d'un
 * agent n'était donc saisissable nulle part, et
 * `PayrollCalculator::calculateForPeriod()` ignorait silencieusement tout
 * employé sans contrat actif.
 *
 * Ce contrôleur comble ce manque et répond aux deux questions du module :
 *   - « QUI est payé ? »  → `is_paid` (+ `paid_from` / `paid_until`)
 *   - « COMBIEN ? »      → `base_salary` (+ `sursalary`, `pay_type`)
 *
 * Scoping multi-boutique : `payroll_contracts` n'a pas de `shop_id`, la
 * boutique se déduit via `payroll_employees.shop_id`. Toutes les lectures et
 * écritures passent par `findById($id, $shopId, $superAdmin)` afin de
 * garantir qu'un admin ne manipule que les contrats de SA boutique.
 */
class PayrollContractController extends Controller
{
    public function index()
    {
        if (!$this->requireAdmin()) {
            return;
        }

        $shopId = $this->getShopId();
        $superAdmin = $this->isSuperAdmin();
        $this->json((new PayrollContract())->all($shopId, $superAdmin));
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

        $contract = (new PayrollContract())->findById($id, $this->getShopId(), $this->isSuperAdmin());
        if (!$contract) {
            $this->status(404)->json(['error' => 'Contrat introuvable']);
            return;
        }

        $this->json($contract);
    }

    public function create()
    {
        if (!$this->requireAdmin()) {
            return;
        }

        $data = $this->getJsonOrPost();

        if (empty($data['employee_id'])) {
            $this->status(400)->json(['error' => 'employee_id obligatoire']);
            return;
        }
        if (empty($data['start_date'])) {
            $this->status(400)->json(['error' => 'start_date obligatoire']);
            return;
        }
        if (!isset($data['base_salary']) || $data['base_salary'] === '' || (float)$data['base_salary'] < 0) {
            $this->status(400)->json(['error' => 'base_salary obligatoire (montant positif)']);
            return;
        }

        // L'employé doit exister ET appartenir à la boutique courante.
        $employee = (new PayrollEmployee())->findById($data['employee_id'], $this->getShopId(), $this->isSuperAdmin());
        if (!$employee) {
            $this->status(404)->json(['error' => 'Employé introuvable dans cette boutique']);
            return;
        }

        // Un seul contrat actif par employé : on refuse d'en créer un second,
        // sinon le calcul prendrait toujours le plus récent (ORDER BY
        // start_date DESC) et l'ancien deviendrait un contrat fantôme.
        $existingActive = (new PayrollContract())->findActiveByEmployee($employee['id']);
        if ($existingActive) {
            $this->status(409)->json([
                'error' => 'Cet employé a déjà un contrat actif (modifiez-le ou désactivez-le).',
            ]);
            return;
        }

        $id = (new PayrollContract())->create($data);
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

        $model = new PayrollContract();
        if (!$model->findById($id, $this->getShopId(), $this->isSuperAdmin())) {
            $this->status(404)->json(['error' => 'Contrat introuvable']);
            return;
        }

        $data = $this->getJsonOrPost();

        // Le salaire ne peut pas être négatif.
        if (isset($data['base_salary']) && (float)$data['base_salary'] < 0) {
            $this->status(400)->json(['error' => 'base_salary ne peut pas être négatif']);
            return;
        }

        // Cohérence des bornes de la période de rémunération.
        if (!empty($data['paid_from']) && !empty($data['paid_until'])
            && $data['paid_from'] > $data['paid_until']) {
            $this->status(400)->json(['error' => 'paid_from doit être antérieur à paid_until']);
            return;
        }

        $ok = $model->update($id, $data);
        $this->json(['success' => (bool)$ok]);
    }

    /**
     * Bascule le statut « payé / non payé » d'un contrat.
     *
     * Endpoint dédié (et non un PUT générique) car c'est l'action la plus
     * courante de la page : un agent intérimaire ou saisonnier est payé
     * certains mois seulement.
     */
    public function togglePaid($params)
    {
        if (!$this->requireAdmin()) {
            return;
        }

        $id = $params['id'] ?? null;
        if (!$id) {
            $this->status(400)->json(['error' => 'ID manquant']);
            return;
        }

        $model = new PayrollContract();
        $contract = $model->findById($id, $this->getShopId(), $this->isSuperAdmin());
        if (!$contract) {
            $this->status(404)->json(['error' => 'Contrat introuvable']);
            return;
        }

        $newState = empty($contract['is_paid']) ? 1 : 0;
        $ok = $model->update($id, ['is_paid' => $newState]);

        $this->json([
            'success' => (bool)$ok,
            'is_paid' => $newState,
            'message' => $newState
                ? 'L\'agent est désormais payé (entré au calcul).'
                : 'L\'agent n\'est plus payé (exclu du calcul).',
        ]);
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

        $model = new PayrollContract();
        if (!$model->findById($id, $this->getShopId(), $this->isSuperAdmin())) {
            $this->status(404)->json(['error' => 'Contrat introuvable']);
            return;
        }

        $ok = $model->delete($id);
        $this->json(['success' => (bool)$ok]);
    }

    private function getJsonOrPost()
    {
        $raw = file_get_contents('php://input');
        return (!empty($raw)) ? (json_decode($raw, true) ?: $_POST) : $_POST;
    }
}
