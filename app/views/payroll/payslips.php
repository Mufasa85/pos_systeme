<!-- Payroll Payslips -->
<div id="page-payroll" class="page <?= $page == 'payroll' ? 'active' : '' ?>">
    <div class="page-header">
        <h2>Bulletins de paie</h2>
    </div>

    <div class="card" style="margin-bottom: 1.5rem;">
        <div class="card-header"><h3>Sélectionner une période</h3></div>
        <div class="card-body">
            <select id="periodSelect" class="form-control" onchange="loadPayslips()">
                <option value="">Choisir une période</option>
            </select>
        </div>
    </div>

    <div class="card">
        <div class="card-header"><h3>Bulletins</h3></div>
        <div class="card-body">
            <div id="payslipList"><em>Sélectionnez une période</em></div>
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

        const url = new URLSearchParams(location.search);
        if (url.get('period')) {
            select.value = url.get('period');
            loadPayslips();
        }
    })();

    async function loadPayslips() {
        const periodId = document.getElementById('periodSelect').value;
        const list = document.getElementById('payslipList');
        if (!periodId) { list.innerHTML = '<em>Sélectionnez une période</em>'; return; }

        const payslips = await fetch('/api/payroll/payslips/period/' + periodId).then(r => r.json());
        if (!payslips.length) {
            list.innerHTML = '<div class="empty-state">Aucun bulletin. Lancez le calcul depuis Périodes.</div>';
            return;
        }

        let html = '<div style="overflow-x:auto"><table class="data-table payslip-table" style="width:100%">';
        html += '<thead><tr><th>Employé</th><th>Matricule</th><th>Brut</th><th>Retenues</th><th>Net</th><th>Statut</th><th>Actions</th></tr></thead><tbody>';
        payslips.forEach(p => {
            html += `<tr>
                <td class="employee-name" data-label="Employé">${p.nom_complet}</td>
                <td data-label="Matricule">${p.matricule}</td>
                <td data-label="Brut">${parseFloat(p.gross_amount).toLocaleString('fr-FR', {minimumFractionDigits:2})}</td>
                <td data-label="Retenues">${parseFloat(p.total_deductions).toLocaleString('fr-FR', {minimumFractionDigits:2})}</td>
                <td data-label="Net">${parseFloat(p.net_amount).toLocaleString('fr-FR', {minimumFractionDigits:2})}</td>
                <td data-label="Statut">${p.status}</td>
                <td data-label="Actions" class="payslip-actions">
                    <a href="/payroll/payslip_detail?id=${p.id}" class="btn btn-sm btn-secondary">Détail</a>
                    <a href="/api/payroll/payslips/pdf/${p.id}" target="_blank" class="btn btn-sm btn-info">PDF</a>
                    ${p.status === 'calculated' ? `<button onclick="validate(${p.id})" class="btn btn-sm btn-success">Valider</button>` : ''}
                </td>
            </tr>`;
        });
        html += '</tbody></table></div>';
        list.innerHTML = html;
    }

    async function validate(id) {
        if (!confirm('Valider ce bulletin ?')) return;
        const res = await fetch('/api/payroll/payslips/validate/' + id, { method: 'POST' });
        const data = await res.json();
        if (data.success) { alert('Bulletin validé'); loadPayslips(); }
        else { alert(data.error || 'Erreur'); }
    }
</script>
