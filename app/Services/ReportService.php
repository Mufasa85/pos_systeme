<?php

namespace App\Services;

use App\Models\Report;

class ReportService
{
    private $reportModel;

    public function __construct()
    {
        $this->reportModel = new Report();
    }

    // ── Génération générique (Z et X) ───────────────────────────

    public function generate($shopId, $type, $startDate, $endDate)
    {
        $shopInfo = $this->reportModel->getShopInfo($shopId);

        $denomination = $shopInfo['nom'] ?? $shopInfo['name'] ?? 'N/A';
        $isf = $shopInfo['isf'] ?? 'N/A';
        $adresse = $shopInfo['adresse'] ?? '';
        $telephone = $shopInfo['telephone'] ?? '';

        $salesByInvoiceType = $this->reportModel->getSalesByInvoiceType($shopId, $startDate, $endDate);
        $salesByTaxGroup = $this->reportModel->getSalesByTaxGroup($shopId, $startDate, $endDate);
        $discounts = $this->reportModel->getTotalDiscounts($shopId, $startDate, $endDate);
        $creditNotes = $this->reportModel->getCreditNotes($shopId, $startDate, $endDate);
        $incompleteSales = $this->reportModel->getIncompleteSalesCount($shopId, $startDate, $endDate);

        // Paiements : parsing JSON en PHP
        $rawPayments = $this->reportModel->getPaymentsParsed($shopId, $startDate, $endDate);
        $paymentsByMethod = [];
        $paymentLabels = [
            'ESPECES' => 'Espèces', 'MOBILEMONEY' => 'Mobile Money',
            'CARTEBANCAIRE' => 'Carte Bancaire', 'VIREMENT' => 'Virement',
            'CREDIT' => 'Crédit', 'CHEQUES' => 'Chèques', 'AUTRE' => 'Autres',
        ];
        foreach ($rawPayments as $row) {
            $payments = json_decode($row['payments'] ?? '[]', true);
            if (!is_array($payments)) continue;
            foreach ($payments as $p) {
                $typeP = $p['type'] ?? ($p['mode'] ?? 'ESPECES');
                $amount = floatval($p['amount'] ?? 0);
                $paymentsByMethod[$typeP] = ($paymentsByMethod[$typeP] ?? 0) + $amount;
            }
        }

        return [
            'header' => [
                'type' => $type,
                'denomination' => $denomination,
                'nif' => $shopInfo['isf'] ?? 'N/A',
                'isf' => $isf,
                'adresse' => $adresse,
                'telephone' => $telephone,
                'generated_at' => date('Y-m-d H:i:s'),
                'period_start' => $startDate,
                'period_end' => $endDate,
            ],
            'totals' => [
                'total_ht' => array_sum(array_column($salesByInvoiceType, 'total_ht')),
                'total_tax' => array_sum(array_column($salesByInvoiceType, 'total_tax')),
                'total_ttc' => array_sum(array_column($salesByInvoiceType, 'total_ttc')),
                'total_invoices' => array_sum(array_column($salesByInvoiceType, 'invoice_count')),
            ],
            'by_invoice_type' => $salesByInvoiceType,
            'by_tax_group' => $salesByTaxGroup,
            'by_payment_method' => array_map(function ($k, $v) use ($paymentLabels) {
                return [
                    'method' => $k,
                    'label' => $paymentLabels[$k] ?? $k,
                    'total' => $v,
                ];
            }, array_keys($paymentsByMethod), $paymentsByMethod),
            'discounts' => $discounts,
            'credit_notes' => $creditNotes,
            'incomplete_sales' => $incompleteSales,
        ];
    }

    // ── Z-rapport : clôture la période et enregistre en base ───

    public function generateZReport($shopId)
    {
        $last = $this->reportModel->getLastZReport($shopId);
        $periodStart = $last ? $last['period_end'] : date('Y-m-d 00:00:00', strtotime('-1 year'));
        $periodEnd = date('Y-m-d H:i:s');
        $data = $this->generate($shopId, 'Z', $periodStart, $periodEnd);
        $this->reportModel->createZReport($shopId, $periodStart, $periodEnd, [
            'total_ht' => $data['totals']['total_ht'],
            'total_tax' => $data['totals']['total_tax'],
            'total_ttc' => $data['totals']['total_ttc'],
            'invoice_count' => $data['totals']['total_invoices'],
        ]);
        return $data;
    }

    // ── X-rapport quotidien ────────────────────────────────────

    public function generateXReportDaily($shopId)
    {
        $last = $this->reportModel->getLastZReport($shopId);
        $periodStart = $last ? $last['period_end'] : date('Y-m-d 00:00:00');
        return $this->generate($shopId, 'X_DAILY', $periodStart, date('Y-m-d H:i:s'));
    }

    // ── X-rapport périodique ───────────────────────────────────

    public function generateXReportPeriodic($shopId, $startDate, $endDate)
    {
        return $this->generate($shopId, 'X_PERIODIC', $startDate, $endDate);
    }

    // ── A-rapport : détail par article (clôture la période A) ──

    public function generateAReport($shopId)
    {
        $last = $this->reportModel->getLastAReport($shopId);
        $periodStart = $last ? $last['period_end'] : date('Y-m-d 00:00:00', strtotime('-1 year'));
        $periodEnd = date('Y-m-d H:i:s');

        $shopInfo = $this->reportModel->getShopInfo($shopId);
        $articles = $this->reportModel->getArticlesReport($shopId, $periodStart, $periodEnd);

        $totalQtySold = array_sum(array_column($articles, 'quantite_vendue'));
        $totalQtyReturned = array_sum(array_column($articles, 'quantite_retournee'));
        $totalCollected = array_sum(array_column($articles, 'montant_collecte'));

        $this->reportModel->createAReport($shopId, $periodStart, $periodEnd, [
            'articles_count' => count($articles),
            'total_quantity_sold' => $totalQtySold,
            'total_quantity_returned' => $totalQtyReturned,
            'total_amount_collected' => $totalCollected,
        ]);

        return [
            'header' => [
                'type' => 'A',
                'denomination' => $shopInfo['nom'] ?? $shopInfo['name'] ?? 'N/A',
                'nif' => $shopInfo['isf'] ?? 'N/A',
                'isf' => $shopInfo['isf'] ?? 'N/A',
                'adresse' => $shopInfo['adresse'] ?? '',
                'telephone' => $shopInfo['telephone'] ?? '',
                'generated_at' => $periodEnd,
                'period_start' => $periodStart,
                'period_end' => $periodEnd,
            ],
            'articles' => $articles,
            'totals' => [
                'total_quantity_sold' => $totalQtySold,
                'total_quantity_returned' => $totalQtyReturned,
                'total_amount_collected' => $totalCollected,
                'articles_count' => count($articles),
            ],
        ];
    }
}
