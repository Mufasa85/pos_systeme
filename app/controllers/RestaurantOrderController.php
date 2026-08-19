<?php

namespace App\Controllers;

use App\Models\RestaurantOrder;
use App\Models\RestaurantTable;
use App\Models\RestaurantMenuItem;
use App\Models\Sale;
use App\controllers\Controller;

class RestaurantOrderController extends Controller
{
    public function create()
    {
        if (!$this->requireAuth()) return;

        $input = json_decode(file_get_contents('php://input'), true) ?? [];
        $tableId = (int)($input['table_id'] ?? 0);
        $shopId = $this->getShopId();

        if (!$tableId) {
            $this->status(400)->json(['error' => 'Table requise']);
            return;
        }

        $tableModel = new RestaurantTable();
        $orderModel = new RestaurantOrder();

        $table = $tableModel->findById($tableId, $this->isSuperAdmin() ? null : $shopId);
        if (!$table) {
            $this->status(404)->json(['error' => 'Table introuvable']);
            return;
        }

        // Réutilise une commande déjà ouverte pour cette table si elle existe
        $existing = $orderModel->findOpenByTable($tableId);
        if ($existing) {
            $this->json(['success' => true, 'id' => $existing['id']]);
            return;
        }

        $id = $orderModel->create([
            'shop_id'    => $table['shop_id'],
            'table_id'   => $tableId,
            'serveur_id' => $_SESSION['user_id'],
        ]);

        $tableModel->updateState($tableId, 'occupee');

        $this->logAudit('create', 'restaurant_commande', $id, ['table_id' => $tableId]);
        $this->json(['success' => true, 'id' => $id]);
    }

    public function get($params)
    {
        if (!$this->requireAuth()) return;

        $id = is_array($params) ? ($params['id'] ?? null) : $params;
        $id = (int)$id;

        $orderModel = new RestaurantOrder();
        $shopId = $this->isSuperAdmin() ? null : $this->getShopId();
        $order = $orderModel->findByIdFull($id, $shopId);

        if (!$order) {
            $this->status(404)->json(['error' => 'Commande introuvable']);
            return;
        }

        $order['details'] = $orderModel->getDetails($id);
        $this->json($order);
    }

    private function checkOrderAccess(RestaurantOrder $orderModel, $id)
    {
        $shopId = $this->isSuperAdmin() ? null : $this->getShopId();
        $order = $orderModel->findById($id, $shopId);
        if (!$order) {
            $this->status(404)->json(['error' => 'Commande introuvable']);
            return null;
        }
        if (!in_array($order['statut'], ['ouverte', 'envoyee_cuisine'])) {
            $this->status(409)->json(['error' => 'Commande déjà clôturée']);
            return null;
        }
        return $order;
    }

    public function addItem($params)
    {
        if (!$this->requireAuth()) return;

        $id = is_array($params) ? ($params['id'] ?? null) : $params;
        $id = (int)$id;

        $orderModel = new RestaurantOrder();
        $order = $this->checkOrderAccess($orderModel, $id);
        if (!$order) return;

        $input = json_decode(file_get_contents('php://input'), true) ?? [];
        $menuItemId = (int)($input['menu_item_id'] ?? 0);
        $quantite = max(1, (int)($input['quantite'] ?? 1));
        $commentaire = isset($input['commentaire']) ? $this->sanitaze($input['commentaire']) : null;

        $menuModel = new RestaurantMenuItem();
        $item = $menuModel->findById($menuItemId, $order['shop_id']);
        if (!$item) {
            $this->status(404)->json(['error' => 'Plat introuvable']);
            return;
        }
        if (!$item['disponible']) {
            $this->status(409)->json(['error' => 'Ce plat n\'est pas disponible actuellement']);
            return;
        }

        $detailId = $orderModel->addDetail([
            'commande_id'   => $id,
            'menu_item_id'  => $menuItemId,
            'quantite'      => $quantite,
            'prix_unitaire' => $item['prix'],
            'commentaire'   => $commentaire,
        ]);

        $this->json(['success' => true, 'id' => $detailId, 'order' => $orderModel->findById($id)]);
    }

    public function updateItem($params)
    {
        if (!$this->requireAuth()) return;

        $detailId = is_array($params) ? ($params['detailId'] ?? null) : $params;
        $detailId = (int)$detailId;

        $orderModel = new RestaurantOrder();
        $detail = $orderModel->findDetailById($detailId);
        if (!$detail) {
            $this->status(404)->json(['error' => 'Ligne introuvable']);
            return;
        }

        $order = $this->checkOrderAccess($orderModel, $detail['commande_id']);
        if (!$order) return;

        $input = json_decode(file_get_contents('php://input'), true) ?? [];
        $quantite = max(1, (int)($input['quantite'] ?? 1));

        $orderModel->updateDetailQuantity($detailId, $quantite, $detail['commande_id']);
        $this->json(['success' => true, 'order' => $orderModel->findById($detail['commande_id'])]);
    }

    public function removeItem($params)
    {
        if (!$this->requireAuth()) return;

        $detailId = is_array($params) ? ($params['detailId'] ?? null) : $params;
        $detailId = (int)$detailId;

        $orderModel = new RestaurantOrder();
        $detail = $orderModel->findDetailById($detailId);
        if (!$detail) {
            $this->status(404)->json(['error' => 'Ligne introuvable']);
            return;
        }

        $order = $this->checkOrderAccess($orderModel, $detail['commande_id']);
        if (!$order) return;

        $orderModel->removeDetail($detailId, $detail['commande_id']);
        $this->json(['success' => true, 'order' => $orderModel->findById($detail['commande_id'])]);
    }

    public function setRemise($params)
    {
        if (!$this->requireAuth()) return;

        $id = is_array($params) ? ($params['id'] ?? null) : $params;
        $id = (int)$id;

        $orderModel = new RestaurantOrder();
        $order = $this->checkOrderAccess($orderModel, $id);
        if (!$order) return;

        $input = json_decode(file_get_contents('php://input'), true) ?? [];
        $remise = max(0, (float)($input['remise'] ?? 0));

        $orderModel->setRemise($id, $remise);
        $this->json(['success' => true, 'order' => $orderModel->findById($id)]);
    }

    public function sendToKitchen($params)
    {
        if (!$this->requireAuth()) return;

        $id = is_array($params) ? ($params['id'] ?? null) : $params;
        $id = (int)$id;

        $orderModel = new RestaurantOrder();
        $order = $this->checkOrderAccess($orderModel, $id);
        if (!$order) return;

        $details = $orderModel->getDetails($id);
        if (empty($details)) {
            $this->status(400)->json(['error' => 'Ajoutez au moins un plat avant d\'envoyer en cuisine']);
            return;
        }

        $orderModel->updateStatut($id, 'envoyee_cuisine');
        $this->logAudit('send_to_kitchen', 'restaurant_commande', $id);
        $this->json(['success' => true]);
    }

    public function cancel($params)
    {
        if (!$this->requireAuth()) return;

        $id = is_array($params) ? ($params['id'] ?? null) : $params;
        $id = (int)$id;

        $orderModel = new RestaurantOrder();
        $order = $this->checkOrderAccess($orderModel, $id);
        if (!$order) return;

        $orderModel->updateStatut($id, 'annulee');

        $tableModel = new RestaurantTable();
        $tableModel->updateState($order['table_id'], 'libre');

        $this->logAudit('cancel', 'restaurant_commande', $id);
        $this->json(['success' => true]);
    }

    public function pay($params)
    {
        if (!$this->requireAuth()) return;

        $id = is_array($params) ? ($params['id'] ?? null) : $params;
        $id = (int)$id;

        $orderModel = new RestaurantOrder();
        $shopId = $this->isSuperAdmin() ? null : $this->getShopId();
        $order = $orderModel->findById($id, $shopId);

        if (!$order) {
            $this->status(404)->json(['error' => 'Commande introuvable']);
            return;
        }
        if (!in_array($order['statut'], ['ouverte', 'envoyee_cuisine', 'servie'])) {
            $this->status(409)->json(['error' => 'Commande déjà payée ou annulée']);
            return;
        }

        $details = $orderModel->getDetails($id);
        if (empty($details)) {
            $this->status(400)->json(['error' => 'Commande vide']);
            return;
        }

        $input = json_decode(file_get_contents('php://input'), true) ?? [];
        $payments = $input['payments'] ?? null;
        $clientId = $input['client_id'] ?? null;

        try {
            $saleModel = new Sale();
            $numeroFacture = $saleModel->generateInvoiceNumber();

            $saleId = $saleModel->create([
                'numero_facture' => $numeroFacture,
                'client_id'      => $clientId,
                'sous_total_ht'  => $order['sous_total'] - $order['remise'],
                'tva'            => $order['taxes'],
                'total'          => $order['total'],
                'payments'       => $payments,
                'vendeur_id'     => $_SESSION['user_id'],
                'shop_id'        => $order['shop_id'],
                'date'           => date('Y-m-d H:i:s'),
                'comment'        => 'Commande restaurant #' . $id,
                'service'        => 'restaurant',
            ]);

            $orderModel->markPaid($id, $saleId);
            $orderModel->updateStatut($id, 'payee');

            $tableModel = new RestaurantTable();
            $tableModel->updateState($order['table_id'], 'nettoyage');

            $this->logAudit('pay', 'restaurant_commande', $id, ['vente_id' => $saleId, 'total' => $order['total']]);
            $this->json(['success' => true, 'vente_id' => $saleId, 'numero_facture' => $numeroFacture]);
        } catch (\Exception $e) {
            $this->status(500)->json(['error' => 'Erreur lors du paiement: ' . $e->getMessage()]);
        }
    }
}
