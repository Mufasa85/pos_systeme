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

        $data = [
            'code_barres' => $_POST['code_barres'],
            'nom' => $_POST['nom'],
            'categorie' => $_POST['categorie'],
            'prix' => $_POST['prix'],
            'stock' => $_POST['stock'],
            'stock_minimum' => $_POST['stock_minimum']
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
        echo json_encode(['success' => true, 'id' => $id]);
    }

    public function apiUpdate() {
        header('Content-Type: application/json');
        if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
            http_response_code(403);
            echo json_encode(['error' => 'Accès refusé']);
            return;
        }

        $id = $_POST['id'] ?? null;
        if (!$id) {
            http_response_code(400);
            echo json_encode(['error' => 'ID manquant']);
            return;
        }

        $data = [
            'code_barres' => $_POST['code_barres'],
            'nom' => $_POST['nom'],
            'categorie' => $_POST['categorie'],
            'prix' => $_POST['prix'],
            'stock' => $_POST['stock'],
            'stock_minimum' => $_POST['stock_minimum']
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
        $success = $productModel->update($id, $data);
        echo json_encode(['success' => $success]);
    }

    public function apiDelete() {
        header('Content-Type: application/json');
        if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
            http_response_code(403);
            echo json_encode(['error' => 'Accès refusé']);
            return;
        }

        $id = $_POST['id'] ?? $_GET['id'] ?? 0;
        if ($id) {
            $productModel = new Product();
            $success = $productModel->delete($id);
            echo json_encode(['success' => $success]);
        } else {
            http_response_code(400);
            echo json_encode(['error' => 'ID manquant']);
        }
    }
}


