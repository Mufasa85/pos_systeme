<?php

namespace App\Controllers;

#require_once BASE_PATH . 'app/models/Product.php';
#require_once BASE_PATH . 'app/models/Sale.php';
#require_once BASE_PATH . 'app/models/User.php';

//use App\Models\Category;
use App\Models\User;
use App\Models\Product;
use App\Models\Sale;

class PageController
{
    public function __construct()
    {
        // if (!isset($_SESSION['user_id'])) {
        //   header('Location:/');cw
        /// exit;
        //}
    }

    private function render($view, $data = [])
    {
        $page = $view;
        extract($data);
        require_once dirname(__DIR__).DIRECTORY_SEPARATOR . 'views/layout/header.php';
        require_once dirname(__DIR__).DIRECTORY_SEPARATOR . 'views/' . $view . '.php';
        require_once  dirname(__DIR__).DIRECTORY_SEPARATOR . 'views/layout/footer.php';
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
        $this->render('caisse');
    }

    public function produits()
    {
        $productModel = new Product();
        $produits = $productModel->getAll();
        $this->render('produits', ['produits' => $produits]);
    }

    public function utilisateurs()
    {
        //if ($_SESSION['role'] !== 'admin') {
        //  header('Location: /dashboard');
        //exit;
        //}
        $userModel = new User();
        $utilisateurs = $userModel->getAllUsers();
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
        /// if ($_SESSION['role'] !== 'admin') {
        ///  header('Location: ' . APP_URL . '/dashboard');
        //exit;
        //}
        $this->render('parametres');
    }

    public function categories()
    {
        //  if ($_SESSION['role'] !== 'admin') {
        ///    header('Location: ' . APP_URL . '/dashboard');
        // exit;
        //}
        $productModel = new Product();

        // Get all products to count by category
        $produits = $productModel->getAll();
        $categories = [];
        $categoryCounts = [];

        foreach ($produits as $p) {
            $cat = $p['categorie'];
            if (!isset($categoryCounts[$cat])) {
                $categoryCounts[$cat] = 0;
            }
            $categoryCounts[$cat]++;
        }

        // Define default categories with colors
        $defaultCategories = [
            'Comestible' => '#0B5E88',
            'Non Comestible' => '#8B5E3C',
            'Service' => '#5E8B3C',
            //'Boissons' => '#3C8B8B',
            //'Alimentation' => '#8B3C5E',
            //'Hygiène' => '#5E3C8B',
           // 'Ménage' => '#8B8B3C'
        ];

        foreach ($defaultCategories as $name => $color) {
            $categories[] = [
                'id' => array_search($name, array_keys($defaultCategories)) + 1,
                'nom' => $name,
                'couleur' => $color,
                'nombre_produits' => $categoryCounts[$name] ?? 0
            ];
        }

        $this->render('categories', ['categories' => $categories]);
    }
}
