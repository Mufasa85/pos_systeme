<!-- Payroll Reports -->
<div id="page-payroll" class="page <?= $page == 'payroll' ? 'active' : '' ?>">
    <div class="page-header">
        <h2>Rapports paie</h2>
    </div>

    <div class="card" style="margin-bottom: 1.5rem;">
        <div class="card-header"><h3>Sélectionner une période</h3></div>
        <div class="card-body">
            <select id="periodSelect" class="form-control" onchange="loadReport()">
                <option value="">Choisir une période</option>
            </select>
        </div>
    </div>

    <div class="card" id="summaryCard" hidden>
        <div class="card-header"><h3>Synthèse</h3></div>
        <div class="card-body">
            <div id="summary" class="stats-grid" style="grid-template-columns: repeat(auto-fit, minmax(180px, 1fr)); gap: 1rem;"></div>
            <a id="csvLink" href="#" class="btn btn-secondary" style="margin-top:1rem">Exporter CSV</a>
        </div>
    </div>

    <div class="card" id="paymentsCard" hidden>
        <div class="card-header"><h3>Paiements effectués</h3></div>
        <div class="card-body">
            <div id="paymentsSummary" style="margin-bottom:1rem; font-weight:600;"></div>
            <div id="paymentsTable"></div>
        </div>
    </div>

    <div class="card" id="contributionsCard" hidden>
        <div class="card-header"><h3>Cotisations employeur</h3></div>
        <div class="card-body">
            <div id="contributionsSummary" style="margin-bottom:1rem; font-weight:600;"></div>
            <div id="contributionsTable"></div>
        </div>
    </div>
</div>

<script>
    (async function () {
        const periods = await fetch('/api/payroll/periods').then(r => r.json());
        const select = document.getElementById('periodSelect');
        periods.forEach(p => {
            const opt = document.createElement('option');
            opt.value = p.id;
            opt.textContent = `${String(p.month).padStart(2,'0')}/${p.year} — ${p.status}`;
            select.appendChild(opt);
        });
    })();

    async function loadReport() {
        const id = document.getElementById('periodSelect').value;
        if (!id) { document.getElementById('summaryCard').hidden = true; document.getElementById('paymentsCard').hidden = true; return; }

        const data = await fetch('/api/payroll/reports/period/' + id).then(r => r.json());
        const container = document.getElementById('summary');

        function card(label, value, color) {
            return `<div class="stat-card">
                <div class="stat-icon ${color}"></div>
                <div class="stat-info"><span class="stat-label">${label}</span><span class="stat-value">${value}</span></div>
            </div>`;
        }

        container.innerHTML =
            card('Brut total', data.total_gross.toLocaleString('fr-FR', {minimumFractionDigits:2}), 'blue') +
            card('Net total', data.total_net.toLocaleString('fr-FR', {minimumFractionDigits:2}), 'green') +
            card('Retenues', data.total_deductions.toLocaleString('fr-FR', {minimumFractionDigits:2}), 'red') +
            card('Coût employeur', data.total_employer_cost.toLocaleString('fr-FR', {minimumFractionDigits:2}), 'purple');

        document.getElementById('csvLink').href = '/api/payroll/reports/csv/' + id;
        document.getElementById('summaryCard').hidden = false;

        const payData = await fetch('/api/payroll/reports/payments/' + id).then(r => r.json());
        const payTable = document.getElementById('paymentsTable');
        if (!payData.payments || !payData.payments.length) {
            payTable.innerHTML = '<div class="empty-state">Aucun paiement</div>';
            document.getElementById('paymentsSummary').textContent = 'Total payé : 0,00';
        } else {
            let html = '<div style="overflow-x:auto"><table class="data-table report-table" style="width:100%"><thead><tr><th>Nom</th><th>Date</th><th>Référence</th><th>Montant</th></tr></thead><tbody>';
            payData.payments.forEach(p => {
                html += `<tr><td class="report-title" data-label="Nom">${p.nom_complet ?? '-'}</td><td data-label="Date">${p.paid_at ?? '-'}</td><td data-label="Référence">${p.reference ?? '-'}</td><td data-label="Montant" class="right">${parseFloat(p.amount).toLocaleString('fr-FR', {minimumFractionDigits:2})}</td></tr>`;
            });
            html += '</tbody></table></div>';
            payTable.innerHTML = html;
            document.getElementById('paymentsSummary').textContent = 'Total payé : ' + payData.total_paid.toLocaleString('fr-FR', {minimumFractionDigits:2});
        }
        document.getElementById('paymentsCard').hidden = false;

        const cotData = await fetch('/api/payroll/reports/contributions/' + id).then(r => r.json());
        const cotTable = document.getElementById('contributionsTable');
        if (!cotData.contributions || !cotData.contributions.length) {
            cotTable.innerHTML = '<div class="empty-state">Aucune cotisation</div>';
            document.getElementById('contributionsSummary').textContent = 'Total cotisations : 0,00';
        } else {
            let html = '<div style="overflow-x:auto"><table class="data-table report-table" style="width:100%"><thead><tr><th>Code</th><th>Libellé</th><th>Montant</th></tr></thead><tbody>';
            cotData.contributions.forEach(c => {
                html += `<tr><td class="report-title" data-label="Code">${c.code}</td><td data-label="Libellé">${c.label}</td><td data-label="Montant" class="right">${parseFloat(c.total).toLocaleString('fr-FR', {minimumFractionDigits:2})}</td></tr>`;
            });
            html += '</tbody></table></div>';
            cotTable.innerHTML = html;
            document.getElementById('contributionsSummary').textContent = 'Total cotisations : ' + cotData.total_contributions.toLocaleString('fr-FR', {minimumFractionDigits:2});
        }
        document.getElementById('contributionsCard').hidden = false;
    }
</script>
