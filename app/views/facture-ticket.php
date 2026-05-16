<!-- Facture Ticket Page - Style like showPreview -->
<!-- Actions bar - will be hidden during print -->
<div class="no-print"
    style="width: 100%; padding: 1rem; display: flex; justify-content: space-between; align-items: center; max-width: 420px; margin: 0 auto;">
    <h2 style="margin: 0; font-size: 1.125rem; color: #333;">Ticket de caisse</h2>
    <div style="display: flex; gap: 0.5rem; align-items: center;">
        <button onclick="window.print()" class="btn btn-primary"
            style="display: inline-flex; align-items: center; gap: 0.375rem;">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <polyline points="6 9 6 2 18 2 18 9"></polyline>
                <path d="M6 18H4a2 2 0 0 1-2-2v-5a2 2 0 0 1 2-2h16a2 2 0 0 1 2 2v5a2 2 0 0 1-2 2h-2"></path>
                <rect x="6" y="14" width="12" height="8"></rect>
            </svg>
            Imprimer
        </button>
    </div>
</div>

<!-- Receipt container - will be replaced for service bills -->
<div id="receipt-container">
    <?php
    // Check if this is a service bill
    $isServiceBill = !empty($sale['service']) && ($sale['service'] === 'EAU' || $sale['service'] === 'ELECTRICITE');
    $hasDgiData = !empty($sale['dgi_data']);
    $serviceBillData = $hasDgiData ? json_decode($sale['dgi_data'], true) : null;
    ?>

    <?php if ($isServiceBill): ?>
        <!-- Service Bill: Will be loaded via JS -->
        <div id="service-bill-loading" style="text-align: center; padding: 40px;">
            <div class="spinner"></div>
            <p style="margin-top: 1rem;">Chargement des donnees service...</p>
        </div>
        <div id="service-bill-content"></div>

        <script>
            const SERVICE_BILL_API_URL = '<?= $baseUrl ?? '' ?>/api/service-bill';
            const SERVICE_SALE_DATA = <?= json_encode($sale) ?>;
            const SERVICE_STORE_INFO = <?= json_encode($storeInfo ?? []) ?>;

            document.addEventListener('DOMContentLoaded', async function() {
                const invoiceNum = '<?= htmlspecialchars($sale['numero_facture'] ?? '') ?>';
                const clientIsf = '<?= htmlspecialchars($storeInfo['isf'] ?? '') ?>';

                try {
                    const resp = await fetch(SERVICE_BILL_API_URL, {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json'
                        },
                        body: JSON.stringify({
                            nfacture: invoiceNum,
                            client_isf: clientIsf
                        })
                    });

                    if (resp.ok) {
                        const data = await resp.json();
                        if (data.success && data.data) {
                            document.getElementById('service-bill-loading').style.display = 'none';
                            document.getElementById('service-bill-content').innerHTML = renderServiceBillReceipt(data, SERVICE_SALE_DATA, SERVICE_STORE_INFO);
                        } else {
                            document.getElementById('service-bill-loading').innerHTML = '<div style="background:#ffebee;padding:15px;border-radius:8px;"><strong>Erreur:</strong> Donnees service non disponibles.</div>';
                        }
                    } else {
                        document.getElementById('service-bill-loading').innerHTML = '<div style="background:#ffebee;padding:15px;border-radius:8px;"><strong>Erreur:</strong> Impossible de charger les donnees.</div>';
                    }
                } catch (e) {
                    console.error('Service bill load error:', e);
                    document.getElementById('service-bill-loading').innerHTML = '<div style="background:#ffebee;padding:15px;border-radius:8px;"><strong>Erreur:</strong> Echec de connexion.</div>';
                }
            });

            function renderServiceBillReceipt(data, sale, storeInfo) {
                let info = data.data || data;
                info = info.data

                // Parser les articles si c'est une chaîne JSON
                let articlesList = [];
                if (info.articles) {
                    if (typeof info.articles === 'string') {
                        try {
                            articlesList = JSON.parse(info.articles);
                        } catch (e) {}
                    } else {
                        articlesList = info.articles;
                    }
                }

                let html = '<div class="receipt">';

                // Header avec PROFORMA
                html += '<div class="receipt-header">';
                html += '<div style="text-align:center; font-weight:800; font-size:24px; color:#000; margin-bottom:10px; border-bottom:2px solid #000; padding-bottom:5px;">PROFORMA</div>';
                html += '<div class="store-name">' + (info.store_name || storeInfo.name || 'Store') + '</div>';
                html += '<div class="store-info">';
                html += '<div>' + (info.store_address || storeInfo.address || '') + '</div>';
                html += '<div>Tel: ' + (info.store_phone || storeInfo.phone || '') + '</div>';
                html += '<div>ID Nat: ' + (info.store_ice || storeInfo.ice || '') + '</div>';
                if (info.store_rccm) html += '<div>RCCM: ' + info.store_rccm + '</div>';
                html += '<div>Numero Impot: ' + (info.store_isf || storeInfo.isf || '') + '</div>';
                html += '</div>';

                // Section Vendeur/Client
                const vendeur = info.sellerName || 'N/A';
                const clientNom = info.client_name || '';
                const clientNumero = info.client_number || '';
                const clientType = info.client_type || '';
                const clientNif = info.client_nif || '';

                html += '<div style="border-top:1px dashed #ccc; margin-top:6px; padding-top:6px; text-align:left; font-size:15px; line-height:1.5;">';
                html += '<div style="display:flex; justify-content:space-between; gap:10px;"><span><strong>Vendeur:</strong></span><span>' + vendeur + '</span></div>';
                if (clientNom) html += '<div style="display:flex; justify-content:space-between; gap:10px;"><span><strong>Client:</strong></span><span>' + clientNom + '</span></div>';
                if (clientNumero) html += '<div style="display:flex; justify-content:space-between; gap:10px;"><span><strong>Num:</strong></span><span>' + formatPhoneNumberService(clientNumero) + '</span></div>';
                if (clientType) html += '<div style="display:flex; justify-content:space-between; gap:10px;"><span><strong>Type:</strong></span><span>' + clientType + '</span></div>';
                if (clientNif) html += '<div style="display:flex; justify-content:space-between; gap:10px;"><span><strong>NIF:</strong></span><span>' + clientNif + '</span></div>';
                html += '</div></div>';

                // Meta
                html += '<div class="receipt-meta">';
                html += '<span>' + (info.invoice_number || sale.numero_facture) + '</span>';
                html += '<span>' + (info.invoice_type || 'FV') + '</span>';
                html += '</div>';

                // Items
                html += '<div class="receipt-items receipt-items-grid">';
                if (articlesList.length > 0) {
                    articlesList.forEach(article => {
                        const articleHT = parseFloat(article.price) || 0;
                        const taxLabel = article.taxSpecificValue || 'Exonere';
                        html += '<div class="receipt-item">';
                        html += '<span class="item-name">' + article.name + '<span class="item-tax-badge">' + taxLabel + '</span></span>';
                        html += '<span class="item-qty">x' + (article.quantity || 1) + '</span>';
                        html += '<span class="item-price">' + articleHT.toFixed(2) + '</span>';
                        html += '</div>';
                    });
                }
                html += '</div>';

                // Totaux
                const total = parseFloat(info.total || 0);
                const tva = parseFloat(info.vtotal || 0);
                const sousTotalHT = total - tva;

                html += '<div class="receipt-totals">';
                html += '<div class="receipt-total-row"><span>TVA:</span><span>' + tva.toFixed(2) + ' Fc</span></div>';
                html += '<div class="receipt-total-row grand-total"><span>TOTAL TTC:</span><span>' + total.toFixed(2) + ' Fc</span></div>';

                if (info.comment || info.providerService) {
                    html += '<div style="margin:10px 0; font-size:11px; color:#333; border:1px dashed #ccc; padding:8px; border-radius:4px; text-align:left;">';
                    html += '<div style="font-weight:bold; text-decoration:underline; margin-bottom:4px;">Commentaire/Remarque :</div>';
                    html += '<div>' + (info.comment ? info.comment : "Aucun commentaire") + '</div>';
                    html += '</div>';
                }
                html += '</div>';

                // Elements DGI
                if (info.codeDEFDGI || info.counters || info.nim) {
                    html += '<div style="background:#e8f5e9; border:1px solid #4caf50; border-radius:8px; padding:10px; margin:10px 0; text-align:center;">';
                    html += '<div style="color:#2e7d32; font-weight:bold; font-size:11px;">--- Elements de securite ---</div>';
                    html += '<div style="font-size:12px; color:#555; margin-top:4px;">';
                    if (info.codeDEFDGI) html += 'CODE DEF/DGI: ' + info.codeDEFDGI + '<br>';
                    if (info.nim) html += ' DEF NID : ' + info.nim + '<br>';
                    if (info.counters) html += ' DEF Compteurs: ' + info.counters + '<br>';
                    html += ' ISF : ' + (info.isf || info.store_isf || '0');
                    html += '</div></div>';
                }

                // Footer
                html += '<div class="receipt-footer">';
                if (info.qrCode) html += '<div id="service-ticket-qrcode" class="qrcode-container"></div>';
                html += '<div class="barcode">' + (info.invoice_number || sale.numero_facture) + '</div>';
                html += '<div class="thank-you">Merci de votre visite!</div>';
                html += '<p style="margin-top:5px; font-size:9px; font-style:italic;">---Powered By Osat---</p>';
                html += '</div></div>';

                // Generate QR after render
                setTimeout(() => {
                    if (info.qrCode && typeof QRCodeStyling !== 'undefined') {
                        const qrCode = new QRCodeStyling({
                            width: 140,
                            height: 140,
                            type: "svg",
                            data: info.qrCode,
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
                        qrCode.append(document.getElementById('service-ticket-qrcode'));
                    }
                }, 100);

                return html;
            }

            function formatPhoneNumberService(phone) {
                if (!phone || phone.length < 6) return phone;
                return phone.substring(0, 6) + '****';
            }
        </script>
    <?php else: ?>
        <!-- Normal Receipt -->
        <div class="receipt" id="receipt-ticket">
            <div class="receipt-header">
                <div style="text-align: center; font-weight: 800; font-size: 24px; color: #000; margin-bottom: 10px; border-bottom: 2px solid #000; padding-bottom: 5px;">PROFORMA</div>
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
                <div
                    style="border-top: 1px dashed #ccc; margin-top: 6px; padding-top: 6px; text-align: left; font-size: 15px; line-height: 1.5;">
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
                <div
                    style="margin: 10px 0; font-size: 11px; color: #333; border: 1px dashed #ccc; padding: 8px; border-radius: 4px; text-align: left;">
                    <div style="font-weight: bold; text-decoration: underline; margin-bottom: 4px;">Commentaire/Remarque :</div>
                    <div><?= !empty($sale['comment']) ? htmlspecialchars($sale['comment']) : 'Aucun commentaire' ?></div>
                </div>
            </div>

            <!-- DGI Validation -->
            <?php if (!empty($sale['counters'])): ?>
                <div
                    style="background: #e8f5e9; border: 1px solid #4caf50; border-radius: 8px; padding: 10px; margin: 10px 0; text-align: center;">
                    <div style="color: #2e7d32; font-weight: bold; font-size: 11px;">--- Elements de securite de la facture
                        normalisee ---</div>
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
    <?php endif; ?>
</div>