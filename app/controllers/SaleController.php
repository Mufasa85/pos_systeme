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
        if (!$this->requireAuth()) return;

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

        // Logique de négation (cohérent avec le frontend):
        // Si la facture N'est PAS FA ou EA, les quantités et les totaux
        // sont négatifs. Le SQL `stock - :quantite` ajoutera au stock
        // quand :quantite est négatif (ce qui correspond aux avoirs/annulations).
        $typeFacture = $data['type_facture'] ?? 'FV';
        $shouldNegate = $typeFacture !== 'FA' && $typeFacture !== 'EA';

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
                'payments'       => $data['payments'] ?? null,
                'vendeur_id'     => $_SESSION['user_id'],
                'shop_id'        => $this->getShopId(),
                'date'           => date('Y-m-d H:i:s'),
                'dateDGI'        => $dgiData['dateDGI'] ?? null,
                'qrCode'         => $dgiData['qrCode'] ?? null,
                'codeDEFDGI'     => $dgiData['codeDEFDGI'] ?? null,
                'counters'       => $dgiData['counters'] ?? null,
                'nim'            => $dgiData['nim'] ?? null,
                'comment'        => $dgiData['comment'] ?? null,
                'service'        => $data['providerService'] ?? null
            ]);

            // Si providerService existe (recharges), ne pas enregistrer dans details_vente
            $isRecharge = !empty($data['providerService']);

            if (!$isRecharge) {
                foreach ($data['articles'] as $item) {
                    $produitId = $item['produit_id'] ?? 0;
                    $quantite = $item['quantite'] ?? 0;

                    // Vérifier le stock uniquement pour les ventes positives
                    // (pour les avoirs/annulations avec quantité négative, on ne vérifie pas)
                    if ($quantite > 0) {
                        $product = $productModel->findById($produitId);
                        if (!$product || $product['stock'] < $quantite) {
                            $db->rollBack();
                            $this->status(400)->json([
                                'error' => 'Stock insuffisant pour le produit: ' . ($product['nom'] ?? $item['produit_id'])
                            ]);
                            return;
                        }
                    }

                    // Mettre à jour le stock: si quantite est positif on décrémente,
                    // si négatif on incrémente (retour/annulation)
                    $productModel->updateStock($produitId, $quantite);

                    // Vérifier si le stock passe sous le minimum → notification
                    if ($quantite > 0) {
                        $updatedProduct = $productModel->findById($produitId);
                        if ($updatedProduct && $updatedProduct['stock'] <= ($updatedProduct['stock_minimum'] ?? 0)) {
                            $this->notifyShopAdmins(
                                $this->getShopId(),
                                'stock_low',
                                'Stock faible : ' . $updatedProduct['nom'],
                                "Le produit \"{$updatedProduct['nom']}\" n'a plus que {$updatedProduct['stock']} unités (minimum: {$updatedProduct['stock_minimum']})",
                                '/produits'
                            );
                        }
                    }

                    // Créer le détail avec la quantité (négative ou positive selon le type)
                    $detailModel->create([
                        'vente_id'   => $saleId,
                        'produit_id' => $produitId,
                        'quantite'   => $quantite,
                        'prix'       => $item['prix'],
                        'remise_type' => $item['remise_type'] ?? '%',
                        'remise_value' => $item['remise_value'] ?? 0,
                        'taxe_specifique_type' => $item['taxe_specifique_type'] ?? '%',
                        'taxe_specifique_value' => $item['taxe_specifique_value'] ?? 0
                    ]);
                }
            }

            $db->commit();

            $this->logAudit('create', 'vente', $saleId, [
                'numero_facture' => $invoiceNum,
                'total' => $data['total'],
                'type_facture' => $typeFacture
            ]);

            $this->json([
                'success' => true,
                'numero_facture' => $invoiceNum,
                'vente_id' => $saleId,
                'type_facture' => $typeFacture,
                'should_negate' => $shouldNegate
            ]);
        } catch (\Exception $e) {
            $db->rollBack();
            $this->status(500)->json(['error' => 'Erreur lors de la vente: ' . $e->getMessage()]);
        }
    }

    public function delete($id)
    {
        if (!$this->requireAdmin()) return;

        if (!$id) {
            $this->status(400)->json(['error' => 'ID de vente manquant']);
            return;
        }

        $saleModel   = new Sale();
        $productModel = new Product();
        $detailModel = new SaleDetail();

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

            // Vérifier que la vente appartient à la boutique (sauf super_admin)
            if (!$this->isSuperAdmin() && $sale['shop_id'] != $this->getShopId()) {
                $db->rollBack();
                $this->status(403)->json(['error' => 'Cette vente ne fait pas partie de votre boutique']);
                return;
            }

            // Restaurer le stock avant suppression
            $details = $detailModel->getBySaleId($id);
            foreach ($details as $detail) {
                // stock - (-quantite) = stock + quantite → restauration
                $productModel->updateStock($detail['produit_id'], -$detail['quantite']);
            }

            // Supprimer la vente (les détails sont supprimés en cascade)
            $db->prepare("DELETE FROM ventes WHERE id = ?")->execute([$id]);

            $db->commit();

            $this->logAudit('delete', 'vente', $id, [
                'numero_facture' => $sale['numero_facture'],
                'total' => $sale['total']
            ]);

            // Notification action suspecte
            $this->notifySuperAdmins(
                'suspicious_action',
                'Suppression de vente',
                "La vente #{$sale['numero_facture']} (total: {$sale['total']}) a été supprimée par " . ($_SESSION['nom_complet'] ?? 'inconnu'),
                '/historique'
            );

            $this->json(['success' => true, 'message' => 'Vente supprimée avec succès (stock restauré)']);
        } catch (\Exception $e) {
            $db->rollBack();
            $this->status(500)->json(['error' => 'Erreur lors de la suppression: ' . $e->getMessage()]);
        }
    }

    public function details($params)
    {
        if (!$this->requireAuth()) return;

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
        if (!$this->requireAuth()) return;

        $saleModel = new Sale();
        $invoiceNum = $saleModel->generateInvoiceNumber();
        $this->json(['invoice_number' => $invoiceNum]);
    }
}
