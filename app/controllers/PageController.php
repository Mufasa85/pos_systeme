<?php
// app/controllers/PageController.php

require_once BASE_PATH . 'app/models/Product.php';
require_once BASE_PATH . 'app/models/Sale.php';
require_once BASE_PATH . 'app/models/User.php';

class PageController {
    public function __construct() {
        if (!isset($_SESSION['user_id'])) {
            header('Location: ' . APP_URL . '/');
            exit;
        }
    }

    private function render($view, $data = []) {
        $page = $view;
        extract($data);
        require_once BASE_PATH . 'app/views/layout/header.php';
        require_once BASE_PATH . 'app/views/' . $view . '.php';
        require_once BASE_PATH . 'app/views/layout/footer.php';
    }

    public function dashboard() {
        $saleModel = new Sale();
        $productModel = new Product();
        $ventes = $saleModel->getAllSales();
        $produits = $productModel->getAll();
        
        $today = date('Y-m-d');
        $semaine_start = date('Y-m-d', strtotime('-6 days'));
        $ventes_jour = 0;
        $ventes_semaine = 0;
        foreach($ventes as $v) {
            if (strpos($v['date'], $today) === 0) {
                $ventes_jour += $v['total'];
            }
            if (strpos($v['date'], $semaine_start) === 0) {
                $ventes_semaine += $v['total'];
            }
        }

        $this->render('dashboard', [
            'ventes' => $ventes,
            'produits_compte' => count($produits),
            'ventes_jour' => $ventes_jour,
            'ventes_semaine' => $ventes_semaine,
            'stock_faible' => array_filter($produits, function($p) { return $p['stock'] <= $p['stock_minimum']; })
        ]);
    }

    public function caisse() {
        $this->render('caisse');
    }

    public function produits() {
        $productModel = new Product();
        $produits = $productModel->getAll();
        $this->render('produits', ['produits' => $produits]);
    }

    public function utilisateurs() {
        if ($_SESSION['role'] !== 'admin') {
            header('Location: ' . APP_URL . '/dashboard');
            exit;
        }
        $userModel = new User();
        $utilisateurs = $userModel->getAllUsers();
        $this->render('utilisateurs', ['utilisateurs' => $utilisateurs]);
    }

    public function historique() {
        $saleModel = new Sale();
        $ventes = $saleModel->getAllSales();
        $this->render('historique', ['ventes' => $ventes]);
    }

    public function parametres() {
        if ($_SESSION['role'] !== 'admin') {
            header('Location: ' . APP_URL . '/dashboard');
            exit;
        }
        $this->render('parametres');
    }
}
