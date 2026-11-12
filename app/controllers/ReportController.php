<?php

namespace App\Controllers;

use App\Exceptions\DgiReportException;
use App\Models\DgiZReport;
use App\Models\Shop;
use App\Services\DgiReportService;
use App\Services\ReportService;

class ReportController extends Controller
{
    private $reportService;
    private $dgiReportService;

    public function __construct(?callable $dgiTransport = null)
    {
        $this->reportService = new ReportService();
        $this->dgiReportService = new DgiReportService($dgiTransport);
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

    // GET /api/reports/dgi/x?from=YYYY-MM-DD&to=YYYY-MM-DD&types=FV,FT
    public function generateDgiXReport()
    {
        if (!$this->requireAdmin()) {
            return;
        }

        $from = (string)($_GET['from'] ?? date('Y-m-d'));
        $to = (string)($_GET['to'] ?? date('Y-m-d'));
        if (!$this->isValidDate($from) || !$this->isValidDate($to)) {
            $this->status(400)->json([
                'success' => false,
                'code' => 'dgi_report_invalid_date',
                'message' => 'Dates invalides. Utilisez le format YYYY-MM-DD.',
            ]);
            return;
        }
        if ($from > $to) {
            $this->status(400)->json([
                'success' => false,
                'code' => 'dgi_report_invalid_period',
                'message' => 'La date de début doit preceder la date de fin.',
            ]);
            return;
        }

        $this->respondWithDgiReport(
            $from . ' 00:00:00',
            $to . ' 23:59:59',
            'X_DGI'
        );
    }

    // GET /api/reports/dgi/z
    //
    // Consultation (lecture seule) de la période DGI à clôturer : elle va de
    // la fin de la dernière clôture DGI jusqu'à maintenant. La clôture
    // elle-même est une POST (cf. generateDgiZClosure).
    public function generateDgiZReport()
    {
        if (!$this->requireAdmin()) {
            return;
        }

        [$shop, $error] = $this->resolveDgiShop();
        if ($error !== null) {
            $this->respondDgiError($error);
            return;
        }

        [$periodStart, $periodEnd] = $this->resolveDgiZPeriod((int)$shop['id']);

        $this->respondWithDgiReport($periodStart, $periodEnd, 'Z_DGI', $shop);
    }

    // POST /api/reports/dgi/z
    //
    // Clôture DGI : récupère l'agrégat de la période courante via l'API OSAT,
    // l'enregistre (compteur de clôture) et chaîne la période suivante.
    public function generateDgiZClosure()
    {
        if (!$this->requireAdmin()) {
            return;
        }

        [$shop, $error] = $this->resolveDgiShop();
        if ($error !== null) {
            $this->respondDgiError($error);
            return;
        }

        $shopId = (int)$shop['id'];
        [$periodStart, $periodEnd] = $this->resolveDgiZPeriod($shopId);

        // La DGI peut mettre du temps à répondre : on désactive la limite
        // d'exécution de PHP, sinon la requête serait interrompue à 30 s.
        @set_time_limit(0);

        try {
            $report = $this->dgiReportService->getReport(
                (string)$shop['isf'],
                $periodStart,
                $periodEnd
            );
        } catch (DgiReportException $e) {
            $this->respondDgiError([
                'success' => false,
                'code' => $e->getErrorCode(),
                'message' => $e->getMessage(),
            ], $e->getHttpStatus());
            return;
        }

        // Une clôture sur une période SANS aucune facture est refusée : elle
        // figerait une période vide (cas typique : deux clôtures consécutives
        // à quelques minutes d'intervalle) et la période resterait bloquée.
        if (!$this->dgiReportService->hasInvoices($report)) {
            $this->respondDgiError([
                'success' => false,
                'code' => 'dgi_closure_empty_period',
                'message' => 'La période ne contient aucune facture : clôture refusée. '
                    . 'La période reste ouverte et pourra être clôturée plus tard.',
            ], 409);
            return;
        }

        $zReportModel = new DgiZReport();
        $snapshot = json_encode([
            'totals' => $report['totals'],
            'by_invoice_type' => $report['by_invoice_type'],
            'tax_groups' => $report['tax_groups'],
            'warnings' => $report['warnings'],
        ], JSON_UNESCAPED_UNICODE);

        try {
            $zReportId = $zReportModel->create(
                $shopId,
                $periodStart,
                $periodEnd,
                $report['totals'],
                $snapshot
            );
        } catch (\Exception $e) {
            error_log('[ReportController] Ecriture clôture DGI impossible: ' . $e->getMessage());
            $this->status(500)->json([
                'success' => false,
                'code' => 'dgi_closure_persist_failed',
                'message' => "La clôture DGI n'a pas pu être enregistrée.",
            ]);
            return;
        }

        $this->logAudit('close', 'dgi_z_report', $zReportId, [
            'period_start' => $periodStart,
            'period_end' => $periodEnd,
            'total_ht' => $report['totals']['ht'],
            'total_ttc' => $report['totals']['ttc'],
        ]);

        $data = $this->buildDgiPayload($shop, $periodStart, $periodEnd, 'Z_DGI', $report);
        $data['header']['official_closure'] = true;
        $data['header']['closure_id'] = $zReportId;
        $data['header']['closure_counter'] = $zReportModel->nextCounter($shopId) - 1;

        $this->status(200)->json([
            'success' => true,
            'data' => $data,
            'message' => 'Clôture DGI effectuée. La période suivante démarrera à la fin de cette période.',
        ]);
    }

    /**
     * Détermine la période DGI à clôturer.
     *
     * La période démarre à la fin de la dernière clôture DGI (chaînage) et
     * se termine maintenant. Sans clôture antérieure, on ancre sur la
     * première vente réelle de la boutique (cf. ReportService::resolveFirstClosureStart).
     *
     * ⚠️ On ignore les clôtures VIDES (0 facture) : une clôture vide ferait
     * repartir la période suivante d'un instant arbitraire et empêcherait
     * de clôturer les ventes suivantes.
     *
     * @return array{0: string, 1: string}
     */
    private function resolveDgiZPeriod(int $shopId): array
    {
        $lastClosure = (new DgiZReport())->getLastWithInvoices($shopId);
        if ($lastClosure && !empty($lastClosure['period_end'])) {
            $periodStart = (string)$lastClosure['period_end'];
        } else {
            $periodStart = $this->reportService->resolveFirstClosureStart($shopId);
        }

        // Arrondi à la minute : évite les chaînes de clôtures à la seconde,
        // qui fractionnent la période en bouts de quelques secondes.
        $periodEnd = date('Y-m-d H:i:00');
        if ($periodStart > $periodEnd) {
            $periodStart = $periodEnd;
        }

        return [$periodStart, $periodEnd];
    }

    // GET /api/reports/a-report
    public function generateAReport()
    {
        if (!$this->requireAdmin()) {
            return;
        }
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

    /**
     * Résout la boutique DGI sans jamais accepter un NIF libre.
     *
     * @return array{0: array|null, 1: array|null}
     */
    private function resolveDgiShop(?array $knownShop = null): array
    {
        if ($knownShop !== null) {
            return [$knownShop, null];
        }

        $shopId = null;
        if ($this->isSuperAdmin()) {
            $requestedShopId = $_GET['shop_id'] ?? null;
            if ($requestedShopId === null || $requestedShopId === '' || !ctype_digit((string)$requestedShopId)) {
                return [null, [
                    'success' => false,
                    'code' => 'dgi_report_shop_required',
                    'message' => 'Veuillez sélectionner une boutique pour le rapport DGI.',
                ]];
            }
            $shopId = (int)$requestedShopId;
        } else {
            $shopId = (int)($this->getShopId() ?? 0);
            if ($shopId <= 0) {
                return [null, [
                    'success' => false,
                    'code' => 'dgi_report_shop_required',
                    'message' => 'Aucune boutique n\'est associée à votre session.',
                ]];
            }
        }

        $shop = (new Shop())->findById($shopId);
        if (!$shop || empty($shop['actif'])) {
            return [null, [
                'success' => false,
                'code' => 'dgi_report_shop_not_found',
                'message' => 'La boutique sélectionnée est introuvable ou désactivée.',
            ]];
        }
        if (trim((string)($shop['isf'] ?? '')) === '') {
            return [null, [
                'success' => false,
                'code' => 'dgi_report_missing_isf',
                'message' => "L'ISF de la boutique n'est pas configuré.",
            ]];
        }

        return [$shop, null];
    }

    /**
     * Valide strictement une date au format SQL `Y-m-d`.
     */
    private function isValidDate(string $date): bool
    {
        $parsed = \DateTimeImmutable::createFromFormat('!Y-m-d', $date);
        return $parsed !== false && $parsed->format('Y-m-d') === $date;
    }

    /**
     * Types de facture demandés par le client (filtre « FV, FT, ... »).
     *
     * @return string[]
     */
    private function resolveDgiTypes(): ?array
    {
        $raw = $_GET['types'] ?? '';
        $values = is_array($raw) ? $raw : explode(',', (string)$raw);

        $types = [];
        foreach ($values as $value) {
            $code = strtoupper(trim((string)$value));
            if ($code === '') {
                continue;
            }
            if (!isset(DgiReportService::INVOICE_TYPES[$code])) {
                $this->status(400)->json([
                    'success' => false,
                    'code' => 'dgi_report_invalid_type',
                    'message' => 'Type de facture inconnu : ' . $code,
                ]);
                return null;
            }
            if (!in_array($code, $types, true)) {
                $types[] = $code;
            }
        }

        return $types;
    }

    /**
     * Appelle le provider et retourne la réponse normalisée.
     */
    private function respondWithDgiReport(
        string $periodStart,
        string $periodEnd,
        string $reportType,
        ?array $shop = null
    ): void {
        // La DGI peut mettre du temps à répondre : on désactive la limite
        // d'exécution de PHP, sinon la requête serait interrompue à 30 s.
        @set_time_limit(0);

        if ($shop === null) {
            [$resolvedShop, $error] = $this->resolveDgiShop();
            if ($error !== null) {
                $this->respondDgiError($error);
                return;
            }
            $shop = $resolvedShop;
        }

        $types = $this->resolveDgiTypes();
        if ($types === null) {
            return;
        }

        try {
            $report = $this->dgiReportService->getReport(
                (string)$shop['isf'],
                $periodStart,
                $periodEnd,
                $types
            );
        } catch (DgiReportException $e) {
            $this->respondDgiError([
                'success' => false,
                'code' => $e->getErrorCode(),
                'message' => $e->getMessage(),
            ], $e->getHttpStatus());
            return;
        }

        $this->status(200)->json([
            'success' => true,
            'data' => $this->buildDgiPayload($shop, $periodStart, $periodEnd, $reportType, $report),
            'message' => $reportType === 'Z_DGI'
                ? 'Agrégat DGI de la période à clôturer affiché. Aucune clôture n\'a été effectuée.'
                : 'Rapport DGI affiché.',
        ]);
    }

    /**
     * Assemble la réponse normalisée renvoyée au frontend.
     */
    private function buildDgiPayload(
        array $shop,
        string $periodStart,
        string $periodEnd,
        string $reportType,
        array $report
    ): array {
        $data = [
            'source' => 'dgi',
            'header' => [
                'type' => $reportType,
                'denomination' => (string)($shop['nom'] ?? $shop['name'] ?? 'N/A'),
                'nif' => (string)$shop['isf'],
                'isf' => (string)$shop['isf'],
                'adresse' => (string)($shop['adresse'] ?? ''),
                'telephone' => (string)($shop['telephone'] ?? ''),
                'generated_at' => date('Y-m-d H:i:s'),
                'period_start' => $periodStart,
                'period_end' => $periodEnd,
                'official_closure' => false,
            ],
        ];

        return array_merge($data, $report);
    }

    /**
     * Envoie une erreur applicative avec le statut HTTP approprié.
     */
    private function respondDgiError(array $error, int $status = 400): void
    {
        if ($status === 400) {
            $statusByCode = [
                'dgi_report_shop_not_found' => 404,
                'dgi_report_missing_isf' => 422,
            ];
            $status = $statusByCode[$error['code'] ?? ''] ?? $status;
        }

        $this->status($status)->json($error);
    }

    // GET /api/reports/dgi/z-history
    //
    // Historique des clôtures DGI de la boutique.
    public function getDgiZHistory()
    {
        if (!$this->requireAdmin()) {
            return;
        }

        $shopId = null;
        if (!$this->isSuperAdmin()) {
            $shopId = (int)($this->getShopId() ?? 0);
        } else {
            $requestedShopId = $_GET['shop_id'] ?? null;
            if ($requestedShopId !== null && $requestedShopId !== '' && ctype_digit((string)$requestedShopId)) {
                $shopId = (int)$requestedShopId;
            }
        }

        $this->json([
            'success' => true,
            'data' => (new DgiZReport())->getHistory($shopId > 0 ? $shopId : null),
        ]);
    }

    // GET /api/reports/history
    //
    // Historique UNIQUE des Z-rapports : on y fusionne les Z-rapports locaux
    // du POS (`z_reports`) et les clôtures DGI (`dgi_z_reports`). Le champ
    // `origin` permet à l'interface de distinguer les deux sources.
    public function getReportHistory()
    {
        if (!$this->requireAdmin()) {
            return;
        }
        $shopId = $this->resolveShopId();

        $db = \App\Core\Database::getInstance();
        $sql = 'SELECT zr.*, s.nom as shop_name FROM z_reports zr
                LEFT JOIN shops s ON zr.shop_id = s.id
                WHERE (? IS NULL OR zr.shop_id = ?) ORDER BY zr.issued_at DESC LIMIT 200';
        $localHistory = $db->fetchAll($sql, [$shopId, $shopId]);

        $history = [];
        foreach ($localHistory as $row) {
            $row['origin'] = 'local';
            $row['legacy'] = true;
            $history[] = $row;
        }

        $dgiHistory = (new DgiZReport())->getHistory($shopId);
        foreach ($dgiHistory as $row) {
            // Colonne commune avec les Z locaux : le tableau de l'interface
            // n'a pas besoin d'être adapté.
            $row['origin'] = 'dgi';
            $row['total_sales_ht'] = $row['total_ht'];
            $history[] = $row;
        }

        // Tri antéchronologique sur la date de clôture/émission.
        usort($history, static function (array $a, array $b): int {
            return strcmp((string)$b['issued_at'], (string)$a['issued_at']);
        });

        $this->json(['success' => true, 'data' => array_slice($history, 0, 200)]);
    }

    // GET /api/reports/a-history
    public function getAReportHistory()
    {
        if (!$this->requireAdmin()) {
            return;
        }
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
        if (!$this->requireAuth()) {
            return;
        }
        $db = \App\Core\Database::getInstance();
        if ($this->isSuperAdmin()) {
            $zReport = $db->fetch('SELECT * FROM z_reports WHERE id = ?', [$params['id']]);
        } else {
            $zReport = $db->fetch(
                'SELECT * FROM z_reports WHERE id = ? AND shop_id = ?',
                [$params['id'], $this->getShopId()]
            );
        }
        if (!$zReport) {
            $this->status(404)->json(['success' => false, 'message' => 'Rapport introuvable']);
            return;
        }
        $report = $this->reportService->generate(
            $zReport['shop_id'],
            'Z',
            $zReport['period_start'],
            $zReport['period_end']
        );
        $this->json(['success' => true, 'data' => $report]);
    }
}
