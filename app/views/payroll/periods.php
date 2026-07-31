<!-- Payroll Periods -->
<div id="page-payroll" class="page <?= $page == 'payroll' ? 'active' : '' ?>">
    <div class="page-header">
        <h2>Périodes de paie</h2>
    </div>

    <div class="card" style="margin-bottom: 1.5rem;">
        <div class="card-header"><h3>Ouvrir une nouvelle période</h3></div>
        <div class="card-body">
            <form id="periodForm" class="form-row" style="display: grid; grid-template-columns: repeat(auto-fit, minmax(120px, 1fr)); gap: 1rem;">
                <input id="month" type="number" min="1" max="12" class="form-control" placeholder="Mois" required />
                <input id="year" type="number" class="form-control" placeholder="Année" required />
                <input id="workingDays" type="number" step="0.01" class="form-control" value="22" placeholder="Jours ouvrables" />
                <button type="submit" class="btn btn-primary">Ouvrir</button>
            </form>
        </div>
    </div>

    <div class="card">
        <div class="card-header"><h3>Périodes</h3></div>
        <div class="card-body">
            <div id="periodList"><em>Chargement…</em></div>
        </div>
    </div>
</div>

<script>
    const monthNames = ['Janvier','Février','Mars','Avril','Mai','Juin','Juillet','Août','Septembre','Octobre','Novembre','Décembre'];

    async function load() {
        const periods = await fetch('/api/payroll/periods').then(r => r.json());
        const list = document.getElementById('periodList');
        if (!periods.length) {
            list.innerHTML = '<div class="empty-state">Aucune période</div>';
            return;
        }

        let html = '<div style="overflow-x:auto"><table class="data-table period-table" style="width:100%">';
        html += '<thead><tr><th>Mois</th><th>Année</th><th>Jours</th><th>Statut</th><th>Actions</th></tr></thead><tbody>';
        periods.forEach(p => {
            html += `<tr>
                <td class="period-title" data-label="Mois">${String(p.month).padStart(2,'0')} — ${monthNames[p.month - 1]}</td>
                <td data-label="Année">${p.year}</td>
                <td data-label="Jours">${p.working_days}</td>
                <td data-label="Statut"><span class="badge badge-${p.status}">${p.status}</span></td>
                <td data-label="Actions" class="period-actions">
                    <button onclick="calculate(${p.id})" class="btn btn-sm btn-primary">Calculer</button>
                    <a href="/payroll/attendance?period=${p.id}" class="btn btn-sm btn-secondary">Présences</a>
                    <a href="/payroll/payslips?period=${p.id}" class="btn btn-sm btn-info">Bulletins</a>
                </td>
            </tr>`;
        });
        html += '</tbody></table></div>';
        list.innerHTML = html;
    }
    load();

    document.getElementById('periodForm').addEventListener('submit', async function (e) {
        e.preventDefault();
        const body = {
            month: document.getElementById('month').value,
            year: document.getElementById('year').value,
            working_days: document.getElementById('workingDays').value || 22,
        };
        const res = await fetch('/api/payroll/periods', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(body)
        });
        const data = await res.json();
        if (data.success) {
            alert('Période ouverte');
            location.reload();
        } else {
            alert(data.error || 'Erreur');
        }
    });

    async function calculate(id) {
        if (!confirm('Calculer les bulletins pour cette période ?')) return;
        const res = await fetch('/api/payroll/payslips/period/calculate/' + id, { method: 'POST' });
        const data = await res.json();
        if (data.success) {
            alert(data.count + ' bulletins calculés');
            location.reload();
        } else {
            alert(data.error || 'Erreur');
        }
    }
</script>
