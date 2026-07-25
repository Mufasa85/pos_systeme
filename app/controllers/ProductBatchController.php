<?php

namespace App\Controllers;

use App\Models\ProductBatch;
use App\Models\Product;
use App\controllers\Controller;

class ProductBatchController extends Controller
{
    public function index()
    {
        if (!$this->requireAuth()) return;

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
        if (!$this->requireAdmin()) return;

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
            'date_reception' => date('Y-m-d')
        ]);

        $this->logAudit('create', 'lot_produit', $id, [
            'produit' => $product['nom'],
            'quantite' => $stock,
            'date_expiration' => $dateExpiration
        ]);

        $this->json(['success' => true, 'id' => $id]);
    }

    public function update()
    {
        if (!$this->requireAdmin()) return;

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
            'batch_number' => $batchNumber
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
        if (!$this->requireAdmin()) return;

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

    public function alerts()
    {
        if (!$this->requireAuth()) return;

        $days = (int)($_GET['days'] ?? 7);
        $shopId = $this->isSuperAdmin() ? ($_GET['shop_id'] ?? null) : $this->getShopId();

        $batchModel = new ProductBatch();
        $expiringSoon = $batchModel->getExpiringSoon($days, $shopId);
        $expired = $batchModel->getExpired($shopId);

        $this->json([
            'success' => true,
            'expiring_soon' => $expiringSoon,
            'expired' => $expired
        ]);
    }
}
