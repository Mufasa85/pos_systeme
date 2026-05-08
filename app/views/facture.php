<!-- Facture Page - Unified Design -->
<div class="no-print" style="width: 100%; padding: 1rem; display: flex; justify-content: space-between; align-items: center; max-width: 420px; margin: 0 auto;">
    <h2 style="margin: 0; font-size: 1.125rem; color: #333;">Détails de la facture</h2>
    <div style="display: flex; gap: 0.5rem; align-items: center;">
        <button onclick="window.print()" class="btn btn-primary" style="display: inline-flex; align-items: center; gap: 0.375rem;">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <polyline points="6 9 6 2 18 2 18 9"></polyline>
                <path d="M6 18H4a2 2 0 0 1-2-2v-5a2 2 0 0 1 2-2h16a2 2 0 0 1 2 2v5a2 2 0 0 1-2 2h-2"></path>
                <rect x="6" y="14" width="12" height="8"></rect>
            </svg>
            Imprimer
        </button>
    </div>
</div>

<div style="display: flex; justify-content: center; padding: 1rem; background: #f5f5f5; min-height: 100vh;">
    <div class="receipt" id="receipt-ticket">
        <!-- Receipt Header -->
        <div class="receipt-header">
            <div style="text-align: center; font-weight: 800; font-size: 24px; color: #000; margin-bottom: 10px; border-bottom: 2px solid #000; padding-bottom: 5px;">PRO FORMAT</div>
            <div class="store-name">
                <?= htmlspecialchars($storeInfo['name'] ?? 'SuperMarche Express') ?>
            </div>
            <div class="store-info">
                <div><?= htmlspecialchars($storeInfo['address'] ?? '') ?></div>
                <div>Tel: <?= htmlspecialchars($storeInfo['phone'] ?? '') ?></div>
                <div>ID Nat: <?= htmlspecialchars($storeInfo['ice'] ?? '') ?></div>
                <?php if (!empty($storeInfo['isf'])): ?>
                    <div>Numero Impot: <?= htmlspecialchars($storeInfo['isf']) ?></div>
                <?php endif; ?>
            </div>

            <!-- Info Section (Vendeur, Client) -->
            <?php
            $vendeur = $sale['nom_vendeur'] ?? 'N/A';
            $clientNom = $sale['client_nom'] ?? $sale['nom_client'] ?? '';
            $clientNumero = $sale['client_numero'] ?? '';
            $clientType = $sale['client_type_code'] ?? $sale['client_type'] ?? '';
            $clientNif = $sale['client_nif'] ?? '';
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
                <?php if (!empty($storeInfo['isf'])): ?>
                    <div style="display: flex; justify-content: space-between; gap: 10px;">
                        <span><strong>ISF:</strong></span>
                        <span><?= htmlspecialchars($storeInfo['isf']) ?></span>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Receipt Meta -->
        <div class="receipt-meta">
            <span><?= htmlspecialchars($sale['numero_facture'] ?? '') ?></span>
            <span><?= htmlspecialchars($sale['type_facture'] ?? 'FV') ?></span>
        </div>

        <!-- Receipt Items -->
        <div class="receipt-items receipt-items-grid">
            <?php foreach (($details ?? []) as $item):
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
                <span><?= number_format(floatval($sale['sous_total_ht'] ?? 0), 2, '.', '') ?> Fc</span>
            </div>
            <div class="receipt-total-row">
                <span>TVA:</span>
                <span><?= number_format(floatval($sale['tva'] ?? 0), 2, '.', '') ?> Fc</span>
            </div>
            <div class="receipt-total-row grand-total">
                <span>TOTAL TTC:</span>
                <span><?= number_format(floatval($sale['total'] ?? 0), 2, '.', '') ?> Fc</span>
            </div>
            <div style="margin: 10px 0; font-size: 11px; color: #333; border: 1px dashed #ccc; padding: 8px; border-radius: 4px; text-align: left;">
                <div style="font-weight: bold; text-decoration: underline; margin-bottom: 4px;">Commentaire/Remarque :</div>
                <div><?= !empty($sale['comment']) ? htmlspecialchars($sale['comment']) : 'Aucun commentaire' ?></div>
            </div>
        </div>

        <!-- DGI Validation -->
        <?php if (!empty($sale['counters'])): ?>
            <div style="background: #e8f5e9; border: 1px solid #4caf50; border-radius: 8px; padding: 10px; margin: 10px 0; text-align: center;">
                <div style="color: #2e7d32; font-weight: bold; font-size: 11px;">--- Elements de securite de la facture normalisee ---</div>
                <div style="font-size: 12px; color: #555; margin-top: 4px;">
                    <?php if (!empty($sale['codeDEFDGI'])): ?>
                        CODE DEF/DGI: <?= htmlspecialchars($sale['codeDEFDGI']) ?>
                    <?php endif; ?>
                    <?php if (!empty($sale['nim'])): ?>
                        <br> DEF NID : <?= htmlspecialchars($sale['nim']) ?>
                    <?php endif; ?>
                    <?php if (!empty($sale['counters'])): ?>
                        <br> DEF Compteurs: <?= htmlspecialchars($sale['counters']) ?>
                    <?php endif; ?>
                    <?php if (!empty($sale['dateDGI'])): ?>
                        <br> DEF Heure : <?= htmlspecialchars($sale['dateDGI']) ?>
                    <?php endif; ?>
                    <br> ISF : <?= htmlspecialchars($storeInfo['isf'] ?? '0') ?>
                </div>
            </div>
        <?php endif; ?>

        <!-- Receipt Footer -->
        <div class="receipt-footer">
            <div id="ticket-qrcode" class="qrcode-container"></div>
            
            <div class="barcode" style="margin: 0.5rem 0;">
                <?= htmlspecialchars($sale['numero_facture'] ?? '') ?>
            </div>

            <div class="thank-you">
                Merci de votre visite!
            </div>
            <p style="margin-top: 5px; color: #555; font-size: 9px; opacity: 0.7;">---Powered By Osat---</p>
        </div>
    </div>
</div>

<?php if (!empty($sale['qrCode'])): ?>
    <script src="https://cdn.jsdelivr.net/npm/qr-code-styling@1.5.0/lib/qr-code-styling.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const qrCode = new QRCodeStyling({
                width: 140,
                height: 140,
                type: "svg",
                data: "<?= addslashes($sale['qrCode']) ?>",
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
