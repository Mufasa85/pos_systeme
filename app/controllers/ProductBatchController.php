<?php

namespace App\Controllers;

use App\Models\Product;
use App\Models\ProductBatch;

class ProductBatchController extends Controller
{
    public function index()
    {
        if (!$this->requireAuth()) {
            return;
        }

        $productId = (int)($_GET['product_id'] ?? 0);
        if (!$productId) {
            $this->status(400)->json(['error' => 'ID produit manquant']);
            return;
        }

        $batchModel = new ProductBatch();
        $batches = $batchModel->findByProductId($productId);
        $this->json(['success' => true, 'data' => $batches]);
    }

    public function create()
    {
        if (!$this->requireAdmin()) {
            return;
        }

        $productId = (int)$this->sanitaze($_POST['product_id'] ?? 0);
        $stock = (float)$this->sanitaze($_POST['stock'] ?? 0);
        $dateExpiration = !empty($_POST['date_expiration']) ? $this->sanitaze($_POST['date_expiration']) : null;
        $batchNumber = !empty($_POST['batch_number']) ? $this->sanitaze($_POST['batch_number']) : null;

        if (!$productId || $stock <= 0) {
            $this->status(400)->json(['error' => 'Produit et quantité valides requis']);
            return;
        }

        $productModel = new Product();
        $product = $productModel->findById($productId);
        if (!$product) {
            $this->status(404)->json(['error' => 'Produit introuvable']);
            return;
        }

        $batchModel = new ProductBatch();
        $id = $batchModel->create([
            'product_id' => $productId,
            'stock' => $stock,
            'date_expiration' => $dateExpiration,
            'batch_number' => $batchNumber,
            'date_reception' => date('Y-m-d'),
        ]);

        $this->logAudit('create', 'lot_produit', $id, [
            'produit' => $product['nom'],
            'quantite' => $stock,
            'date_expiration' => $dateExpiration,
        ]);

        $this->json(['success' => true, 'id' => $id]);
    }

    public function update()
    {
        if (!$this->requireAdmin()) {
            return;
        }

        $id = (int)$this->sanitaze($_POST['id'] ?? 0);
        $stock = (float)$this->sanitaze($_POST['stock'] ?? 0);
        $dateExpiration = !empty($_POST['date_expiration']) ? $this->sanitaze($_POST['date_expiration']) : null;
        $batchNumber = !empty($_POST['batch_number']) ? $this->sanitaze($_POST['batch_number']) : null;

        if (!$id) {
            $this->status(400)->json(['error' => 'ID lot manquant']);
            return;
        }

        $batchModel = new ProductBatch();
        $success = $batchModel->update($id, [
            'stock' => $stock,
            'date_expiration' => $dateExpiration,
            'batch_number' => $batchNumber,
        ]);

        if (!$success) {
            $this->status(404)->json(['error' => 'Lot introuvable']);
            return;
        }

        $this->logAudit('update', 'lot_produit', $id, ['stock' => $stock, 'date_expiration' => $dateExpiration]);
        $this->json(['success' => true]);
    }

    public function delete()
    {
        if (!$this->requireAdmin()) {
            return;
        }

        $id = (int)$this->sanitaze($_POST['id'] ?? 0);
        if (!$id) {
            $this->status(400)->json(['error' => 'ID lot manquant']);
            return;
        }

        $batchModel = new ProductBatch();
        $success = $batchModel->delete($id);

        if (!$success) {
            $this->status(404)->json(['error' => 'Lot introuvable']);
            return;
        }

        $this->logAudit('delete', 'lot_produit', $id, []);
        $this->json(['success' => true]);
    }

    /**
     * PAGE DES LOTS : liste paginée des lots de la boutique, triés par
     * péremption.
     *
     * Pagination KEYSET par défaut : le coût reste constant quelle que
     * soit la page. Le client passe la borne vue (`exp` + `id`) pour
     * obtenir la suivante.
     *
     * Le mode `offset` reste disponible (paramètre `mode=offset`) pour
     * un accès direct à une page — utile pour un lien « aller à la page
     * N », au prix d'un tri complet.
     */
    public function page()
    {
        if (!$this->requireAuth()) {
            return;
        }

        $limit  = (int)($_GET['limit'] ?? 50);
        $offset = (int)($_GET['offset'] ?? 0);
        $mode   = ($_GET['mode'] ?? 'keyset') === 'offset' ? 'offset' : 'keyset';

        $shopId = $this->isSuperAdmin() ? ($_GET['shop_id'] ?? null) : $this->getShopId();
        if ($shopId !== null) {
            $shopId = (int)$shopId;
        }

        $lastExpiration = !empty($_GET['exp']) ? $this->sanitaze($_GET['exp']) : null;
        $lastId         = isset($_GET['id']) && $_GET['id'] !== '' ? (int)$_GET['id'] : null;

        // Un lot n'est listé que s'il a du stock : un lot vidé (vendu)
        // ne pollue pas la liste des lots à surveiller.
        $onlyWithStock = ($_GET['with_stock'] ?? '1') !== '0';

        $batchModel = new ProductBatch();
        $result = $batchModel->getPage(
            $shopId,
            $limit,
            $offset,
            $mode,
            $lastExpiration,
            $lastId,
            $onlyWithStock
        );

        // La borne de la page suivante : le dernier couple (date, id)
        // affiché. C'est ce que le client renvoie pour la page suivante.
        $rows = $result['rows'];
        $next = null;
        if (!empty($rows)) {
            $last = end($rows);
            $next = [
                'exp' => $last['date_expiration'],
                'id'  => (int)$last['id'],
            ];
        }

        $this->json([
            'success' => true,
            'rows'    => $rows,
            'total'   => $result['total'],
            'limit'   => $result['limit'],
            'offset'  => $result['offset'],
            'mode'    => $mode,
            'next'    => $next,
            'has_more'=> count($rows) >= $result['limit'],
        ]);
    }

    public function alerts()
    {
        if (!$this->requireAuth()) {
            return;
        }

        $days = (int)($_GET['days'] ?? 7);
        $shopId = $this->isSuperAdmin() ? ($_GET['shop_id'] ?? null) : $this->getShopId();

        // ⚠️ La limite est EXPOSÉE mais bornée à 200 lignes. Sans borne,
        // cet endpoint retournait TOUS les lots en fin de série : mesuré
        // à 50 000 produits / 85 000 lots, 19 382 lignes pour une fenêtre
        // de 90 jours. Les compteurs donnent le total exact.
        $limit = (int)($_GET['limit'] ?? 50);
        $limit = max(1, min(200, $limit));

        $batchModel = new ProductBatch();
        $expiringSoon = $batchModel->getExpiringSoon($days, $shopId, $limit);
        $expired = $batchModel->getExpired($shopId, $limit);

        $this->json([
            'success' => true,
            'expiring_soon' => $expiringSoon,
            'expired' => $expired,
            'expiring_soon_total' => $batchModel->countExpiringSoon($days, $shopId),
            'expired_total' => $batchModel->countExpired($shopId),
        ]);
    }
}
