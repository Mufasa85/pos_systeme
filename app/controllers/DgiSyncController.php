<?php

namespace App\Controllers;

use App\Services\DgiSyncService;

/**
 * DgiSyncController
 *
 * Routes de pilotage de la synchronisation DGI (complémentaires de la route
 * poussée par OSAT : POST /api/dgi/update).
 *
 *   POST /api/vente/[id]/dgi-sync  → relance la relecture d'UNE vente
 *   GET  /api/dgi/pending          → liste des ventes en attente
 *   POST /api/dgi/sync-all         → relance la relecture par lot
 *
 * Ces routes exigent une session (utilisateur connecté) : à la différence du
 * webhook OSAT, elles sont déclenchées depuis l'interface.
 */
class DgiSyncController extends Controller
{
    /** @var DgiSyncService */
    private $service;

    public function __construct()
    {
        $this->service = new DgiSyncService();
    }

    /**
     * POST /api/vente/[id]/dgi-sync
     *
     * Relit une vente auprès d'OSAT et écrit les éléments de sécurité.
     */
    public function syncSale($params = [])
    {
        if (!$this->requireAuth()) {
            return;
        }

        $saleId = (int)($params['id'] ?? 0);
        if ($saleId <= 0) {
            $this->status(400)->json([
                'success' => false,
                'error'   => 'ID de vente manquant',
            ]);
            return;
        }

        $sale = (new \App\Models\Sale())->exist($saleId);

        if (!$sale) {
            $this->status(404)->json([
                'success' => false,
                'error'   => 'Vente inexistante',
            ]);
            return;
        }

        // Contrôle d'accès : la vente doit appartenir à la boutique de
        // l'utilisateur (le super_admin peut cibler n'importe quelle boutique).
        if (!$this->isSuperAdmin() && (int)$sale['shop_id'] !== (int)$this->getShopId()) {
            $this->status(403)->json([
                'success' => false,
                'error'   => 'Cette vente ne fait pas partie de votre boutique',
            ]);
            return;
        }

        $force = isset($_GET['force']) && $_GET['force'] === '1';

        $result = $this->service->syncSale($saleId, $force);

        $this->json([
            'success' => $result['success'],
            'status'  => $result['status'],
            'message' => $result['message'],
            'applied' => $result['applied'],
            'sale'    => [
                'id'              => (int)$saleId,
                'numero_facture' => $sale['numero_facture'] ?? '',
                'dgi_status'     => $result['status'] === 'synced' ? DgiSyncService::STATUS_SYNCED : $sale['dgi_status'] ?? null,
            ],
        ]);
    }

    /**
     * GET /api/dgi/pending
     *
     * Liste les ventes en attente de résolution DGI (pour l'admin).
     */
    public function pending()
    {
        if (!$this->requireAdmin()) {
            return;
        }

        $shopId = $this->isSuperAdmin() ? ($_GET['shop_id'] ?? null) : $this->getShopId();
        $limit  = min(200, max(1, (int)($_GET['limit'] ?? 50)));

        $sales = $this->service->findPendingSales($limit, $shopId !== null ? (int)$shopId : null);

        $items = [];
        foreach ($sales as $sale) {
            $items[] = [
                'id'              => (int)$sale['id'],
                'numero_facture'  => $sale['numero_facture'] ?? '',
                'dgi_invoice_number' => $sale['dgi_invoice_number'] ?? '',
                'store_isf'       => $sale['store_isf'] ?? '',
                'date'            => $sale['date'] ?? '',
                'total'           => $sale['total'] ?? 0,
                'dgi_attempts'    => (int)($sale['dgi_attempts'] ?? 0),
                'dgi_last_error'  => $sale['dgi_last_error'] ?? null,
            ];
        }

        $this->json([
            'success' => true,
            'count'   => count($items),
            'data'    => $items,
        ]);
    }

    /**
     * POST /api/dgi/sync-all
     *
     * Relance la relecture de toutes les ventes en attente (par lot).
     */
    public function syncAll()
    {
        if (!$this->requireAdmin()) {
            return;
        }

        $shopId = $this->isSuperAdmin() ? ($_GET['shop_id'] ?? null) : $this->getShopId();
        $limit  = min(200, max(1, (int)($_GET['limit'] ?? 50)));

        $summary = $this->service->syncPending($limit, $shopId !== null ? (int)$shopId : null);

        $this->json([
            'success' => true,
            'summary' => $summary,
        ]);
    }
}
