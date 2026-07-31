<!-- Payroll Attendance -->
<div id="page-payroll" class="page <?= $page == 'payroll' ? 'active' : '' ?>">
    <div class="page-header">
        <h2>Présences & pointage</h2>
    </div>

    <div class="card" style="margin-bottom: 1.5rem;">
        <div class="card-header"><h3>Sélectionner une période</h3></div>
        <div class="card-body">
            <select id="periodSelect" class="form-control" onchange="loadAttendance()">
                <option value="">Choisir une période</option>
            </select>
        </div>
    </div>

    <div class="card" style="margin-bottom: 1.5rem;">
        <div class="card-header"><h3>Grille de présence</h3></div>
        <div class="card-body">
            <form id="attendanceForm" class="attendance-form">
                <div id="attendanceGrid"><em>Sélectionnez une période</em></div>
                <div class="form-actions"><button type="submit" class="btn btn-primary" id="saveAttendance" hidden>Enregistrer les présences</button></div>
            </form>
        </div>
    </div>

    <div class="card" style="margin-bottom: 1.5rem;">
        <div class="card-header"><h3>Ajouter une absence</h3></div>
        <div class="card-body">
            <form id="absenceForm" style="display: grid; grid-template-columns: repeat(auto-fit, minmax(150px, 1fr)); gap: 1rem;">
                <select id="absEmployee" class="form-control" required></select>
                <select id="absType" class="form-control" required>
                    <option value="unjustified">Injustifiée</option>
                    <option value="paid_leave">Congés payés</option>
                    <option value="sick">Maladie</option>
                    <option value="unpaid">Non payée</option>
                    <option value="other">Autre</option>
                </select>
                <input id="absStart" type="date" class="form-control" required />
                <input id="absEnd" type="date" class="form-control" required />
                <input id="absDays" type="number" step="0.01" class="form-control" placeholder="Jours" required />
                <label style="display:flex; align-items:center; gap:0.5rem"><input id="absPaid" type="checkbox" value="1" /> Payée</label>
                <button type="submit" class="btn btn-secondary">Ajouter</button>
            </form>
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
            loadAttendance();
        }

        const emps = await fetch('/api/payroll/employees').then(r => r.json());
        const absEmp = document.getElementById('absEmployee');
        emps.forEach(e => {
            const opt = document.createElement('option');
            opt.value = e.id;
            opt.textContent = e.nom_complet + ' (' + e.matricule + ')';
            absEmp.appendChild(opt);
        });
    })();

    async function loadAttendance() {
        const periodId = document.getElementById('periodSelect').value;
        const grid = document.getElementById('attendanceGrid');
        const saveBtn = document.getElementById('saveAttendance');
        if (!periodId) { grid.innerHTML = '<em>Sélectionnez une période</em>'; saveBtn.hidden = true; return; }

        const data = await fetch('/api/payroll/attendance/period/' + periodId).then(r => r.json());
        const employees = await fetch('/api/payroll/employees').then(r => r.json());

        if (!employees.length) {
            grid.innerHTML = '<div class="empty-state">Aucun employé. Créez des fiches employés d\'abord.</div>';
            saveBtn.hidden = true;
            return;
        }

        let html = '<div class="attendance-table-wrap" style="overflow-x:auto"><table class="data-table attendance-table" style="width:100%"><thead><tr><th>Employé</th><th>Jours travaillés</th><th>Heures</th><th>Jours payés</th><th>Jours attendus</th><th>Notes</th></tr></thead><tbody>';
        employees.forEach(e => {
            const a = data.attendance.find(x => x.employee_id == e.id) || {};
            html += `<tr>
                <td class="employee-name">${e.nom_complet}</td>
                <td><input type="number" step="0.01" name="worked_days[${e.id}]" value="${a.worked_days || ''}" class="form-control right" /></td>
                <td><input type="number" step="0.01" name="worked_hours[${e.id}]" value="${a.worked_hours || ''}" class="form-control right" /></td>
                <td><input type="number" step="0.01" name="paid_days[${e.id}]" value="${a.paid_days || ''}" class="form-control right" /></td>
                <td><input type="number" step="0.01" name="expected[${e.id}]" value="${a.expected_working_days || ''}" class="form-control right" /></td>
                <td><input type="text" name="notes[${e.id}]" value="${a.notes || ''}" class="form-control" /></td>
            </tr>`;
        });
        html += '</tbody></table></div>';
        grid.innerHTML = html;
        saveBtn.hidden = false;
    }

    document.getElementById('attendanceForm').addEventListener('submit', async function (e) {
        e.preventDefault();
        const periodId = document.getElementById('periodSelect').value;
        const rows = [];
        const inputs = document.querySelectorAll('#attendanceGrid input');
        const byEmp = {};
        inputs.forEach(inp => {
            const m = inp.name.match(/^(\w+)\[(\d+)\]$/);
            if (!m) return;
            const [_, field, empId] = m;
            if (!byEmp[empId]) byEmp[empId] = { employee_id: empId, payroll_period_id: periodId };
            byEmp[empId][field === 'expected' ? 'expected_working_days' : field] = inp.value;
        });
        Object.values(byEmp).forEach(r => rows.push(r));

        const res = await fetch('/api/payroll/attendance/bulk', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ attendance: rows })
        });
        const data = await res.json();
        if (data.success) { alert(data.saved + ' lignes enregistrées'); }
        else { alert(data.error || 'Erreur'); }
    });

    document.getElementById('absenceForm').addEventListener('submit', async function (e) {
        e.preventDefault();
        const periodId = document.getElementById('periodSelect').value;
        const body = {
            payroll_period_id: periodId,
            employee_id: document.getElementById('absEmployee').value,
            type: document.getElementById('absType').value,
            start_date: document.getElementById('absStart').value,
            end_date: document.getElementById('absEnd').value,
            days: document.getElementById('absDays').value,
            is_paid: document.getElementById('absPaid').checked ? 1 : 0,
        };
        const res = await fetch('/api/payroll/absence', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(body)
        });
        const data = await res.json();
        if (data.success) { alert('Absence ajoutée'); loadAttendance(); }
        else { alert(data.error || 'Erreur'); }
    });
</script>
