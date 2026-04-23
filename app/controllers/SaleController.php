<?php

namespace App\Controllers;

use App\Models\Product;
use App\Models\Sale;
use App\Models\SaleDetail;
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

        try {
            $db = \App\Core\Database::getInstance()->getConnection();
            $db->beginTransaction();

            $invoiceNum = $saleModel->generateInvoiceNumber();

            $saleId = $saleModel->create([
                'numero_facture' => $invoiceNum,
                'sous_total_ht'  => $data['sous_total_ht'],
                'tva'            => $data['tva'],
                'total'          => $data['total'],
                'vendeur_id'     => $_SESSION['user_id'],
                'date'           => date('Y-m-d H:i:s')
            ]);

            foreach ($data['articles'] as $item) {
                // Créer le détail
                $detailModel->create([
                    'vente_id'   => $saleId,
                    'produit_id' => $item['produit_id'],
                    'quantite'   => $item['quantite'],
                    'prix'       => $item['prix']
                ]);

                // Mettre à jour le stock
                $productModel->updateStock($item['produit_id'], $item['quantite']);
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
}
