<?php

namespace App\Controllers;

use App\Models\PressingDepot;
use App\Models\PressingArticle;
use App\Models\Client;
use App\Models\Sale;
use App\Models\PressingStatusHistory;
use App\Models\PressingPayment;
use App\Models\PressingPhoto;
use App\controllers\Controller;

class PressingDepotController extends Controller
{
    public function index()
    {
        if (!$this->requireAuth()) return;

        $shopId = $this->isSuperAdmin() ? null : $this->getShopId();
        $filters = [
            'statut'    => $_GET['statut'] ?? null,
            'client_id' => $_GET['client_id'] ?? null,
            'date_from' => $_GET['date_from'] ?? null,
            'date_to'   => $_GET['date_to'] ?? null,
        ];

        $depotModel = new PressingDepot();
        $this->json($depotModel->all($shopId, $filters));
    }

    public function get($params)
    {
        if (!$this->requireAuth()) return;

        $id = is_array($params) ? ($params['id'] ?? null) : $params;
        $id = (int)$id;

        $depotModel = new PressingDepot();
        $articleModel = new PressingArticle();
        $shopId = $this->isSuperAdmin() ? null : $this->getShopId();

        $depot = $depotModel->findById($id, $shopId);
        if (!$depot) {
            $this->status(404)->json(['error' => 'Dépôt introuvable']);
            return;
        }

        $depot['articles'] = $articleModel->getByDepot($id);
        $depot['historique'] = (new PressingStatusHistory())->getByDepot($id);
        $depot['paiements']  = (new PressingPayment())->getByDepot($id);
        $depot['photos']     = (new PressingPhoto())->getByDepot($id);
        $depot['paid']       = $depotModel->getPaidAmount($id);
        $depot['solde']      = $depotModel->getSolde($id);
        $this->json($depot);
    }

    public function search()
    {
        if (!$this->requireAuth()) return;

        $numero = $this->sanitaze($_GET['numero'] ?? '');
        if (!$numero) {
            $this->status(400)->json(['error' => 'Numéro requis']);
            return;
        }

        $depotModel = new PressingDepot();
        $depot = $depotModel->findByNumero($numero);

        if (!$depot) {
            $this->status(404)->json(['error' => 'Dépôt introuvable']);
            return;
        }
        if (!$this->isSuperAdmin() && $depot['shop_id'] != $this->getShopId()) {
            $this->status(403)->json(['error' => 'Ce dépôt ne fait pas partie de votre boutique']);
            return;
        }

        $articleModel = new PressingArticle();
        $depot['articles'] = $articleModel->getByDepot($depot['id']);
        $depot['historique'] = (new PressingStatusHistory())->getByDepot($depot['id']);
        $depot['paiements']  = (new PressingPayment())->getByDepot($depot['id']);
        $depot['photos']     = (new PressingPhoto())->getByDepot($depot['id']);
        $depot['paid']       = $depotModel->getPaidAmount($depot['id']);
        $depot['solde']      = $depotModel->getSolde($depot['id']);
        $this->json($depot);
    }

    public function create()
    {
        if (!$this->requireAuth()) return;

        $input = json_decode(file_get_contents('php://input'), true) ?? [];
        $shopId = $this->getShopId();

        $clientId = (int)($input['client_id'] ?? 0);
        $articles = $input['articles'] ?? [];
        $datePrevue = isset($input['date_prevue']) && $input['date_prevue'] !== '' ? $this->sanitaze($input['date_prevue']) : null;
        $dateRetour = isset($input['date_retour_prevue']) && $input['date_retour_prevue'] !== '' ? $this->sanitaze($input['date_retour_prevue']) : null;
        $adresseLivraison = isset($input['adresse_livraison']) ? $this->sanitaze($input['adresse_livraison']) : null;
        $remise = max(0, (float)($input['remise'] ?? 0));

        if (!$clientId) {
            $this->status(400)->json(['error' => 'Client requis']);
            return;
        }
        if (empty($articles)) {
            $this->status(400)->json(['error' => 'Ajoutez au moins un article']);
            return;
        }

        $clientModel = new Client();
        $client = $clientModel->findById($clientId);
        if (!$client) {
            $this->status(404)->json(['error' => 'Client introuvable']);
            return;
        }

        $articleModel = new PressingArticle();
        $validServices = $articleModel->getValidServices();

        $sousTotal = 0;
        $cleanArticles = [];
        foreach ($articles as $a) {
            $nom = $this->sanitaze($a['nom_article'] ?? '');
            $quantite = max(1, (int)($a['quantite'] ?? 1));
            $service = $this->sanitaze($a['service'] ?? '');
            $prixUnitaire = (float)($a['prix_unitaire'] ?? 0);

            if (!$nom || !in_array($service, $validServices) || $prixUnitaire <= 0) {
                $this->status(400)->json(['error' => 'Article invalide : nom, service et prix unitaire (> 0) requis']);
                return;
            }

            $prixTotal = round($quantite * $prixUnitaire, 2);
            $sousTotal += $prixTotal;

            $cleanArticles[] = [
                'nom_article'   => $nom,
                'quantite'      => $quantite,
                'etat_initial'  => isset($a['etat_initial']) ? $this->sanitaze($a['etat_initial']) : null,
                'commentaire'   => isset($a['commentaire']) ? $this->sanitaze($a['commentaire']) : null,
                'service'       => $service,
                'service_id'    => !empty($a['service_id']) ? (int)$a['service_id'] : null,
                'prix_unitaire' => $prixUnitaire,
                'prix_total'    => $prixTotal,
            ];
        }

        $total = round($sousTotal - $remise, 2);

        $depotModel = new PressingDepot();
        $numero = $depotModel->generateNumero();

        try {
            $db = \App\Core\Database::getInstance()->getConnection();
            $db->beginTransaction();

            $depotId = $depotModel->create([
                'shop_id'            => $shopId,
                'numero'             => $numero,
                'client_id'          => $clientId,
                'sous_total'         => $sousTotal,
                'remise'             => $remise,
                'total'              => $total,
                'date_prevue'        => $datePrevue,
                'adresse_livraison'  => $adresseLivraison,
                'date_retour_prevue' => $dateRetour,
                'created_by'         => $_SESSION['user_id'],
            ]);

            foreach ($cleanArticles as $a) {
                $a['depot_id'] = $depotId;
                $articleModel->create($a);
            }

            $db->commit();

            $this->logAudit('create', 'pressing_depot', $depotId, ['numero' => $numero]);
            $this->json(['success' => true, 'id' => $depotId, 'numero' => $numero]);
        } catch (\Exception $e) {
            $db->rollBack();
            $this->status(500)->json(['error' => 'Erreur lors de la création du dépôt: ' . $e->getMessage()]);
        }
    }

    public function updateStatus($params)
    {
        if (!$this->requireAuth()) return;

        $id = is_array($params) ? ($params['id'] ?? null) : $params;
        $id = (int)$id;

        $depotModel = new PressingDepot();
        $shopId = $this->isSuperAdmin() ? null : $this->getShopId();
        $depot = $depotModel->findById($id, $shopId);

        if (!$depot) {
            $this->status(404)->json(['error' => 'Dépôt introuvable']);
            return;
        }

        $input = json_decode(file_get_contents('php://input'), true) ?? [];
        $statut = $this->sanitaze($input['statut'] ?? '');

        if (!in_array($statut, $depotModel->getValidStatuts())) {
            $this->status(400)->json(['error' => 'Statut invalide']);
            return;
        }
        if ($statut === 'livre' && !$depotModel->isPaid($id)) {
            $this->status(409)->json(['error' => 'Le dépôt doit être payé avant d\'être marqué comme livré']);
            return;
        }

        $depotModel->updateStatut($id, $statut);
        $this->logAudit('update_status', 'pressing_depot', $id, ['statut' => $statut]);
        $this->json(['success' => true]);
    }

    public function pay($params)
    {
        if (!$this->requireAuth()) return;

        $id = is_array($params) ? ($params['id'] ?? null) : $params;
        $id = (int)$id;

        $depotModel = new PressingDepot();
        $shopId = $this->isSuperAdmin() ? null : $this->getShopId();
        $depot = $depotModel->findById($id, $shopId);

        if (!$depot) {
            $this->status(404)->json(['error' => 'Dépôt introuvable']);
            return;
        }
        if ($depotModel->isPaid($id)) {
            $this->status(409)->json(['error' => 'Ce dépôt est déjà payé']);
            return;
        }

        $input = json_decode(file_get_contents('php://input'), true) ?? [];
        $payments = $input['payments'] ?? null;

        try {
            $saleModel = new Sale();
            $numeroFacture = $saleModel->generateInvoiceNumber();

            $saleId = $saleModel->create([
                'numero_facture' => $numeroFacture,
                'client_id'      => $depot['client_id'],
                'sous_total_ht'  => $depot['sous_total'] - $depot['remise'],
                'tva'            => 0,
                'total'          => $depot['total'],
                'payments'       => $payments,
                'vendeur_id'     => $_SESSION['user_id'],
                'shop_id'        => $depot['shop_id'],
                'date'           => date('Y-m-d H:i:s'),
                'comment'        => 'Dépôt pressing #' . $depot['numero'],
                'service'        => 'pressing',
            ]);

            $depotModel->markPaid($id, $saleId);

            $this->logAudit('pay', 'pressing_depot', $id, ['vente_id' => $saleId, 'total' => $depot['total']]);
            $this->json(['success' => true, 'vente_id' => $saleId, 'numero_facture' => $numeroFacture]);
        } catch (\Exception $e) {
            $this->status(500)->json(['error' => 'Erreur lors du paiement: ' . $e->getMessage()]);
        }
    }

    public function withdraw($params)
    {
        if (!$this->requireAuth()) return;

        $id = is_array($params) ? ($params['id'] ?? null) : $params;
        $id = (int)$id;

        $depotModel = new PressingDepot();
        $shopId = $this->isSuperAdmin() ? null : $this->getShopId();
        $depot = $depotModel->findById($id, $shopId);

        if (!$depot) {
            $this->status(404)->json(['error' => 'Dépôt introuvable']);
            return;
        }
        if (!$depotModel->isPaid($id)) {
            $this->status(409)->json(['error' => 'Le dépôt doit être encaissé avant le retrait']);
            return;
        }
        if ($depot['statut'] === 'livre') {
            $this->status(409)->json(['error' => 'Ce dépôt a déjà été retiré']);
            return;
        }

        $depotModel->updateStatut($id, 'livre');
        $this->logAudit('withdraw', 'pressing_depot', $id);
        $this->json(['success' => true]);
    }
}
