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
    let html = '';

    // En-tete
    html += `<div class="center bold large">${h.denomination}</div>`;
    html += `<div class="center">NIF: ${h.nif}</div>`;
    html += `<div class="center">ISF: ${h.isf}</div>`;
    if (h.adresse) html += `<div class="center">${h.adresse}</div>`;
    if (h.telephone) html += `<div class="center">Tel: ${h.telephone}</div>`;
    html += `<div class="separator"></div>`;
    html += `<div class="center bold large">${label}</div>`;
    html += `<div class="center">Genere le: ${h.generated_at}</div>`;
    html += `<div class="center">Periode: ${h.period_start}</div>`;
    html += `<div class="center">au ${h.period_end}</div>`;
    html += `<div class="double-separator"></div>`;

    if (isAReport) {
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
            html += `<table><thead><tr><th>Etiquette</th><th class="right">Taux</th><th class="right">Nb</th><th class="right">HT</th><th class="right">Taxe</th><th class="right">TTC</th></tr></thead><tbody>`;
            data.by_tax_group.forEach(g => {
                html += `<tr><td>${g.etiquette || '-'}</td><td class="right">${g.taux || 0}%</td><td class="right">${g.invoice_count}</td><td class="right">${fmtMoney(g.total_ht)}</td><td class="right">${fmtMoney(g.total_tax)}</td><td class="right">${fmtMoney(g.total_ttc)}</td></tr>`;
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

    function fmtNum(v) { return new Intl.NumberFormat('fr-FR').format(v || 0); }
    function fmtMoney(v) { return new Intl.NumberFormat('fr-FR', { minimumFractionDigits: 0, maximumFractionDigits: 2 }).format(v || 0) + ' Fc'; }

    // Impression automatique
    setTimeout(() => window.print(), 300);
})();
</script>

</body>
</html>
