<?php

namespace App\Controllers;

use App\Models\Product;
use App\Models\Sale;
use App\Models\SaleDetail;
use App\Models\Client;
use App\controllers\Controller;

class SaleController extends Controller
{
    public function create()
    {
        if (!isset($_SESSION['user_id'])) {
            $this->status(403)->json(['error' => 'Non authentifié']);
            return;
        }

        $data = json_decode(file_get_contents('php://input'), true);
        if (empty($data) || empty($data['articles'])) {
            $this->status(400)->json(['error' => 'Panier vide ou données invalides']);
            return;
        }

        $saleModel = new Sale();
        $detailModel = new SaleDetail();
        $productModel = new Product();

        // Si pas de client_id, utiliser null (pas de client sélectionné)
        // Pour les recharges, on n'a pas besoin de client dans la table clients
        $clientId = $data['client_id'] ?? null;

        try {
            $db = \App\Core\Database::getInstance()->getConnection();
            $db->beginTransaction();

            $invoiceNum = $saleModel->generateInvoiceNumber();

            // Récupérer les données DGI (si présentes)
            $dgiData = $data['dgi_data'] ?? [];

            $saleId = $saleModel->create([
                'numero_facture' => $invoiceNum,
                'client_id'      => $clientId,
                'sous_total_ht'  => $data['sous_total_ht'],
                'tva'            => $data['tva'],
                'total'          => $data['total'],
                'vendeur_id'     => $_SESSION['user_id'],
                'date'           => date('Y-m-d H:i:s'),
                'dateDGI'        => $dgiData['dateDGI'] ?? null,
                'qrCode'         => $dgiData['qrCode'] ?? null,
                'codeDEFDGI'     => $dgiData['codeDEFDGI'] ?? null,
                'counters'       => $dgiData['counters'] ?? null,
                'nim'            => $dgiData['nim'] ?? null,
                'comment'        => $dgiData['comment'] ?? null
            ]);

            // Si providerService existe (recharges), ne pas enregistrer dans details_vente
            $isRecharge = !empty($data['providerService']);

            if (!$isRecharge) {
                foreach ($data['articles'] as $item) {
                    $produitId = $item['produit_id'] ?? 0;

                    // Vérifier le stock pour les vraies ventes
                    $product = $productModel->findById($produitId);
                    if (!$product || $product['stock'] < $item['quantite']) {
                        $db->rollBack();
                        $this->status(400)->json([
                            'error' => 'Stock insuffisant pour le produit: ' . ($product['nom'] ?? $item['produit_id'])
                        ]);
                        return;
                    }
                    // Mettre à jour le stock (décrémenter)
                    $productModel->updateStock($produitId, $item['quantite']);

                    // Créer le détail
                    $detailModel->create([
                        'vente_id'   => $saleId,
                        'produit_id' => $produitId,
                        'quantite'   => $item['quantite'],
                        'prix'       => $item['prix']
                    ]);
                }
            }

            $db->commit();
            $this->json(['success' => true, 'numero_facture' => $invoiceNum, 'vente_id' => $saleId]);
        } catch (\Exception $e) {
            $db->rollBack();
            $this->status(500)->json(['error' => 'Erreur lors de la vente: ' . $e->getMessage()]);
        }
    }

    public function delete($id)
    {

        if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
            $this->status(403)->json(['error' => 'Accès refusé']);
            return;
        }

        if (!$id) {
            $this->status(400)->json(['error' => 'ID de vente manquant']);
            return;
        }

        $saleModel   = new Sale();
        # $detailModel = new SaleDetail();

        try {
            $db = \App\Core\Database::getInstance()->getConnection();
            $db->beginTransaction();

            // Vérifier si la vente existe
            $sale = $saleModel->exist($id);
            if (!$sale) {
                $db->rollBack();
                $this->status(404)->json(['error' => 'Vente inexistante']);
                return;
            }

            // Supprimer la vente
            $db->prepare("DELETE FROM ventes WHERE id = ?")->execute([$id]);

            $db->commit();
            $this->json(['success' => true, 'message' => 'Vente supprimée avec succès']);
        } catch (\Exception $e) {
            $db->rollBack();
            http_response_code(500);
            $this->status(500)->json(['error' => 'Erreur lors de la suppression: ' . $e->getMessage()]);
        }
    }

    public function details($params)
    {
        $id = $params['id'] ?? null;

        if (!$id) {
            $this->status(400)->json(['error' => 'ID manquant']);
            return;
        }

        $saleModel = new Sale();
        $detailModel = new SaleDetail();

        $sale = $saleModel->exist($id);
        if (!$sale) {
            $this->status(404)->json(['error' => 'Vente inexistante']);
            return;
        }

        $details = $detailModel->getBySaleId($id);

        $this->json([
            'sale' => $sale,
            'details' => $details
        ]);
    }

    public function nextInvoice()
    {
        $saleModel = new Sale();
        $invoiceNum = $saleModel->generateInvoiceNumber();
        $this->json(['invoice_number' => $invoiceNum]);
    }
}
