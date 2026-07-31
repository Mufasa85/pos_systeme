<!-- Payroll Payments -->
<div id="page-payroll" class="page <?= $page == 'payroll' ? 'active' : '' ?>">
    <div class="page-header">
        <h2>Paiements</h2>
    </div>

    <div class="card" style="margin-bottom: 1.5rem;">
        <div class="card-header"><h3>Bulletin à payer</h3></div>
        <div class="card-body">
            <form id="paymentForm" style="display: grid; grid-template-columns: repeat(auto-fit, minmax(150px, 1fr)); gap: 1rem;">
                <select id="payslipSelect" class="form-control" required></select>
                <input id="amount" type="number" step="0.01" class="form-control" placeholder="Montant" required />
                <input id="paidAt" type="date" class="form-control" required />
                <input id="reference" class="form-control" placeholder="Référence" />
                <button type="submit" class="btn btn-primary">Enregistrer le paiement</button>
            </form>
        </div>
    </div>

    <div class="card">
        <div class="card-header"><h3>Bulletins en attente</h3></div>
        <div class="card-body">
            <div id="pendingList"><em>Chargement…</em></div>
        </div>
    </div>
</div>

<script>
    (async function () {
        const periods = await fetch('/api/payroll/periods').then(r => r.json());
        const allPayslips = [];
        for (const p of periods) {
            const ps = await fetch('/api/payroll/payslips/period/' + p.id).then(r => r.json());
            ps.forEach(x => { x.period = p; });
            allPayslips.push(...ps);
        }

        const pending = allPayslips.filter(p => p.status !== 'paid');
        const select = document.getElementById('payslipSelect');
        pending.forEach(p => {
            const opt = document.createElement('option');
            opt.value = p.id;
            opt.textContent = `${p.nom_complet} — Net ${parseFloat(p.net_amount).toLocaleString('fr-FR', {minimumFractionDigits:2})} — ${String(p.period.month).padStart(2,'0')}/${p.period.year}`;
            select.appendChild(opt);
        });

        const list = document.getElementById('pendingList');
        if (!pending.length) { list.innerHTML = '<div class="empty-state">Aucun bulletin en attente</div>'; return; }

        let html = '<div style="overflow-x:auto"><table class="data-table" style="width:100%"><thead><tr><th>Nom</th><th>Période</th><th>Net</th><th>Statut</th><th>Actions</th></tr></thead><tbody>';
        pending.forEach(p => {
            html += `<tr>
                <td>${p.nom_complet}</td>
                <td>${String(p.period.month).padStart(2,'0')}/${p.period.year}</td>
                <td>${parseFloat(p.net_amount).toLocaleString('fr-FR', {minimumFractionDigits:2})}</td>
                <td>${p.status}</td>
                <td><button onclick="fillPayment(${p.id}, ${p.net_amount})" class="btn btn-sm btn-primary">Payer</button></td>
            </tr>`;
        });
        html += '</tbody></table></div>';
        list.innerHTML = html;
    })();

    function fillPayment(id, amount) {
        document.getElementById('payslipSelect').value = id;
        document.getElementById('amount').value = amount;
        document.getElementById('paidAt').value = new Date().toISOString().split('T')[0];
    }

    document.getElementById('paymentForm').addEventListener('submit', async function (e) {
        e.preventDefault();
        const body = {
            payslip_id: document.getElementById('payslipSelect').value,
            amount: document.getElementById('amount').value,
            paid_at: document.getElementById('paidAt').value,
            reference: document.getElementById('reference').value,
        };
        const res = await fetch('/api/payroll/payments', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(body)
        });
        const data = await res.json();
        if (data.success) { alert('Paiement enregistré'); location.reload(); }
        else { alert(data.error || 'Erreur'); }
    });
</script>
