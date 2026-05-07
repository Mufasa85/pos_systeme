<!-- Facture Ticket Page - Style like showPreview -->
<!-- Actions bar - will be hidden during print -->
<div class="no-print" style="width: 100%; padding: 1rem 1rem; display: flex; justify-content: space-between; align-items: center;">
    <h2 style="margin: 0; font-size: 1.125rem; color: #333;">Ticket de caisse</h2>
    <div style="display: flex; gap: 0.5rem; align-items: center;">
        <button onclick="window.print()" style="display: inline-flex; align-items: center; gap: 0.375rem; padding: 0.5rem 1rem; background: #0B5E88; color: white; border: none; border-radius: 8px; font-size: 0.875rem; cursor: pointer;">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <polyline points="6 9 6 2 18 2 18 9"></polyline>
                <path d="M6 18H4a2 2 0 0 1-2-2v-5a2 2 0 0 1 2-2h16a2 2 0 0 1 2 2v5a2 2 0 0 1-2 2h-2"></path>
                <rect x="6" y="14" width="12" height="8"></rect>
            </svg>
            Imprimer
        </button>
    </div>
</div>

<!-- Receipt - The actual ticket -->
<div class="receipt" id="receipt-ticket" style="background: white; border-radius: 12px; overflow: hidden;">
    <!-- Receipt Header -->
    <div style="text-align: center; padding: 1.5rem 1rem 1rem; border-bottom: 2px solid #000; background: linear-gradient(135deg, #f8f9fa 0%, #fff 100%);">
        <div style="font-size: 1.25rem; font-weight: 700; text-transform: uppercase; letter-spacing: 0.5px; color: #0B5E88; margin-bottom: 0.5rem;">
            <?= htmlspecialchars($storeInfo['name'] ?? 'SuperMarche Express') ?>
        </div>
        <div style="font-size: 0.8125rem; line-height: 1.5; color: #555;">
            <?= htmlspecialchars($storeInfo['address'] ?? '') ?><br>
            Tel: <?= htmlspecialchars($storeInfo['phone'] ?? '') ?><br>
            ID nat: <?= htmlspecialchars($storeInfo['ice'] ?? '') ?>
            <?php if (!empty($storeInfo['rccm'])): ?>
                <br>RCCM: <?= htmlspecialchars($storeInfo['rccm']) ?>
            <?php endif; ?>
            <?php if (!empty($storeInfo['isf'])): ?>
                <br>ISF: <?= htmlspecialchars($storeInfo['isf']) ?>
            <?php endif; ?>
        </div>
    </div>

    <!-- Info Section: Vendeur + Client -->
    <?php
    $vendeur = $sale['nom_vendeur'] ?? 'N/A';
    $clientNom = $sale['client_nom'] ?? '';
    $clientNumero = $sale['client_numero'] ?? '';
    $clientType = $sale['client_type'] ?? '';
    $clientNif = $sale['client_nif'] ?? '';
    ?>
    <?php if ($vendeur || $clientNom || $clientNumero || $clientType || $clientNif || !empty($storeInfo['isf'])): ?>
        <div style="border-top: 1px dashed #ccc; margin: 0 1rem; padding: 0.75rem 0; text-align: left; font-size: 0.75rem; line-height: 1.5;">
            <?php if ($vendeur && $vendeur !== 'N/A'): ?>
                <div style="display: flex; justify-content: space-between; gap: 10px;">
                    <span><strong>Vendeur:</strong></span>
                    <span><?= htmlspecialchars($vendeur) ?></span>
                </div>
            <?php endif; ?>
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
    <?php endif; ?>

    <!-- Receipt Meta -->
    <div style="display: flex; justify-content: space-between; font-size: 0.875rem; font-weight: 600; padding: 0.75rem 1rem; margin: 0 1rem; border-bottom: 2px solid #000;">
        <span style="font-family: 'JetBrains Mono', monospace;"><?= htmlspecialchars($sale['numero_facture'] ?? '') ?></span>
        <span style="color: #555;"><?= date('d/m/Y H:i', strtotime($sale['date'] ?? 'now')) ?></span>
    </div>

    <!-- Receipt Items with Tax Badges -->
    <div style="padding: 1rem;">
        <?php foreach (($details ?? []) as $item):
            $itemHT = floatval($item['quantite']) * floatval($item['prix']);
            $taxRate = floatval($item['tax_rate'] ?? 0);
            $itemTax = $itemHT * ($taxRate / 100);
            $itemTTC = $itemHT + $itemTax;
            $taxLabel = !empty($item['tax_etiquette']) ? htmlspecialchars($item['tax_etiquette']) : ($taxRate > 0 ? 'TVA ' . $taxRate . '%' : 'Exonere');
        ?>
            <div style="display: flex; justify-content: space-between; align-items: baseline; font-size: 0.875rem; margin-bottom: 0.5rem; gap: 0.5rem;">
                <span style="flex: 2; min-width: 0; word-wrap: break-word;">
                    <?= htmlspecialchars($item['produit_nom'] ?? 'Produit') ?>
                    <span style="display: inline-block; font-size: 0.625rem; background: <?= $taxRate > 0 ? '#e3f2fd' : '#f5f5f5' ?>; color: <?= $taxRate > 0 ? '#1565c0' : '#666' ?>; padding: 1px 4px; border-radius: 3px; margin-left: 4px; vertical-align: middle;">
                        <?= $taxLabel ?>
                    </span>
                </span>
                <span style="flex: 0 0 auto; white-space: nowrap; font-weight: 600;">x<?= $item['quantite'] ?></span>
                <span style="flex: 1; text-align: right; font-weight: 700; white-space: nowrap;"><?= number_format($itemTTC, 2) ?></span>
            </div>
        <?php endforeach; ?>
    </div>

    <!-- Receipt Totals -->
    <div style="padding: 0.75rem 1rem; border-top: 1px dashed #ccc; margin-top: 0.5rem;">
        <div style="display: flex; justify-content: space-between; font-size: 0.875rem; margin-bottom: 0.375rem;">
            <span>Sous-total HT:</span>
            <span><?= number_format(floatval($sale['sous_total_ht'] ?? 0), 2) ?> Fc</span>
        </div>
        <div style="display: flex; justify-content: space-between; font-size: 0.875rem; margin-bottom: 0.375rem;">
            <span>TVA:</span>
            <span><?= number_format(floatval($sale['tva'] ?? 0), 2) ?> Fc</span>
        </div>
        <div style="display: flex; justify-content: space-between; font-size: 1.125rem; font-weight: 700; border-top: 3px solid #000; border-bottom: 3px solid #000; padding: 0.5rem 0; margin-top: 0.5rem;">
            <span>TOTAL TTC:</span>
            <span><?= number_format(floatval($sale['total'] ?? 0), 2) ?> Fc</span>
        </div>
    </div>

    <!-- DGI Validation -->
    <?php if (!empty($sale['counters'])): ?>
        <div style="margin: 1rem; padding: 0.75rem; background: #e8f5e9; border: 1px solid #4caf50; border-radius: 8px; text-align: center;">
            <div style="display: inline-flex; align-items: center; gap: 0.375rem; color: #2e7d32; font-weight: 600; font-size: 0.875rem;">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"></path>
                    <polyline points="22 4 12 14.01 9 11.01"></polyline>
                </svg>
                Validé DGI
            </div>
            <div style="font-size: 0.75rem; color: #555; margin-top: 0.375rem; line-height: 1.4;">
                Compteur: <?= htmlspecialchars($sale['counters']) ?>
                <?php if (!empty($sale['dateDGI'])): ?>
                    <br>Date: <?= htmlspecialchars($sale['dateDGI']) ?>
                <?php endif; ?>
                <?php if (!empty($sale['codeDEFDGI'])): ?>
                    <br>DEF: <?= htmlspecialchars($sale['codeDEFDGI']) ?>
                <?php endif; ?>
                <?php if (!empty($sale['nim'])): ?>
                    <br>NIM: <?= htmlspecialchars($sale['nim']) ?>
                <?php endif; ?>
            </div>
        </div>
    <?php endif; ?>

    <!-- Receipt Footer -->
    <div style="text-align: center; padding: 1rem; margin-top: 0.5rem; border-top: 2px solid #000;">
        <div id="ticket-qrcode" style="margin: 10px auto; text-align: center;"></div>
        <div style="font-size: 1.125rem; letter-spacing: 3px; font-weight: 700; margin: 0.5rem 0; color: #000; font-family: 'JetBrains Mono', monospace;">
            <?= htmlspecialchars($sale['numero_facture'] ?? '') ?>
        </div>
        <div style="font-style: italic; font-size: 0.875rem; color: #333;">
            Merci de votre visite!
        </div>
        <p style="margin-top: 5px; color: #555; font-size: 9px;">---Powered By Osat---</p>
    </div>
</div>

<?php if (!empty($sale['qrCode'])): ?>
    <script>
        // Generate QR Code for ticket
        async function generateTicketQR() {
            try {
                const script = document.createElement('script');
                script.src = 'https://cdn.jsdelivr.net/npm/qr-code-styling@1.5.0/lib/qr-code-styling.js';
                script.onload = function() {
                    const qrCode = new QRCodeStyling({
                        width: 100,
                        height: 100,
                        type: "svg",
                        data: "<?= htmlspecialchars($sale['qrCode'] ?? $sale['numero_facture'] ?? '') ?>",
                        margin: 5,
                        dotsOptions: {
                            color: "#000000",
                            type: "rounded"
                        },
                        backgroundOptions: {
                            color: "#ffffff"
                        }
                    });
                    qrCode.append(document.getElementById('ticket-qrcode'));
                };
                document.head.appendChild(script);
            } catch (e) {
                console.warn('QR Code generation failed:', e);
            }
        }
        generateTicketQR();
    </script>
<?php endif; ?>