<!-- Payroll Timeclock -->
<div id="page-payroll" class="page <?= $page == 'payroll' ? 'active' : '' ?>">
    <div class="page-header">
        <h2>Pointage</h2>
    </div>

    <div class="card" style="margin-bottom: 1.5rem;">
        <div class="card-header"><h3>Importer un fichier CSV</h3></div>
        <div class="card-body">
            <form id="importForm" enctype="multipart/form-data">
                <input type="file" id="csvFile" accept=".csv,.txt" class="form-control" required />
                <button type="submit" class="btn btn-primary" style="margin-top:0.5rem">Importer</button>
            </form>
            <div id="importResult" style="margin-top:1rem"></div>
        </div>
    </div>

    <div class="card" style="margin-bottom: 1.5rem;">
        <div class="card-header"><h3>Pointage manuel</h3></div>
        <div class="card-body">
            <form id="manualForm" style="display: grid; grid-template-columns: repeat(auto-fit, minmax(150px, 1fr)); gap: 1rem;">
                <select id="tcEmployee" class="form-control" required></select>
                <select id="tcType" class="form-control" required>
                    <option value="IN">Entrée</option>
                    <option value="OUT">Sortie</option>
                    <option value="BREAK_START">Début pause</option>
                    <option value="BREAK_END">Fin pause</option>
                </select>
                <input id="tcDate" type="datetime-local" class="form-control" required />
                <button type="submit" class="btn btn-secondary">Ajouter</button>
            </form>
        </div>
    </div>

    <div class="card">
        <div class="card-header"><h3>Sélectionner une période</h3></div>
        <div class="card-body">
            <select id="periodSelect" class="form-control" onchange="loadTimeClock()">
                <option value="">Choisir une période</option>
            </select>
            <div id="eventsList" style="margin-top:1rem"><em>Sélectionnez une période</em></div>
        </div>
    </div>
</div>

<script>
    (async function () {
        const [periods, employees] = await Promise.all([
            fetch('/api/payroll/periods').then(r => r.json()),
            fetch('/api/payroll/employees').then(r => r.json())
        ]);

        const pSelect = document.getElementById('periodSelect');
        periods.forEach(p => {
            const opt = document.createElement('option');
            opt.value = p.id;
            opt.textContent = `${String(p.month).padStart(2,'0')}/${p.year} — ${p.status}`;
            pSelect.appendChild(opt);
        });

        const eSelect = document.getElementById('tcEmployee');
        employees.forEach(e => {
            const opt = document.createElement('option');
            opt.value = e.id;
            opt.textContent = e.nom_complet;
            eSelect.appendChild(opt);
        });
    })();

    async function loadTimeClock() {
        const id = document.getElementById('periodSelect').value;
        const list = document.getElementById('eventsList');
        if (!id) { list.innerHTML = '<em>Sélectionnez une période</em>'; return; }

        const events = await fetch('/api/payroll/timeclock/period/' + id).then(r => r.json());
        if (!events.length) { list.innerHTML = '<div class="empty-state">Aucun événement</div>'; return; }

        let html = '<div style="overflow-x:auto"><table class="data-table" style="width:100%"><thead><tr><th>Employé</th><th>Type</th><th>Date/Heure</th><th>Source</th></tr></thead><tbody>';
        events.forEach(ev => {
            html += `<tr><td>${ev.nom_complet}</td><td>${ev.event_type}</td><td>${ev.event_at}</td><td>${ev.source}</td></tr>`;
        });
        html += '</tbody></table></div>';
        list.innerHTML = html;
    }

    document.getElementById('manualForm').addEventListener('submit', async function (e) {
        e.preventDefault();
        const body = {
            employee_id: document.getElementById('tcEmployee').value,
            event_type: document.getElementById('tcType').value,
            event_at: document.getElementById('tcDate').value,
        };
        const res = await fetch('/api/payroll/timeclock', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(body)
        });
        const data = await res.json();
        if (data.success) { alert('Pointage ajouté'); document.getElementById('tcDate').value = ''; loadTimeClock(); }
        else { alert(data.error || 'Erreur'); }
    });

    document.getElementById('importForm').addEventListener('submit', async function (e) {
        e.preventDefault();
        const fd = new FormData();
        fd.append('file', document.getElementById('csvFile').files[0]);
        const res = await fetch('/api/payroll/timeclock/import', { method: 'POST', body: fd });
        const data = await res.json();
        if (data.success) {
            document.getElementById('importResult').textContent = `Import : ${data.import.rows_ok} OK, ${data.import.rows_skipped} ignorés, ${data.import.rows_error} erreurs`;
        } else {
            document.getElementById('importResult').textContent = data.error || 'Erreur';
        }
    });
</script>
