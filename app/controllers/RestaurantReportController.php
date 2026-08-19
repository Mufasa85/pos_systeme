<?php

namespace App\Controllers;

use App\controllers\Controller;

class RestaurantReportController extends Controller
{
    public function index()
    {
        if (!$this->requireAdmin()) return;

        $shopId = $this->isSuperAdmin() ? ($_GET['shop_id'] ?? null) : $this->getShopId();
        $db = \App\Core\Database::getInstance();

        $where = "WHERE v.service = 'restaurant'";
        $params = [];
        if ($shopId) {
            $where .= " AND v.shop_id = ?";
            $params[] = $shopId;
        }

        // Nombre de commandes payées + CA
        $totals = $db->fetch(
            "SELECT COUNT(*) as nb_commandes, COALESCE(SUM(v.total),0) as ca
             FROM ventes v $where",
            $params
        );

        // Ventes par jour (30 derniers jours)
        $byDay = $db->fetchAll(
            "SELECT DATE(v.date) as jour, COUNT(*) as nb, COALESCE(SUM(v.total),0) as total
             FROM ventes v $where AND v.date >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)
             GROUP BY DATE(v.date) ORDER BY jour ASC",
            $params
        );

        // Ventes par mois (12 derniers mois)
        $byMonth = $db->fetchAll(
            "SELECT DATE_FORMAT(v.date, '%Y-%m') as mois, COUNT(*) as nb, COALESCE(SUM(v.total),0) as total
             FROM ventes v $where AND v.date >= DATE_SUB(CURDATE(), INTERVAL 12 MONTH)
             GROUP BY DATE_FORMAT(v.date, '%Y-%m') ORDER BY mois ASC",
            $params
        );

        // Ventes par serveur
        $byServeur = $db->fetchAll(
            "SELECT u.nom_complet, COUNT(*) as nb, COALESCE(SUM(v.total),0) as total
             FROM ventes v LEFT JOIN utilisateurs u ON v.vendeur_id = u.id
             $where GROUP BY v.vendeur_id ORDER BY total DESC",
            $params
        );

        // Plats les plus vendus (à partir des commandes payées liées aux ventes)
        $whereOrders = "WHERE c.statut = 'payee'";
        $paramsOrders = [];
        if ($shopId) {
            $whereOrders .= " AND c.shop_id = ?";
            $paramsOrders[] = $shopId;
        }
        $topPlats = $db->fetchAll(
            "SELECT m.nom, SUM(d.quantite) as quantite_totale, SUM(d.quantite * d.prix_unitaire) as ca_genere
             FROM restaurant_commande_details d
             INNER JOIN restaurant_commandes c ON d.commande_id = c.id
             INNER JOIN restaurant_menu_items m ON d.menu_item_id = m.id
             $whereOrders
             GROUP BY d.menu_item_id
             ORDER BY quantite_totale DESC
             LIMIT 10",
            $paramsOrders
        );

        $this->json([
            'nb_commandes' => (int)($totals['nb_commandes'] ?? 0),
            'ca' => (float)($totals['ca'] ?? 0),
            'par_jour' => $byDay,
            'par_mois' => $byMonth,
            'par_serveur' => $byServeur,
            'top_plats' => $topPlats,
        ]);
    }
}
