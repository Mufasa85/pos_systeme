<?php

namespace App\Controllers;

//use App\Models\Category;
use App\Models\User;
use App\Models\Product;
use App\Models\Sale;
use App\Models\Settings;

class PageController
{
    public function __construct()
    {

        if (!isset($_SESSION['user_id'])) {
            header('Location: http://localhost:8000/');
            exit;
        }
    }

    private function render($view, $data = [])
    {
        $page = $view;
        // Charger le nom du magasin pour toutes les pages
        $settingsModel = new Settings();
        $data['storeName'] = $settingsModel->get('store_name') ?? 'Mon Magasin';
        extract($data);
        require_once dirname(__DIR__) . DIRECTORY_SEPARATOR . 'views/layout/header.php';
        require_once dirname(__DIR__) . DIRECTORY_SEPARATOR . 'views/' . $view . '.php';
        require_once  dirname(__DIR__) . DIRECTORY_SEPARATOR . 'views/layout/footer.php';
    }

    public function dashboard()
    {
        $saleModel = new Sale();
        $productModel = new Product();
        $ventes = $saleModel->getAllSales();
        $produits = $productModel->getAll();

        $today = date('Y-m-d');
        $semaine_start = date('Y-m-d', strtotime('-6 days'));
        $ventes_jour = 0;
        $ventes_semaine = 0;
        foreach ($ventes as $v) {
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
            'stock_faible' => array_filter($produits, function ($p) {
                return $p['stock'] <= $p['stock_minimum'];
            })
        ]);
    }

    public function caisse()
    {
        $categoryModel = new \App\Models\Category();
        $categories = $categoryModel->all();
        $this->render('caisse', ['categories' => $categories]);
    }

    public function produits()
    {
        $productModel = new Product();
        $categoryModel = new \App\Models\Category();

        $produits = $productModel->getAll();
        $categories = $categoryModel->all();

        $this->render('produits', [
            'produits' => $produits,
            'categories' => $categories
        ]);
    }

    public function utilisateurs()
    {
        if ($_SESSION['role'] !== 'admin') {
            header('Location: /dashboard');
            exit;
        }
        $userModel = new User();
        $utilisateurs = $userModel->all();
        $this->render('utilisateurs', ['utilisateurs' => $utilisateurs]);
    }

    public function historique()
    {
        $saleModel = new Sale();
        $ventes = $saleModel->getAllSales();
        $this->render('historique', ['ventes' => $ventes]);
    }

    public function parametres()
    {
        if ($_SESSION['role'] !== 'admin') {
            header('Location: /dashboard');
            exit;
        }
        $this->render('parametres');
    }

    public function categories()
    {
        if ($_SESSION['role'] !== 'admin') {
            header('Location: /dashboard');
            exit;
        }
        $productModel = new Product();
        $categoryModel = new \App\Models\Category();

        // Get all categories from database
        $dbCategories = $categoryModel->all();

        // Get all products to count by category
        $produits = $productModel->getAll();
        $categoryCounts = [];

        foreach ($produits as $p) {
            $cat = $p['categorie'];
            if (!isset($categoryCounts[$cat])) {
                $categoryCounts[$cat] = 0;
            }
            $categoryCounts[$cat]++;
        }

        // Build categories array from database with product counts
        $categories = [];
        $colors = ['#0B5E88', '#8B5E3C', '#5E8B3C', '#3C8B8B', '#8B3C5E', '#5E3C8B', '#8B8B3C'];
        $colorIndex = 0;

        foreach ($dbCategories as $c) {
            $categories[] = [
                'id' => $c['id'],
                'nom' => $c['category'],
                'couleur' => $c['couleur'] ?? $colors[$colorIndex % count($colors)],
                'nombre_produits' => $categoryCounts[$c['category']] ?? 0
            ];
            $colorIndex++;
        }

        $this->render('categories', ['categories' => $categories]);
    }
}
