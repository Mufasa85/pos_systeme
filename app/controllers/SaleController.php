<?php
// app/controllers/SaleController.php

require_once BASE_PATH . 'app/models/Sale.php';
require_once BASE_PATH . 'app/models/SaleDetail.php';
require_once BASE_PATH . 'app/models/Product.php';

class SaleController {
    
    public function apiCreate() {
        header('Content-Type: application/json');
        if (!isset($_SESSION['user_id'])) {
            http_response_code(403);
            echo json_encode(['error' => 'Non authentifié']);
            return;
        }

        $data = json_decode(file_get_contents('php://input'), true);
        if (empty($data) || empty($data['articles'])) {
            http_response_code(400);
            echo json_encode(['error' => 'Panier vide ou données invalides']);
            return;
        }

        $saleModel = new Sale();
        $detailModel = new SaleDetail();
        $productModel = new Product();

        try {
            $db = Database::getInstance()->getConnection();
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
            echo json_encode(['success' => true, 'numero_facture' => $invoiceNum, 'vente_id' => $saleId]);

        } catch (Exception $e) {
            $db->rollBack();
            http_response_code(500);
            echo json_encode(['error' => 'Erreur lors de la vente: ' . $e->getMessage()]);
        }
    }
}
