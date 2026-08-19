<?php

namespace App\Controllers;

use App\Models\RestaurantReservation;
use App\Models\RestaurantSplitBill;
use App\Models\RestaurantPayment;
use App\Models\RestaurantMenuItemOption;
use App\Models\RestaurantCommandeDetailOption;
use App\Models\RestaurantMenu;
use App\Models\RestaurantMenuComposition;
use App\Models\RestaurantFidelite;
use App\Models\RestaurantFideliteRegle;
use App\Models\RestaurantTable;
use App\Models\RestaurantOrder;
use App\controllers\Controller;

class RestaurantPartBController extends Controller
{
    // =========================================================================
    // RÉSERVATIONS
    // =========================================================================

    public function reservations()
    {
        if (!$this->requireAuth()) return;

        $shopId = $this->isSuperAdmin() ? null : $this->getShopId();
        $date = $_GET['date'] ?? null;
        $statut = $_GET['statut'] ?? null;

        $model = new RestaurantReservation();
        $this->json($model->all($shopId, ['date' => $date, 'statut' => $statut]));
    }

    public function createReservation()
    {
        if (!$this->requireAuth()) return;

        $input = json_decode(file_get_contents('php://input'), true) ?? [];
        $shopId = $this->isSuperAdmin() ? ($input['shop_id'] ?? null) : $this->getShopId();

        if (!$shopId) {
            $this->status(400)->json(['error' => 'Boutique requise']);
            return;
        }

        $data = [
            'shop_id'         => $shopId,
            'table_id'        => !empty($input['table_id']) ? (int)$input['table_id'] : null,
            'client_nom'      => isset($input['client_nom']) ? $this->sanitaze($input['client_nom']) : null,
            'client_telephone' => $input['client_telephone'] ?? null,
            'client_id'       => !empty($input['client_id']) ? (int)$input['client_id'] : null,
            'date_heure'      => $input['date_heure'] ?? null,
            'nb_personnes'    => (int)($input['nb_personnes'] ?? 1),
            'commentaire'     => isset($input['commentaire']) ? $this->sanitaze($input['commentaire']) : null,
            'created_by'      => $_SESSION['user_id'],
        ];

        if (!$data['date_heure']) {
            $this->status(400)->json(['error' => 'Date et heure requises']);
            return;
        }

        $model = new RestaurantReservation();
        $id = $model->create($data);
        $this->logAudit('create', 'restaurant_reservation', $id, $data);
        $this->json(['success' => true, 'id' => $id]);
    }

    public function updateReservation($params)
    {
        if (!$this->requireAuth()) return;

        $id = is_array($params) ? ($params['id'] ?? null) : $params;
        $id = (int)$id;
        if (!$id) {
            $this->status(400)->json(['error' => 'ID manquant']);
            return;
        }

        $model = new RestaurantReservation();
        $shopId = $this->isSuperAdmin() ? null : $this->getShopId();
        $existing = $model->findById($id, $shopId);
        if (!$existing) {
            $this->status(404)->json(['error' => 'Réservation introuvable']);
            return;
        }

        $input = json_decode(file_get_contents('php://input'), true) ?? [];
        $data = [];
        foreach (['table_id', 'client_nom', 'client_telephone', 'client_id', 'date_heure', 'nb_personnes', 'statut', 'commentaire'] as $field) {
            if (array_key_exists($field, $input)) {
                if (in_array($field, ['table_id', 'client_id', 'nb_personnes'])) {
                    $data[$field] = $input[$field] !== '' ? (int)$input[$field] : null;
                } elseif (in_array($field, ['client_nom', 'commentaire'])) {
                    $data[$field] = $this->sanitaze($input[$field]);
                } elseif ($field === 'client_telephone') {
                    $data[$field] = $input[$field] ?? null;
                } else {
                    $data[$field] = $input[$field];
                }
            }
        }

        $model->update($id, $data);
        $this->logAudit('update', 'restaurant_reservation', $id, $data);
        $this->json(['success' => true]);
    }

    public function deleteReservation($params)
    {
        if (!$this->requireAuth()) return;

        $id = is_array($params) ? ($params['id'] ?? null) : $params;
        $id = (int)$id;
        if (!$id) {
            $this->status(400)->json(['error' => 'ID manquant']);
            return;
        }

        $model = new RestaurantReservation();
        $shopId = $this->isSuperAdmin() ? null : $this->getShopId();
        $existing = $model->findById($id, $shopId);
        if (!$existing) {
            $this->status(404)->json(['error' => 'Réservation introuvable']);
            return;
        }

        $model->delete($id, $shopId);
        $this->logAudit('delete', 'restaurant_reservation', $id, ['id' => $id]);
        $this->json(['success' => true]);
    }

    // =========================================================================
    // ADDITIONS PARTAGÉES
    // =========================================================================

    public function splitBills($params)
    {
        if (!$this->requireAuth()) return;

        $commandeId = is_array($params) ? ($params['id'] ?? null) : $params;
        $commandeId = (int)$commandeId;
        if (!$commandeId) {
            $this->status(400)->json(['error' => 'ID commande manquant']);
            return;
        }

        $model = new RestaurantSplitBill();
        $bills = $model->all($commandeId);
        foreach ($bills as &$b) {
            $b['solde'] = $model->getSolde($b['id']);
        }
        $this->json($bills);
    }

    public function createSplitBill()
    {
        if (!$this->requireAuth()) return;

        $input = json_decode(file_get_contents('php://input'), true) ?? [];
        $commandeId = (int)($input['commande_id'] ?? 0);
        $total = (float)($input['total'] ?? 0);

        if (!$commandeId) {
            $this->status(400)->json(['error' => 'Commande requise']);
            return;
        }

        $model = new RestaurantSplitBill();
        $id = $model->create([
            'commande_id' => $commandeId,
            'label'       => isset($input['label']) ? $this->sanitaze($input['label']) : 'Part',
            'total'       => $total,
        ]);
        $this->logAudit('create', 'restaurant_split_bill', $id, ['commande_id' => $commandeId]);
        $this->json(['success' => true, 'id' => $id]);
    }

    public function updateSplitBill($params)
    {
        if (!$this->requireAuth()) return;

        $id = is_array($params) ? ($params['id'] ?? null) : $params;
        $id = (int)$id;
        if (!$id) {
            $this->status(400)->json(['error' => 'ID manquant']);
            return;
        }

        $input = json_decode(file_get_contents('php://input'), true) ?? [];
        $data = [];
        if (isset($input['label'])) $data['label'] = $this->sanitaze($input['label']);
        if (isset($input['total'])) $data['total'] = (float)$input['total'];

        $model = new RestaurantSplitBill();
        $model->update($id, $data);
        $this->logAudit('update', 'restaurant_split_bill', $id, $data);
        $this->json(['success' => true]);
    }

    public function deleteSplitBill($params)
    {
        if (!$this->requireAuth()) return;

        $id = is_array($params) ? ($params['id'] ?? null) : $params;
        $id = (int)$id;
        if (!$id) {
            $this->status(400)->json(['error' => 'ID manquant']);
            return;
        }

        $model = new RestaurantSplitBill();
        $model->delete($id);
        $this->logAudit('delete', 'restaurant_split_bill', $id, ['id' => $id]);
        $this->json(['success' => true]);
    }

    // =========================================================================
    // PAIEMENTS MIXTES
    // =========================================================================

    public function payments($params)
    {
        if (!$this->requireAuth()) return;

        $commandeId = is_array($params) ? ($params['id'] ?? null) : $params;
        $commandeId = (int)$commandeId;
        if (!$commandeId) {
            $this->status(400)->json(['error' => 'ID commande manquant']);
            return;
        }

        $model = new RestaurantPayment();
        $this->json($model->getByCommande($commandeId));
    }

    public function createPayment()
    {
        if (!$this->requireAuth()) return;

        $input = json_decode(file_get_contents('php://input'), true) ?? [];
        $commandeId = (int)($input['commande_id'] ?? 0);
        $montant = (float)($input['montant'] ?? 0);
        $mode = $this->sanitaze($input['mode_paiement'] ?? 'cash');

        if (!$commandeId || $montant <= 0) {
            $this->status(400)->json(['error' => 'Commande et montant requis']);
            return;
        }

        $model = new RestaurantPayment();
        $id = $model->create([
            'commande_id'   => $commandeId,
            'split_bill_id' => !empty($input['split_bill_id']) ? (int)$input['split_bill_id'] : null,
            'montant'       => $montant,
            'mode_paiement' => $mode,
            'reference'     => $input['reference'] ?? null,
            'created_by'    => $_SESSION['user_id'],
        ]);

        $orderModel = new RestaurantOrder();
        $commande = $orderModel->findById($commandeId);
        $solde = max(0, (float)($commande['total'] ?? 0) - (float)($commande['paid_amount'] ?? 0));

        if ($solde <= 0.001) {
            $orderModel->updateStatut($commandeId, 'payee');
        }

        $this->logAudit('create', 'restaurant_paiement', $id, ['commande_id' => $commandeId, 'montant' => $montant]);
        $this->json(['success' => true, 'id' => $id, 'solde' => $solde]);
    }

    public function deletePayment($params)
    {
        if (!$this->requireAdmin()) return;

        $id = is_array($params) ? ($params['id'] ?? null) : $params;
        $id = (int)$id;
        if (!$id) {
            $this->status(400)->json(['error' => 'ID manquant']);
            return;
        }

        $model = new RestaurantPayment();
        $model->delete($id);
        $this->logAudit('delete', 'restaurant_paiement', $id, ['id' => $id]);
        $this->json(['success' => true]);
    }

    // =========================================================================
    // OPTIONS DE PLATS
    // =========================================================================

    public function menuItemOptions($params)
    {
        if (!$this->requireAuth()) return;

        $menuItemId = is_array($params) ? ($params['id'] ?? null) : $params;
        $menuItemId = (int)$menuItemId;
        if (!$menuItemId) {
            $this->status(400)->json(['error' => 'ID plat manquant']);
            return;
        }

        $model = new RestaurantMenuItemOption();
        $this->json($model->all($menuItemId));
    }

    public function createMenuItemOption()
    {
        if (!$this->requireAdmin()) return;

        $input = json_decode(file_get_contents('php://input'), true) ?? [];
        $menuItemId = (int)($input['menu_item_id'] ?? 0);
        $nom = $this->sanitaze($input['nom'] ?? '');

        if (!$menuItemId || !$nom) {
            $this->status(400)->json(['error' => 'Plat et nom requis']);
            return;
        }

        $model = new RestaurantMenuItemOption();
        $id = $model->create([
            'menu_item_id' => $menuItemId,
            'nom'          => $nom,
            'prix_supp'    => (float)($input['prix_supp'] ?? 0),
            'obligatoire'  => (int)($input['obligatoire'] ?? 0),
            'actif'        => (int)($input['actif'] ?? 1),
        ]);
        $this->logAudit('create', 'restaurant_menu_item_option', $id, ['menu_item_id' => $menuItemId, 'nom' => $nom]);
        $this->json(['success' => true, 'id' => $id]);
    }

    public function updateMenuItemOption($params)
    {
        if (!$this->requireAdmin()) return;

        $id = is_array($params) ? ($params['id'] ?? null) : $params;
        $id = (int)$id;
        if (!$id) {
            $this->status(400)->json(['error' => 'ID manquant']);
            return;
        }

        $input = json_decode(file_get_contents('php://input'), true) ?? [];
        $data = [];
        if (isset($input['nom'])) $data['nom'] = $this->sanitaze($input['nom']);
        if (isset($input['prix_supp'])) $data['prix_supp'] = (float)$input['prix_supp'];
        if (isset($input['obligatoire'])) $data['obligatoire'] = (int)$input['obligatoire'];
        if (isset($input['actif'])) $data['actif'] = (int)$input['actif'];

        $model = new RestaurantMenuItemOption();
        $model->update($id, $data);
        $this->logAudit('update', 'restaurant_menu_item_option', $id, $data);
        $this->json(['success' => true]);
    }

    public function deleteMenuItemOption($params)
    {
        if (!$this->requireAdmin()) return;

        $id = is_array($params) ? ($params['id'] ?? null) : $params;
        $id = (int)$id;
        if (!$id) {
            $this->status(400)->json(['error' => 'ID manquant']);
            return;
        }

        $model = new RestaurantMenuItemOption();
        $model->delete($id);
        $this->logAudit('delete', 'restaurant_menu_item_option', $id, ['id' => $id]);
        $this->json(['success' => true]);
    }

    // =========================================================================
    // MENUS / FORMULES
    // =========================================================================

    public function menus()
    {
        if (!$this->requireAuth()) return;

        $shopId = $this->isSuperAdmin() ? null : $this->getShopId();
        $model = new RestaurantMenu();
        $this->json($model->all($shopId));
    }

    public function createMenu()
    {
        if (!$this->requireAdmin()) return;

        $input = json_decode(file_get_contents('php://input'), true) ?? [];
        $shopId = $this->isSuperAdmin() ? ($input['shop_id'] ?? null) : $this->getShopId();
        $nom = $this->sanitaze($input['nom'] ?? '');
        $prix = (float)($input['prix'] ?? 0);

        if (!$shopId || !$nom || $prix <= 0) {
            $this->status(400)->json(['error' => 'Boutique, nom et prix requis']);
            return;
        }

        $model = new RestaurantMenu();
        $id = $model->create([
            'shop_id'     => $shopId,
            'nom'         => $nom,
            'description' => isset($input['description']) ? $this->sanitaze($input['description']) : null,
            'prix'        => $prix,
            'actif'       => (int)($input['actif'] ?? 1),
        ]);
        $this->logAudit('create', 'restaurant_menu', $id, ['shop_id' => $shopId, 'nom' => $nom]);
        $this->json(['success' => true, 'id' => $id]);
    }

    public function updateMenu($params)
    {
        if (!$this->requireAdmin()) return;

        $id = is_array($params) ? ($params['id'] ?? null) : $params;
        $id = (int)$id;
        if (!$id) {
            $this->status(400)->json(['error' => 'ID manquant']);
            return;
        }

        $model = new RestaurantMenu();
        $shopId = $this->isSuperAdmin() ? null : $this->getShopId();
        $existing = $model->findById($id, $shopId);
        if (!$existing) {
            $this->status(404)->json(['error' => 'Menu introuvable']);
            return;
        }

        $input = json_decode(file_get_contents('php://input'), true) ?? [];
        $data = [];
        if (isset($input['nom'])) $data['nom'] = $this->sanitaze($input['nom']);
        if (isset($input['description'])) $data['description'] = $this->sanitaze($input['description']);
        if (isset($input['prix'])) $data['prix'] = (float)$input['prix'];
        if (isset($input['actif'])) $data['actif'] = (int)$input['actif'];

        $model->update($id, $data);
        $this->logAudit('update', 'restaurant_menu', $id, $data);
        $this->json(['success' => true]);
    }

    public function deleteMenu($params)
    {
        if (!$this->requireAdmin()) return;

        $id = is_array($params) ? ($params['id'] ?? null) : $params;
        $id = (int)$id;
        if (!$id) {
            $this->status(400)->json(['error' => 'ID manquant']);
            return;
        }

        $model = new RestaurantMenu();
        $shopId = $this->isSuperAdmin() ? null : $this->getShopId();
        $existing = $model->findById($id, $shopId);
        if (!$existing) {
            $this->status(404)->json(['error' => 'Menu introuvable']);
            return;
        }

        $comp = new RestaurantMenuComposition();
        $comp->deleteByMenu($id);
        $model->delete($id, $shopId);
        $this->logAudit('delete', 'restaurant_menu', $id, ['id' => $id]);
        $this->json(['success' => true]);
    }

    public function menuCompositions($params)
    {
        if (!$this->requireAuth()) return;

        $menuId = is_array($params) ? ($params['id'] ?? null) : $params;
        $menuId = (int)$menuId;
        if (!$menuId) {
            $this->status(400)->json(['error' => 'ID menu manquant']);
            return;
        }

        $model = new RestaurantMenuComposition();
        $this->json($model->getByMenu($menuId));
    }

    public function createMenuComposition()
    {
        if (!$this->requireAdmin()) return;

        $input = json_decode(file_get_contents('php://input'), true) ?? [];
        $menuId = (int)($input['menu_id'] ?? 0);
        $itemId = (int)($input['menu_item_id'] ?? 0);

        if (!$menuId || !$itemId) {
            $this->status(400)->json(['error' => 'Menu et plat requis']);
            return;
        }

        $model = new RestaurantMenuComposition();
        $id = $model->create([
            'menu_id'      => $menuId,
            'menu_item_id' => $itemId,
            'quantite'     => (int)($input['quantite'] ?? 1),
            'ordre'        => (int)($input['ordre'] ?? 0),
        ]);
        $this->logAudit('create', 'restaurant_menu_composition', $id, ['menu_id' => $menuId, 'item_id' => $itemId]);
        $this->json(['success' => true, 'id' => $id]);
    }

    public function deleteMenuComposition($params)
    {
        if (!$this->requireAdmin()) return;

        $id = is_array($params) ? ($params['id'] ?? null) : $params;
        $id = (int)$id;
        if (!$id) {
            $this->status(400)->json(['error' => 'ID manquant']);
            return;
        }

        $model = new RestaurantMenuComposition();
        $model->delete($id);
        $this->logAudit('delete', 'restaurant_menu_composition', $id, ['id' => $id]);
        $this->json(['success' => true]);
    }

    // =========================================================================
    // FIDÉLITÉ
    // =========================================================================

    public function fideliteRegles()
    {
        if (!$this->requireAuth()) return;

        $shopId = $this->isSuperAdmin() ? null : $this->getShopId();
        $model = new RestaurantFideliteRegle();
        $this->json($model->all($shopId));
    }

    public function createFideliteRegle()
    {
        if (!$this->requireAdmin()) return;

        $input = json_decode(file_get_contents('php://input'), true) ?? [];
        $shopId = $this->isSuperAdmin() ? ($input['shop_id'] ?? null) : $this->getShopId();

        if (!$shopId) {
            $this->status(400)->json(['error' => 'Boutique requise']);
            return;
        }

        $model = new RestaurantFideliteRegle();
        $id = $model->create([
            'shop_id'         => $shopId,
            'montant_depense' => (float)($input['montant_depense'] ?? 0),
            'points_gagnes'   => (int)($input['points_gagnes'] ?? 0),
            'points_requis'   => (int)($input['points_requis'] ?? 0),
            'remise_points'   => (float)($input['remise_points'] ?? 0),
            'actif'           => (int)($input['actif'] ?? 1),
        ]);
        $this->logAudit('create', 'restaurant_fidelite_regle', $id, ['shop_id' => $shopId]);
        $this->json(['success' => true, 'id' => $id]);
    }

    public function updateFideliteRegle($params)
    {
        if (!$this->requireAdmin()) return;

        $id = is_array($params) ? ($params['id'] ?? null) : $params;
        $id = (int)$id;
        if (!$id) {
            $this->status(400)->json(['error' => 'ID manquant']);
            return;
        }

        $input = json_decode(file_get_contents('php://input'), true) ?? [];
        $data = [];
        if (isset($input['montant_depense'])) $data['montant_depense'] = (float)$input['montant_depense'];
        if (isset($input['points_gagnes'])) $data['points_gagnes'] = (int)$input['points_gagnes'];
        if (isset($input['points_requis'])) $data['points_requis'] = (int)$input['points_requis'];
        if (isset($input['remise_points'])) $data['remise_points'] = (float)$input['remise_points'];
        if (isset($input['actif'])) $data['actif'] = (int)$input['actif'];

        $model = new RestaurantFideliteRegle();
        $model->update($id, $data);
        $this->logAudit('update', 'restaurant_fidelite_regle', $id, $data);
        $this->json(['success' => true]);
    }

    public function deleteFideliteRegle($params)
    {
        if (!$this->requireAdmin()) return;

        $id = is_array($params) ? ($params['id'] ?? null) : $params;
        $id = (int)$id;
        if (!$id) {
            $this->status(400)->json(['error' => 'ID manquant']);
            return;
        }

        $model = new RestaurantFideliteRegle();
        $model->delete($id);
        $this->logAudit('delete', 'restaurant_fidelite_regle', $id, ['id' => $id]);
        $this->json(['success' => true]);
    }

    public function fidelite($params)
    {
        if (!$this->requireAuth()) return;

        $clientId = is_array($params) ? ($params['id'] ?? null) : $params;
        $clientId = (int)$clientId;
        $shopId = $this->isSuperAdmin() ? null : $this->getShopId();

        if (!$clientId || !$shopId) {
            $this->status(400)->json(['error' => 'Client et boutique requis']);
            return;
        }

        $model = new RestaurantFidelite();
        $this->json($model->findByClient($clientId, $shopId));
    }

    public function addFidelitePoints()
    {
        if (!$this->requireAuth()) return;

        $input = json_decode(file_get_contents('php://input'), true) ?? [];
        $clientId = (int)($input['client_id'] ?? 0);
        $shopId = $this->isSuperAdmin() ? ($input['shop_id'] ?? null) : $this->getShopId();
        $montant = (float)($input['montant'] ?? 0);

        if (!$clientId || !$shopId || $montant <= 0) {
            $this->status(400)->json(['error' => 'Client, boutique et montant requis']);
            return;
        }

        $model = new RestaurantFidelite();
        $id = $model->addPoints($clientId, $shopId, $montant);
        $this->logAudit('add_fidelite_points', 'restaurant_fidelite', $id, ['client_id' => $clientId, 'montant' => $montant]);
        $this->json(['success' => true, 'id' => $id]);
    }

    public function useFidelitePoints()
    {
        if (!$this->requireAuth()) return;

        $input = json_decode(file_get_contents('php://input'), true) ?? [];
        $clientId = (int)($input['client_id'] ?? 0);
        $shopId = $this->isSuperAdmin() ? ($input['shop_id'] ?? null) : $this->getShopId();
        $points = (int)($input['points'] ?? 0);

        if (!$clientId || !$shopId || $points <= 0) {
            $this->status(400)->json(['error' => 'Client, boutique et points requis']);
            return;
        }

        $model = new RestaurantFidelite();
        $result = $model->usePoints($clientId, $shopId, $points);
        $this->logAudit('use_fidelite_points', 'restaurant_fidelite', 0, ['client_id' => $clientId, 'points' => $points]);
        $this->json(['success' => (bool)$result]);
    }

    // =========================================================================
    // QR CODE TABLE
    // =========================================================================

    public function generateTableQr($params)
    {
        if (!$this->requireAdmin()) return;

        $id = is_array($params) ? ($params['id'] ?? null) : $params;
        $id = (int)$id;
        if (!$id) {
            $this->status(400)->json(['error' => 'ID table manquant']);
            return;
        }

        $tableModel = new RestaurantTable();
        $shopId = $this->isSuperAdmin() ? null : $this->getShopId();
        $existing = $tableModel->findById($id, $shopId);
        if (!$existing) {
            $this->status(404)->json(['error' => 'Table introuvable']);
            return;
        }

        $token = bin2hex(random_bytes(16));
        $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
        $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
        $url = $protocol . '://' . $host . '/restaurant/qr?token=' . $token;

        $tableModel->update($id, ['qr_token' => $token, 'qr_code' => $url]);
        $this->logAudit('generate_qr', 'restaurant_table', $id, ['token' => $token]);
        $this->json(['success' => true, 'token' => $token, 'url' => $url]);
    }

    // =========================================================================
    // TRANSFERT / FUSION
    // =========================================================================

    public function transferOrder()
    {
        if (!$this->requireAuth()) return;

        $input = json_decode(file_get_contents('php://input'), true) ?? [];
        $commandeId = (int)($input['commande_id'] ?? 0);
        $tableId = (int)($input['table_id'] ?? 0);

        if (!$commandeId || !$tableId) {
            $this->status(400)->json(['error' => 'Commande et table requises']);
            return;
        }

        $orderModel = new RestaurantOrder();
        $order = $orderModel->findById($commandeId, $this->isSuperAdmin() ? null : $this->getShopId());
        if (!$order) {
            $this->status(404)->json(['error' => 'Commande introuvable']);
            return;
        }

        $tableModel = new RestaurantTable();
        $table = $tableModel->findById($tableId, $this->isSuperAdmin() ? null : $this->getShopId());
        if (!$table) {
            $this->status(404)->json(['error' => 'Table introuvable']);
            return;
        }

        $orderModel->transferTable($commandeId, $tableId);
        $this->logAudit('transfer_order', 'restaurant_commande', $commandeId, ['table_id' => $tableId]);
        $this->json(['success' => true]);
    }

    public function mergeOrders()
    {
        if (!$this->requireAuth()) return;

        $input = json_decode(file_get_contents('php://input'), true) ?? [];
        $fromId = (int)($input['from_id'] ?? 0);
        $toId = (int)($input['to_id'] ?? 0);

        if (!$fromId || !$toId || $fromId === $toId) {
            $this->status(400)->json(['error' => 'Deux commandes distinctes requises']);
            return;
        }

        $orderModel = new RestaurantOrder();
        $shopId = $this->isSuperAdmin() ? null : $this->getShopId();
        $from = $orderModel->findById($fromId, $shopId);
        $to = $orderModel->findById($toId, $shopId);

        if (!$from || !$to) {
            $this->status(404)->json(['error' => 'Commande introuvable']);
            return;
        }

        if (!$orderModel->merge($fromId, $toId)) {
            $this->status(500)->json(['error' => 'Échec de la fusion']);
            return;
        }

        $this->logAudit('merge_orders', 'restaurant_commande', $toId, ['from_id' => $fromId]);
        $this->json(['success' => true]);
    }
}
