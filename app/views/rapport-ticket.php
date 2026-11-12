<?php
// Template d'impression des rapports fiscaux (format A4)
// Donnees recues via window.opener.reportData / window.opener.reportLabel
?>
<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Rapport Fiscal</title>
<style>
  * { margin: 0; padding: 0; box-sizing: border-box; }
  body {
    font-family: 'Segoe UI', Arial, sans-serif;
    font-size: 12px;
    color: #000;
    background: #fff;
    padding: 20mm 15mm;
    max-width: 210mm;
    margin: 0 auto;
  }
  .center { text-align: center; }
  .bold { font-weight: bold; }
  .large { font-size: 16px; }
  .xlarge { font-size: 18px; }
  .separator { border-top: 1px solid #000; margin: 10px 0; }
  .double-separator { border-top: 2px solid #000; margin: 10px 0; }
  table { width: 100%; border-collapse: collapse; margin: 4px 0; }
  th, td { padding: 4px 8px; text-align: left; font-size: 12px; }
  th { font-weight: bold; border-bottom: 2px solid #000; background: #f0f0f0; }
  tbody tr:nth-child(even) { background: #fafafa; }
  .right { text-align: right; }
  .section-title { font-weight: bold; margin: 12px 0 6px; font-size: 14px; }
  .row { display: flex; justify-content: space-between; padding: 3px 0; }
  .total-row { font-weight: bold; border-top: 2px solid #000; margin-top: 6px; padding-top: 6px; font-size: 14px; }
  @media print {
    @page { size: A4; margin: 10mm; }
    body { max-width: none; padding: 0; }
    .no-print { display: none; }
  }
</style>
</head>
<body>

<div id="ticket-content">
  <p class="center">Chargement...</p>
</div>

<script>
(function() {
    const data = window.opener?.reportData || window.reportData;
    const label = window.opener?.reportLabel || window.reportLabel || 'Rapport';

    if (!data) {
        document.getElementById('ticket-content').innerHTML = '<p class="center">Aucune donnee de rapport.</p>';
        return;
    }

    const h = data.header;
    const isAReport = h.type === 'A';
    const isDgiReport = data.source === 'dgi' || h.type === 'X_DGI' || h.type === 'Z_DGI';
    let html = '';

    // En-tete
    // ⚠️ Le NIF est volontairement MASQUÉ sur le rapport (le libellé reste
    // affiché, sans valeur) : seule l'ISF est imprimé, comme sur la facture
    // finale (cf. app/views/facture.php, ligne « ISF: »).
    html += `<div class="center bold large">${esc(h.denomination)}</div>`;
    html += `<div class="center">NIF:</div>`;
    html += `<div class="center">ISF: ${esc(h.isf)}</div>`;
    if (h.adresse) html += `<div class="center">${esc(h.adresse)}</div>`;
    if (h.telephone) html += `<div class="center">Tel: ${esc(h.telephone)}</div>`;
    html += `<div class="separator"></div>`;
    html += `<div class="center bold large">${esc(label)}</div>`;
    html += `<div class="center">Genere le: ${esc(h.generated_at)}</div>`;
    html += `<div class="center">Periode: ${esc(h.period_start)}</div>`;
    html += `<div class="center">au ${esc(h.period_end)}</div>`;
    html += `<div class="double-separator"></div>`;

    if (isDgiReport) {
        const totals = data.totals || {};
        html += '<div class="section-title">TOTAUX DGI / OSAT</div>';
        html += `<div class="row"><span>Total HT:</span><span>${fmtMoney(totals.ht)}</span></div>`;
        html += `<div class="row"><span>TVA:</span><span>${fmtMoney(totals.tva)}</span></div>`;
        html += `<div class="row"><span>TS total:</span><span>${fmtMoney(totals.ts_total)}</span></div>`;
        html += `<div class="row total-row"><span>TOTAL TTC:</span><span>${fmtMoney(totals.ttc)}</span></div>`;
        html += `<div class="row"><span>Factures:</span><span>${fmtNum(totals.invoice_count)}</span></div>`;
        html += `<div class="row"><span>Remises:</span><span>${fmtMoney(totals.discount_total)}</span></div>`;
        html += '<div class="separator"></div>';

        if (data.payments?.length) {
            html += '<div class="section-title">PAR MODE DE PAIEMENT</div>';
            html += '<table><thead><tr><th>Mode</th><th class="right">Montant</th></tr></thead><tbody>';
            data.payments.forEach((payment) => {
                html += '<tr>'
                    + `<td>${esc(payment.label || payment.code)}<br><small>${esc(payment.code)}</small></td>`
                    + `<td class="right">${fmtMoney(payment.total)}</td>`
                    + '</tr>';
            });
            html += '</tbody></table><div class="separator"></div>';
        }

        if (data.by_invoice_type?.length) {
            html += '<div class="section-title">PAR TYPE DE FACTURE</div>';
            html += '<table><thead><tr><th>Type</th><th class="right">Factures</th><th class="right">HT</th><th class="right">TVA</th><th class="right">TS</th><th class="right">TTC</th><th class="right">Remise</th></tr></thead><tbody>';
            data.by_invoice_type.forEach((type) => {
                const typeTotals = type.totals || {};
                html += '<tr>'
                    + `<td>${esc(type.type)}<br><small>${esc(type.label || '')}</small></td>`
                    + `<td class="right">${fmtNum(typeTotals.invoice_count)}</td>`
                    + `<td class="right">${fmtMoney(typeTotals.ht)}</td>`
                    + `<td class="right">${fmtMoney(typeTotals.tva)}</td>`
                    + `<td class="right">${fmtMoney(typeTotals.ts_total)}</td>`
                    + `<td class="right">${fmtMoney(typeTotals.ttc)}</td>`
                    + `<td class="right">${fmtMoney(typeTotals.discount_total)}</td>`
                    + '</tr>';
            });
            html += '</tbody></table><div class="separator"></div>';
        }

        const groups = (data.tax_groups || []).filter((group) =>
            Math.abs(Number(group.amount) || 0) > 0.001
            || Math.abs(Number(group.tva) || 0) > 0.001
            || Math.abs(Number(group.ts) || 0) > 0.001
        );
        if (groups.length) {
            html += '<div class="section-title">PAR CATEGORIE FISCALE DGI</div>';
            html += '<table><thead><tr><th>Cat.</th><th>Libelle</th><th class="right">Taux</th><th class="right">HT</th><th class="right">TVA</th><th class="right">TS</th></tr></thead><tbody>';
            groups.forEach((group) => {
                html += '<tr>'
                    + `<td>${esc(group.code)}</td>`
                    + `<td>${esc(group.label || '')}</td>`
                    + `<td class="right">${fmtPercent(group.rate)}</td>`
                    + `<td class="right">${fmtMoney(group.amount)}</td>`
                    + `<td class="right">${fmtMoney(group.tva)}</td>`
                    + `<td class="right">${fmtMoney(group.ts)}</td>`
                    + '</tr>';
            });
            html += '</tbody></table><div class="separator"></div>';
        }

        if (data.warnings?.length) {
            html += '<div class="section-title">AVERTISSEMENTS DE COHERENCE</div><ul>';
            data.warnings.forEach((warning) => {
                html += `<li>${esc(warning)}</li>`;
            });
            html += '</ul><div class="separator"></div>';
        }

        if (data.header?.official_closure === true) {
            html += '<p><strong>Cloture DGI enregistree.</strong> '
                + `Cloture n° ${esc(data.header.closure_counter)}`
                + ` — periode ${esc(data.header.period_start)} au ${esc(data.header.period_end)}.</p>`;
        } else if (data.header?.official_closure === false) {
            html += '<p><strong>Consultation en lecture seule.</strong> La periode n\'a pas ete cloturee aupres de la DGI.</p>';
        }
    } else if (isAReport) {
        // A-rapport : tableau des articles
        html += `<div class="section-title">DETAIL DES ARTICLES</div>`;
        html += `<div class="row"><span>Total articles:</span><span>${data.totals.articles_count}</span></div>`;
        html += `<div class="row"><span>Qte vendue:</span><span>${fmtNum(data.totals.total_quantity_sold)}</span></div>`;
        html += `<div class="row"><span>Qte retournee:</span><span>${fmtNum(data.totals.total_quantity_returned)}</span></div>`;
        html += `<div class="row"><span>Montant collecte:</span><span>${fmtMoney(data.totals.total_amount_collected)}</span></div>`;
        html += `<div class="separator"></div>`;

        if (data.articles?.length) {
            // Grouper par categorie
            const categories = {};
            data.articles.forEach(a => {
                const cat = a.category_name || 'Sans categorie';
                if (!categories[cat]) categories[cat] = [];
                categories[cat].push(a);
            });

            Object.keys(categories).sort().forEach(catName => {
                html += `<div class="section-title">${catName}</div>`;
                html += `<table><thead><tr><th>Article</th><th class="right">Prix</th><th class="right">Taxe</th><th class="right">Vendu</th><th class="right">Retour</th><th class="right">Montant</th></tr></thead><tbody>`;
                categories[catName].forEach(a => {
                    html += `<tr><td>${a.article_name}</td><td class="right">${fmtMoney(a.unit_price)}</td><td class="right">${a.tax_rate || 0}%</td><td class="right">${fmtNum(a.quantite_vendue)}</td><td class="right">${fmtNum(a.quantite_retournee)}</td><td class="right">${fmtMoney(a.montant_collecte)}</td></tr>`;
                });
                html += `</tbody></table>`;
                html += `<div class="separator"></div>`;
            });
        } else {
            html += `<p>Aucun article vendu.</p>`;
        }
    } else {
        // Z / X rapports
        html += `<div class="section-title">TOTAUX GENERAUX</div>`;
        html += `<div class="row"><span>Total HT:</span><span>${fmtMoney(data.totals.total_ht)}</span></div>`;
        html += `<div class="row"><span>Total Taxe:</span><span>${fmtMoney(data.totals.total_tax)}</span></div>`;
        html += `<div class="row"><span>TS totale:</span><span>${fmtMoney(data.totals.total_ts || 0)}</span></div>`;
        html += `<div class="row total-row"><span>TOTAL TTC:</span><span>${fmtMoney(data.totals.total_ttc)}</span></div>`;
        html += `<div class="row"><span>Factures:</span><span>${data.totals.total_invoices}</span></div>`;
        html += `<div class="separator"></div>`;

        // Par type de facture
        if (data.by_invoice_type?.length) {
            html += `<div class="section-title">PAR TYPE DE FACTURE</div>`;
            html += `<table><thead><tr><th>Type</th><th class="right">Nb</th><th class="right">HT</th><th class="right">Taxe</th><th class="right">TTC</th></tr></thead><tbody>`;
            data.by_invoice_type.forEach(t => {
                html += `<tr><td>${t.invoice_type}</td><td class="right">${t.invoice_count}</td><td class="right">${fmtMoney(t.total_ht)}</td><td class="right">${fmtMoney(t.total_tax)}</td><td class="right">${fmtMoney(t.total_ttc)}</td></tr>`;
            });
            html += `</tbody></table><div class="separator"></div>`;
        }

        // Par groupe de taxation
        if (data.by_tax_group?.length) {
            html += `<div class="section-title">PAR GROUPE DE TAXATION</div>`;
            html += `<table><thead><tr><th>Etiquette</th><th class="right">Taux</th><th class="right">Nb</th><th class="right">HT</th><th class="right">Taxe</th><th class="right">TS</th><th class="right">TTC</th></tr></thead><tbody>`;
            data.by_tax_group.forEach(g => {
                html += `<tr><td>${g.etiquette || '-'}</td><td class="right">${fmtPercent(g.taux)}</td><td class="right">${g.invoice_count}</td><td class="right">${fmtMoney(g.total_ht)}</td><td class="right">${fmtMoney(g.total_tax)}</td><td class="right">${fmtMoney(g.total_ts || 0)}</td><td class="right">${fmtMoney(g.total_ttc)}</td></tr>`;
            });
            html += `</tbody></table><div class="separator"></div>`;
        }

        // Par mode de paiement
        if (data.by_payment_method?.length) {
            html += `<div class="section-title">PAR MODE DE PAIEMENT</div>`;
            data.by_payment_method.forEach(p => {
                html += `<div class="row"><span>${p.label}:</span><span>${fmtMoney(p.total)}</span></div>`;
            });
            html += `<div class="separator"></div>`;
        }

        // ── BLOCS HÔTEL ────────────────────────────────────────────────
        // Le CA hôtelier est intégré au rapport : synthèse, ventilation par
        // chambre, et détail des extras (déjà inclus dans les factures hôtel).
        const hotel = data.hotel_breakdown;
        if (hotel && hotel.factures && hotel.factures.nb_factures > 0) {
            html += `<div class="section-title">HOTEL - SYNTHESE</div>`;
            html += `<div class="row"><span>Factures hotel:</span><span>${hotel.factures.nb_factures}</span></div>`;
            html += `<div class="row"><span>Nuitees:</span><span>${fmtNum(hotel.nuites.nb_nuitees)}</span></div>`;
            html += `<div class="row"><span>CA nuitees (HT):</span><span>${fmtMoney(hotel.nuites.total_ht)}</span></div>`;
            html += `<div class="row"><span>TS hotel:</span><span>${fmtMoney((hotel.nuites.total_ts || 0) + (hotel.extras.total_ts || 0))}</span></div>`;
            html += `<div class="row"><span>CA hotel (TTC):</span><span>${fmtMoney(hotel.factures.total_ttc)}</span></div>`;
            html += `<div class="separator"></div>`;
        }

        if (data.hotel_by_room?.length) {
            html += `<div class="section-title">HOTEL - PAR CHAMBRE</div>`;
            html += `<table><thead><tr><th>Chambre</th><th class="right">Nuitees</th><th class="right">HT</th><th class="right">Taxe</th><th class="right">TS</th><th class="right">TTC</th></tr></thead><tbody>`;
            data.hotel_by_room.forEach(r => {
                html += `<tr><td>${r.chambre_numero || '-'}</td><td class="right">${fmtNum(r.nb_nuitees)}</td><td class="right">${fmtMoney(r.total_ht)}</td><td class="right">${fmtMoney(r.total_tax)}</td><td class="right">${fmtMoney(r.total_ts || 0)}</td><td class="right">${fmtMoney(r.total_ttc)}</td></tr>`;
            });
            html += `</tbody></table><div class="separator"></div>`;
        }

        if (data.hotel_extras?.length) {
            html += `<div class="section-title">HOTEL - EXTRAS (DEJA INCLUS DANS LES FACTURES HOTEL)</div>`;
            html += `<table><thead><tr><th>Article</th><th class="right">Qte</th><th class="right">HT</th><th class="right">Taxe</th></tr></thead><tbody>`;
            data.hotel_extras.forEach(x => {
                html += `<tr><td>${x.article_nom || '-'}</td><td class="right">${fmtNum(x.quantite)}</td><td class="right">${fmtMoney(x.total_ht)}</td><td class="right">${fmtMoney(x.total_tax)}</td></tr>`;
            });
            html += `</tbody></table><div class="separator"></div>`;
        }

        // Reductions
        if (data.discounts && (data.discounts.invoices_with_discount > 0 || data.discounts.total_discount_amount > 0)) {
            html += `<div class="section-title">REDUCTIONS COMMERCIALES</div>`;
            html += `<div class="row"><span>Factures avec remise:</span><span>${data.discounts.invoices_with_discount}</span></div>`;
            html += `<div class="row"><span>Montant total:</span><span>${fmtMoney(data.discounts.total_discount_amount)}</span></div>`;
            html += `<div class="separator"></div>`;
        }

        // Avoirs et annulations
        if (data.credit_notes?.length) {
            html += `<div class="section-title">AVOIRS ET ANNULATIONS</div>`;
            html += `<table><thead><tr><th>Type</th><th class="right">Nb</th><th class="right">HT</th><th class="right">Taxe</th><th class="right">TTC</th></tr></thead><tbody>`;
            data.credit_notes.forEach(cn => {
                html += `<tr><td>${cn.invoice_type}</td><td class="right">${cn.invoice_count}</td><td class="right">${fmtMoney(cn.total_ht)}</td><td class="right">${fmtMoney(cn.total_tax)}</td><td class="right">${fmtMoney(cn.total_ttc)}</td></tr>`;
            });
            html += `</tbody></table><div class="separator"></div>`;
        }

        // Ventes incompletes
        html += `<div class="section-title">VENTES INCOMPLETES</div>`;
        html += `<div class="row"><span>Nombre:</span><span>${data.incomplete_sales ?? 0}</span></div>`;
    }

    html += `<div class="double-separator"></div>`;
    html += `<div class="center">Fin du rapport</div>`;

    document.getElementById('ticket-content').innerHTML = html;

    function esc(value) {
        if (value === null || value === undefined) return '';
        return String(value)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#039;');
    }
    function fmtNum(v) { return new Intl.NumberFormat('fr-FR').format(v || 0); }
    function fmtMoney(v) { return new Intl.NumberFormat('fr-FR', { minimumFractionDigits: 0, maximumFractionDigits: 2 }).format(v || 0) + ' Fc'; }
    // Taux de taxation avec 2 décimales (« 16.00% ») : le `|| 0` brut
    // affichait « 0% » / « 16% » sans décimales.
    function fmtPercent(v) {
        const n = parseFloat(v);
        return (isNaN(n) ? 0 : n).toFixed(2) + '%';
    }

    // Impression automatique
    setTimeout(() => window.print(), 300);
})();
</script>

</body>
</html>
