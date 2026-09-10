const ReportsManager = {
    init() {
        this.bindEvents();
        this.loadHistory();
    },

    bindEvents() {
        document.getElementById('btn-z-report')?.addEventListener('click', () => this.generateZ());
        document.getElementById('btn-a-report')?.addEventListener('click', () => this.generateA());
        document.getElementById('btn-x-daily')?.addEventListener('click', () => this.generateXD());
        document.getElementById('btn-x-periodic')?.addEventListener('click', () => this.generateXP());
    },

    generateZ() {
        if (!confirm('Generer un Z-rapport va cloturer la periode. Continuer ?')) return;
        this.fetch('/api/reports/z-report', 'Z-rapport');
    },

    generateA() {
        if (!confirm('Generer un A-rapport va cloturer la periode articles. Continuer ?')) return;
        this.fetch('/api/reports/a-report', 'A-rapport (detail articles)');
    },

    generateXD() { this.fetch('/api/reports/x-report/daily', 'X-rapport quotidien'); },

    generateXP() {
        const from = document.getElementById('report-from')?.value;
        const to = document.getElementById('report-to')?.value;
        if (!from || !to) { alert('Selectionnez une periode.'); return; }
        this.fetch(`/api/reports/x-report/periodic?from=${from}&to=${to}`, 'X-rapport periodique');
    },

    fetch(url, label) {
        const c = document.getElementById('report-content');
        c.innerHTML = '<div class="loading">Generation en cours...</div>';
        fetch(url).then(r => r.json()).then(resp => {
            if (!resp.success) { c.innerHTML = `<div class="error">${resp.message}</div>`; return; }
            this.display(resp.data, label);
            this.loadHistory();
        }).catch(e => { c.innerHTML = `<div class="error">Erreur: ${e.message}</div>`; });
    },

    display(data, label) {
        window._currentReportData = data;
        window._currentReportLabel = label;
        const c = document.getElementById('report-content');
        const h = data.header;
        const isAReport = h.type === 'A';

        let html = `
            <div class="report-header">
                <h2>${label}</h2>
                <p><strong>${h.denomination}</strong> | NIF: ${h.nif} | ISF: ${h.isf}</p>
                <p>Periode: ${h.period_start} -> ${h.period_end}</p>
                <p>Genere: ${h.generated_at}</p>
            </div>`;

        if (isAReport) {
            html += this.displayAReport(data);
        } else {
            html += this.displayZXReport(data);
        }

        html += `<button onclick="ReportsManager.print()" class="btn btn-primary" style="margin-top: 1rem; padding: 0.5rem 1rem; border: none; border-radius: var(--radius); cursor: pointer;">Imprimer</button>`;
        c.innerHTML = html;
    },

    displayAReport(data) {
        let html = `<p style="margin: 0.5rem 0;"><strong>Total articles :</strong> ${data.totals.articles_count}
                     | Qte vendue : ${this.number(data.totals.total_quantity_sold)}
                     | Qte retournee : ${this.number(data.totals.total_quantity_returned)}
                     | Montant collecte : ${this.money(data.totals.total_amount_collected)}</p>`;
        if (data.articles?.length) {
            // Grouper par categorie
            const categories = {};
            data.articles.forEach(a => {
                const cat = a.category_name || 'Sans categorie';
                if (!categories[cat]) categories[cat] = [];
                categories[cat].push(a);
            });

            Object.keys(categories).sort().forEach(catName => {
                html += `<div class="report-section"><h4>${catName}</h4>`;
                html += `<table class="report-table">
                    <thead><tr><th>Code</th><th>Article</th><th>Prix U.</th><th>Taxe</th>
                    <th>Qte vendue</th><th>Qte retour</th><th>Stock</th><th>Montant</th></tr></thead><tbody>`;
                categories[catName].forEach(a => {
                    html += `<tr><td>${a.article_code || '-'}</td><td>${a.article_name}</td>
                        <td>${this.money(a.unit_price)}</td><td>${a.tax_rate || 0}%</td>
                        <td>${this.number(a.quantite_vendue)}</td><td>${this.number(a.quantite_retournee)}</td>
                        <td>${this.number(a.stock_quantity)}</td>
                        <td>${this.money(a.montant_collecte)}</td></tr>`;
                });
                html += `</tbody></table></div>`;
            });
        } else {
            html += `<p>Aucun article vendu sur cette periode.</p>`;
        }
        return html;
    },

    displayZXReport(data) {
        let html = `<div class="report-section">
            <h4>Totaux generaux</h4>
            <table class="report-table">
                <tr><th>Total HT</th><td>${this.money(data.totals.total_ht)}</td></tr>
                <tr><th>Total Taxe</th><td>${this.money(data.totals.total_tax)}</td></tr>
                <tr class="totaux"><th>TOTAL TTC</th><td>${this.money(data.totals.total_ttc)}</td></tr>
                <tr><th>Nombre de factures</th><td>${data.totals.total_invoices}</td></tr>
            </table></div>`;

        // Par type de facture
        if (data.by_invoice_type?.length) {
            html += `<div class="report-section"><h4>Par type de facture</h4>
                <table class="report-table">
                <thead><tr><th>Type</th><th>Factures</th><th>Total HT</th><th>Taxe</th><th>TTC</th></tr></thead><tbody>`;
            data.by_invoice_type.forEach(t => {
                html += `<tr><td>${t.invoice_type}</td><td>${t.invoice_count}</td>
                    <td>${this.money(t.total_ht)}</td><td>${this.money(t.total_tax)}</td>
                    <td>${this.money(t.total_ttc)}</td></tr>`;
            });
            html += `</tbody></table></div>`;
        }

        // Par groupe de taxation
        if (data.by_tax_group?.length) {
            html += `<div class="report-section"><h4>Par groupe de taxation</h4>
                <table class="report-table">
                <thead><tr><th>Groupe</th><th>Etiquette</th><th>Taux</th><th>Factures</th><th>HT</th><th>Taxe</th><th>TTC</th></tr></thead><tbody>`;
            data.by_tax_group.forEach(g => {
                html += `<tr><td>${g.groupe_taxe || '-'}</td><td>${g.etiquette || '-'}</td>
                    <td>${g.taux || 0}%</td><td>${g.invoice_count}</td>
                    <td>${this.money(g.total_ht)}</td><td>${this.money(g.total_tax)}</td>
                    <td>${this.money(g.total_ttc)}</td></tr>`;
            });
            html += `</tbody></table></div>`;
        }

        // Par mode de paiement
        if (data.by_payment_method?.length) {
            html += `<div class="report-section"><h4>Par mode de paiement</h4>
                <table class="report-table">
                <thead><tr><th>Mode</th><th>Montant</th></tr></thead><tbody>`;
            data.by_payment_method.forEach(p => {
                html += `<tr><td>${p.label}</td><td>${this.money(p.total)}</td></tr>`;
            });
            html += `</tbody></table></div>`;
        }

        // Reductions
        if (data.discounts && (data.discounts.invoices_with_discount > 0 || data.discounts.total_discount_amount > 0)) {
            html += `<div class="report-section"><h4>Reductions commerciales</h4>
                <table class="report-table">
                <tr><th>Factures avec remise</th><td>${data.discounts.invoices_with_discount}</td></tr>
                <tr><th>Montant total des remises</th><td>${this.money(data.discounts.total_discount_amount)}</td></tr>
                </table></div>`;
        }

        // Avoirs et annulations
        if (data.credit_notes?.length) {
            html += `<div class="report-section"><h4>Avoirs et annulations</h4>
                <table class="report-table">
                <thead><tr><th>Type</th><th>Nombre</th><th>HT</th><th>Taxe</th><th>TTC</th></tr></thead><tbody>`;
            data.credit_notes.forEach(cn => {
                html += `<tr><td>${cn.invoice_type}</td><td>${cn.invoice_count}</td>
                    <td>${this.money(cn.total_ht)}</td><td>${this.money(cn.total_tax)}</td>
                    <td>${this.money(cn.total_ttc)}</td></tr>`;
            });
            html += `</tbody></table></div>`;
        }

        // Ventes incompletes
        html += `<div class="report-section"><h4>Ventes incompletes</h4>
            <p>Nombre : <strong>${data.incomplete_sales ?? 0}</strong></p></div>`;

        return html;
    },

    number(v) { return new Intl.NumberFormat('fr-FR').format(v || 0); },

    money(v) { return new Intl.NumberFormat('fr-FR', { minimumFractionDigits: 0, maximumFractionDigits: 2 }).format(v || 0) + ' FC'; },

    print() {
        const w = window.open('/rapport-ticket', '_blank');
        if (w) {
            w.reportData = window._currentReportData;
            w.reportLabel = window._currentReportLabel;
        }
    },

    loadHistory() {
        // Historique Z-rapports
        const zList = document.getElementById('report-history-list');
        if (zList) {
            fetch('/api/reports/history').then(r => r.json()).then(resp => {
                if (!resp.success) return;
                if (!resp.data.length) { zList.innerHTML = '<p style="padding: 1rem; color: var(--muted);">Aucun Z-rapport genere.</p>'; return; }
                zList.innerHTML = '<table class="report-table"><thead><tr><th>#</th><th>Date</th><th>Periode</th><th>Total TTC</th><th>Factures</th></tr></thead><tbody>' +
                    resp.data.map(z => `<tr><td>${z.counter}</td><td>${z.issued_at}</td>
                        <td>${z.period_start} -> ${z.period_end}</td>
                        <td>${this.money(z.total_ttc)}</td><td>${z.invoice_count}</td></tr>`).join('') + '</tbody></table>';
            }).catch(() => {});
        }

        // Historique A-rapports
        const aList = document.getElementById('a-report-history-list');
        if (aList) {
            fetch('/api/reports/a-history').then(r => r.json()).then(resp => {
                if (!resp.success) return;
                if (!resp.data.length) { aList.innerHTML = '<p style="padding: 1rem; color: var(--muted);">Aucun A-rapport genere.</p>'; return; }
                aList.innerHTML = '<table class="report-table"><thead><tr><th>#</th><th>Date</th><th>Periode</th><th>Articles</th><th>Qte vendue</th><th>Montant</th></tr></thead><tbody>' +
                    resp.data.map(a => `<tr><td>${a.counter}</td><td>${a.issued_at}</td>
                        <td>${a.period_start} -> ${a.period_end}</td>
                        <td>${a.articles_count}</td>
                        <td>${this.number(a.total_quantity_sold)}</td>
                        <td>${this.money(a.total_amount_collected)}</td></tr>`).join('') + '</tbody></table>';
            }).catch(() => {});
        }
    },
};
document.addEventListener('DOMContentLoaded', () => ReportsManager.init());
