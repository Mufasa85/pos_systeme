<!-- Payroll Employee Form -->
<div id="page-payroll" class="page <?= $page == 'payroll' ? 'active' : '' ?>">
    <div class="page-header">
        <h2 id="empTitle">Fiche employé</h2>
        <a href="/payroll/employees" class="btn btn-sm btn-secondary">Retour</a>
    </div>

    <div class="card">
        <div class="card-body">
            <form id="employeeForm" class="form-row" style="display: grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap: 1rem;">
                <input type="hidden" id="id" />
                <div>
                    <label>Matricule</label>
                    <input id="matricule" class="form-control" required />
                </div>
                <div>
                    <label>Date d'embauche</label>
                    <input id="hireDate" type="date" class="form-control" required />
                </div>
                <div>
                    <label>Direction</label>
                    <input id="direction" class="form-control" />
                </div>
                <div>
                    <label>Fonction</label>
                    <input id="jobTitle" class="form-control" />
                </div>
                <div>
                    <label>Taux IER employeur (%)</label>
                    <input id="ierRate" type="number" step="0.0001" class="form-control" />
                </div>
                <div>
                    <label>Nombre d'enfants</label>
                    <input id="taxDependents" type="number" class="form-control" />
                </div>
                <div>
                    <label>Statut</label>
                    <select id="status" class="form-control">
                        <option value="active">Actif</option>
                        <option value="suspended">Suspendu</option>
                        <option value="left">Parti</option>
                    </select>
                </div>
                <div style="grid-column: 1 / -1;">
                    <button type="submit" class="btn btn-primary">Enregistrer</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
    (async function () {
        const url = new URLSearchParams(location.search);
        const id = url.get('id');
        if (!id) return location.href = '/payroll/employees';

        const emp = await fetch('/api/payroll/employees/' + id).then(r => r.json());
        if (emp.error) return location.href = '/payroll/employees';

        document.getElementById('id').value = emp.id;
        document.getElementById('empTitle').textContent = 'Fiche : ' + emp.nom_complet;
        document.getElementById('matricule').value = emp.matricule;
        document.getElementById('hireDate').value = emp.hire_date;
        document.getElementById('direction').value = emp.direction || '';
        document.getElementById('jobTitle').value = emp.job_title || '';
        document.getElementById('ierRate').value = emp.ier_rate;
        document.getElementById('taxDependents').value = emp.tax_dependents;
        document.getElementById('status').value = emp.status;
    })();

    document.getElementById('employeeForm').addEventListener('submit', async function (e) {
        e.preventDefault();
        const id = document.getElementById('id').value;
        const body = {
            matricule: document.getElementById('matricule').value,
            hire_date: document.getElementById('hireDate').value,
            direction: document.getElementById('direction').value,
            job_title: document.getElementById('jobTitle').value,
            ier_rate: document.getElementById('ierRate').value,
            tax_dependents: document.getElementById('taxDependents').value,
            status: document.getElementById('status').value,
        };
        const res = await fetch('/api/payroll/employees/update/' + id, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(body)
        });
        const data = await res.json();
        if (data.success) {
            alert('Fiche mise à jour');
            location.href = '/payroll/employees';
        } else {
            alert(data.error || 'Erreur');
        }
    });
</script>
