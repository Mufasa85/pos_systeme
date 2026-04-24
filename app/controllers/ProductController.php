<?php

namespace App\Controllers;

use App\Models\Product;
use App\controllers\Controller;

class ProductController extends Controller
{
    public function index()
    {
        $productModel = new Product();
        $this->json($productModel->getAll());
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
        if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
            self::status(403)->json(['error' => 'Accès refusé']);
            return;
        }

        $data = [
            'code_barres' => $this->sanitaze($_POST['code_barres']),
            'nom' => $this->sanitaze($_POST['nom']),
            'category_id' => (int)$this->sanitaze($_POST['category_id']),
            'prix' => (float)$this->sanitaze($_POST['prix']),
            'stock' => (int)$this->sanitaze($_POST['stock']),
            'stock_minimum' => (int)$this->sanitaze($_POST['stock_minimum'])
        ];

        // Image upload
        if (isset($_FILES['image']) && $_FILES['image']['error'] === UPLOAD_ERR_OK) {
            $uploadDir = BASE_PATH . 'public/assets/img/products/';
            if (!is_dir($uploadDir)) {
                mkdir($uploadDir, 0755, true);
            }
            $fileName = uniqid() . '_' . basename($_FILES['image']['name']);
            $filePath = $uploadDir . $fileName;
            if (move_uploaded_file($_FILES['image']['tmp_name'], $filePath)) {
                $data['image'] = 'assets/img/products/' . $fileName;
            }
        }

        $productModel = new Product();
        $id = $productModel->create($data);
        $this->json(['success' => true, 'id' => $id]);
    }

    public function update()
    {
        if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
            $this->status(403)->json(['error' => 'Accès refusé']);
            return;
        }

        $id = $this->sanitaze($_POST['id'] ?? null);
        if (!$id) {
            $this->status(400)->json(['error' => 'ID manquant']);
            return;
        }

        $data = [
            'code_barres' => $this->sanitaze($_POST['code_barres']),
            'nom' => $this->sanitaze($_POST['nom']),
            'category_id' => (int)$this->sanitaze($_POST['category_id']),
            'prix' => (float)$this->sanitaze($_POST['prix']),
            'stock' => (int)$this->sanitaze($_POST['stock']),
            'stock_minimum' => (int)$this->sanitaze($_POST['stock_minimum'])
        ];

        // Image upload
        if (isset($_FILES['image']) && $_FILES['image']['error'] === UPLOAD_ERR_OK) {
            $uploadDir = dirname(__DIR__, 2) . 'public/assets/img/products/';
            if (!is_dir($uploadDir)) {
                mkdir($uploadDir, 0755, true);
            }
            $fileName = uniqid() . '_' . basename($_FILES['image']['name']);
            $filePath = $uploadDir . $fileName;
            if (move_uploaded_file($_FILES['image']['tmp_name'], $filePath)) {
                $data['image'] = 'assets/img/products/' . $fileName;
            }
        }

        $productModel = new Product();
        $success = $productModel->update($id, $data);
        $this->json(['success' => $success]);
    }

    public function delete()
    {
        if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
            $this->status(403)->json(['error' => 'Accès refusé']);
            return;
        }

        $id = $this->sanitaze((int)$_POST['id']);

        if ($id > 0) {
            $productModel = new Product();
            $success = $productModel->delete($id);
            $this->json(['success' => $success]);
        } else {
            $this->status(400)->json(['error' => 'ID manquant '.$id]);
        }
    }
}
