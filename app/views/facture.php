<!-- Facture Page - Unified Design -->
<?php
// Identifiants pour fetch DGI
$invoiceNumber = $sale['numero_facture'] ?? '';
$storeIsf     = $storeInfo['isf'] ?? '';
$isDgiRegistered = !empty($sale['counters']) || !empty($sale['codeDEFDGI']) || !empty($sale['nim']);
$localQrData  = $sale['qrCode'] ?? '';
?>
<div class="no-print" style="width: 100%; padding: 1rem; display: flex; justify-content: space-between; align-items: center; max-width: 420px; margin: 0 auto;">
    <h2 style="margin: 0; font-size: 1.125rem; color: #333;">Détails de la facture</h2>
    <div style="display: flex; gap: 0.5rem; align-items: center;">
        <span id="facture-loading-badge" style="display:none; font-size:11px; color:#666; align-items:center; gap:6px;">
            <span class="spinner" style="display:inline-block; width:10px; height:10px; border:2px solid #0b5e88; border-top-color:transparent; border-radius:50%; animation: spin 0.8s linear infinite;"></span>
            Chargement DGI...
        </span>
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
    <!-- Conteneur qui sera remplacé par la version DGI si disponible -->
    <div class="receipt" id="receipt-ticket"
        data-invoice-number="<?= htmlspecialchars($invoiceNumber) ?>"
        data-store-isf="<?= htmlspecialchars($storeIsf) ?>"
        data-dgi-registered="<?= $isDgiRegistered ? '1' : '0' ?>">
        <!-- Receipt Header -->
        <div class="receipt-header">
            <div style="text-align: center; font-weight: 800; font-size: 24px; color: #000; margin-bottom: 10px; border-bottom: 2px solid #000; padding-bottom: 5px;">PRO FORMAT</div>
            <div class="store-name">
                <?= htmlspecialchars($storeInfo['name'] ?? 'SuperMarche Express') ?>
            </div>
            <div class="store-info">
                <div><strong>Adresse:</strong> <?= htmlspecialchars($storeInfo['address'] ?? '') ?></div>
                <div><strong>Tel:</strong> <?= htmlspecialchars($storeInfo['phone'] ?? '') ?></div>
                <div><strong>Email:</strong> <?= htmlspecialchars($storeInfo['email'] ?? '') ?></div>
                <div><strong>Point de vente :</strong> <?= htmlspecialchars($storeInfo['pdv'] ?? '') ?></div>
                <div><strong>ID Nat:</strong> <?= htmlspecialchars($storeInfo['ice'] ?? '') ?></div>
                <?php if (!empty($storeInfo['isf'])): ?>
                    <div><strong>Numero impot:</strong> <?= htmlspecialchars($storeInfo['isf']) ?></div>
                <?php endif; ?>
                <?php if (!empty($storeInfo['rccm'])): ?>
                    <div><strong>RCCM:</strong> <?= htmlspecialchars($storeInfo['rccm']) ?></div>
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
                $quantity = floatval($item['quantite']);
                $unitPrice = floatval($item['prix']);
                $taxRate = floatval($item['tax_rate'] ?? 0);
                $specificTaxValue = floatval($item['taxe_specifique_value'] ?? 0);
                $specificTaxType = $item['taxe_specifique_type'] ?? '%';
                $specificTaxUnit = $specificTaxType === 'CDF' ? $specificTaxValue : ($unitPrice * ($specificTaxValue / 100));
                $itemHT = $quantity * ($unitPrice + $specificTaxUnit);
                $itemTax = $itemHT * ($taxRate / 100);
                $itemTTC = $itemHT + $itemTax;
                $taxLabel = !empty($item['tax_etiquette']) ? htmlspecialchars($item['tax_etiquette']) : ($taxRate > 0 ? 'TVA ' . $taxRate . '%' : 'Exonere');
                $specificTaxLabel = $specificTaxValue > 0
                    ? ($specificTaxType === 'CDF'
                        ? ' <span style="color:#b45309;font-weight:700;">[TS]</span> ' . number_format($specificTaxValue, 2, '.', ' ') . ' Fc taxe spécifique'
                        : ' <span style="color:#b45309;font-weight:700;">[TS]</span> ' . htmlspecialchars($specificTaxValue) . '% taxe spécifique')
                    : '';
            ?>
                <div class="receipt-item">
                    <span class="item-name">
                        <?= htmlspecialchars($item['produit_nom'] ?? 'Produit') ?>
                        <span class="item-tax-badge"><?= $taxLabel ?></span>
                        <small style="color:#b45309;font-weight:600;"><?= $specificTaxLabel ?></small>
                    </span>
                    <span class="item-qty"><?= $item['quantite'] ?> × <?= number_format(floatval($item['prix']), 2, '.', ' ') ?> Fc</span>
                    <span class="item-price"><?= number_format($itemHT, 2, '.', ' ') ?> Fc</span>
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
                    <br> Numero impot : <?= htmlspecialchars($storeInfo['isf'] ?? '0') ?>
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

<style>
    @keyframes spin {
        to {
            transform: rotate(360deg);
        }
    }
</style>

<!-- QR Code Styling (utilisé en local si DGI indisponible) -->
<script src="https://cdn.jsdelivr.net/npm/qr-code-styling@1.6.0-rc.1/lib/qr-code-styling.js"></script>

<script>
    (function() {
        'use strict';

        var container = document.getElementById('receipt-ticket');
        if (!container) return;

        var isDgiRegistered = container.getAttribute('data-dgi-registered') === '1';
        var invoiceNumber = container.getAttribute('data-invoice-number') || '';
        var storeIsf = container.getAttribute('data-store-isf') || '';
        var localQrData = <?= json_encode($localQrData) ?>;

        function esc(s) {
            if (s === null || s === undefined) return '';
            return String(s).replace(/&/g, '&').replace(/</g, '<').replace(/>/g, '>').replace(/"/g, '"').replace(/'/g, '&#039;');
        }

        function numToFr(n) {
            if (n === 0) return 'zéro';
            var u = ['', 'un', 'deux', 'trois', 'quatre', 'cinq', 'six', 'sept', 'huit', 'neuf', 'dix', 'onze', 'douze', 'treize', 'quatorze', 'quinze', 'seize', 'dix-sept', 'dix-huit', 'dix-neuf'];
            var t = ['', '', 'vingt', 'trente', 'quarante', 'cinquante', 'soixante', 'soixante', 'quatre-vingt', 'quatre-vingt-dix'];
            var ip = Math.floor(n),
                dp = Math.round((n - ip) * 100);

            function tdw(x) {
                if (x === 0) return '';
                if (x < 20) return u[x];
                if (x < 100) {
                    var tn = Math.floor(x / 10),
                        un = x % 10;
                    if (tn === 7 || tn === 9) {
                        var b = tn === 7 ? 60 : 80;
                        if (un === 1) return b + '-et-' + u[un];
                        return b + '-' + u[un];
                    }
                    if (tn === 8 && un === 0) return 'quatre-vingts';
                    if (un === 0) return t[tn];
                    return t[tn] + (un === 1 ? '-et-' : '-') + u[un];
                }
                var hu = Math.floor(x / 100),
                    re = x % 100;
                var lbl = ['', 'cent', 'deux cents', 'trois cents', 'quatre cents', 'cinq cents', 'six cents', 'sept cents', 'huit cents', 'neuf cents'];
                if (hu === 1) return re === 0 ? 'cent' : 'cent ' + tdw(re);
                return lbl[hu] + (re ? ' ' + tdw(re) : '');
            }

            function ch(x) {
                if (x === 0) return '';
                if (x < 1000) return tdw(x);
                if (x < 1000000) {
                    var k = Math.floor(x / 1000),
                        r = x % 1000;
                    if (k === 1) return r === 0 ? 'mille' : 'mille ' + tdw(r);
                    return tdw(k) + ' mille' + (r ? ' ' + tdw(r) : '');
                }
                var m = Math.floor(x / 1000000),
                    r = x % 1000000;
                return (m === 1 ? 'un million' : tdw(m) + ' millions') + (r ? ' ' + ch(r) : '');
            }
            var r = ch(ip);
            if (ip > 1) r += ' francs';
            else if (ip === 1) r += ' franc';
            if (dp > 0) r += ' ' + tdw(dp) + ' centimes';
            return r.charAt(0).toUpperCase() + r.slice(1);
        }

        function phoneFmt(p) {
            if (!p || p.length < 6) return p || '';
            return p.substring(0, 6) + '****';
        }

        function invoiceLabel(c) {
            var m = {
                'FV': 'Facture de Vente',
                'EV': "Fac de Vente à l'exportation",
                'FT': "Facture d'acompte",
                'FA': "Facture d'avoir",
                'EA': "Fac d'avoir à l'exportation",
                'ET': "Fac d'acompte à l'exportation"
            };
            return m[c] || c || 'Facture de Vente';
        }

        function clientLabel(c) {
            var m = {
                'PP': 'Personne Physique',
                'PM': 'Personne Morale',
                'PC': 'Personne Physique Commerçante',
                'PL': 'Profession Libérale',
                'AO': 'Ambassades et Organisations Internationales'
            };
            return m[c] || c || 'Type inconnu';
        }

        function exonerationLabel(c) {
            var m = {
                'RAM': 'Avoir suite reprise',
                'RRR': 'Remise,Ristourne,Rabais',
                'RAN': 'Annulation',
                'COR': 'Correction'
            };
            return m[c] || c || '';
        }
        var TAX_CATS = [{
                key: 'haa',
                label: 'A',
                tax: 0,
                desc: 'EXONERE ET HORS CHAMP'
            },
            {
                key: 'hab',
                label: 'B',
                tax: 16,
                desc: 'Taxable'
            },
            {
                key: 'hac',
                label: 'C',
                tax: 5,
                desc: 'Taxable'
            },
            {
                key: 'had',
                label: 'D',
                tax: 0,
                desc: 'Régimes dérogatoires TVA'
            },
            {
                key: 'hae',
                label: 'E',
                tax: 0,
                desc: 'Exportation et opération assimilée'
            },
            {
                key: 'haf',
                label: 'F',
                tax: 16,
                desc: 'TVA marché public à financement ext.'
            },
            {
                key: 'hag',
                label: 'G',
                tax: 5,
                desc: 'TVA marché public à financement ext.'
            },
            {
                key: 'hah',
                label: 'H',
                tax: 0,
                desc: 'Consignation/déconsignation emballage'
            },
            {
                key: 'hai',
                label: 'I',
                tax: 0,
                desc: 'Garantie et caution'
            },
            {
                key: 'haj',
                label: 'J',
                tax: 0,
                desc: 'Débours'
            },
            {
                key: 'hak',
                label: 'K',
                tax: 0,
                desc: 'Opérations par les non-assujettis'
            },
            {
                key: 'hal',
                label: 'L',
                tax: 0,
                desc: 'Prélèvements sur les ventes'
            },
            {
                key: 'ham',
                label: 'M',
                tax: 0,
                desc: 'Ventes réglementées TVA spécifique'
            },
            {
                key: 'han',
                label: 'N',
                tax: 0,
                desc: 'TVA spécifique'
            },
            {
                key: 'hao',
                label: 'O',
                tax: 1,
                desc: 'Taxable'
            },
            {
                key: 'hap',
                label: 'P',
                tax: 1,
                desc: 'TVA marché public à financement ext.'
            }
        ];

        function parseHtTva(raw) {
            var ha = {},
                va = {};
            try {
                if (!raw) return {
                    ha: ha,
                    va: va
                };
                var p = typeof raw === 'string' ? JSON.parse(raw) : raw;
                if (p && p.data) {
                    ha = p.data.ha || {};
                    va = p.data.va || {};
                }
            } catch (e) {}
            return {
                ha: ha,
                va: va
            };
        }

        function taxBreakdownHtml(raw) {
            var x = parseHtTva(raw);
            var h = '';
            for (var i = 0; i < TAX_CATS.length; i++) {
                var cat = TAX_CATS[i];
                var ht = parseFloat(x.ha[cat.key]) || 0;
                var vat = parseFloat(x.va['va' + cat.key.slice(-1)]) || 0;
                if (Math.abs(ht) > 0 || Math.abs(vat) > 0) {
                    h += '<div class="receipt-total-row" style="font-size:11px; padding-left:10px;">' +
                        '<span>HT[' + cat.label + '] ' + cat.desc + ' ' + cat.tax + '% :</span>' +
                        '<span>' + ht.toFixed(2) + ' Fc</span></div>';
                    if (Math.abs(vat) > 0) {
                        h += '<div class="receipt-total-row" style="font-size:11px; padding-left:10px; color:#666;">' +
                            '<span>TVA[' + cat.label + '] ' + cat.desc + ' ' + cat.tax + '% :</span>' +
                            '<span>' + vat.toFixed(2) + ' Fc</span></div>';
                    }
                }
            }
            return h;
        }

        function renderProforma(info) {
            var articles = [];
            try {
                if (info.articles) {
                    if (typeof info.articles === 'string') articles = JSON.parse(info.articles);
                    else if (Array.isArray(info.articles)) articles = info.articles;
                }
            } catch (e) {}
            var totalTTC = parseFloat(info.total || 0);
            var tva = parseFloat(info.vtotal || 0);
            var usdRate = (typeof window.USD_RATE !== 'undefined' && window.USD_RATE > 0) ? window.USD_RATE : 0;
            var invNum = info.invoice_number || invoiceNumber;

            var html = '';
            // Header
            html += '<div class="receipt-header">';
            html += '<div style="text-align:center; font-weight:800; font-size:24px; color:#000; margin-bottom:10px; border-bottom:2px solid #000; padding-bottom:5px;">PROFORMA</div>';
            html += '<div class="store-name">' + esc(info.store_name || (window.STORE_INFO && window.STORE_INFO.name) || '') + '</div>';
            html += '<div class="store-info">';
            html += '<div><strong>Point de vente :</strong> ' + esc(info.store_address || '') + '</div>';
            html += '<div>Tel: ' + esc(info.store_phone || '') + '</div>';
            if (info.store_email || (window.STORE_INFO && window.STORE_INFO.email)) html += '<div>Email: ' + esc(info.store_email || window.STORE_INFO.email) + '</div>';
            if (info.store_ice) html += '<div>ID Nat: ' + esc(info.store_ice) + '</div>';
            if (info.store_rccm) html += '<div>RCCM: ' + esc(info.store_rccm) + '</div>';
            if (info.store_isf) html += '<div>Numero Agent: ' + esc(info.store_isf) + '</div>';
            html += '</div>';

            // Vendeur / Client
            var vendeur = info.sellerName || (window.SALE_DATA && window.SALE_DATA.nom_vendeur) || 'N/A';
            html += '<div style="border-top:1px dashed #ccc; margin-top:6px; padding-top:6px; text-align:left; font-size:15px; line-height:1.5;">';
            html += '<div style="display:flex; justify-content:space-between; gap:10px;"><span><strong>VENDEUR:</strong></span><span>' + esc(vendeur) + '</span></div>';
            if (info.client_name) html += '<div style="display:flex; justify-content:space-between; gap:10px;"><span><strong>CLIENT:</strong></span><span>' + esc(info.client_name) + '</span></div>';
            if (info.client_number) html += '<div style="display:flex; justify-content:space-between; gap:10px;"><span><strong>NUM:</strong></span><span>' + esc(phoneFmt(info.client_number)) + '</span></div>';
            if (info.client_type) html += '<div style="display:flex; justify-content:space-between; gap:10px;"><span><strong>TYPE:</strong></span><span>' + esc(clientLabel(info.client_type)) + '</span></div>';
            if (info.client_nif) html += '<div style="display:flex; justify-content:space-between; gap:10px;"><span><strong>NIF:</strong></span><span>' + esc(info.client_nif) + '</span></div>';
            html += '</div></div>';

            // Meta
            html += '<div class="receipt-meta" style="justify-content:center; font-size:14px; font-weight:555; display:flex; flex-direction:column; text-align:center; gap:4px;">';
            html += '<div>' + esc(invoiceLabel(info.invoice_type)) + '</div>';
            if (info.invoice_ref) html += '<div style="font-size:11px; color:#888; font-style:italic;">Réf: ' + esc(info.invoice_ref) + '</div>';
            if (info.ref_facture) html += '<div style="font-size:11px; color:#888; font-style:italic;">' + esc(info.ref_facture) + '</div>';
            if (info.exoneration) html += '<div style="font-size:11px; color:#888; font-style:italic;">Exonération: ' + esc(exonerationLabel(info.exoneration)) + '</div>';
            if (info.payment_type) html += '<div style="font-size:11px; color:#666;">Paiement: ' + esc(info.payment_type) + '</div>';
            html += '</div>';

            // Articles
            html += '<div class="receipt-items receipt-items-grid"><table class="receipt-table"><thead><tr><th>Article</th><th style="text-align:right">Total HT</th></tr></thead><tbody>';
            var totalQty = 0;
            if (articles.length > 0) {
                for (var j = 0; j < articles.length; j++) {
                    var a = articles[j];
                    var price = parseFloat(a.price) || 0;
                    var qty = parseFloat(a.quantity) || 1;
                    totalQty += qty;
                    var totalHt = price * qty;
                    var taxLabel = a.taxSpecificValue || 'Exonere';
                    var typeLabel = a.type ? '<span class="item-prod-service">[' + esc(a.type) + ']</span>' : '';
                    // Ligne 1 : nom article (colspan=2)
                    html += '<tr class="item-name-row">';
                    html += '<td colspan="2"><span class="item-name">' + esc(a.name || 'Article') + '<span class="item-tax-badge">' + esc(taxLabel) + '</span>' + typeLabel + '</span></td>';
                    html += '</tr>';
                    // Ligne 2 : qty × prix unitaire | total
                    html += '<tr class="item-detail-row">';
                    html += '<td class="item-qty">' + qty + ' × ' + price.toFixed(2) + ' Fc</td>';
                    html += '<td class="item-total">' + totalHt.toFixed(2) + ' Fc</td>';
                    html += '</tr>';
                }
            } else {
                html += '<tr><td colspan="2" style="text-align:center; color:#888; padding:8px;">Aucun article</td></tr>';
            }
            html += '</tbody></table></div>';

            // Totaux
            html += '<div class="receipt-totals">';
            html += taxBreakdownHtml(info.ht_tva);
            html += '<div class="receipt-total-row"><span>Total TVA:</span><span>' + tva.toFixed(2) + ' Fc</span></div>';
            html += '<div class="receipt-total-row grand-total"><span>TOTAL TTC:</span><span>' + totalTTC.toFixed(2) + ' Fc</span></div>';
            html += '<div class="receipt-total-row" style="font-size:11px; color:#555;"><span>TAUX DU JOUR :</span><span>' + (usdRate || '-') + ' Fc/USD</span></div>';
            html += '<div class="receipt-total-row" style="font-size:11px; color:#555;"><span>Equivalent en USD :</span><span>' + (usdRate ? (totalTTC / usdRate).toFixed(2) + ' $' : '-') + '</span></div>';
            html += '<div class="receipt-total-row" style="font-size:11px; color:#555;"><span>Nombre d\'article(s):</span><span>' + totalQty.toFixed(2) + '</span></div>';
            html += '<div style="text-align:center; font-size:12px; color:#888; font-style:italic; margin-top:2px;">Arrêté la présente proforma à la somme de ' + numToFr(totalTTC) + ' congolais toutes taxes comprises</div>';
            if (info.isf || info.store_isf) {
                html += '<div style="margin:10px 0; font-size:11px; color:#333; border:1px dashed #ccc; padding:8px; border-radius:4px; text-align:center;">ISF : ' + esc(info.isf || info.store_isf) + '</div>';
            }
            if (info.comment || info.providerService) {
                html += '<div style="margin:10px 0; font-size:11px; color:#333; border:1px dashed #ccc; padding:8px; border-radius:4px; text-align:left;">';
                html += '<div style="font-weight:bold; text-decoration:underline; margin-bottom:4px;">Commentaire/Remarque :</div>';
                html += '<div>' + esc(info.comment || info.providerService || 'Aucun commentaire') + '</div></div>';
            }
            html += '</div>';

            // Bloc sécurité DGI
            if (info.codeDEFDGI || info.counters || info.nim) {
                html += '<div style="background:#e8f5e9; border:1px solid #4caf50; border-radius:8px; padding:10px; margin:10px 0; text-align:center;">';
                html += '<div style="color:#2e7d32; font-weight:bold; font-size:11px;">--- Elements de securite de la facture normalisee ---</div>';
                html += '<div style="font-size:12px; color:#555; margin-top:4px;">';
                if (info.codeDEFDGI) html += 'CODE DEF/DGI: ' + esc(info.codeDEFDGI);
                if (info.nim) html += '<br> DEF NID : ' + esc(info.nim);
                if (info.counters) html += '<br> DEF Compteurs: ' + esc(info.counters);
                if (info.dateDGI) html += '<br> DEF Heure : ' + esc(info.dateDGI);
                html += '</div></div>';
            }

            // Footer
            html += '<div class="receipt-footer">';
            if (info.qrCode) html += '<div id="ticket-qrcode-dgi" class="qrcode-container"></div>';
            html += '<div class="barcode" style="margin:0.5rem 0;">FACTURE n°' + esc(invNum) + '</div>';
            if (info.dateDGI) html += '<div style="font-size:10px; color:#666; margin-top:4px;">Date : ' + esc(info.dateDGI) + '</div>';
            html += '<div class="thank-you">Merci de votre visite!</div>';
            html += '<p style="margin-top:5px; color:#555; font-size:9px; opacity:0.7;">---Powered By Osat---</p>';
            html += '</div>';

            return html;
        }

        function generateQr(qrData, containerId) {
            try {
                if (typeof QRCodeStyling === 'undefined') return;
                var qr = new QRCodeStyling({
                    width: 200,
                    height: 200,
                    type: 'svg',
                    data: qrData,
                    margin: 5,
                    dotsOptions: {
                        color: '#000000',
                        type: 'rounded'
                    },
                    backgroundOptions: {
                        color: '#ffffff'
                    },
                    cornersSquareOptions: {
                        type: 'extra-rounded'
                    }
                });
                var c = document.getElementById(containerId);
                if (c) {
                    c.innerHTML = '';
                    qr.append(c);
                }
            } catch (e) {}
        }

        function loadLocalQr() {
            if (localQrData) {
                generateQr(localQrData, 'ticket-qrcode');
            }
        }

        async function loadCurrencyRate() {
            try {
                var res = await fetch((window.APP_URL || '') + '/api/currency');
                var data = await res.json();
                if (data && data.success && data.data) {
                    var rate = (Array.isArray(data.data) ? data.data[0] : data.data).rate;
                    if (rate && rate > 1) {
                        window.USD_RATE = rate;
                    }
                }
            } catch (e) {}
        }

        async function loadDgi() {
            if (!isDgiRegistered || !invoiceNumber || !storeIsf) {
                console.log('[FACTURE] Pas de DGI enregistré, conservation des donnees locales.');
                loadLocalQr();
                return;
            }
            var badge = document.getElementById('facture-loading-badge');
            if (badge) badge.style.display = 'inline-flex';

            // 1. Charger le taux de change
            await loadCurrencyRate();

            // 2. Fetch DGI
            try {
                var url = (window.APP_URL || '') + '/api/service-bill?store_isf=' + encodeURIComponent(storeIsf) + '&invoice_number=' + encodeURIComponent(invoiceNumber);
                var res = await fetch(url, {
                    method: 'GET',
                    headers: {
                        'Accept': 'application/json'
                    }
                });
                if (!res.ok) throw new Error('HTTP ' + res.status);
                var data = await res.json();
                if (data && data.success && data.data) {
                    var info = data.data;
                    if (info && info.data && info.data.invoice_number) info = info.data;
                    container.innerHTML = renderProforma(info);
                    if (info.qrCode) {
                        setTimeout(function() {
                            generateQr(info.qrCode, 'ticket-qrcode-dgi');
                        }, 100);
                    }
                    console.log('[FACTURE] Proforma DGI generee avec succes.');
                } else {
                    console.warn('[FACTURE] API DGI sans succes, conservation des donnees locales.');
                    loadLocalQr();
                }
            } catch (e) {
                console.warn('[FACTURE] API DGI indisponible, fallback sur donnees locales:', e);
                loadLocalQr();
            } finally {
                if (badge) badge.style.display = 'none';
            }
        }

        if (document.readyState === 'loading') {
            document.addEventListener('DOMContentLoaded', loadDgi);
        } else {
            loadDgi();
        }
    })();
</script>