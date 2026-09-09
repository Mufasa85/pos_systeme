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

    // GET /api/reports/z-report
    public function generateZReport()
    {
        if (!$this->requireAdmin()) return;
        try {
            $report = $this->reportService->generateZReport($this->getShopId());
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
                'data' => $this->reportService->generateXReportDaily($this->getShopId())]);
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
                    $this->getShopId(), $from . ' 00:00:00', $to . ' 23:59:59')]);
        } catch (\Exception $e) {
            $this->status(500)->json(['success' => false, 'message' => $e->getMessage()]);
        }
    }

    // GET /api/reports/a-report
    public function generateAReport()
    {
        if (!$this->requireAdmin()) return;
        try {
            $report = $this->reportService->generateAReport($this->getShopId());
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
        $db = \App\Core\Database::getInstance();
        $history = $db->fetchAll(
            'SELECT zr.*, s.nom as shop_name FROM z_reports zr
             LEFT JOIN shops s ON zr.shop_id = s.id
             WHERE zr.shop_id = ? ORDER BY zr.issued_at DESC LIMIT 50',
            [$this->getShopId()]);
        $this->json(['success' => true, 'data' => $history]);
    }

    // GET /api/reports/a-history
    public function getAReportHistory()
    {
        if (!$this->requireAdmin()) return;
        $db = \App\Core\Database::getInstance();
        $history = $db->fetchAll(
            'SELECT ar.*, s.nom as shop_name FROM a_reports ar
             LEFT JOIN shops s ON ar.shop_id = s.id
             WHERE ar.shop_id = ? ORDER BY ar.issued_at DESC LIMIT 50',
            [$this->getShopId()]);
        $this->json(['success' => true, 'data' => $history]);
    }

    // GET /api/reports/print/:id
    public function printReport($params)
    {
        if (!$this->requireAuth()) return;
        $db = \App\Core\Database::getInstance();
        $zReport = $db->fetch('SELECT * FROM z_reports WHERE id = ? AND shop_id = ?',
            [$params['id'], $this->getShopId()]);
        if (!$zReport) {
            $this->status(404)->json(['success' => false, 'message' => 'Rapport introuvable']);
            return;
        }
        $report = $this->reportService->generate($this->getShopId(), 'Z',
            $zReport['period_start'], $zReport['period_end']);
        $this->json(['success' => true, 'data' => $report]);
    }
}
