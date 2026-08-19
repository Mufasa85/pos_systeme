<?php

namespace App\Controllers;

use App\Models\RestaurantOrder;
use App\controllers\Controller;

class RestaurantKitchenController extends Controller
{
    /**
     * Liste des commandes envoyées en cuisine, avec statut calculé
     * automatiquement (en_preparation -> pret dès que le temps de
     * préparation du plat est écoulé depuis started_at).
     */
    public function index()
    {
        if (!$this->requireAuth()) return;

        $shopId = $this->isSuperAdmin() ? null : $this->getShopId();
        $db = \App\Core\Database::getInstance();

        $where = "WHERE c.statut IN ('envoyee_cuisine','servie')";
        $params = [];
        if ($shopId) {
            $where .= " AND c.shop_id = ?";
            $params[] = $shopId;
        }

        $rows = $db->fetchAll(
            "SELECT
                d.id as detail_id, d.commande_id, d.menu_item_id, d.quantite,
                d.statut_cuisine, d.started_at, d.commentaire,
                m.nom as plat_nom, m.temps_preparation,
                c.table_id, c.created_at as commande_created_at,
                t.numero as table_numero,
                CASE
                    WHEN d.statut_cuisine = 'en_preparation'
                         AND d.started_at IS NOT NULL
                         AND NOW() >= DATE_ADD(d.started_at, INTERVAL m.temps_preparation MINUTE)
                    THEN 'pret'
                    ELSE d.statut_cuisine
                END AS statut_affiche
             FROM restaurant_commande_details d
             INNER JOIN restaurant_commandes c ON d.commande_id = c.id
             INNER JOIN restaurant_menu_items m ON d.menu_item_id = m.id
             LEFT JOIN restaurant_tables t ON c.table_id = t.id
             $where
             ORDER BY c.created_at ASC, d.id ASC",
            $params
        );

        // Persiste en base le passage automatique en_preparation -> pret
        // (garde l'historique cohérent pour les rapports).
        foreach ($rows as &$row) {
            if ($row['statut_cuisine'] === 'en_preparation' && $row['statut_affiche'] === 'pret') {
                $db->execute("UPDATE restaurant_commande_details SET statut_cuisine = 'pret' WHERE id = ?", [$row['detail_id']]);
                $row['statut_cuisine'] = 'pret';
                $this->notifyShopAdmins(
                    $shopId,
                    'restaurant_plat_pret',
                    'Plat prêt',
                    "\"{$row['plat_nom']}\" (Table {$row['table_numero']}) est prêt à servir",
                    '/restaurant/commandes?table_id=' . $row['table_id']
                );
            }
        }
        unset($row);

        // Regrouper par commande pour l'affichage
        $grouped = [];
        foreach ($rows as $row) {
            $cid = $row['commande_id'];
            if (!isset($grouped[$cid])) {
                $grouped[$cid] = [
                    'commande_id' => $cid,
                    'table_numero' => $row['table_numero'],
                    'created_at' => $row['commande_created_at'],
                    'plats' => [],
                ];
            }
            $grouped[$cid]['plats'][] = [
                'detail_id' => $row['detail_id'],
                'nom' => $row['plat_nom'],
                'quantite' => $row['quantite'],
                'temps_preparation' => $row['temps_preparation'],
                'started_at' => $row['started_at'],
                'statut' => $row['statut_affiche'],
                'commentaire' => $row['commentaire'],
            ];
        }

        $this->json(array_values($grouped));
    }

    public function start($params)
    {
        if (!$this->requireAuth()) return;

        $detailId = is_array($params) ? ($params['detailId'] ?? null) : $params;
        $detailId = (int)$detailId;

        $orderModel = new RestaurantOrder();
        $detail = $orderModel->findDetailById($detailId);
        if (!$detail) {
            $this->status(404)->json(['error' => 'Plat introuvable']);
            return;
        }
        if ($detail['statut_cuisine'] !== 'en_attente') {
            $this->status(409)->json(['error' => 'Ce plat a déjà été démarré']);
            return;
        }

        $db = \App\Core\Database::getInstance();
        $db->execute(
            "UPDATE restaurant_commande_details SET statut_cuisine = 'en_preparation', started_at = NOW() WHERE id = ?",
            [$detailId]
        );

        $this->logAudit('start_preparation', 'restaurant_commande_detail', $detailId);
        $this->json(['success' => true]);
    }

    public function markServed($params)
    {
        if (!$this->requireAuth()) return;

        $detailId = is_array($params) ? ($params['detailId'] ?? null) : $params;
        $detailId = (int)$detailId;

        $orderModel = new RestaurantOrder();
        $detail = $orderModel->findDetailById($detailId);
        if (!$detail) {
            $this->status(404)->json(['error' => 'Plat introuvable']);
            return;
        }

        $db = \App\Core\Database::getInstance();
        $db->execute("UPDATE restaurant_commande_details SET statut_cuisine = 'servi' WHERE id = ?", [$detailId]);

        // Si tous les plats de la commande sont servis, marquer la commande "servie"
        $remaining = $db->fetch(
            "SELECT COUNT(*) as nb FROM restaurant_commande_details WHERE commande_id = ? AND statut_cuisine != 'servi'",
            [$detail['commande_id']]
        );
        if (($remaining['nb'] ?? 0) == 0) {
            $orderModel->updateStatut($detail['commande_id'], 'servie');
        }

        $this->logAudit('mark_served', 'restaurant_commande_detail', $detailId);
        $this->json(['success' => true]);
    }
}
