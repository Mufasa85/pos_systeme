<?php

namespace App\Controllers;

use App\controllers\Controller;
use App\Models\PayrollPayslip;
use App\Models\PayrollPayslipLine;
use App\Models\PayrollPayment;
use App\Models\PayrollEmployee;
use App\Models\PayrollPeriod;

class PayrollReportController extends Controller
{
    public function periodSummary($params)
    {
        if (!$this->requireAdmin()) return;
        $periodId = $params['id'] ?? null;
        if (!$periodId) { $this->status(400)->json(['error' => 'ID période manquant']); return; }

        $shopId = $this->getShopId();
        $payslips = (new PayrollPayslip())->findByPeriod($periodId, $shopId);

        $gross = $net = $deductions = $employerCost = 0;
        foreach ($payslips as $p) {
            $gross        += (float)$p['gross_amount'];
            $net          += (float)$p['net_amount'];
            $deductions   += (float)$p['total_deductions'];
            $employerCost += (float)$p['employer_cost'];
        }

        $this->json([
            'payslips'      => $payslips,
            'total_gross'   => round($gross, 2),
            'total_net'     => round($net, 2),
            'total_deductions' => round($deductions, 2),
            'total_employer_cost' => round($employerCost, 2),
        ]);
    }

    public function periodCsv($params)
    {
        if (!$this->requireAdmin()) return;
        $periodId = $params['id'] ?? null;
        if (!$periodId) { $this->status(400)->json(['error' => 'ID période manquant']); return; }

        $shopId = $this->getShopId();
        $payslips = (new PayrollPayslip())->findByPeriod($periodId, $shopId);
        $period = (new PayrollPeriod())->findById($periodId, $shopId);

        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="paie_' . ($period['year'] ?? '') . '_' . ($period['month'] ?? '') . '.csv"');
        $out = fopen('php://output', 'w');
        fprintf($out, chr(0xEF) . chr(0xBB) . chr(0xBF));
        fputs($out, "sep=;\n");
        fputcsv($out, ['Matricule', 'Nom', 'Brut', 'Retenues', 'Net', 'Coût employeur'], ';');
        foreach ($payslips as $p) {
            fputcsv($out, [
                $p['matricule'] ?? '',
                $p['nom_complet'] ?? '',
                $p['gross_amount'],
                $p['total_deductions'],
                $p['net_amount'],
                $p['employer_cost'],
            ], ';');
        }
        fclose($out);
        exit;
    }

    public function headcount($params)
    {
        if (!$this->requireAdmin()) return;
        $shopId = $this->getShopId();
        $this->json([
            'active'  => count((new PayrollEmployee())->all($shopId, $this->isSuperAdmin())),
            'status'  => 'ok',
        ]);
    }

    public function payments($params)
    {
        if (!$this->requireAdmin()) return;
        $periodId = $params['id'] ?? null;
        if (!$periodId) { $this->status(400)->json(['error' => 'ID période manquant']); return; }

        $shopId = $this->getShopId();
        $payments = (new PayrollPayment())->findByPeriod($periodId, $shopId);

        $total = 0;
        foreach ($payments as $p) { $total += (float)$p['amount']; }

        $this->json([
            'payments'   => $payments,
            'total_paid' => round($total, 2),
        ]);
    }

    public function contributions($params)
    {
        if (!$this->requireAdmin()) return;
        $periodId = $params['id'] ?? null;
        if (!$periodId) { $this->status(400)->json(['error' => 'ID période manquant']); return; }

        $shopId = $this->getShopId();
        $rows = (new PayrollPayslipLine())->contributionsByPeriod($periodId, $shopId);

        $total = 0;
        foreach ($rows as $r) { $total += (float)$r['total']; }

        $this->json([
            'contributions'   => $rows,
            'total_contributions' => round($total, 2),
        ]);
    }

    private function getJsonOrPost()
    {
        $raw = file_get_contents('php://input');
        return (!empty($raw)) ? (json_decode($raw, true) ?: $_POST) : $_POST;
    }
}
