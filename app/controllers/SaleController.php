<?php

namespace App\Controllers;

use App\Models\Client;
use App\Models\Product;
use App\Models\ProductBatch;
use App\Models\Sale;
use App\Models\SaleDetail;

class SaleController extends Controller
{
    public function create()
    {
        if (!$this->requireAuth()) {
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

            // ISF figé sur la vente : c'est la clé de recherche de la facture
            // auprès de la DGI. On conserve exactement l'ISF utilisé lors de
            // l'enregistrement DGI, sinon la facture devient introuvable.
            $shopId = $this->getShopId();

            // 1. ISF envoyé par le client (STORE_INFO.isf) : c'est celui utilisé
            //    pour l'enregistrement DGI, donc la clé de recherche fiable.
            // 2. ISF renvoyé par la DGI en repli.
            $storeIsf = trim((string)($data['store_isf'] ?? ''));
            if ($storeIsf === '') {
                $storeIsf = trim((string)($dgiData['isf'] ?? ''));
            }

            // 3. Repli serveur : boutique rattachée, puis société (super_admin)
            if ($storeIsf === '') {
                $shop = $shopId ? (new \App\Models\Shop())->findById($shopId) : null;
                $storeIsf = trim((string)($shop['isf'] ?? ''));
            }
            if ($storeIsf === '') {
                $companyInfo = (new \App\Models\CompanyInfo())->get();
                $storeIsf = trim((string)($companyInfo['isf'] ?? ''));
            }

            $saleId = $saleModel->create([
                'numero_facture' => $invoiceNum,
                'client_id'      => $clientId,
                'sous_total_ht'  => $data['sous_total_ht'],
                'tva'            => $data['tva'],
                'total'          => $data['total'],
                'payments'       => $data['payments'] ?? null,
                'vendeur_id'     => $_SESSION['user_id'],
                'shop_id'        => $shopId,
                'store_isf'      => $storeIsf !== '' ? $storeIsf : null,
                'date'           => date('Y-m-d H:i:s'),
                'dateDGI'        => $dgiData['dateDGI'] ?? null,
                'qrCode'         => $dgiData['qrCode'] ?? null,
                'codeDEFDGI'     => $dgiData['codeDEFDGI'] ?? null,
                'counters'       => $dgiData['counters'] ?? null,
                'nim'            => $dgiData['nim'] ?? null,
                'comment'        => $dgiData['comment'] ?? null,
                'service'        => $data['providerService'] ?? null,
            ]);

            // Si providerService existe (recharges), ne pas enregistrer dans details_vente
            $isRecharge = !empty($data['providerService']);

            if (!$isRecharge) {
                foreach ($data['articles'] as $item) {
                    $produitId = $item['produit_id'] ?? 0;
                    $quantite = $item['quantite'] ?? 0;

                    // Vérifier le stock uniquement pour les ventes positives
                    // (pour les avoirs/annulations avec quantité négative, on ne vérifie pas)
                    $batchModel = new ProductBatch();
                    if ($quantite > 0) {
                        $product = $productModel->findById($produitId);
                        if (!$product || !$batchModel->hasEnoughStock($produitId, $quantite)) {
                            $db->rollBack();
                            $this->status(400)->json([
                                'error' => 'Stock insuffisant pour le produit: ' . ($product['nom'] ?? $item['produit_id']),
                            ]);
                            return;
                        }
                    }

                    // Mettre à jour le stock par lots (FIFO) pour les ventes positives,
                    // restaurer le stock pour les avoirs/annulations (quantité négative)
                    if ($quantite > 0) {
                        $deductSuccess = $batchModel->deductStock($produitId, $quantite);
                        if (!$deductSuccess) {
                            $db->rollBack();
                            $this->status(400)->json([
                                'error' => 'Erreur lors de la deduction du stock pour: ' . ($product['nom'] ?? $item['produit_id']),
                            ]);
                            return;
                        }
                    } else {
                        $batchModel->restoreStock($produitId, -$quantite);
                    }

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
                        'taxe_specifique_value' => $item['taxe_specifique_value'] ?? 0,
                    ]);
                }
            }

            $db->commit();

            $this->logAudit('create', 'vente', $saleId, [
                'numero_facture' => $invoiceNum,
                'total' => $data['total'],
                'type_facture' => $typeFacture,
            ]);

            $this->json([
                'success' => true,
                'numero_facture' => $invoiceNum,
                'vente_id' => $saleId,
                'type_facture' => $typeFacture,
                'should_negate' => $shouldNegate,
            ]);
        } catch (\Exception $e) {
            $db->rollBack();
            $this->status(500)->json(['error' => 'Erreur lors de la vente: ' . $e->getMessage()]);
        }
    }

    public function delete($id)
    {
        if (!$this->requireAdmin()) {
            return;
        }

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

            // Restaurer le stock avant suppression (via lots FIFO)
            $batchModel = new ProductBatch();
            $details = $detailModel->getBySaleId($id);
            foreach ($details as $detail) {
                $batchModel->restoreStock($detail['produit_id'], $detail['quantite']);
            }

            // Supprimer la vente (les détails sont supprimés en cascade)
            $db->prepare('DELETE FROM ventes WHERE id = ?')->execute([$id]);

            $db->commit();

            $this->logAudit('delete', 'vente', $id, [
                'numero_facture' => $sale['numero_facture'],
                'total' => $sale['total'],
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
        if (!$this->requireAuth()) {
            return;
        }

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
            'details' => $details,
        ]);
    }

    public function nextInvoice()
    {
        if (!$this->requireAuth()) {
            return;
        }

        $saleModel = new Sale();
        $invoiceNum = $saleModel->generateInvoiceNumber();
        $this->json(['invoice_number' => $invoiceNum]);
    }

    // ── Archives ─────────────────────────────────────────────────

    public function archives()
    {
        if (!$this->requireAdmin()) {
            return;
        }

        $shopId = $this->isSuperAdmin() ? ($_GET['shop_id'] ?? null) : $this->getShopId();
        $saleModel = new Sale();
        $archives = $saleModel->searchArchive($shopId, 100, 0);

        $this->json(['data' => $archives]);
    }

    // ── Rapport de clôture ──────────────────────────────────────

    public function cloture()
    {
        if (!$this->requireAdmin()) {
            return;
        }

        $date = $_GET['date'] ?? date('Y-m-d');
        $shopId = $this->isSuperAdmin() ? ($_GET['shop_id'] ?? null) : $this->getShopId();

        $db = \App\Core\Database::getInstance();

        $where = 'WHERE DATE(v.date) = ?';
        $params = [$date];
        if ($shopId) {
            $where .= ' AND v.shop_id = ?';
            $params[] = $shopId;
        }

        // Total ventes du jour
        $sql = "SELECT COUNT(*) as nb_ventes, COALESCE(SUM(v.total),0) as total_ventes,
                       COALESCE(SUM(v.tva),0) as total_tva, COALESCE(SUM(v.sous_total_ht),0) as total_ht
                FROM ventes v $where";
        $totals = $db->fetch($sql, $params);

        // Ventes par vendeur
        $sql = "SELECT u.nom_complet, COUNT(*) as nb, COALESCE(SUM(v.total),0) as total
                FROM ventes v LEFT JOIN utilisateurs u ON v.vendeur_id = u.id
                $where GROUP BY v.vendeur_id ORDER BY total DESC";
        $byVendeur = $db->fetchAll($sql, $params);

        // Ventes par méthode de paiement
        $sql = "SELECT v.payments, COUNT(*) as nb, COALESCE(SUM(v.total),0) as total
                FROM ventes v $where GROUP BY v.payments";
        $byPaymentRaw = $db->fetchAll($sql, $params);

        // payments est stocké en JSON (tableau de {type, amount}) : on agrège par type
        $byPayment = [];
        foreach ($byPaymentRaw as $row) {
            $decoded = json_decode($row['payments'] ?? '[]', true);
            if (is_array($decoded) && count($decoded)) {
                foreach ($decoded as $payment) {
                    $type = strtoupper($payment['type'] ?? $payment['mode'] ?? '');
                    $amount = (float) ($payment['amount'] ?? 0);
                    if (!$type) {
                        continue;
                    }
                    if (!isset($byPayment[$type])) {
                        $byPayment[$type] = ['type' => $type, 'nb' => 0, 'total' => 0];
                    }
                    $byPayment[$type]['nb'] += 1;
                    $byPayment[$type]['total'] += $amount;
                }
            } else {
                $type = strtoupper($row['payments'] ?: 'CASH');
                if (!isset($byPayment[$type])) {
                    $byPayment[$type] = ['type' => $type, 'nb' => 0, 'total' => 0];
                }
                $byPayment[$type]['nb'] += (int)$row['nb'];
                $byPayment[$type]['total'] += (float)$row['total'];
            }
        }
        usort($byPayment, fn ($a, $b) => $b['total'] <=> $a['total']);

        // Top 5 produits vendus
        $sql = "SELECT p.nom, SUM(dv.quantite) as qty, SUM(dv.prix * dv.quantite) as revenue
                FROM details_vente dv
                INNER JOIN ventes v ON dv.vente_id = v.id
                LEFT JOIN produits p ON dv.produit_id = p.id
                $where GROUP BY dv.produit_id ORDER BY qty DESC LIMIT 5";
        $topProducts = $db->fetchAll($sql, $params);

        $this->json([
            'date' => $date,
            'shop_id' => $shopId,
            'totals' => $totals,
            'by_vendeur' => $byVendeur,
            'by_payment' => $byPayment,
            'top_products' => $topProducts,
        ]);
    }

    // ── Export CSV des ventes ────────────────────────────────────

    public function exportCsv()
    {
        if (!$this->requireAdmin()) {
            return;
        }

        $from = $_GET['from'] ?? date('Y-m-01');
        $to = $_GET['to'] ?? date('Y-m-d');
        $shopId = $this->isSuperAdmin() ? ($_GET['shop_id'] ?? null) : $this->getShopId();

        $where = 'WHERE DATE(v.date) BETWEEN ? AND ?';
        $params = [$from, $to];
        if ($shopId) {
            $where .= ' AND v.shop_id = ?';
            $params[] = $shopId;
        }

        $db = \App\Core\Database::getInstance();
        $sql = "SELECT v.numero_facture, v.date, u.nom_complet as vendeur,
                       c.nom_client as client, v.sous_total_ht, v.tva, v.total, v.payments
                FROM ventes v
                LEFT JOIN utilisateurs u ON v.vendeur_id = u.id
                LEFT JOIN clients c ON v.client_id = c.id
                $where ORDER BY v.date ASC";
        $rows = $db->fetchAll($sql, $params);

        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="ventes_' . $from . '_' . $to . '.csv"');

        $out = fopen('php://output', 'w');
        fprintf($out, chr(0xEF) . chr(0xBB) . chr(0xBF)); // BOM UTF-8
        fputs($out, "sep=;\n"); // Indiquer à Excel d'utiliser le point-virgule comme séparateur

        fputcsv($out, ['N° Facture', 'Date', 'Vendeur', 'Client', 'Sous-total HT', 'TVA', 'Total', 'Paiement'], ';');

        $labels = [
            'ESPECES' => 'Espèces',
            'MOBILEMONEY' => 'Mobile Money',
            'CARTEBANCAIRE' => 'Carte Bancaire',
            'VIREMENT' => 'Virement',
            'CREDIT' => 'Crédit',
            'CHEQUES' => 'Chèques',
            'AUTRE' => 'Autres',
        ];

        foreach ($rows as $r) {
            $payments = json_decode($r['payments'] ?? '[]', true);
            if (!is_array($payments) || empty($payments)) {
                $paymentText = 'Espèces';
            } else {
                $parts = [];
                foreach ($payments as $p) {
                    $type = $p['type'] ?? ($p['mode'] ?? 'ESPECES');
                    $amount = floatval($p['amount'] ?? 0);
                    $parts[] = ($labels[$type] ?? $type) . ' : ' . number_format($amount, 2, ',', ' ');
                }
                $paymentText = implode(' | ', $parts);
            }

            fputcsv($out, [
                $r['numero_facture'],
                date('d/m/Y H:i', strtotime($r['date'])),
                $r['vendeur'] ?? '',
                $r['client'] ?? 'Anonyme',
                number_format($r['sous_total_ht'], 2, ',', ' '),
                number_format($r['tva'], 2, ',', ' '),
                number_format($r['total'], 2, ',', ' '),
                $paymentText,
            ], ';');
        }
        fclose($out);
        exit;
    }
}
