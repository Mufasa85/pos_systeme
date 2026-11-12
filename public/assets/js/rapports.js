const ReportsManager = {
    init() {
        this.bindEvents();
        this.initDgiDates();
        this.loadHistory();
    },

    bindEvents() {
        document.getElementById('btn-a-report')?.addEventListener('click', () => this.generateA());
        document.getElementById('btn-dgi-x-report')?.addEventListener('click', () => this.generateDgiX());
        document.getElementById('btn-dgi-z-report')?.addEventListener('click', () => this.generateDgiZ());
        document.getElementById('btn-dgi-z-closure')?.addEventListener('click', () => this.closeDgiZ());
        document.getElementById('report-shop-filter')?.addEventListener('change', () => {
            document.getElementById('report-content').innerHTML = `
                <div class="rpt-empty-state">
                    <p>Selectionnez un type de rapport pour afficher les resultats</p>
                </div>`;
            this.loadHistory();
        });
    },

    // Boutique selectionnee (super_admin) : '' = toutes les boutiques
    getShopId() {
        return document.getElementById('report-shop-filter')?.value || '';
    },

    withShopParam(url) {
        const shopId = this.getShopId();
        if (!shopId) return url;
        return url + (url.includes('?') ? '&' : '?') + 'shop_id=' + encodeURIComponent(shopId);
    },

    generateA() {
        if (!confirm('Generer un A-rapport va cloturer la periode articles. Continuer ?')) return;
        this.fetch(this.withShopParam('/api/reports/a-report'), 'A-rapport (detail articles)');
    },

    initDgiDates() {
        const today = new Date();
        const value = [
            today.getFullYear(),
            String(today.getMonth() + 1).padStart(2, '0'),
            String(today.getDate()).padStart(2, '0')
        ].join('-');
        const from = document.getElementById('dgi-report-from');
        const to = document.getElementById('dgi-report-to');
        if (from && !from.value) from.value = value;
        if (to && !to.value) to.value = value;
    },

    isDgiSuperAdmin() {
        return document.getElementById('dgi-report-panel')?.dataset.superAdmin === '1';
    },

    /**
     * Affiche la durée de la requête DGI.
     */
    updateDgiDuration(data) {
        const target = document.getElementById('dgi-report-duration');
        if (!target) return;

        const ms = Number(data?.meta?.duration_ms);
        if (!Number.isFinite(ms)) {
            target.textContent = '—';
            return;
        }

        const seconds = ms / 1000;
        target.textContent = seconds >= 1
            ? `${seconds.toFixed(2)} s (${ms} ms)`
            : `${ms} ms`;
    },

    generateDgiX() {
        const from = document.getElementById('dgi-report-from')?.value;
        const to = document.getElementById('dgi-report-to')?.value;
        if (!from || !to) {
            this.showDgiError('Sélectionnez une période.');
            return;
        }
        if (from > to) {
            this.showDgiError('La date de début doit précéder la date de fin.');
            return;
        }
        if (this.isDgiSuperAdmin() && !this.getShopId()) {
            this.showDgiError('Sélectionnez une boutique avant de générer le rapport DGI.');
            return;
        }

        const url = this.withShopParam(
            `/api/reports/dgi/x?from=${encodeURIComponent(from)}&to=${encodeURIComponent(to)}`
        );
        this.fetchDgi(url, 'Rapport DGI / OSAT — X');
    },

    generateDgiZ() {
        if (this.isDgiSuperAdmin() && !this.getShopId()) {
            this.showDgiError('Sélectionnez une boutique avant de générer le rapport DGI.');
            return;
        }
        this.fetchDgi(
            this.withShopParam('/api/reports/dgi/z'),
            'Rapport DGI / OSAT — période Z à clôturer'
        );
    },

    /**
     * Clôture DGI : enregistre la période auprès de la DGI.
     */
    async closeDgiZ() {
        if (this.isDgiSuperAdmin() && !this.getShopId()) {
            this.showDgiError('Sélectionnez une boutique avant de clôturer la période DGI.');
            return;
        }
        if (!confirm(
            'Clôturer la période Z auprès de la DGI ?\n\n'
            + 'La période sera enregistrée et la suivante démarrera à sa fin.'
        )) {
            return;
        }

        const c = document.getElementById('report-content');
        c.innerHTML = '<div class="rpt-loading"><div class="rpt-spinner"></div><p>Clôture DGI en cours...</p></div>';

        try {
            const response = await fetch(this.withShopParam('/api/reports/dgi/z'), {
                method: 'POST',
                credentials: 'same-origin',
                cache: 'no-store',
                headers: { 'Content-Type': 'application/json' }
            });
            let payload = null;
            try {
                payload = await response.json();
            } catch (e) {
                throw new Error('Réponse invalide du serveur de rapports.');
            }
            if (!response.ok || !payload?.success) {
                throw new Error(payload?.message || payload?.error || `Erreur HTTP ${response.status}.`);
            }
            this.display(payload.data, 'Clôture DGI / OSAT');
            // L'historique des Z-rapports est fusionné (POS + DGI) : on le recharge.
            this.loadHistory();
        } catch (e) {
            this.showDgiError(e.message || 'La clôture DGI a échoué.');
        }
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

    async fetchDgi(url, label) {
        const c = document.getElementById('report-content');
        c.innerHTML = '<div class="rpt-loading"><div class="rpt-spinner"></div><p>Consultation du rapport DGI en cours...</p></div>';

        try {
            const response = await fetch(url, {
                credentials: 'same-origin',
                cache: 'no-store'
            });
            let payload = null;
            try {
                payload = await response.json();
            } catch (e) {
                throw new Error('Réponse invalide du serveur de rapports.');
            }

            if (!response.ok || !payload?.success) {
                throw new Error(payload?.message || payload?.error || `Erreur HTTP ${response.status}.`);
            }

            this.display(payload.data, label);
        } catch (e) {
            this.showDgiError(e.message || 'Le rapport DGI est momentanément indisponible.');
        }
    },

    showDgiError(message) {
        const c = document.getElementById('report-content');
        if (!c) return;
        c.innerHTML = `<div class="rpt-error">${this.escapeHtml(message)}</div>`;
    },

    escapeHtml(value) {
        if (value === null || value === undefined) return '';
        return String(value)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#039;');
    },

    display(data, label) {
        window._currentReportData = data;
        window._currentReportLabel = label;
        const c = document.getElementById('report-content');
        const h = data.header;
        const isAReport = h.type === 'A';
        const isDgiReport = data.source === 'dgi' || h.type === 'X_DGI' || h.type === 'Z_DGI';

        let html = `
            <div class="rpt-report-header">
                <h2>${this.escapeHtml(label)}</h2>
                <div class="rpt-meta-row">
                    <span class="rpt-meta-tag">${this.escapeHtml(h.denomination)}</span>
                    <span>NIF:</span>
                    <span>ISF: ${this.escapeHtml(h.isf)}</span>
                </div>
                <div class="rpt-meta-row" style="margin-top:4px">
                    <span>Periode: ${this.escapeHtml(h.period_start)} &rarr; ${this.escapeHtml(h.period_end)}</span>
                    <span>Genere le: ${this.escapeHtml(h.generated_at)}</span>
                </div>
            </div>`;

        if (isDgiReport) {
            html += this.displayDgiReport(data);
        } else if (isAReport) {
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

    displayDgiReport(data) {
        const totals = data.totals || {};
        let html = '<div class="rpt-dgi-source">Source : DGI / OSAT</div>';

        // Met à jour la durée de la requête DGI.
        this.updateDgiDuration(data);

        html += `
            <div class="rpt-stats-grid">
                <div class="rpt-stat-pill">
                    <div class="rpt-stat-label">Total HT</div>
                    <div class="rpt-stat-value">${this.money(totals.ht)}</div>
                </div>
                <div class="rpt-stat-pill">
                    <div class="rpt-stat-label">TVA</div>
                    <div class="rpt-stat-value">${this.money(totals.tva)}</div>
                </div>
                <div class="rpt-stat-pill">
                    <div class="rpt-stat-label">TS total</div>
                    <div class="rpt-stat-value">${this.money(totals.ts_total)}</div>
                </div>
                <div class="rpt-stat-pill rpt-stat-accent">
                    <div class="rpt-stat-label">TOTAL TTC</div>
                    <div class="rpt-stat-value">${this.money(totals.ttc)}</div>
                </div>
                <div class="rpt-stat-pill rpt-stat-success">
                    <div class="rpt-stat-label">Factures</div>
                    <div class="rpt-stat-value">${this.number(totals.invoice_count)}</div>
                </div>
                <div class="rpt-stat-pill rpt-stat-danger">
                    <div class="rpt-stat-label">Remises</div>
                    <div class="rpt-stat-value">${this.money(totals.discount_total)}</div>
                </div>
            </div>`;

        if (data.payments?.length) {
            html += '<div class="rpt-section"><h4>Par mode de paiement</h4>';
            html += '<table class="rpt-table"><thead><tr>'
                + '<th>Mode</th><th style="text-align:right">Montant</th>'
                + '</tr></thead><tbody>';
            data.payments.forEach((payment) => {
                html += '<tr>'
                    + `<td>${this.escapeHtml(payment.label || payment.code)}`
                    + `<br><small style="color:var(--muted)">${this.escapeHtml(payment.code)}</small></td>`
                    + `<td style="text-align:right">${this.money(payment.total)}</td>`
                    + '</tr>';
            });
            html += '</tbody></table></div>';
        }

        if (data.by_invoice_type?.length) {
            html += '<div class="rpt-section"><h4>Par type de facture</h4>';
            html += '<table class="rpt-table"><thead><tr>'
                + '<th>Type</th><th style="text-align:right">Factures</th>'
                + '<th style="text-align:right">HT</th><th style="text-align:right">TVA</th>'
                + '<th style="text-align:right">TS</th><th style="text-align:right">TTC</th>'
                + '<th style="text-align:right">Remise</th>'
                + '<th>Paiements</th></tr></thead><tbody>';
            data.by_invoice_type.forEach((type) => {
                const typeTotals = type.totals || {};
                const payments = (type.payments || [])
                    .map((p) => `${p.label || p.code} : ${this.money(p.total)}`)
                    .join('<br>');
                html += '<tr>'
                    + `<td><strong>${this.escapeHtml(type.type)}</strong><br><small style="color:var(--muted)">${this.escapeHtml(type.label || '')}</small></td>`
                    + `<td style="text-align:right">${this.number(typeTotals.invoice_count)}</td>`
                    + `<td style="text-align:right">${this.money(typeTotals.ht)}</td>`
                    + `<td style="text-align:right">${this.money(typeTotals.tva)}</td>`
                    + `<td style="text-align:right">${this.money(typeTotals.ts_total)}</td>`
                    + `<td style="text-align:right">${this.money(typeTotals.ttc)}</td>`
                    + `<td style="text-align:right">${this.money(typeTotals.discount_total)}</td>`
                    + `<td><small>${payments || '—'}</small></td>`
                    + '</tr>';
            });
            html += '</tbody></table></div>';
        }

        const taxGroups = (data.tax_groups || []).filter((group) =>
            Math.abs(Number(group.amount) || 0) > 0.001
            || Math.abs(Number(group.tva) || 0) > 0.001
            || Math.abs(Number(group.ts) || 0) > 0.001
        );
        if (taxGroups.length) {
            html += '<div class="rpt-section"><h4>Par catégorie fiscale DGI</h4>';
            html += '<table class="rpt-table"><thead><tr>'
                + '<th>Catégorie</th><th>Libellé</th><th style="text-align:right">Taux</th>'
                + '<th style="text-align:right">HT</th>'
                + '<th style="text-align:right">TVA</th><th style="text-align:right">TS</th>'
                + '</tr></thead><tbody>';
            taxGroups.forEach((group) => {
                html += '<tr>'
                    + `<td><strong>${this.escapeHtml(group.code)}</strong></td>`
                    + `<td>${this.escapeHtml(group.label || '')}</td>`
                    + `<td style="text-align:right">${this.percent(group.rate)}</td>`
                    + `<td style="text-align:right">${this.money(group.amount - group.tva)}</td>`
                    + `<td style="text-align:right">${this.money(group.tva)}</td>`
                    + `<td style="text-align:right">${this.money(group.ts)}</td>`
                    + '</tr>';
            });
            html += '</tbody></table></div>';
        }

        if (data.warnings?.length) {
            html += '<div class="rpt-warning-box"><strong>Avertissements de cohérence</strong><ul>';
            data.warnings.forEach((warning) => {
                html += `<li>${this.escapeHtml(warning)}</li>`;
            });
            html += '</ul></div>';
        }

        if (data.header?.official_closure === true) {
            html += '<div class="rpt-warning-box"><strong>Clôture DGI enregistrée</strong>'
                + '<ul><li>Période : '
                + `${this.escapeHtml(data.header.period_start)} → ${this.escapeHtml(data.header.period_end)}`
                + `</li><li>Numéro de clôture : ${this.escapeHtml(data.header.closure_counter)}</li></ul></div>`;
        } else if (data.header?.official_closure === false) {
            html += '<div class="rpt-dgi-note">Consultation en lecture seule : la période n\'a pas été clôturée auprès de la DGI.</div>';
        }

        return html;
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
                <div class="rpt-stat-pill">
                    <div class="rpt-stat-label">TS totale</div>
                    <div class="rpt-stat-value">${this.money(data.totals.total_ts)}</div>
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
                <thead><tr><th>Groupe</th><th>Etiquette</th><th style="text-align:right">Taux</th><th style="text-align:right">Nb</th><th style="text-align:right">HT</th><th style="text-align:right">Taxe</th><th style="text-align:right">TS</th><th style="text-align:right">TTC</th></tr></thead><tbody>`;
            data.by_tax_group.forEach(g => {
                html += `<tr><td>${g.groupe_taxe || '-'}</td><td>${g.etiquette || '-'}</td>
                    <td style="text-align:right">${this.percent(g.taux)}</td><td style="text-align:right">${g.invoice_count}</td>
                    <td style="text-align:right">${this.money(g.total_ht)}</td><td style="text-align:right">${this.money(g.total_tax)}</td>
                    <td style="text-align:right">${this.money(g.total_ts)}</td>
                    <td style="text-align:right">${this.money(g.total_ttc)}</td></tr>`;
            });
            html += `</tbody></table></div>`;
        }

        // ── BLOCS HÔTEL ────────────────────────────────────────────────
        // Le CA hôtelier est intégré aux rapports : les nuitées apparaissent
        // dans leur groupe de taxation et le CA est ventilé par chambre.
        const hotel = data.hotel_breakdown;
        if (hotel && hotel.factures && hotel.factures.nb_factures > 0) {
            html += `<div class="rpt-section"><h4>Hôtel — synthèse</h4>
                <div class="rpt-stats-grid">
                    <div class="rpt-stat-pill">
                        <div class="rpt-stat-label">Factures hôtel</div>
                        <div class="rpt-stat-value">${this.number(hotel.factures.nb_factures)}</div>
                    </div>
                    <div class="rpt-stat-pill">
                        <div class="rpt-stat-label">Nuitées</div>
                        <div class="rpt-stat-value">${this.number(hotel.nuites.nb_nuitees)}</div>
                    </div>
                    <div class="rpt-stat-pill">
                        <div class="rpt-stat-label">CA nuitées (HT)</div>
                        <div class="rpt-stat-value">${this.money(hotel.nuites.total_ht)}</div>
                    </div>
                    <div class="rpt-stat-pill">
                        <div class="rpt-stat-label">TS hôtel</div>
                        <div class="rpt-stat-value">${this.money((hotel.nuites.total_ts || 0) + (hotel.extras.total_ts || 0))}</div>
                    </div>
                    <div class="rpt-stat-pill rpt-stat-accent">
                        <div class="rpt-stat-label">CA hôtel (TTC)</div>
                        <div class="rpt-stat-value">${this.money(hotel.factures.total_ttc)}</div>
                    </div>
                </div>
                <p style="font-size:11.5px;color:var(--muted);margin:8px 0 0">
                    Total des factures hôtel (nuitées + extras consolidés).
                    Les extras ci-dessous sont DÉJÀ inclus dans ce total : ils ne sont pas additionnés une seconde fois.
                </p></div>`;
        }
        if (data.hotel_by_room?.length) {
            html += `<div class="rpt-section"><h4>Hôtel — par chambre</h4>
                <table class="rpt-table">
                <thead><tr><th>Chambre</th><th>Type</th><th style="text-align:right">Nuitées</th><th style="text-align:right">Factures</th><th style="text-align:right">HT</th><th style="text-align:right">Taxe</th><th style="text-align:right">TS</th><th style="text-align:right">TTC</th></tr></thead><tbody>`;
            data.hotel_by_room.forEach(r => {
                html += `<tr><td><strong>${r.chambre_numero || '-'}</strong></td><td>${r.chambre_type || '-'}</td>
                    <td style="text-align:right">${this.number(r.nb_nuitees)}</td><td style="text-align:right">${r.nb_factures}</td>
                    <td style="text-align:right">${this.money(r.total_ht)}</td><td style="text-align:right">${this.money(r.total_tax)}</td>
                    <td style="text-align:right">${this.money(r.total_ts)}</td>
                    <td style="text-align:right">${this.money(r.total_ttc)}</td></tr>`;
            });
            html += `</tbody></table></div>`;
        }

        if (data.hotel_extras?.length) {
            html += `<div class="rpt-section"><h4>Hôtel — extras (déjà inclus dans les factures hôtel)</h4>
                <table class="rpt-table">
                <thead><tr><th>Article</th><th style="text-align:right">Qté</th><th style="text-align:right">HT</th><th style="text-align:right">Taxe</th></tr></thead><tbody>`;
            data.hotel_extras.forEach(x => {
                html += `<tr><td>${x.article_nom || '-'}</td>
                    <td style="text-align:right">${this.number(x.quantite)}</td>
                    <td style="text-align:right">${this.money(x.total_ht)}</td>
                    <td style="text-align:right">${this.money(x.total_tax)}</td></tr>`;
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

    // Taux de taxation : toujours affiché avec 2 décimales (« 16.00% »),
    // comme dans les documents fiscaux. Le `|| 0` brut affichait « 0% »
    // ou « 16% » sans décimales.
    percent(v) {
        const n = parseFloat(v);
        return (isNaN(n) ? 0 : n).toFixed(2) + '%';
    },

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
            fetch(this.withShopParam('/api/reports/history')).then(r => r.json()).then(resp => {
                if (!resp.success) return;
                this._histData.z = resp.data;
                this._histPage.z = 1;
                this.renderHistoryPage('z');
            }).catch(() => { });
        }

        const aList = document.getElementById('a-report-history-list');
        if (aList) {
            fetch(this.withShopParam('/api/reports/a-history')).then(r => r.json()).then(resp => {
                if (!resp.success) return;
                this._histData.a = resp.data;
                this._histPage.a = 1;
                this.renderHistoryPage('a');
            }).catch(() => { });
        }
    },

    renderHistoryPage(type) {
        const containers = {
            z: 'report-history-list',
            a: 'a-report-history-list'
        };
        const container = document.getElementById(containers[type]);
        if (!container) return;

        const data = this._histData[type] || [];
        if (!data.length) {
            const labels = {
                z: 'Aucun Z-rapport genere.',
                a: 'Aucun A-rapport genere.'
            };
            container.innerHTML = `<p class="rpt-history-empty">${labels[type] || 'Aucun rapport.'}</p>`;
            return;
        }

        const perPage = this._histPerPage;
        const totalPages = Math.ceil(data.length / perPage);
        const page = Math.min(this._histPage[type], totalPages);
        const start = (page - 1) * perPage;
        const pageItems = data.slice(start, start + perPage);

        const showShopCol = !!document.getElementById('report-shop-filter');
        const shopCell = (row) => showShopCol ? `<td>${row.shop_name || '-'}</td>` : '';
        const shopHeader = showShopCol ? '<th>Boutique</th>' : '';

        let html = '<table class="rpt-table">';

        if (type === 'z') {
            html += `<thead><tr><th>#</th>${shopHeader}<th>Date</th><th>Periode</th><th style="text-align:right">TTC</th><th style="text-align:right">Factures</th></tr></thead><tbody>`;
            pageItems.forEach(z => {
                const originBadge = z.origin === 'dgi'
                    ? '<span class="rpt-origin-badge rpt-origin-dgi">DGI</span>'
                    : '<span class="rpt-origin-badge rpt-origin-local" title="Ancienne cloture POS (desactivee)">POS</span>';
                const legacyNote = z.legacy
                    ? '<br><small style="color:var(--muted);font-size:0.68rem">archive POS</small>'
                    : '';
                html += `<tr><td><strong>${z.counter}</strong><br>${originBadge}${legacyNote}</td>${shopCell(z)}<td>${z.issued_at}</td>
                    <td>${z.period_start}<br><span style="color:var(--muted);font-size:0.75rem">&rarr; ${z.period_end}</span></td>
                    <td style="text-align:right">${this.money(z.total_ttc)}</td><td style="text-align:right">${z.invoice_count}</td></tr>`;
            });
        } else if (type === 'dgi') {
            html += `<thead><tr><th>#</th>${shopHeader}<th>Clôturé le</th><th>Periode</th><th style="text-align:right">HT</th><th style="text-align:right">TTC</th><th style="text-align:right">Factures</th></tr></thead><tbody>`;
            pageItems.forEach(z => {
                const statusBadge = z.status === 'active'
                    ? '<span style="color:#059669;font-weight:700">active</span>'
                    : '<span style="color:var(--muted)">archivée</span>';
                html += `<tr><td><strong>${z.counter}</strong></td>${shopCell(z)}<td>${z.issued_at}<br><small style="color:var(--muted);font-size:0.72rem">${statusBadge}</small></td>
                    <td>${z.period_start}<br><span style="color:var(--muted);font-size:0.75rem">&rarr; ${z.period_end}</span></td>
                    <td style="text-align:right">${this.money(z.total_ht)}</td>
                    <td style="text-align:right">${this.money(z.total_ttc)}</td>
                    <td style="text-align:right">${z.invoice_count}</td></tr>`;
            });
        } else {
            html += `<thead><tr><th>#</th>${shopHeader}<th>Date</th><th>Periode</th><th style="text-align:right">Articles</th><th style="text-align:right">Qte</th><th style="text-align:right">Montant</th></tr></thead><tbody>`;
            pageItems.forEach(a => {
                html += `<tr><td><strong>${a.counter}</strong></td>${shopCell(a)}<td>${a.issued_at}</td>
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
