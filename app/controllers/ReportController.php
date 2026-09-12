<?php

namespace App\Controllers;

use App\Services\ReportService;

class ReportController extends Controller
{
    private $reportService;

    public function __construct()
    {
        $this->reportService = new ReportService();
    }

    /**
     * Détermine la boutique à utiliser pour la requête courante.
     * - Un utilisateur "shop" (admin/vendeur) ne peut voir que les données de sa propre boutique.
     * - Le super_admin peut voir toutes les boutiques (null = agrégation) ou filtrer via ?shop_id=.
     */
    private function resolveShopId()
    {
        if ($this->isSuperAdmin()) {
            $shopId = $_GET['shop_id'] ?? null;
            return ($shopId === null || $shopId === '') ? null : (int) $shopId;
        }
        return $this->getShopId();
    }

    // GET /api/reports/z-report
    public function generateZReport()
    {
        if (!$this->requireAdmin()) return;
        $shopId = $this->resolveShopId();
        if ($this->isSuperAdmin() && $shopId === null) {
            $this->status(400)->json(['success' => false, 'message' => 'Veuillez sélectionner une boutique pour générer un Z-rapport.']);
            return;
        }
        try {
            $report = $this->reportService->generateZReport($shopId);
            $this->json(['success' => true, 'data' => $report,
                'message' => 'Z-rapport généré. Période clôturée.']);
        } catch (\Exception $e) {
            $this->status(500)->json(['success' => false, 'message' => $e->getMessage()]);
        }
    }

    // GET /api/reports/x-report/daily
    public function generateXReportDaily()
    {
        if (!$this->requireAuth()) return;
        try {
            $this->json(['success' => true,
                'data' => $this->reportService->generateXReportDaily($this->resolveShopId())]);
        } catch (\Exception $e) {
            $this->status(500)->json(['success' => false, 'message' => $e->getMessage()]);
        }
    }

    // GET /api/reports/x-report/periodic?from=YYYY-MM-DD&to=YYYY-MM-DD
    public function generateXReportPeriodic()
    {
        if (!$this->requireAuth()) return;
        $from = $_GET['from'] ?? date('Y-m-d');
        $to = $_GET['to'] ?? date('Y-m-d');
        if (!strtotime($from) || !strtotime($to)) {
            $this->status(400)->json(['success' => false, 'message' => 'Dates invalides']);
            return;
        }
        try {
            $this->json(['success' => true,
                'data' => $this->reportService->generateXReportPeriodic(
                    $this->resolveShopId(), $from . ' 00:00:00', $to . ' 23:59:59')]);
        } catch (\Exception $e) {
            $this->status(500)->json(['success' => false, 'message' => $e->getMessage()]);
        }
    }

    // GET /api/reports/a-report
    public function generateAReport()
    {
        if (!$this->requireAdmin()) return;
        $shopId = $this->resolveShopId();
        if ($this->isSuperAdmin() && $shopId === null) {
            $this->status(400)->json(['success' => false, 'message' => 'Veuillez sélectionner une boutique pour générer un A-rapport.']);
            return;
        }
        try {
            $report = $this->reportService->generateAReport($shopId);
            $this->json(['success' => true, 'data' => $report,
                'message' => 'A-rapport généré. Période article clôturée.']);
        } catch (\Exception $e) {
            $this->status(500)->json(['success' => false, 'message' => $e->getMessage()]);
        }
    }

    // GET /api/reports/history
    public function getReportHistory()
    {
        if (!$this->requireAdmin()) return;
        $shopId = $this->resolveShopId();
        $db = \App\Core\Database::getInstance();
        $sql = 'SELECT zr.*, s.nom as shop_name FROM z_reports zr
                LEFT JOIN shops s ON zr.shop_id = s.id
                WHERE (? IS NULL OR zr.shop_id = ?) ORDER BY zr.issued_at DESC LIMIT 50';
        $history = $db->fetchAll($sql, [$shopId, $shopId]);
        $this->json(['success' => true, 'data' => $history]);
    }

    // GET /api/reports/a-history
    public function getAReportHistory()
    {
        if (!$this->requireAdmin()) return;
        $shopId = $this->resolveShopId();
        $db = \App\Core\Database::getInstance();
        $sql = 'SELECT ar.*, s.nom as shop_name FROM a_reports ar
                LEFT JOIN shops s ON ar.shop_id = s.id
                WHERE (? IS NULL OR ar.shop_id = ?) ORDER BY ar.issued_at DESC LIMIT 50';
        $history = $db->fetchAll($sql, [$shopId, $shopId]);
        $this->json(['success' => true, 'data' => $history]);
    }

    // GET /api/reports/print/:id
    public function printReport($params)
    {
        if (!$this->requireAuth()) return;
        $db = \App\Core\Database::getInstance();
        if ($this->isSuperAdmin()) {
            $zReport = $db->fetch('SELECT * FROM z_reports WHERE id = ?', [$params['id']]);
        } else {
            $zReport = $db->fetch('SELECT * FROM z_reports WHERE id = ? AND shop_id = ?',
                [$params['id'], $this->getShopId()]);
        }
        if (!$zReport) {
            $this->status(404)->json(['success' => false, 'message' => 'Rapport introuvable']);
            return;
        }
        $report = $this->reportService->generate($zReport['shop_id'], 'Z',
            $zReport['period_start'], $zReport['period_end']);
        $this->json(['success' => true, 'data' => $report]);
    }
}
