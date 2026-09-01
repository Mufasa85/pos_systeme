<?php

namespace App\Controllers;

use App\Models\Product;
use App\Models\ProductBatch;
use App\controllers\Controller;
use App\Services\ImageProcessor;

class ProductController extends Controller
{
    public function index()
    {
        $productModel = new Product();
        $shopId = $this->isSuperAdmin() ? null : $this->getShopId();
        $this->json($productModel->getAll($shopId));
    }

    public function find()
    {
        $barcode = $this->sanitaze(trim($_GET['code_barres'] ?? ''));
        if (!$barcode) {
            $this->json(['error' => 'Code-barre manquant']);
            return;
        }

        $productModel = new Product();
        $product = $productModel->findByBarcode($barcode);
        if ($product) {
            echo json_encode($product);
        } else {
            self::status(404)->json(['error' => 'Produit introuvable']);
        }
    }

    public function create()
    {
        if (!$this->requireAdmin()) return;

        $data = [
            'code_barres' => $this->sanitaze($_POST['code_barres']),
            'nom' => $this->sanitaze($_POST['nom']),
            'category_id' => (int)$this->sanitaze($_POST['category_id']),
            'prix' => (float)$this->sanitaze($_POST['prix']),
            'stock' => (int)$this->sanitaze($_POST['stock']),
            'stock_minimum' => (int)$this->sanitaze($_POST['stock_minimum']),
            'taxe_id' => (int)$this->sanitaze($_POST['taxe_id']) ?: 1,
            'product_type' => $this->sanitaze($_POST['product_type']) ?: 'unite',
            'prod_service' => $this->sanitaze($_POST['prod_service']) ?: null,
            'remise_type' => in_array($this->sanitaze($_POST['remise_type']), ['%', 'CDF']) ? $this->sanitaze($_POST['remise_type']) : '%',
            'remise_value' => (float)$this->sanitaze($_POST['remise_value']) ?: 0,
            'taxe_specifique_type' => in_array($this->sanitaze($_POST['taxe_specifique_type'] ?? '%'), ['%', 'CDF']) ? $this->sanitaze($_POST['taxe_specifique_type'] ?? '%') : '%',
            'taxe_specifique_value' => (float)$this->sanitaze($_POST['taxe_specifique_value'] ?? 0) ?: 0,
            'date_expiration' => !empty($_POST['date_expiration']) ? $this->sanitaze($_POST['date_expiration']) : null
        ];

        // Vérifier si le code-barres existe déjà
        $productModel = new Product();
        $existingProduct = $productModel->findByBarcode($data['code_barres']);
        if ($existingProduct) {
            $this->status(409)->json(['error' => 'Ce code-barres existe déjà pour le produit: ' . $existingProduct['nom']]);
            return;
        }

        // Image upload
        $processedImage = $this->processProductImage($data['nom']);
        if ($processedImage) {
            $data['image'] = $processedImage;
        }

        $data['shop_id'] = $this->getShopId();

        $productModel = new Product();
        $id = $productModel->create($data);

        // Creer un lot initial si un stock est fourni
        if ($id && (float)$data['stock'] > 0) {
            $batchModel = new ProductBatch();
            $batchModel->create([
                'product_id' => $id,
                'batch_number' => null,
                'stock' => (float)$data['stock'],
                'date_expiration' => $data['date_expiration'] ?? null,
                'date_reception' => date('Y-m-d')
            ]);
        }

        $this->logAudit('create', 'produit', $id, ['nom' => $data['nom']]);
        $this->json(['success' => true, 'id' => $id]);
    }

    public function update()
    {
        if (!$this->requireAdmin()) return;

        $id = $this->sanitaze($_POST['id'] ?? null);
        if (!$id) {
            $this->status(400)->json(['error' => 'ID manquant']);
            return;
        }

        // Récupérer l'ancien produit pour conserver l'image existante
        $productModel = new Product();
        $oldProduct = $productModel->findById($id);
        $oldImage = $oldProduct ? $oldProduct['image'] : '';

        // Vérifier si le code-barres existe déjà pour un autre produit
        $newBarcode = $this->sanitaze($_POST['code_barres']);
        $existingProduct = $productModel->findByBarcode($newBarcode);
        if ($existingProduct && $existingProduct['id'] != $id) {
            $this->status(409)->json(['error' => 'Ce code-barres existe déjà pour le produit: ' . $existingProduct['nom']]);
            return;
        }

        $data = [
            'code_barres' => $this->sanitaze($_POST['code_barres']),
            'nom' => $this->sanitaze($_POST['nom']),
            'category_id' => (int)$this->sanitaze($_POST['category_id']),
            'prix' => (float)$this->sanitaze($_POST['prix']),
            'stock' => (int)$this->sanitaze($_POST['stock']),
            'stock_minimum' => (int)$this->sanitaze($_POST['stock_minimum']),
            'taxe_id' => (int)$this->sanitaze($_POST['taxe_id']) ?: 1,
            'product_type' => $this->sanitaze($_POST['product_type']) ?: 'unite',
            'prod_service' => $this->sanitaze($_POST['prod_service']) ?: null,
            'remise_type' => in_array($this->sanitaze($_POST['remise_type']), ['%', 'CDF']) ? $this->sanitaze($_POST['remise_type']) : '%',
            'remise_value' => (float)$this->sanitaze($_POST['remise_value']) ?: 0,
            'taxe_specifique_type' => in_array($this->sanitaze($_POST['taxe_specifique_type'] ?? '%'), ['%', 'CDF']) ? $this->sanitaze($_POST['taxe_specifique_type'] ?? '%') : '%',
            'taxe_specifique_value' => (float)$this->sanitaze($_POST['taxe_specifique_value'] ?? 0) ?: 0,
            'image' => $oldImage,
            'date_expiration' => !empty($_POST['date_expiration']) ? $this->sanitaze($_POST['date_expiration']) : null
        ];

        // Image upload - seulement si une nouvelle image est uploadée
        $processedImage = $this->processProductImage($data['nom']);
        if ($processedImage) {
            $data['image'] = $processedImage;
            // Supprimer l'ancienne image (nouveau format uniquement, hors public/)
            if ($oldImage && strpos($oldImage, 'media/product/') === 0) {
                $oldPath = dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . 'storage' . DIRECTORY_SEPARATOR . 'uploads' . DIRECTORY_SEPARATOR . 'products' . DIRECTORY_SEPARATOR . basename($oldImage);
                if (is_file($oldPath)) {
                    @unlink($oldPath);
                }
            }
        }

        $data['shop_id'] = $this->getShopId();

        // Notification si le prix a changé
        if ($oldProduct && (float)$oldProduct['prix'] !== (float)$data['prix']) {
            $this->notifySuperAdmins(
                'suspicious_action',
                'Modification de prix',
                "Le prix du produit \"{$data['nom']}\" a été modifié de {$oldProduct['prix']} à {$data['prix']} par " . ($_SESSION['nom_complet'] ?? 'inconnu'),
                '/produits'
            );
        }

        $success = $productModel->update($id, $data);

        $this->logAudit('update', 'produit', $id, ['nom' => $data['nom']]);
        $this->json(['success' => $success]);
    }

    public function delete()
    {
        if (!$this->requireAdmin()) return;

        $id = $this->sanitaze((int)$_POST['id']);

        if ($id > 0) {
            $productModel = new Product();
            $success = $productModel->delete($id);
            $this->json(['success' => $success]);
        } else {
            $this->status(400)->json(['error' => 'ID manquant ' . $id]);
        }
    }

    /**
     * Valide, redimensionne/compresse et stocke l'image produit hors de public/.
     * Retourne la référence relative à stocker en base ('media/product/xxx.jpg') ou null.
     */
    private function processProductImage(string $productName): ?string
    {
        if (!isset($_FILES['image']) || $_FILES['image']['error'] !== UPLOAD_ERR_OK) {
            return null;
        }

        $file = $_FILES['image'];
        $maxSize = 5 * 1024 * 1024; // 5MB avant compression
        if ($file['size'] > $maxSize) {
            return null;
        }

        if (!ImageProcessor::validate($file['tmp_name'])) {
            return null;
        }

        $uploadDir = dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . 'storage' . DIRECTORY_SEPARATOR . 'uploads' . DIRECTORY_SEPARATOR . 'products';
        if (!is_dir($uploadDir)) {
            mkdir($uploadDir, 0755, true);
        }

        $slug = preg_replace('/[^a-z0-9_-]/', '_', strtolower($productName));
        $slug = trim(preg_replace('/_+/', '_', $slug), '_') ?: 'produit';
        $fileName = $slug . '_' . uniqid() . '.jpg';
        $filePath = $uploadDir . DIRECTORY_SEPARATOR . $fileName;

        if (!ImageProcessor::resizeAndSave($file['tmp_name'], $filePath, 1000, 82)) {
            return null;
        }

        return 'media/product/' . $fileName;
    }
}
