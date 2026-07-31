<!-- Payroll Payslip Detail -->
<div id="page-payroll" class="page <?= $page == 'payroll' ? 'active' : '' ?>">
    <div class="page-header">
        <h2 id="title">Détail du bulletin</h2>
        <a href="/payroll/payslips" class="btn btn-sm btn-secondary">Retour</a>
    </div>

    <div class="card">
        <div class="card-body" id="detail">
            <em>Chargement…</em>
        </div>
    </div>
</div>

<script>
    (async function () {
        const url = new URLSearchParams(location.search);
        const id = url.get('id');
        if (!id) return location.href = '/payroll/payslips';

        const p = await fetch('/api/payroll/payslips/' + id).then(r => r.json());
        if (p.error) return location.href = '/payroll/payslips';

        document.getElementById('title').textContent = 'Bulletin ' + (p.nom_complet || '') + ' — ' + String(p.month).padStart(2,'0') + '/' + p.year;

        const lines = p.lines || [];
        const earnings = lines.filter(l => l.type === 'earning');
        const deductions = lines.filter(l => l.type === 'deduction');
        const employer = lines.filter(l => l.type === 'employer');

        function section(title, rows) {
            let h = `<h4 style="margin-top:1.5rem">${title}</h4>`;
            h += `<div class="table-wrap"><table class="data-table" style="width:100%"><thead><tr><th>Libellé</th><th class="right">Montant</th></tr></thead><tbody>`;
            rows.forEach(l => {
                h += `<tr><td>${l.label}</td><td class="right">${parseFloat(l.amount).toLocaleString('fr-FR', {minimumFractionDigits:2})}</td></tr>`;
            });
            h += '</tbody></table></div>';
            return h;
        }

        let html = `<div class="payslip-summary">
            <p><strong>Matricule :</strong> ${p.matricule}</p>
            <p><strong>Brut :</strong> ${parseFloat(p.gross_amount).toLocaleString('fr-FR', {minimumFractionDigits:2})}</p>
            <p><strong>Retenues :</strong> ${parseFloat(p.total_deductions).toLocaleString('fr-FR', {minimumFractionDigits:2})}</p>
            <p><strong>Net :</strong> ${parseFloat(p.net_amount).toLocaleString('fr-FR', {minimumFractionDigits:2})}</p>
            <p><strong>Coût employeur :</strong> ${parseFloat(p.employer_cost).toLocaleString('fr-FR', {minimumFractionDigits:2})}</p>
            <p><strong>Statut :</strong> ${p.status}</p>
        </div>`;

        html += section('Gains', earnings);
        html += section('Retenues', deductions);
        html += section('Charges employeur', employer);

        html += `<div class="payslip-actions">
            <a href="/api/payroll/payslips/pdf/${id}" target="_blank" class="btn btn-info">Voir PDF</a>
            ${p.status === 'calculated' ? `<button onclick="validatePayslip(${id})" class="btn btn-success">Valider</button>` : ''}
        </div>`;

        document.getElementById('detail').innerHTML = html;
    })();

    async function validatePayslip(id) {
        if (!confirm('Valider ce bulletin ?')) return;
        const res = await fetch('/api/payroll/payslips/validate/' + id, { method: 'POST' });
        const data = await res.json();
        if (data.success) { alert('Bulletin validé'); location.reload(); }
        else { alert(data.error || 'Erreur'); }
    }
</script>
