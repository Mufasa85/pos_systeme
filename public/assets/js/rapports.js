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
        c.innerHTML = '<div class="rpt-loading"><div class="rpt-spinner"></div><p>Generation en cours...</p></div>';
        fetch(url).then(r => r.json()).then(resp => {
            if (!resp.success) { c.innerHTML = `<div class="rpt-error"><svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"></circle><line x1="12" y1="8" x2="12" y2="12"></line><line x1="12" y1="16" x2="12.01" y2="16"></line></svg>${resp.message || 'Erreur'}</div>`; return; }
            this.display(resp.data, label);
            this.loadHistory();
        }).catch(e => { c.innerHTML = `<div class="rpt-error">Erreur: ${e.message}</div>`; });
    },

    display(data, label) {
        window._currentReportData = data;
        window._currentReportLabel = label;
        const c = document.getElementById('report-content');
        const h = data.header;
        const isAReport = h.type === 'A';

        let html = `
            <div class="rpt-report-header">
                <h2>${label}</h2>
                <div class="rpt-meta-row">
                    <span class="rpt-meta-tag">${h.denomination}</span>
                    <span>NIF: ${h.nif}</span>
                    <span>ISF: ${h.isf}</span>
                </div>
                <div class="rpt-meta-row" style="margin-top:4px">
                    <span>Periode: ${h.period_start} &rarr; ${h.period_end}</span>
                    <span>Genere le: ${h.generated_at}</span>
                </div>
            </div>`;

        if (isAReport) {
            html += this.displayAReport(data);
        } else {
            html += this.displayZXReport(data);
        }

        html += `<button onclick="ReportsManager.print()" class="rpt-print-btn">
            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <polyline points="6 9 6 2 18 2 18 9"></polyline>
                <path d="M6 18H4a2 2 0 0 1-2-2v-5a2 2 0 0 1 2-2h16a2 2 0 0 1 2 2v5a2 2 0 0 1-2 2h-2"></path>
                <rect x="6" y="14" width="12" height="8"></rect>
            </svg>
            Imprimer
        </button>`;
        c.innerHTML = html;
    },

    displayAReport(data) {
        let html = `
            <div class="rpt-stats-grid">
                <div class="rpt-stat-pill">
                    <div class="rpt-stat-label">Total articles</div>
                    <div class="rpt-stat-value">${this.number(data.totals.articles_count)}</div>
                </div>
                <div class="rpt-stat-pill rpt-stat-success">
                    <div class="rpt-stat-label">Qte vendue</div>
                    <div class="rpt-stat-value">${this.number(data.totals.total_quantity_sold)}</div>
                </div>
                <div class="rpt-stat-pill rpt-stat-danger">
                    <div class="rpt-stat-label">Qte retournee</div>
                    <div class="rpt-stat-value">${this.number(data.totals.total_quantity_returned)}</div>
                </div>
                <div class="rpt-stat-pill rpt-stat-accent">
                    <div class="rpt-stat-label">Montant collecte</div>
                    <div class="rpt-stat-value">${this.money(data.totals.total_amount_collected)}</div>
                </div>
            </div>`;

        if (data.articles?.length) {
            const categories = {};
            data.articles.forEach(a => {
                const cat = a.category_name || 'Sans categorie';
                if (!categories[cat]) categories[cat] = [];
                categories[cat].push(a);
            });

            Object.keys(categories).sort().forEach(catName => {
                html += `<div class="rpt-section"><h4>${catName}</h4>`;
                html += `<table class="rpt-table">
                    <thead><tr><th>Code</th><th>Article</th><th>Prix U.</th><th>Taxe</th>
                    <th style="text-align:right">Vendu</th><th style="text-align:right">Retour</th><th>Stock</th><th style="text-align:right">Montant</th></tr></thead><tbody>`;
                categories[catName].forEach(a => {
                    html += `<tr><td>${a.article_code || '-'}</td><td>${a.article_name}</td>
                        <td>${this.money(a.unit_price)}</td><td>${a.tax_rate || 0}%</td>
                        <td style="text-align:right">${this.number(a.quantite_vendue)}</td>
                        <td style="text-align:right">${this.number(a.quantite_retournee)}</td>
                        <td>${this.number(a.stock_quantity)}</td>
                        <td style="text-align:right">${this.money(a.montant_collecte)}</td></tr>`;
                });
                html += `</tbody></table></div>`;
            });
        } else {
            html += `<div class="rpt-empty-state"><p>Aucun article vendu sur cette periode.</p></div>`;
        }
        return html;
    },

    displayZXReport(data) {
        let html = `
            <div class="rpt-stats-grid">
                <div class="rpt-stat-pill">
                    <div class="rpt-stat-label">Total HT</div>
                    <div class="rpt-stat-value">${this.money(data.totals.total_ht)}</div>
                </div>
                <div class="rpt-stat-pill">
                    <div class="rpt-stat-label">Total Taxe</div>
                    <div class="rpt-stat-value">${this.money(data.totals.total_tax)}</div>
                </div>
                <div class="rpt-stat-pill rpt-stat-accent">
                    <div class="rpt-stat-label">TOTAL TTC</div>
                    <div class="rpt-stat-value">${this.money(data.totals.total_ttc)}</div>
                </div>
                <div class="rpt-stat-pill rpt-stat-success">
                    <div class="rpt-stat-label">Factures</div>
                    <div class="rpt-stat-value">${this.number(data.totals.total_invoices)}</div>
                </div>
            </div>`;

        if (data.by_invoice_type?.length) {
            html += `<div class="rpt-section"><h4>Par type de facture</h4>
                <table class="rpt-table">
                <thead><tr><th>Type</th><th style="text-align:right">Factures</th><th style="text-align:right">HT</th><th style="text-align:right">Taxe</th><th style="text-align:right">TTC</th></tr></thead><tbody>`;
            data.by_invoice_type.forEach(t => {
                html += `<tr><td><strong>${t.invoice_type}</strong></td><td style="text-align:right">${t.invoice_count}</td>
                    <td style="text-align:right">${this.money(t.total_ht)}</td><td style="text-align:right">${this.money(t.total_tax)}</td>
                    <td style="text-align:right">${this.money(t.total_ttc)}</td></tr>`;
            });
            html += `</tbody></table></div>`;
        }

        if (data.by_tax_group?.length) {
            html += `<div class="rpt-section"><h4>Par groupe de taxation</h4>
                <table class="rpt-table">
                <thead><tr><th>Groupe</th><th>Etiquette</th><th style="text-align:right">Taux</th><th style="text-align:right">Nb</th><th style="text-align:right">HT</th><th style="text-align:right">Taxe</th><th style="text-align:right">TTC</th></tr></thead><tbody>`;
            data.by_tax_group.forEach(g => {
                html += `<tr><td>${g.groupe_taxe || '-'}</td><td>${g.etiquette || '-'}</td>
                    <td style="text-align:right">${g.taux || 0}%</td><td style="text-align:right">${g.invoice_count}</td>
                    <td style="text-align:right">${this.money(g.total_ht)}</td><td style="text-align:right">${this.money(g.total_tax)}</td>
                    <td style="text-align:right">${this.money(g.total_ttc)}</td></tr>`;
            });
            html += `</tbody></table></div>`;
        }

        if (data.by_payment_method?.length) {
            html += `<div class="rpt-section"><h4>Par mode de paiement</h4>
                <table class="rpt-table">
                <thead><tr><th>Mode</th><th style="text-align:right">Montant</th></tr></thead><tbody>`;
            data.by_payment_method.forEach(p => {
                html += `<tr><td>${p.label}</td><td style="text-align:right">${this.money(p.total)}</td></tr>`;
            });
            html += `</tbody></table></div>`;
        }

        if (data.discounts && (data.discounts.invoices_with_discount > 0 || data.discounts.total_discount_amount > 0)) {
            html += `<div class="rpt-section"><h4>Reductions commerciales</h4>
                <div class="rpt-stats-grid">
                    <div class="rpt-stat-pill">
                        <div class="rpt-stat-label">Factures avec remise</div>
                        <div class="rpt-stat-value">${this.number(data.discounts.invoices_with_discount)}</div>
                    </div>
                    <div class="rpt-stat-pill rpt-stat-danger">
                        <div class="rpt-stat-label">Montant total</div>
                        <div class="rpt-stat-value">${this.money(data.discounts.total_discount_amount)}</div>
                    </div>
                </div></div>`;
        }

        if (data.credit_notes?.length) {
            html += `<div class="rpt-section"><h4>Avoirs et annulations</h4>
                <table class="rpt-table">
                <thead><tr><th>Type</th><th style="text-align:right">Nombre</th><th style="text-align:right">HT</th><th style="text-align:right">Taxe</th><th style="text-align:right">TTC</th></tr></thead><tbody>`;
            data.credit_notes.forEach(cn => {
                html += `<tr><td><strong>${cn.invoice_type}</strong></td><td style="text-align:right">${cn.invoice_count}</td>
                    <td style="text-align:right">${this.money(cn.total_ht)}</td><td style="text-align:right">${this.money(cn.total_tax)}</td>
                    <td style="text-align:right">${this.money(cn.total_ttc)}</td></tr>`;
            });
            html += `</tbody></table></div>`;
        }

        html += `<div class="rpt-section"><h4>Ventes incompletes</h4>
            <div class="rpt-stat-pill" style="display:inline-block">
                <div class="rpt-stat-label">Nombre</div>
                <div class="rpt-stat-value">${this.number(data.incomplete_sales ?? 0)}</div>
            </div></div>`;

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

    _histData: { z: [], a: [] },
    _histPage: { z: 1, a: 1 },
    _histPerPage: 5,

    loadHistory() {
        const zList = document.getElementById('report-history-list');
        if (zList) {
            fetch('/api/reports/history').then(r => r.json()).then(resp => {
                if (!resp.success) return;
                this._histData.z = resp.data;
                this._histPage.z = 1;
                this.renderHistoryPage('z');
            }).catch(() => {});
        }

        const aList = document.getElementById('a-report-history-list');
        if (aList) {
            fetch('/api/reports/a-history').then(r => r.json()).then(resp => {
                if (!resp.success) return;
                this._histData.a = resp.data;
                this._histPage.a = 1;
                this.renderHistoryPage('a');
            }).catch(() => {});
        }
    },

    renderHistoryPage(type) {
        const container = document.getElementById(type === 'z' ? 'report-history-list' : 'a-report-history-list');
        if (!container) return;

        const data = this._histData[type];
        if (!data.length) {
            container.innerHTML = `<p class="rpt-history-empty">Aucun ${type === 'z' ? 'Z' : 'A'}-rapport genere.</p>`;
            return;
        }

        const perPage = this._histPerPage;
        const totalPages = Math.ceil(data.length / perPage);
        const page = Math.min(this._histPage[type], totalPages);
        const start = (page - 1) * perPage;
        const pageItems = data.slice(start, start + perPage);

        let html = '<table class="rpt-table">';

        if (type === 'z') {
            html += '<thead><tr><th>#</th><th>Date</th><th>Periode</th><th style="text-align:right">TTC</th><th style="text-align:right">Factures</th></tr></thead><tbody>';
            pageItems.forEach(z => {
                html += `<tr><td><strong>${z.counter}</strong></td><td>${z.issued_at}</td>
                    <td>${z.period_start}<br><span style="color:var(--muted);font-size:0.75rem">&rarr; ${z.period_end}</span></td>
                    <td style="text-align:right">${this.money(z.total_ttc)}</td><td style="text-align:right">${z.invoice_count}</td></tr>`;
            });
        } else {
            html += '<thead><tr><th>#</th><th>Date</th><th>Periode</th><th style="text-align:right">Articles</th><th style="text-align:right">Qte</th><th style="text-align:right">Montant</th></tr></thead><tbody>';
            pageItems.forEach(a => {
                html += `<tr><td><strong>${a.counter}</strong></td><td>${a.issued_at}</td>
                    <td>${a.period_start}<br><span style="color:var(--muted);font-size:0.75rem">&rarr; ${a.period_end}</span></td>
                    <td style="text-align:right">${a.articles_count}</td>
                    <td style="text-align:right">${this.number(a.total_quantity_sold)}</td>
                    <td style="text-align:right">${this.money(a.total_amount_collected)}</td></tr>`;
            });
        }
        html += '</tbody></table>';

        if (totalPages > 1) {
            html += this.renderPagination(type, page, totalPages);
        }

        container.innerHTML = html;
        this.bindPagination(type);
    },

    renderPagination(type, page, totalPages) {
        let html = '<div class="rpt-pagination">';
        html += `<button class="rpt-page-btn" data-type="${type}" data-page="${page - 1}" ${page <= 1 ? 'disabled' : ''}>
            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="15 18 9 12 15 6"></polyline></svg>
        </button>`;

        for (let i = 1; i <= totalPages; i++) {
            if (i === 1 || i === totalPages || Math.abs(i - page) <= 1) {
                html += `<button class="rpt-page-btn ${i === page ? 'rpt-page-active' : ''}" data-type="${type}" data-page="${i}">${i}</button>`;
            } else if (Math.abs(i - page) === 2) {
                html += '<span class="rpt-page-ellipsis">...</span>';
            }
        }

        html += `<button class="rpt-page-btn" data-type="${type}" data-page="${page + 1}" ${page >= totalPages ? 'disabled' : ''}>
            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="9 18 15 12 9 6"></polyline></svg>
        </button>`;
        html += `<span class="rpt-page-info">${page} / ${totalPages}</span>`;
        html += '</div>';
        return html;
    },

    bindPagination(type) {
        document.querySelectorAll(`.rpt-page-btn[data-type="${type}"]`).forEach(btn => {
            btn.addEventListener('click', () => {
                const p = parseInt(btn.dataset.page);
                if (p < 1 || p > Math.ceil(this._histData[type].length / this._histPerPage)) return;
                this._histPage[type] = p;
                this.renderHistoryPage(type);
            });
        });
    },
};
document.addEventListener('DOMContentLoaded', () => ReportsManager.init());
