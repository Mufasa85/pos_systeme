<?php
// app/controllers/ProductController.php

require_once BASE_PATH . 'app/models/Product.php';

class ProductController {
    
    public function apiList() {
        header('Content-Type: application/json');
        $productModel = new Product();
        echo json_encode($productModel->getAll());
    }

    public function apiFind() {
        header('Content-Type: application/json');
        $barcode = $_GET['code_barres'] ?? '';
        if (!$barcode) {
            echo json_encode(['error' => 'Code-barre manquant']);
            return;
        }

        $productModel = new Product();
        $product = $productModel->findByBarcode($barcode);
        if ($product) {
            echo json_encode($product);
        } else {
            http_response_code(404);
            echo json_encode(['error' => 'Produit introuvable']);
        }
    }

    public function apiCreate() {
        header('Content-Type: application/json');
        if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
            http_response_code(403);
            echo json_encode(['error' => 'Accès refusé']);
            return;
        }

        $data = json_decode(file_get_contents('php://input'), true);
        if (!empty($data)) {
            $productModel = new Product();
            $id = $productModel->create($data);
            echo json_encode(['success' => true, 'id' => $id]);
        } else {
            http_response_code(400);
            echo json_encode(['error' => 'Données invalides']);
        }
    }
}
