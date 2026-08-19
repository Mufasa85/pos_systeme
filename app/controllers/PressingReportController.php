<?php

namespace App\Controllers;

use App\controllers\Controller;

class PressingReportController extends Controller
{
    public function index()
    {
        if (!$this->requireAdmin()) return;

        $shopId = $this->isSuperAdmin() ? ($_GET['shop_id'] ?? null) : $this->getShopId();
        $db = \App\Core\Database::getInstance();

        $where = "WHERE (d.vente_id IS NOT NULL OR d.paid_amount > 0)";
        $params = [];
        if ($shopId) {
            $where .= " AND d.shop_id = ?";
            $params[] = $shopId;
        }

        // Totaux
        $totals = $db->fetch(
            "SELECT COUNT(*) as nb_depots, COALESCE(SUM(d.paid_amount),0) as revenus
             FROM pressing_depots d $where",
            $params
        );

        // Revenus journaliers (30 derniers jours)
        $byDay = $db->fetchAll(
            "SELECT DATE(d.date_reception) as jour, COUNT(*) as nb, COALESCE(SUM(d.paid_amount),0) as total
             FROM pressing_depots d $where AND d.date_reception >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)
             GROUP BY DATE(d.date_reception) ORDER BY jour ASC",
            $params
        );

        // Revenus mensuels (12 derniers mois)
        $byMonth = $db->fetchAll(
            "SELECT DATE_FORMAT(d.date_reception, '%Y-%m') as mois, COUNT(*) as nb, COALESCE(SUM(d.paid_amount),0) as total
             FROM pressing_depots d $where AND d.date_reception >= DATE_SUB(CURDATE(), INTERVAL 12 MONTH)
             GROUP BY DATE_FORMAT(d.date_reception, '%Y-%m') ORDER BY mois ASC",
            $params
        );

        // Services les plus utilisés
        $whereArticles = "WHERE (dep.vente_id IS NOT NULL OR dep.paid_amount > 0)";
        $paramsArticles = [];
        if ($shopId) {
            $whereArticles .= " AND dep.shop_id = ?";
            $paramsArticles[] = $shopId;
        }
        $topServices = $db->fetchAll(
            "SELECT a.service, COUNT(*) as nb, SUM(a.prix_total) as total
             FROM pressing_articles a
             INNER JOIN pressing_depots dep ON a.depot_id = dep.id
             $whereArticles
             GROUP BY a.service ORDER BY nb DESC",
            $paramsArticles
        );

        // Clients fidèles (nombre de dépôts)
        $topClients = $db->fetchAll(
            "SELECT c.nom_client, COUNT(*) as nb_depots, COALESCE(SUM(d.paid_amount),0) as total_depense
             FROM pressing_depots d
             LEFT JOIN clients c ON d.client_id = c.id
             $where
             GROUP BY d.client_id, c.nom_client
             ORDER BY nb_depots DESC
             LIMIT 10",
            $params
        );

        $this->json([
            'nb_depots' => (int)($totals['nb_depots'] ?? 0),
            'revenus' => (float)($totals['revenus'] ?? 0),
            'par_jour' => $byDay,
            'par_mois' => $byMonth,
            'top_services' => $topServices,
            'top_clients' => $topClients,
        ]);
    }
}
