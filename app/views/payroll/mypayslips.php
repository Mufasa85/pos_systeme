<!-- Payroll My Payslips -->
<div id="page-payroll" class="page <?= $page == 'payroll' ? 'active' : '' ?>">
    <div class="page-header">
        <h2>Mes bulletins de paie</h2>
    </div>

    <div class="card">
        <div class="card-header"><h3>Historique</h3></div>
        <div class="card-body">
            <div id="myPayslips"><em>Chargement…</em></div>
        </div>
    </div>
</div>

<script>
    (async function () {
        const payslips = await fetch('/api/payroll/payslips/my').then(r => r.json());
        const list = document.getElementById('myPayslips');
        if (!payslips.length) {
            list.innerHTML = '<div class="empty-state">Aucun bulletin pour le moment</div>';
            return;
        }

        let html = '<div style="overflow-x:auto"><table class="data-table" style="width:100%"><thead><tr><th>Période</th><th>Brut</th><th>Retenues</th><th>Net</th><th>Statut</th></tr></thead><tbody>';
        payslips.forEach(p => {
            html += `<tr>
                <td>${String(p.month).padStart(2,'0')}/${p.year}</td>
                <td class="right">${parseFloat(p.gross_amount).toLocaleString('fr-FR', {minimumFractionDigits:2})}</td>
                <td class="right">${parseFloat(p.total_deductions).toLocaleString('fr-FR', {minimumFractionDigits:2})}</td>
                <td class="right">${parseFloat(p.net_amount).toLocaleString('fr-FR', {minimumFractionDigits:2})}</td>
                <td>${p.status}</td>
            </tr>`;
        });
        html += '</tbody></table></div>';
        list.innerHTML = html;
    })();
</script>
