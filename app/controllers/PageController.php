<?php

namespace App\Controllers;

use App\Models\User;
use App\Models\Product;
use App\Models\Sale;
use App\Models\Settings;

class PageController
{
    public function __construct()
    {
        // Ne rien faire ici - pas de redirection automatique
    }

    private function render($view, $data = [])
    {
        // Vérifier la session AVANT d'afficher la page
        if (!isset($_SESSION['user_id'])) {
            // Juste rediriger vers /, chemin relatif
            header('Location: /');
            exit;
        }

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
        $today_start = date('Y-m-d 00:00:00');
        $today_end = date('Y-m-d 23:59:59');
        $semaine_start = date('Y-m-d', strtotime('-6 days'));
        $semaine_start_dt = date('Y-m-d 00:00:00', strtotime('-6 days'));
        $semaine_end = date('Y-m-d 23:59:59');
        $mois_start = date('Y-m-01 00:00:00');
        $mois_end = date('Y-m-t 23:59:59');

        // Calculs des ventes par période
        $ventes_jour = 0;
        $ventes_semaine = 0;
        $ventes_mois = 0;
        $nb_ventes_jour = 0;
        $nb_ventes_semaine = 0;
        $nb_ventes_mois = 0;

        // Données pour le graphique des 7 derniers jours
        $chart_data = [];
        for ($i = 6; $i >= 0; $i--) {
            $day = date('Y-m-d', strtotime("-$i days"));
            $chart_data[$day] = ['total' => 0, 'count' => 0];
        }

        foreach ($ventes as $v) {
            $v_date = strtotime($v['date']);
            $v_date_str = date('Y-m-d', $v_date);

            // Aujourd'hui
            if ($v_date >= strtotime($today_start) && $v_date <= strtotime($today_end)) {
                $ventes_jour += $v['total'];
                $nb_ventes_jour++;
            }

            // Cette semaine
            if ($v_date >= strtotime($semaine_start_dt) && $v_date <= strtotime($semaine_end)) {
                $ventes_semaine += $v['total'];
                $nb_ventes_semaine++;
            }

            // Ce mois
            if ($v_date >= strtotime($mois_start) && $v_date <= strtotime($mois_end)) {
                $ventes_mois += $v['total'];
                $nb_ventes_mois++;
            }

            // Graphique - 7 derniers jours
            if (isset($chart_data[$v_date_str])) {
                $chart_data[$v_date_str]['total'] += $v['total'];
                $chart_data[$v_date_str]['count']++;
            }
        }

        // Préparer les données pour Chart.js
        $chart_labels = [];
        $chart_values = [];
        foreach ($chart_data as $date => $data) {
            $chart_labels[] = date('d/m', strtotime($date));
            $chart_values[] = round($data['total'], 2);
        }

        $this->render('dashboard', [
            'ventes' => $ventes,
            'produits_compte' => count($produits),
            'ventes_jour' => $ventes_jour,
            'ventes_semaine' => $ventes_semaine,
            'ventes_mois' => $ventes_mois,
            'nb_ventes_jour' => $nb_ventes_jour,
            'nb_ventes_semaine' => $nb_ventes_semaine,
            'nb_ventes_mois' => $nb_ventes_mois,
            'chart_labels' => json_encode($chart_labels),
            'chart_values' => json_encode($chart_values),
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
        if (!isset($_SESSION['user_id'])) {
            header('Location: /');
            exit;
        }
        if ($_SESSION['role'] !== 'admin') {
            header('Location: /');
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
        if (!isset($_SESSION['user_id'])) {
            header('Location: /');
            exit;
        }
        if ($_SESSION['role'] !== 'admin') {
            header('Location: /');
            exit;
        }
        $this->render('parametres');
    }

    public function categories()
    {
        if (!isset($_SESSION['user_id'])) {
            header('Location: /');
            exit;
        }
        if ($_SESSION['role'] !== 'admin') {
            header('Location: /');
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
