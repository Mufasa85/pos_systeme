<!-- Payroll Employees -->
<div id="page-payroll" class="page <?= $page == 'payroll' ? 'active' : '' ?>">
    <div class="page-header">
        <h2>Employés Paie</h2>
    </div>

    <div class="card" style="margin-bottom: 1.5rem;">
        <div class="card-header"><h3>Transformer un vendeur en employé</h3></div>
        <div class="card-body">
            <form id="payrollEmployeeForm" class="form-row" style="display: grid; grid-template-columns: repeat(auto-fit, minmax(180px, 1fr)); gap: 1rem;">
                <select id="vendorSelect" class="form-control" required>
                    <option value="">Choisir un vendeur</option>
                </select>
                <input id="matricule" class="form-control" placeholder="Matricule" required />
                <input id="hireDate" type="date" class="form-control" required />
                <button type="submit" class="btn btn-primary">Créer la fiche</button>
            </form>
        </div>
    </div>

    <div class="card">
        <div class="card-header"><h3>Liste des employés</h3></div>
        <div class="card-body">
            <div id="employeeList"><em>Chargement…</em></div>
        </div>
    </div>
</div>

<script>
    (async function () {
        const [emps, vendors] = await Promise.all([
            fetch('/api/payroll/employees').then(r => r.json()),
            fetch('/api/payroll/employees/vendors').then(r => r.json())
        ]);

        const select = document.getElementById('vendorSelect');
        vendors.forEach(v => {
            const opt = document.createElement('option');
            opt.value = v.id;
            opt.textContent = v.nom_complet + (v.email ? ' — ' + v.email : '');
            select.appendChild(opt);
        });

        const list = document.getElementById('employeeList');
        if (!emps.length) {
            list.innerHTML = '<div class="empty-state">Aucun employé enregistré</div>';
            return;
        }

        let html = '<div style="overflow-x:auto"><table class="data-table" style="width:100%">';
        html += '<thead><tr><th>Matricule</th><th>Nom</th><th>Email</th><th>Téléphone</th><th>Statut</th><th>Actions</th></tr></thead><tbody>';
        emps.forEach(e => {
            html += `<tr>
                <td>${e.matricule}</td>
                <td>${e.nom_complet}</td>
                <td>${e.email || '-'}</td>
                <td>${e.telephone || '-'}</td>
                <td>${e.status}</td>
                <td><a href="/payroll/employee_form?id=${e.id}" class="btn btn-sm btn-secondary">Modifier</a></td>
            </tr>`;
        });
        html += '</tbody></table></div>';
        list.innerHTML = html;
    })();

    document.getElementById('payrollEmployeeForm').addEventListener('submit', async function (e) {
        e.preventDefault();
        const body = {
            user_id: document.getElementById('vendorSelect').value,
            matricule: document.getElementById('matricule').value,
            hire_date: document.getElementById('hireDate').value,
        };

        const res = await fetch('/api/payroll/employees', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(body)
        });
        const data = await res.json();
        if (data.success) {
            alert('Fiche employé créée');
            location.reload();
        } else {
            alert(data.error || 'Erreur');
        }
    });
</script>
