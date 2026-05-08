<!-- Facture Client Page - Unified Design (Public) -->
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Facture <?= htmlspecialchars($saleData['numero_facture'] ?? '') ?> - <?= htmlspecialchars($storeData['name'] ?? 'Mon Magasin') ?></title>
    <link rel="stylesheet" href="/assets/css/styles.css?v=210">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=JetBrains+Mono:wght@400;500&display=swap" rel="stylesheet">
    <style>
        body { margin: 0; padding: 0; background: #f5f5f5; font-family: 'Inter', sans-serif; }
        .public-container { display: flex; justify-content: center; align-items: flex-start; min-height: 100vh; padding: 2rem 1rem; }
    </style>
</head>
<body>

<div class="public-container">
    <div class="receipt" id="receipt-ticket">
        <!-- Receipt Header -->
        <div class="receipt-header">
            <div style="text-align: center; font-weight: 800; font-size: 24px; color: #000; margin-bottom: 10px; border-bottom: 2px solid #000; padding-bottom: 5px;">PRO FORMAT</div>
            <div class="store-name">
                <?= htmlspecialchars($storeData['name'] ?? 'SuperMarche Express') ?>
            </div>
            <div class="store-info">
                <div><?= htmlspecialchars($storeData['address'] ?? '') ?></div>
                <div>Tel: <?= htmlspecialchars($storeData['phone'] ?? '') ?></div>
                <div>ID Nat: <?= htmlspecialchars($storeData['ice'] ?? '') ?></div>
                <?php if (!empty($storeData['isf'])): ?>
                    <div>Numero Impot: <?= htmlspecialchars($storeData['isf']) ?></div>
                <?php endif; ?>
            </div>

            <!-- Info Section (Vendeur, Client) -->
            <?php
            $vendeur = $saleData['nom_vendeur'] ?? 'N/A';
            $clientNom = $saleData['client_nom'] ?? $saleData['nom_client'] ?? '';
            $clientNumero = $saleData['client_numero'] ?? '';
            $clientType = $saleData['client_type_code'] ?? $saleData['client_type'] ?? '';
            $clientNif = $saleData['client_nif'] ?? '';
            ?>
            <div style="border-top: 1px dashed #ccc; margin-top: 6px; padding-top: 6px; text-align: left; font-size: 15px; line-height: 1.5;">
                <div style="display: flex; justify-content: space-between; gap: 10px;">
                    <span><strong>Vendeur:</strong></span>
                    <span><?= htmlspecialchars($vendeur) ?></span>
                </div>
                <?php if ($clientNom): ?>
                    <div style="display: flex; justify-content: space-between; gap: 10px;">
                        <span><strong>Client:</strong></span>
                        <span><?= htmlspecialchars($clientNom) ?></span>
                    </div>
                <?php endif; ?>
                <?php if ($clientNumero): ?>
                    <div style="display: flex; justify-content: space-between; gap: 10px;">
                        <span><strong>Num:</strong></span>
                        <span><?= htmlspecialchars($clientNumero) ?></span>
                    </div>
                <?php endif; ?>
                <?php if ($clientType): ?>
                    <div style="display: flex; justify-content: space-between; gap: 10px;">
                        <span><strong>Type:</strong></span>
                        <span><?= htmlspecialchars($clientType) ?></span>
                    </div>
                <?php endif; ?>
                <?php if ($clientNif): ?>
                    <div style="display: flex; justify-content: space-between; gap: 10px;">
                        <span><strong>NIF:</strong></span>
                        <span><?= htmlspecialchars($clientNif) ?></span>
                    </div>
                <?php endif; ?>
                <?php if (!empty($storeData['isf'])): ?>
                    <div style="display: flex; justify-content: space-between; gap: 10px;">
                        <span><strong>ISF:</strong></span>
                        <span><?= htmlspecialchars($storeData['isf']) ?></span>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Receipt Meta -->
        <div class="receipt-meta">
            <span><?= htmlspecialchars($saleData['numero_facture'] ?? '') ?></span>
            <span><?= htmlspecialchars($saleData['type_facture'] ?? 'FV') ?></span>
        </div>

        <!-- Receipt Items -->
        <div class="receipt-items receipt-items-grid">
            <?php foreach (($saleDetails ?? []) as $item):
                $itemHT = floatval($item['quantite']) * floatval($item['prix']);
                $taxRate = floatval($item['tax_rate'] ?? 0);
                $itemTax = $itemHT * ($taxRate / 100);
                $itemTTC = $itemHT + $itemTax;
                $taxLabel = !empty($item['tax_etiquette']) ? htmlspecialchars($item['tax_etiquette']) : ($taxRate > 0 ? 'TVA ' . $taxRate . '%' : 'Exonere');
            ?>
                <div class="receipt-item">
                    <span class="item-name">
                        <?= htmlspecialchars($item['produit_nom'] ?? 'Produit') ?>
                        <span class="item-tax-badge"><?= $taxLabel ?></span>
                    </span>
                    <span class="item-qty">x<?= $item['quantite'] ?></span>
                    <span class="item-price"><?= number_format($itemTTC, 2, '.', '') ?></span>
                </div>
            <?php endforeach; ?>
        </div>

        <!-- Receipt Totals -->
        <div class="receipt-totals">
            <div class="receipt-total-row">
                <span>Sous-total HT:</span>
                <span><?= number_format(floatval($saleData['sous_total_ht'] ?? 0), 2, '.', '') ?> Fc</span>
            </div>
            <div class="receipt-total-row">
                <span>TVA:</span>
                <span><?= number_format(floatval($saleData['tva'] ?? 0), 2, '.', '') ?> Fc</span>
            </div>
            <div class="receipt-total-row grand-total">
                <span>TOTAL TTC:</span>
                <span><?= number_format(floatval($saleData['total'] ?? 0), 2, '.', '') ?> Fc</span>
            </div>
            <div style="margin: 10px 0; font-size: 11px; color: #333; border: 1px dashed #ccc; padding: 8px; border-radius: 4px; text-align: left;">
                <div style="font-weight: bold; text-decoration: underline; margin-bottom: 4px;">Commentaire/Remarque :</div>
                <div><?= !empty($saleData['comment']) ? htmlspecialchars($saleData['comment']) : 'Aucun commentaire' ?></div>
            </div>
        </div>

        <!-- DGI Validation -->
        <?php if (!empty($saleData['counters'])): ?>
            <div style="background: #e8f5e9; border: 1px solid #4caf50; border-radius: 8px; padding: 10px; margin: 10px 0; text-align: center;">
                <div style="color: #2e7d32; font-weight: bold; font-size: 11px;">--- Elements de securite de la facture normalisee ---</div>
                <div style="font-size: 12px; color: #555; margin-top: 4px;">
                    <?php if (!empty($saleData['codeDEFDGI'])): ?>
                        CODE DEF/DGI: <?= htmlspecialchars($saleData['codeDEFDGI']) ?>
                    <?php endif; ?>
                    <?php if (!empty($saleData['nim'])): ?>
                        <br> DEF NID : <?= htmlspecialchars($saleData['nim']) ?>
                    <?php endif; ?>
                    <?php if (!empty($saleData['counters'])): ?>
                        <br> DEF Compteurs: <?= htmlspecialchars($saleData['counters']) ?>
                    <?php endif; ?>
                    <?php if (!empty($saleData['dateDGI'])): ?>
                        <br> DEF Heure : <?= htmlspecialchars($saleData['dateDGI']) ?>
                    <?php endif; ?>
                    <br> ISF : <?= htmlspecialchars($storeData['isf'] ?? '0') ?>
                </div>
            </div>
        <?php endif; ?>

        <!-- Receipt Footer -->
        <div class="receipt-footer">
            <div id="ticket-qrcode" class="qrcode-container"></div>
            
            <div class="barcode" style="margin: 0.5rem 0;">
                <?= htmlspecialchars($saleData['numero_facture'] ?? '') ?>
            </div>

            <div class="thank-you">
                Merci de votre confiance!
            </div>
            <p style="margin-top: 5px; color: #555; font-size: 9px; opacity: 0.7;">---Powered By Osat---</p>
        </div>
    </div>
</div>

<?php if (!empty($saleData['qrCode'])): ?>
    <script src="https://cdn.jsdelivr.net/npm/qr-code-styling@1.5.0/lib/qr-code-styling.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const qrCode = new QRCodeStyling({
                width: 140,
                height: 140,
                type: "svg",
                data: "<?= addslashes($saleData['qrCode']) ?>",
                margin: 0,
                dotsOptions: {
                    color: "#000000",
                    type: "rounded"
                },
                backgroundOptions: {
                    color: "#ffffff"
                },
                cornersSquareOptions: {
                    type: "extra-rounded"
                }
            });
            qrCode.append(document.getElementById('ticket-qrcode'));
        });
    </script>
<?php endif; ?>

</body>
</html>

