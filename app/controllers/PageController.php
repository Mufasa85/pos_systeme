<?php

namespace App\Controllers;

//use App\Models\Category;
use App\Models\User;
use App\Models\Product;
use App\Models\Sale;
use App\Models\Settings;
use App\Models\Shop;
use App\Models\ServiceType;
use App\Models\Notification;
use App\controllers\Controller;

class PageController extends Controller
{
    public function __construct()
    {
        // Pas de redirection automatique dans le constructeur
        // Chaque méthode vérifie la session si nécessaire
    }

    private function render($view, $data = [])
    {
        // Vérifier la session AVANT d'afficher la page
        if (!isset($_SESSION['user_id'])) {
            // Redirection vers login
            $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? "https" : "http";
            $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
            header('Location: ' . $protocol . '://' . $host . '/');
            exit;
        }

        $page = $view;
        // Charger le nom du magasin et le type de service pour toutes les pages
        $settingsModel = new Settings();
        $shopModel = new Shop();
        $serviceTypeModel = new ServiceType();
        $shopId = $this->getShopId();
        
        // Get shop name from shops table instead of settings
        $storeName = 'Mon Magasin'; // default
        $serviceType = 'Caisse'; // default
        if ($shopId) {
            $shop = $shopModel->findById($shopId);
            if ($shop) {
                $storeName = $shop['nom'] ?? 'Mon Magasin';
                if (!empty($shop['service_type_id'])) {
                    $serviceTypeData = $serviceTypeModel->findById($shop['service_type_id']);
                    if ($serviceTypeData) {
                        $serviceType = $serviceTypeData['name'];
                    }
                }
            }
        }
        // Company info (super_admin) pour l'affichage du nom entreprise dans le layout
        if ($this->isSuperAdmin()) {
            try {
                $companyInfoModel = new \App\Models\CompanyInfo();
                $companyInfo = $companyInfoModel->get();
                $data['companyInfo'] = $companyInfo;
                $data['companyName'] = $companyInfo['name'] ?? 'Mon Entreprise';
            } catch (\Exception $e) {
                $data['companyInfo'] = ['name' => 'Mon Entreprise'];
                $data['companyName'] = 'Mon Entreprise';
            }
        } else {
            // éviter les undefined variables dans le layout
            $data['companyInfo'] = ['name' => null];
            $data['companyName'] = null;
        }

        $data['storeName'] = $storeName;
        // Pour le super_admin, forcer le type de service affiché à 'Caisse' (brut)
        $data['serviceType'] = $this->isSuperAdmin() ? 'Caisse' : $serviceType;

        $data['currentRole'] = $_SESSION['role'] ?? '';
        $data['currentShopId'] = $shopId;

        // Current user profile image
        $data['currentUserProfileImage'] = null;
        if (isset($_SESSION['user_id'])) {
            $userModel = new User();
            $currentUser = $userModel->exist($_SESSION['user_id']);
            if ($currentUser && !empty($currentUser['profile_image'])) {
                $data['currentUserProfileImage'] = $currentUser['profile_image'];
            }
        }

        // Notifications non lues
        
        try {
            $notifModel = new Notification();
            $data['unreadNotifications'] = $notifModel->getUnreadCount($_SESSION['user_id']);
        } catch (\Exception $e) {
            $data['unreadNotifications'] = 0;
        }
        extract($data);
        require_once dirname(__DIR__) . DIRECTORY_SEPARATOR . 'views/layout/header.php';
        require_once dirname(__DIR__) . DIRECTORY_SEPARATOR . 'views/' . $view . '.php';
        require_once  dirname(__DIR__) . DIRECTORY_SEPARATOR . 'views/layout/footer.php';
    }

    public function dashboard()
    {
        $saleModel = new Sale();
        $productModel = new Product();
        $shopId = $this->isSuperAdmin() ? null : $this->getShopId();
        $ventes = $saleModel->getAllSales($shopId);
        $produits = $productModel->getAll($shopId);

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

        // Multi-boutique stats pour super_admin
        $shopStats = [];
        if ($this->isSuperAdmin()) {
            $shopModel = new \App\Models\Shop();
            $allShops = $shopModel->getAll();
            $db = \App\Core\Database::getInstance();
            foreach ($allShops as $shop) {
                $sid = $shop['id'];
                $row = $db->fetch(
                    "SELECT COUNT(*) as nb, COALESCE(SUM(total),0) as total FROM ventes WHERE shop_id = ? AND DATE(date) = ?",
                    [$sid, $today]
                );
                $rowMonth = $db->fetch(
                    "SELECT COALESCE(SUM(total),0) as total FROM ventes WHERE shop_id = ? AND date BETWEEN ? AND ?",
                    [$sid, $mois_start, $mois_end]
                );
                $prodCount = $db->fetch(
                    "SELECT COUNT(*) as c FROM produits WHERE shop_id = ?",
                    [$sid]
                );
                $shopStats[] = [
                    'id' => $sid,
                    'nom' => $shop['nom'],
                    'code' => $shop['code'],
                    'ventes_jour' => $row['total'] ?? 0,
                    'nb_ventes_jour' => $row['nb'] ?? 0,
                    'ventes_mois' => $rowMonth['total'] ?? 0,
                    'produits' => $prodCount['c'] ?? 0,
                ];
            }
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
            }),
            'shopStats' => $shopStats,
            'isSuperAdmin' => $this->isSuperAdmin()
        ]);
    }

    public function caisse()
    {
        $categoryModel = new \App\Models\Category();
        $shopId = $this->isSuperAdmin() ? null : $this->getShopId();
        $categories = $categoryModel->all($shopId);

        // Charger les types de clients pour le select
        $typeClientModel = new \App\Models\TypeClient();
        $clientTypes = $typeClientModel->getAll();

        $this->render('caisse', [
            'categories' => $categories,
            'clientTypes' => $clientTypes
        ]);
    }

    public function recharges()
    {
        $categoryModel = new \App\Models\Category();
        $shopId = $this->isSuperAdmin() ? null : $this->getShopId();
        $categories = $categoryModel->all($shopId);
        $typeClientModel = new \App\Models\TypeClient();
        $clientTypes = $typeClientModel->getAll();

        $this->render('recharges', [
            'categories' => $categories,
            'clientTypes' => $clientTypes
        ]);
    }

    public function produits()
    {
        $productModel = new Product();
        $categoryModel = new \App\Models\Category();
        $taxModel = new \App\Models\Tax();

        $shopId = $this->isSuperAdmin() ? null : $this->getShopId();
        $produits = $productModel->getAll($shopId);
        $categories = $categoryModel->all($shopId);
        $taxes = $taxModel->getAll();

        $this->render('produits', [
            'produits' => $produits,
            'categories' => $categories,
            'taxes' => $taxes
        ]);
    }

    public function utilisateurs()
    {
        if (!$this->isAdmin() && !$this->isSuperAdmin()) {
            $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? "https" : "http";
            $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
            header('Location: ' . $protocol . '://' . $host . '/dashboard');
            exit;
        }
        $userModel = new User();
        $callerRole = $_SESSION['role'] ?? 'vendeur';
        $shopId = $this->isSuperAdmin() ? null : $this->getShopId();
        $utilisateurs = $userModel->all($shopId, $callerRole);

        // Charger les boutiques pour le super_admin
        $shops = [];
        if ($this->isSuperAdmin()) {
            $shopModel = new \App\Models\Shop();
            $shops = $shopModel->getAll();
        }

        $this->render('utilisateurs', ['utilisateurs' => $utilisateurs, 'shops' => $shops]);
    }

    public function otpCodes()
    {
        if (!$this->isSuperAdmin()) {
            $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? "https" : "http";
            $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
            header('Location: ' . $protocol . '://' . $host . '/dashboard');
            exit;
        }
        $this->render('otp-codes');
    }

    public function monProfil()
    {
        if (!isset($_SESSION['user_id'])) {
            $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? "https" : "http";
            $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
            header('Location: ' . $protocol . '://' . $host . '/');
            exit;
        }
        $userModel = new User();
        $currentUser = $userModel->exist($_SESSION['user_id']);
        $this->render('mon-profil', ['currentUser' => $currentUser]);
    }

    public function historique()
    {
        $saleModel = new Sale();
        $shopId = $this->isSuperAdmin() ? null : $this->getShopId();
        $ventes = $saleModel->getAllSales($shopId);
        $this->render('historique', ['ventes' => $ventes]);
    }

    public function analytics()
    {
        if (!$this->isAdmin() && !$this->isSuperAdmin()) {
            $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? "https" : "http";
            $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
            header('Location: ' . $protocol . '://' . $host . '/dashboard');
            exit;
        }

        $saleModel = new Sale();
        $saleDetailModel = new \App\Models\SaleDetail();
        $productModel = new Product();
        $userModel = new User();
        $categoryModel = new \App\Models\Category();
        $clientModel = new \App\Models\Client();

        // Gestion du filtre shop_id (super_admin)
        $filteredShopId = null;
        if ($this->isSuperAdmin() && isset($_GET['shop_id']) && $_GET['shop_id'] !== '') {
            $filteredShopId = (int)$_GET['shop_id'];
        }

        $shopId = $this->isSuperAdmin() ? $filteredShopId : $this->getShopId();
        $ventes = $saleModel->getAllSales($shopId);
        $produits = $productModel->getAll($shopId);
        $categories = $categoryModel->all($shopId);
        $users = $userModel->all($shopId);
        $clients = $clientModel->getAll($shopId);

        // Périodes
        $today = date('Y-m-d');
        $weekStart = date('Y-m-d 00:00:00', strtotime('-6 days'));
        $monthStart = date('Y-m-01 00:00:00');
        $yearStart = date('Y-01-01 00:00:00');
        $yearEnd = date('Y-12-31 23:59:59');

        $stats = [
            'today' => ['total' => 0, 'count' => 0],
            'week' => ['total' => 0, 'count' => 0],
            'month' => ['total' => 0, 'count' => 0],
            'year' => ['total' => 0, 'count' => 0],
            'all_time' => ['total' => 0, 'count' => 0],
        ];

        $salesByDay = [];
        $salesByMonth = [];
        $salesByHour = [];
        $salesBySeller = [];
        $revenueByCategory = [];
        $productQuantities = [];
        $clientSales = [];
        $rechargeVsProducts = ['products' => 0, 'recharges' => 0];

        $categoryNames = [];
        foreach ($categories as $cat) {
            $categoryNames[$cat['id']] = $cat['category'];
        }

        foreach ($ventes as $v) {
            $vDate = strtotime($v['date']);
            $vDateStr = date('Y-m-d', $vDate);
            $vMonthStr = date('Y-m', $vDate);
            $vHour = (int) date('H', $vDate);
            $total = (float) $v['total'];
            $sellerId = $v['vendeur_id'];
            $sellerName = $v['nom_vendeur'] ?? 'Inconnu';
            $clientId = $v['client_id'];
            $isRecharge = !empty($v['service']);

            // Stats globales
            $stats['all_time']['total'] += $total;
            $stats['all_time']['count']++;
            if ($vDateStr === $today) {
                $stats['today']['total'] += $total;
                $stats['today']['count']++;
            }
            if ($vDate >= strtotime($weekStart)) {
                $stats['week']['total'] += $total;
                $stats['week']['count']++;
            }
            if ($vDate >= strtotime($monthStart)) {
                $stats['month']['total'] += $total;
                $stats['month']['count']++;
            }
            if ($vDate >= strtotime($yearStart) && $vDate <= strtotime($yearEnd)) {
                $stats['year']['total'] += $total;
                $stats['year']['count']++;
            }

            // Recharge vs produits
            if ($isRecharge) {
                $rechargeVsProducts['recharges'] += $total;
            } else {
                $rechargeVsProducts['products'] += $total;
            }

            // Par jour
            $salesByDay[$vDateStr] = ($salesByDay[$vDateStr] ?? 0) + $total;
            // Par mois
            $salesByMonth[$vMonthStr] = ($salesByMonth[$vMonthStr] ?? 0) + $total;
            // Par heure
            $salesByHour[$vHour] = ($salesByHour[$vHour] ?? 0) + $total;
            // Par vendeur
            if (!isset($salesBySeller[$sellerId])) {
                $salesBySeller[$sellerId] = ['name' => $sellerName, 'total' => 0, 'count' => 0];
            }
            $salesBySeller[$sellerId]['total'] += $total;
            $salesBySeller[$sellerId]['count']++;

            // Par client
            if ($clientId) {
                $clientSales[$clientId] = ($clientSales[$clientId] ?? 0) + $total;
            }

        }

        // Détails des ventes pour les produits et catégories
        foreach ($ventes as $v) {
            $totalSale = (float) $v['total'];
            $details = $saleDetailModel->getBySaleId($v['id']);
            if (empty($details)) {
                continue;
            }
            foreach ($details as $d) {
                $pid = $d['produit_id'];
                $qty = (float) $d['quantite'];
                $lineTotal = (float) $d['prix'] * $qty;
                $productName = $d['produit_nom'] ?? 'Produit #' . $pid;
                $categoryId = 0;
                foreach ($produits as $p) {
                    if ($p['id'] == $pid) {
                        $categoryId = $p['category_id'];
                        break;
                    }
                }
                $categoryName = $categoryNames[$categoryId] ?? 'Non catégorisé';

                if (!isset($productQuantities[$pid])) {
                    $productQuantities[$pid] = ['name' => $productName, 'qty' => 0, 'revenue' => 0];
                }
                $productQuantities[$pid]['qty'] += $qty;
                $productQuantities[$pid]['revenue'] += $lineTotal;

                if (!isset($revenueByCategory[$categoryName])) {
                    $revenueByCategory[$categoryName] = 0;
                }
                $revenueByCategory[$categoryName] += $lineTotal;
            }
        }

        // Trier les top produits
        uasort($productQuantities, function ($a, $b) {
            return $b['revenue'] <=> $a['revenue'];
        });
        $topProducts = array_slice($productQuantities, 0, 10, true);

        // Trier les vendeurs
        uasort($salesBySeller, function ($a, $b) {
            return $b['total'] <=> $a['total'];
        });
        $topSellers = array_slice($salesBySeller, 0, 10, true);

        // Trier les clients
        arsort($clientSales);
        $topClients = array_slice($clientSales, 0, 10, true);
        $topClientsNames = [];
        foreach ($clients as $c) {
            if (isset($topClients[$c['id']])) {
                $topClientsNames[$c['id']] = $c['nom_client'];
            }
        }

        // Données pour les 30 derniers jours
        $dailyLabels = [];
        $dailyValues = [];
        for ($i = 29; $i >= 0; $i--) {
            $day = date('Y-m-d', strtotime("-$i days"));
            $dailyLabels[] = date('d/m', strtotime($day));
            $dailyValues[] = round($salesByDay[$day] ?? 0, 2);
        }

        // Données pour les 12 derniers mois
        $monthlyLabels = [];
        $monthlyValues = [];
        $monthNames = ['01' => 'Jan', '02' => 'Fév', '03' => 'Mar', '04' => 'Avr', '05' => 'Mai', '06' => 'Juin', '07' => 'Juil', '08' => 'Août', '09' => 'Sep', '10' => 'Oct', '11' => 'Nov', '12' => 'Déc'];
        for ($i = 11; $i >= 0; $i--) {
            $month = date('Y-m', strtotime("-$i months"));
            $monthlyLabels[] = ($monthNames[substr($month, 5, 2)] ?? substr($month, 5, 2)) . ' ' . substr($month, 0, 4);
            $monthlyValues[] = round($salesByMonth[$month] ?? 0, 2);
        }

        // Heures de vente
        $hourlyLabels = [];
        $hourlyValues = [];
        for ($i = 0; $i < 24; $i++) {
            $hourlyLabels[] = sprintf('%02dh', $i);
            $hourlyValues[] = round($salesByHour[$i] ?? 0, 2);
        }

        // Calculs KPI
        $averageBasket = $stats['all_time']['count'] > 0 ? $stats['all_time']['total'] / $stats['all_time']['count'] : 0;
        $bestDay = !empty($salesByDay) ? array_search(max($salesByDay), $salesByDay) : null;
        $bestDayAmount = $bestDay ? $salesByDay[$bestDay] : 0;
        $bestDayLabel = $bestDay ? date('d/m/Y', strtotime($bestDay)) : '-';
        $activeClients = count($clientSales);
        $totalClients = count($clients);
        $customerRate = $totalClients > 0 ? round(($activeClients / $totalClients) * 100, 1) : 0;
        $uniqueDays = count($salesByDay);
        $averageDailySales = $uniqueDays > 0 ? $stats['all_time']['total'] / $uniqueDays : 0;

        // Best seller
        $bestSeller = !empty($topSellers) ? reset($topSellers) : null;
        $bestSellerName = $bestSeller ? $bestSeller['name'] : '-';
        $bestSellerAmount = $bestSeller ? $bestSeller['total'] : 0;

        // Best product
        $bestProduct = !empty($topProducts) ? reset($topProducts) : null;
        $bestProductName = $bestProduct ? $bestProduct['name'] : '-';
        $bestProductRevenue = $bestProduct ? $bestProduct['revenue'] : 0;

        // Stock
        $stockAlerts = array_filter($produits, function ($p) {
            return $p['stock'] <= $p['stock_minimum'];
        });
        $stockOut = count(array_filter($produits, function ($p) {
            return (int) $p['stock'] === 0;
        }));

        $this->render('analytics', [
            'ventes' => $ventes,
            'produits' => $produits,
            'categories' => $categories,
            'users' => $users,
            'clients' => $clients,
            'stats' => $stats,
            'salesByDay' => $salesByDay,
            'salesByMonth' => $salesByMonth,
            'salesByHour' => $salesByHour,
            'salesBySeller' => $salesBySeller,
            'topSellers' => $topSellers,
            'topProducts' => $topProducts,
            'topClients' => $topClients,
            'topClientsNames' => $topClientsNames,
            'revenueByCategory' => $revenueByCategory,
            'rechargeVsProducts' => $rechargeVsProducts,
            'dailyLabels' => json_encode($dailyLabels),
            'dailyValues' => json_encode($dailyValues),
            'monthlyLabels' => json_encode($monthlyLabels),
            'monthlyValues' => json_encode($monthlyValues),
            'hourlyLabels' => json_encode($hourlyLabels),
            'hourlyValues' => json_encode($hourlyValues),
            'categoryLabels' => json_encode(array_keys($revenueByCategory)),
            'categoryValues' => json_encode(array_values($revenueByCategory)),
            'sellerLabels' => json_encode(array_values(array_map(function ($s) {
                return $s['name'];
            }, $topSellers))),
            'sellerValues' => json_encode(array_values(array_map(function ($s) {
                return $s['total'];
            }, $topSellers))),
            'rechargeValues' => json_encode([$rechargeVsProducts['products'], $rechargeVsProducts['recharges']]),
            'averageBasket' => $averageBasket,
            'averageDailySales' => $averageDailySales,
            'bestDayLabel' => $bestDayLabel,
            'bestDayAmount' => $bestDayAmount,
            'bestSellerName' => $bestSellerName,
            'bestSellerAmount' => $bestSellerAmount,
            'bestProductName' => $bestProductName,
            'bestProductRevenue' => $bestProductRevenue,
            'activeClients' => $activeClients,
            'totalClients' => $totalClients,
            'customerRate' => $customerRate,
            'stockAlerts' => $stockAlerts,
            'stockOut' => $stockOut,
            'shops' => $this->isSuperAdmin() ? (new \App\Models\Shop())->getAll() : [],
        ]);
    }

    public function parametres()
    {
        if (!$this->isAdmin() && !$this->isSuperAdmin()) {
            $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? "https" : "http";
            $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
            header('Location: ' . $protocol . '://' . $host . '/dashboard');
            exit;
        }
        $data = [];
        if ($this->isSuperAdmin()) {
            $data['shops'] = (new \App\Models\Shop())->getAll();
        }
        $this->render('parametres', $data);
    }

    public function taxes()
    {
        if (!$this->isAdmin() && !$this->isSuperAdmin()) {
            $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? "https" : "http";
            $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
            header('Location: ' . $protocol . '://' . $host . '/dashboard');
            exit;
        }
        $taxModel = new \App\Models\Tax();
        $taxes = $taxModel->getAll();
        $this->render('taxes', ['taxes' => $taxes]);
    }

    public function categories()
    {
        if (!$this->isAdmin() && !$this->isSuperAdmin()) {
            $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? "https" : "http";
            $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
            header('Location: ' . $protocol . '://' . $host . '/dashboard');
            exit;
        }
        $productModel = new Product();
        $categoryModel = new \App\Models\Category();

        $shopId = $this->isSuperAdmin() ? null : $this->getShopId();

        // Get all categories from database
        $dbCategories = $categoryModel->all($shopId);

        // Get all products to count by category
        $produits = $productModel->getAll($shopId);
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

    public function shops()
    {
        if (!$this->isSuperAdmin()) {
            $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? "https" : "http";
            $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
            header('Location: ' . $protocol . '://' . $host . '/dashboard');
            exit;
        }
        $shopModel = new \App\Models\Shop();
        $shops = $shopModel->getAll();
        $this->render('shops', ['shops' => $shops]);
    }

    public function scanner()
    {
        // Vérifier la session
        if (!isset($_SESSION['user_id'])) {
            $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? "https" : "http";
            $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
            header('Location: ' . $protocol . '://' . $host . '/');
            exit;
        }

        // Pour le scanner, on charge une page spéciale sans le layout principal
        $settingsModel = new Settings();
        $storeName = $settingsModel->get('store_name') ?? 'Mon Magasin';
        $baseUrl = $this->getBaseUrl();

        // Rendre la page scanner directement (sans header/footer du layout)
        require_once dirname(__DIR__) . DIRECTORY_SEPARATOR . 'views/scanner.php';
    }

    // Nouveau scanner propre
    public function newScanner()
    {
        // Vérifier la session
        if (!isset($_SESSION['user_id'])) {
            $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? "https" : "http";
            $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
            header('Location: ' . $protocol . '://' . $host . '/');
            exit;
        }

        // Charger la nouvelle page scanner
        require_once dirname(__DIR__) . DIRECTORY_SEPARATOR . 'views/new-scanner.php';
    }

    private function getBaseUrl()
    {
        $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? "https" : "http";
        $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
        return $protocol . '://' . $host;
    }
}
