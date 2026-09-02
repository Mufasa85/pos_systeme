<?php

namespace App\Services;

use App\Models\PayrollEmployee;
use App\Models\PayrollPayslip;
use App\Models\PayrollPayslipLine;
use App\Models\PayrollPeriod;

class PayrollPdfService
{
    private $payslip;
    private $payslipLine;
    private $employee;
    private $period;

    public function __construct()
    {
        $this->payslip     = new PayrollPayslip();
        $this->payslipLine = new PayrollPayslipLine();
        $this->employee    = new PayrollEmployee();
        $this->period      = new PayrollPeriod();
    }

    public function generate($payslipId, $shopId = null)
    {
        if (!class_exists('Dompdf\Dompdf')) {
            throw new \Exception('Dompdf n\'est pas installé. Lancez : composer require dompdf/dompdf');
        }

        $payslip = $this->payslip->findById($payslipId, $shopId);
        if (!$payslip) {
            throw new \Exception('Bulletin introuvable');
        }

        $lines = $this->payslipLine->forPayslip($payslipId);
        $employee = $this->employee->findById($payslip['employee_id']);
        $period = $this->period->findById($payslip['payroll_period_id']);

        $html = $this->renderHtml($payslip, $lines, $employee, $period);

        $dir = $this->pdfDirectory($payslip['shop_id'], $period['year'], $period['month']);
        if (!is_dir($dir)) {
            mkdir($dir, 0777, true);
        }

        $filename = 'bulletin_' . $payslipId . '_' . ($employee['matricule'] ?? $payslipId) . '.pdf';
        $path = $dir . '/' . $filename;

        $dompdf = new \Dompdf\Dompdf();
        $dompdf->loadHtml($html);
        $dompdf->setPaper('A4', 'portrait');
        $dompdf->render();
        file_put_contents($path, $dompdf->output());

        $this->payslip->update($payslipId, ['pdf_path' => $path]);
        return $path;
    }

    public function stream($payslipId, $shopId = null)
    {
        if (!class_exists('Dompdf\Dompdf')) {
            throw new \Exception('Dompdf n\'est pas installé.');
        }

        $payslip = $this->payslip->findById($payslipId, $shopId);
        if (!$payslip) {
            throw new \Exception('Bulletin introuvable');
        }

        $lines = $this->payslipLine->forPayslip($payslipId);
        $employee = $this->employee->findById($payslip['employee_id']);
        $period = $this->period->findById($payslip['payroll_period_id']);

        $html = $this->renderHtml($payslip, $lines, $employee, $period);

        $dompdf = new \Dompdf\Dompdf();
        $dompdf->loadHtml($html);
        $dompdf->setPaper('A4', 'portrait');
        $dompdf->render();

        header('Content-Type: application/pdf');
        header('Content-Disposition: inline; filename="bulletin_' . $payslipId . '.pdf"');
        echo $dompdf->output();
        exit;
    }

    private function pdfDirectory($shopId, $year, $month)
    {
        return realpath(__DIR__ . '/../../storage') . '/payslips/' . $shopId . '/' . $year . '/' . str_pad($month, 2, '0', STR_PAD_LEFT);
    }

    private function renderHtml($payslip, $lines, $employee, $period)
    {
        $earnings = array_values(array_filter($lines, fn ($l) => $l['type'] === 'earning'));
        $deductions = array_values(array_filter($lines, fn ($l) => $l['type'] === 'deduction'));
        $employer = array_values(array_filter($lines, fn ($l) => $l['type'] === 'employer'));

        ob_start();
        ?>
        <!DOCTYPE html>
        <html>
        <head>
            <meta charset="utf-8">
            <style>
                body { font-family: Arial, sans-serif; font-size: 12px; }
                table { width: 100%; border-collapse: collapse; margin-top: 10px; }
                th, td { border: 1px solid #333; padding: 6px; text-align: left; }
                th { background: #eee; }
                .right { text-align: right; }
                .title { font-size: 18px; font-weight: bold; margin-bottom: 10px; }
                .section { margin-top: 20px; }
            </style>
        </head>
        <body>
            <div class="title">Bulletin de paie</div>
            <p>
                <strong>Employé :</strong> <?= htmlspecialchars($employee['nom_complet'] ?? '') ?><br>
                <strong>Matricule :</strong> <?= htmlspecialchars($employee['matricule'] ?? '') ?><br>
                <strong>Période :</strong> <?= str_pad($period['month'] ?? '', 2, '0', STR_PAD_LEFT) ?> / <?= $period['year'] ?? '' ?>
            </p>

            <div class="section"><strong>Gains</strong></div>
            <table>
                <tr><th>Libellé</th><th class="right">Montant</th></tr>
                <?php foreach ($earnings as $l): ?>
                    <tr>
                        <td><?= htmlspecialchars($l['label']) ?></td>
                        <td class="right"><?= number_format($l['amount'], 2) ?></td>
                    </tr>
                <?php endforeach; ?>
            </table>

            <div class="section"><strong>Retenues</strong></div>
            <table>
                <tr><th>Libellé</th><th class="right">Montant</th></tr>
                <?php foreach ($deductions as $l): ?>
                    <tr>
                        <td><?= htmlspecialchars($l['label']) ?></td>
                        <td class="right"><?= number_format($l['amount'], 2) ?></td>
                    </tr>
                <?php endforeach; ?>
            </table>

            <div class="section"><strong>Charges employeur</strong></div>
            <table>
                <tr><th>Libellé</th><th class="right">Montant</th></tr>
                <?php foreach ($employer as $l): ?>
                    <tr>
                        <td><?= htmlspecialchars($l['label']) ?></td>
                        <td class="right"><?= number_format($l['amount'], 2) ?></td>
                    </tr>
                <?php endforeach; ?>
            </table>

            <div class="section">
                <strong>Total brut :</strong> <?= number_format($payslip['gross_amount'], 2) ?><br>
                <strong>Total retenues :</strong> <?= number_format($payslip['total_deductions'], 2) ?><br>
                <strong>Coût employeur :</strong> <?= number_format($payslip['employer_cost'], 2) ?><br>
                <strong>Net à payer :</strong> <?= number_format($payslip['net_amount'], 2) ?>
            </div>
        </body>
        </html>
        <?php
        return ob_get_clean();
    }
}
