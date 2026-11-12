<?php

namespace App\Controllers;

use App\Exceptions\DgiReportException;
use App\Models\Shop;
use App\Services\DgiDashboardService;

/**
 * API des indicateurs DGI/OSAT du tableau de bord.
 */
class DashboardController extends Controller
{
    private DgiDashboardService $dashboardService;

    public function __construct(?DgiDashboardService $dashboardService = null)
    {
        $this->dashboardService = $dashboardService ?? new DgiDashboardService();
    }

    /**
     * GET /api/dashboard/dgi-stats
     *
     * Agrégats DGI pour les trois périodes du tableau de bord (jour, semaine,
     * mois). Remplace les calculs locaux.
     *
     * Le tableau de bord est accessible à tous les utilisateurs
     * authentifiés : la lecture des ventes de sa propre boutique ne doit
     * pas être réservée aux administrateurs.
     *
     * ⚠️ Un super_admin interroge une boutique précise (`?shop_id=`) ou,
     * à défaut, son propre ISF d'entreprise : une seule req HTTP suffit
     * (pas d'agrégation multi-boutiques).
     */
    public function dgiStats()
    {
        if (!$this->requireAuth()) {
            return;
        }

        [$shops, $error] = $this->resolveShops();
        if ($error !== null) {
            $this->status($error['status'])->json([
                'success' => false,
                'code' => $error['code'],
                'message' => $error['message'],
            ]);
            return;
        }

        // La DGI peut mettre plusieurs secondes à répondre (3 périodes ×
        // N boutiques) : on désactive la limite d'exécution de PHP, sinon la
        // requête serait interrompue à 30 s.
        @set_time_limit(0);

        try {
            $stats = $this->dashboardService->getStatsForShops($shops);
            $this->json(['success' => true, 'data' => $stats]);
        } catch (DgiReportException $e) {
            $this->status($e->getHttpStatus())->json([
                'success' => false,
                'code' => $e->getErrorCode(),
                'message' => $e->getMessage(),
            ]);
        } catch (\Throwable $e) {
            error_log('[DashboardController] Stats DGI en echec: ' . $e->getMessage());
            $this->status(502)->json([
                'success' => false,
                'code' => 'dgi_dashboard_unavailable',
                'message' => 'Le service DGI est momentanément indisponible.',
            ]);
        }
    }

    /**
     * Résout les boutiques dont on veut les chiffres.
     *
     * ⚠️ L'ISF n'est JAMAIS accepté depuis la requête : il vient toujours de
     * `shops.isf`.
     *
     * - Un utilisateur standard : sa propre boutique.
     * - Un super_admin : une boutique précise (`?shop_id=`) ou, à défaut,
     *   SON ISF (celui de l'entreprise, `company_info.isf`) — une seule req.
     *
     * @return array{0: array<int, string>, 1: array{status: int, code: string, message: string}|null}
     */
    private function resolveShops(): array
    {
        $shopModel = new Shop();

        if (!$this->isSuperAdmin()) {
            $shopId = (int)($this->getShopId() ?? 0);
            if ($shopId <= 0) {
                return [[], [
                    'status' => 400,
                    'code' => 'dgi_report_shop_required',
                    'message' => "Aucune boutique n'est associée à votre session.",
                ]];
            }

            $shop = $shopModel->findById($shopId);
            if (!$shop || empty($shop['actif'])) {
                return [[], [
                    'status' => 404,
                    'code' => 'dgi_report_shop_not_found',
                    'message' => 'La boutique sélectionnée est introuvable ou désactivée.',
                ]];
            }
            if (trim((string)($shop['isf'] ?? '')) === '') {
                return [[], [
                    'status' => 422,
                    'code' => 'dgi_report_missing_isf',
                    'message' => "L'ISF de la boutique n'est pas configuré.",
                ]];
            }

            return [[(int)$shop['id'] => (string)$shop['isf']], null];
        }

        // Super_admin : une boutique précise a-t-elle été demandée ?
        $requestedShopId = $_GET['shop_id'] ?? null;
        if ($requestedShopId !== null && $requestedShopId !== '' && ctype_digit((string)$requestedShopId)) {
            $shop = $shopModel->findById((int)$requestedShopId);
            if (!$shop || empty($shop['actif'])) {
                return [[], [
                    'status' => 404,
                    'code' => 'dgi_report_shop_not_found',
                    'message' => 'La boutique sélectionnée est introuvable ou désactivée.',
                ]];
            }
            if (trim((string)($shop['isf'] ?? '')) === '') {
                return [[], [
                    'status' => 422,
                    'code' => 'dgi_report_missing_isf',
                    'message' => "L'ISF de la boutique n'est pas configuré.",
                ]];
            }

            return [[(int)$shop['id'] => (string)$shop['isf']], null];
        }

        // Sans boutique sélectionnée : le super_admin interroge SON ISF
        // (celui de l'entreprise, `company_info.isf`), comme il le fait pour
        // les shops. Une seule req HTTP est ainsi nécessaire, au lieu de
        // 3 × N (une par boutique).
        try {
            $companyInfo = (new \App\Models\CompanyInfo())->get();
        } catch (\Throwable $e) {
            error_log('[DashboardController] Lecture company_info impossible: ' . $e->getMessage());
            $companyInfo = [];
        }

        // `CompanyInfo::get()` renvoie `false` si la ligne id=1 n'existe pas.
        $companyIsf = trim((string)(is_array($companyInfo) ? ($companyInfo['isf'] ?? '') : ''));
        if ($companyIsf === '') {
            return [[], [
                'status' => 422,
                'code' => 'dgi_report_missing_isf',
                'message' => "L'ISF de l'entreprise n'est pas configuré.",
            ]];
        }

        return [[0 => $companyIsf], null];
    }
}
