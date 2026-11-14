<!-- Payroll Contracts — CRUD des contrats de paie -->
<div id="page-payroll" class="page <?= $page == 'payroll' ? 'active' : '' ?>">
    <div class="page-header">
        <h2>Contrats de paie</h2>
        <p class="page-subtitle">
            Le contrat définit <strong>qui est payé</strong> et <strong>combien</strong>.
            Un agent sans contrat actif n'est pas inclus dans le calcul des bulletins.
        </p>
    </div>

    <!-- Agents sans contrat : à treat en priorité -->
    <div class="card" style="margin-bottom: 1.5rem;" id="missingCard" hidden>
        <div class="card-header"><h3>Agents sans contrat</h3></div>
        <div class="card-body">
            <div id="missingList"></div>
        </div>
    </div>

    <!-- Import du récap Excel -->
    <div class="card" style="margin-bottom: 1.5rem;">
        <div class="card-header"><h3>Importer un récap de paie (Excel)</h3></div>
        <div class="card-body">
            <p style="margin:0 0 .75rem; font-size:.85rem; color:var(--muted);">
                Le récap Excel du comptable contient, pour chaque agent, son salaire, son sursalaire,
                sa date d'embauche, sa charge fiscale, son taux d'IER… L'import met à jour les
                fiches Paie, les contrats, les primes et les présences de la période indiquée
                dans le fichier.
                <br>
                <strong>Analyse d'abord, import ensuite</strong> : la simulation n'écrit rien et affiche
                ce qui serait importé.
            </p>

            <form id="recapForm" style="display:flex; gap:.75rem; align-items:center; flex-wrap:wrap;">
                <input type="file" id="recapFile" accept=".xlsx,.xlsm" class="form-control" style="max-width:340px" required />
                <button type="submit" class="btn btn-secondary" id="recapPreviewBtn">Analyser</button>
                <button type="button" class="btn btn-primary" id="recapImportBtn" disabled>Importer</button>
                <span id="recapStatus" style="font-size:.85rem; color:var(--muted);"></span>
            </form>

            <div id="recapResult" style="margin-top:1rem"></div>
        </div>
    </div>

    <div class="card" style="margin-bottom: 1.5rem;">
        <div class="card-header"><h3 id="formTitle">Créer un contrat</h3></div>
        <div class="card-body">
            <form id="contractForm" style="display: grid; grid-template-columns: repeat(auto-fit, minmax(170px, 1fr)); gap: 1rem;">
                <input type="hidden" id="contractId" />
                <div>
                    <label style="font-size:.8rem;font-weight:600">Agent *</label>
                    <select id="employeeId" class="form-control" required></select>
                </div>
                <div>
                    <label style="font-size:.8rem;font-weight:600">Type de contrat</label>
                    <select id="contractType" class="form-control">
                        <option value="CDI">CDI</option>
                        <option value="CDD">CDD</option>
                        <option value="Stage">Stage</option>
                        <option value="Journalier">Journalier</option>
                        <option value="Vacataire">Vacataire</option>
                    </select>
                </div>
                <div>
                    <label style="font-size:.8rem;font-weight:600">Salaire de base *</label>
                    <input id="baseSalary" type="number" step="0.01" min="0" class="form-control" placeholder="Ex. 500000" required />
                </div>
                <div>
                    <label style="font-size:.8rem;font-weight:600">Sursalaire</label>
                    <input id="sursalary" type="number" step="0.01" min="0" class="form-control" placeholder="0" />
                </div>
                <div>
                    <label style="font-size:.8rem;font-weight:600">Mode de paie</label>
                    <select id="payType" class="form-control">
                        <option value="monthly">Mensuel</option>
                        <option value="daily">Journalier</option>
                        <option value="hourly">Horaire</option>
                    </select>
                </div>
                <div>
                    <label style="font-size:.8rem;font-weight:600">Devise</label>
                    <input id="currency" type="text" class="form-control" value="Fc" maxlength="10" />
                </div>
                <div>
                    <label style="font-size:.8rem;font-weight:600">Début *</label>
                    <input id="startDate" type="date" class="form-control" required />
                </div>
                <div>
                    <label style="font-size:.8rem;font-weight:600">Fin</label>
                    <input id="endDate" type="date" class="form-control" />
                </div>
                <div>
                    <label style="font-size:.8rem;font-weight:600">Payé à partir de</label>
                    <input id="paidFrom" type="date" class="form-control" />
                </div>
                <div>
                    <label style="font-size:.8rem;font-weight:600">Payé jusqu'au</label>
                    <input id="paidUntil" type="date" class="form-control" />
                </div>
                <div style="display:flex; align-items:flex-end; gap:.5rem;">
                    <label style="display:flex; align-items:center; gap:.5rem; font-size:.85rem;">
                        <input type="checkbox" id="isPaid" checked /> Agent payé
                    </label>
                    <label style="display:flex; align-items:center; gap:.5rem; font-size:.85rem;">
                        <input type="checkbox" id="isActive" checked /> Actif
                    </label>
                </div>
                <div style="grid-column: 1 / -1; display:flex; gap:.5rem; flex-wrap:wrap;">
                    <button type="submit" class="btn btn-primary" id="saveBtn">Créer le contrat</button>
                    <button type="button" class="btn btn-secondary" id="cancelBtn" hidden>Annuler</button>
                </div>
            </form>
        </div>
    </div>

    <div class="card">
        <div class="card-header"><h3>Contrats enregistrés</h3></div>
        <div class="card-body">
            <div id="contractList"><em>Chargement…</em></div>
        </div>
    </div>
</div>

<script>
    const CONTRACTS_API = '/api/payroll/contracts';

    const PAY_TYPES = {
        monthly: 'Mensuel',
        daily: 'Journalier',
        hourly: 'Horaire'
    };

    let contracts = [];
    let employees = [];

    function money(v) {
        const n = parseFloat(v || 0);
        return n.toLocaleString('fr-FR', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
    }

    function esc(s) {
        return String(s ?? '').replace(/[&<>"']/g, c => ({
            '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#039;'
        }[c]));
    }

    async function loadAll() {
        [contracts, employees] = await Promise.all([
            fetch(CONTRACTS_API).then(r => r.json()),
            fetch('/api/payroll/employees').then(r => r.json())
        ]);

        renderEmployeeOptions();
        renderMissing();
        renderList();
    }

    /** Fiches Paie sans contrat actif → exclus du calcul. */
    function employeesWithoutActiveContract() {
        const withActive = contracts
            .filter(c => c.is_active && c.is_paid)
            .map(c => c.employee_id);
        return employees.filter(e => !withActive.includes(e.id));
    }

    /** Remplit le select « Agent » avec les fiches Paie SANS contrat actif. */
    function renderEmployeeOptions() {
        const select = document.getElementById('employeeId');
        const available = employeesWithoutActiveContract();

        select.innerHTML = '';
        if (!available.length) {
            const opt = document.createElement('option');
            opt.value = '';
            opt.textContent = '— Tous les agents ont un contrat —';
            select.appendChild(opt);
            select.disabled = true;
            return;
        }
        select.disabled = false;
        available.forEach(e => {
            const opt = document.createElement('option');
            opt.value = e.id;
            opt.textContent = `${e.nom_complet} (${e.matricule})`;
            select.appendChild(opt);
        });
    }

    /** Bloc « Agents sans contrat » : rappel des fiches Paie non payées. */
    function renderMissing() {
        const card = document.getElementById('missingCard');
        const list = document.getElementById('missingList');
        const missing = employeesWithoutActiveContract();

        if (!missing.length) {
            card.hidden = true;
            return;
        }

        let html = '<p style="margin-bottom:.75rem; font-size:.85rem; color:var(--muted);">'
            + 'Ces fiches Paie n\'ont pas de contrat actif : elles sont <strong>exclues du calcul</strong> '
            + 'des bulletins (aucune erreur n\'est affichée à ce stade).</p>';
        html += '<div style="overflow-x:auto"><table class="data-table" style="width:100%">'
            + '<thead><tr><th>Matricule</th><th>Nom</th><th>Statut fiche</th><th>Action</th></tr></thead><tbody>';
        missing.forEach(e => {
            html += `<tr>
                <td class="period-title" data-label="Matricule">${esc(e.matricule)}</td>
                <td data-label="Nom">${esc(e.nom_complet)}</td>
                <td data-label="Statut fiche">${esc(e.status)}</td>
                <td data-label="Action"><button class="btn btn-sm btn-primary" onclick="prefillEmployee(${e.id})">Créer un contrat</button></td>
            </tr>`;
        });
        html += '</tbody></table></div>';
        list.innerHTML = html;
        card.hidden = false;
    }

    function renderList() {
        const list = document.getElementById('contractList');
        if (!contracts.length) {
            list.innerHTML = '<div class="empty-state">Aucun contrat enregistré</div>';
            return;
        }

        let html = '<div style="overflow-x:auto"><table class="data-table" style="width:100%">'
            + '<thead><tr><th>Agent</th><th>Matricule</th><th>Type</th><th>Salaire de base</th>'
            + '<th>Sursalaire</th><th>Mode</th><th>Début</th><th>Payé</th><th>Actions</th></tr></thead><tbody>';

        contracts.forEach(c => {
            const paid = !!(c.is_active && c.is_paid);
            html += `<tr>
                <td class="employee-name" data-label="Agent">${esc(c.nom_complet)}</td>
                <td data-label="Matricule">${esc(c.matricule)}</td>
                <td data-label="Type">${esc(c.type)}</td>
                <td data-label="Salaire de base" class="left">${money(c.base_salary)}</td>
                <td data-label="Sursalaire" class="left">${money(c.sursalary)}</td>
                <td data-label="Mode">${esc(PAY_TYPES[c.pay_type] || c.pay_type)}</td>
                <td data-label="Début">${esc(c.start_date)}</td>
                <td data-label="Payé">
                    <span class="badge ${paid ? 'badge-validated' : 'badge-draft'}">${paid ? 'Oui' : 'Non'}</span>
                </td>
                <td data-label="Actions">
                    <button class="btn btn-sm btn-secondary" onclick="editContract(${c.id})">Modifier</button>
                    <button class="btn btn-sm ${paid ? 'btn-danger' : 'btn-success'}" onclick="togglePaid(${c.id})">
                        ${paid ? 'Ne plus payer' : 'Payer'}
                    </button>
                    <button class="btn btn-sm btn-danger" onclick="deleteContract(${c.id})">Supprimer</button>
                </td>
            </tr>`;
        });

        html += '</tbody></table></div>';
        list.innerHTML = html;
    }

    function prefillEmployee(employeeId) {
        document.getElementById('employeeId').value = employeeId;
        document.getElementById('startDate').value = new Date().toISOString().slice(0, 10);
        document.getElementById('baseSalary').focus();
        window.scrollTo({ top: document.getElementById('contractForm').offsetTop - 80, behavior: 'smooth' });
    }

    function editContract(id) {
        const c = contracts.find(x => x.id == id);
        if (!c) return;

        document.getElementById('contractId').value = c.id;
        document.getElementById('employeeId').value = c.employee_id;
        document.getElementById('contractType').value = c.type;
        document.getElementById('baseSalary').value = c.base_salary;
        document.getElementById('sursalary').value = c.sursalary;
        document.getElementById('payType').value = c.pay_type;
        document.getElementById('currency').value = c.currency;
        document.getElementById('startDate').value = c.start_date;
        document.getElementById('endDate').value = c.end_date || '';
        document.getElementById('paidFrom').value = c.paid_from || '';
        document.getElementById('paidUntil').value = c.paid_until || '';
        document.getElementById('isPaid').checked = !!(c.is_active && c.is_paid);
        document.getElementById('isActive').checked = !!c.is_active;

        document.getElementById('formTitle').textContent = 'Modifier le contrat — ' + c.nom_complet;
        document.getElementById('saveBtn').textContent = 'Mettre à jour';
        document.getElementById('cancelBtn').hidden = false;

        window.scrollTo({ top: document.getElementById('contractForm').offsetTop - 80, behavior: 'smooth' });
    }

    function resetForm() {
        document.getElementById('contractForm').reset();
        document.getElementById('contractId').value = '';
        document.getElementById('currency').value = 'Fc';
        document.getElementById('isPaid').checked = true;
        document.getElementById('isActive').checked = true;
        document.getElementById('formTitle').textContent = 'Créer un contrat';
        document.getElementById('saveBtn').textContent = 'Créer le contrat';
        document.getElementById('cancelBtn').hidden = true;
    }

    async function togglePaid(id) {
        const res = await fetch(CONTRACTS_API + '/toggle-paid/' + id, { method: 'POST' });
        const data = await res.json();
        if (data.success) {
            alert(data.message);
            await loadAll();
        } else {
            alert(data.error || 'Erreur');
        }
    }

    async function deleteContract(id) {
        if (!confirm('Supprimer ce contrat ?')) return;
        const res = await fetch(CONTRACTS_API + '/delete/' + id, { method: 'POST' });
        const data = await res.json();
        if (data.success) {
            await loadAll();
        } else {
            alert(data.error || 'Erreur');
        }
    }

    document.getElementById('cancelBtn').addEventListener('click', resetForm);

    document.getElementById('contractForm').addEventListener('submit', async function (e) {
        e.preventDefault();

        const id = document.getElementById('contractId').value;
        const body = {
            employee_id: document.getElementById('employeeId').value,
            type: document.getElementById('contractType').value,
            base_salary: document.getElementById('baseSalary').value,
            sursalary: document.getElementById('sursalary').value || 0,
            pay_type: document.getElementById('payType').value,
            currency: document.getElementById('currency').value || 'Fc',
            start_date: document.getElementById('startDate').value,
            end_date: document.getElementById('endDate').value || null,
            paid_from: document.getElementById('paidFrom').value || null,
            paid_until: document.getElementById('paidUntil').value || null,
            is_paid: document.getElementById('isPaid').checked ? 1 : 0,
            is_active: document.getElementById('isActive').checked ? 1 : 0,
        };

        const url = id ? CONTRACTS_API + '/update/' + id : CONTRACTS_API;
        const res = await fetch(url, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(body)
        });
        const data = await res.json();

        if (data.success) {
            alert(id ? 'Contrat mis à jour' : 'Contrat créé');
            resetForm();
            await loadAll();
        } else {
            alert(data.error || 'Erreur');
        }
    });

    // ── Import du récap Excel ─────────────────────────────────────────────
    // Deux étapes : « Analyser » (simulation, n'écrit rien) puis « Importer ».
    let recapPreview = null;

    function renderRecapResult(data) {
        const box = document.getElementById('recapResult');
        const status = document.getElementById('recapStatus');

        let html = '';

        if (data.details && data.details.length) {
            html += '<div style="overflow-x:auto"><table class="data-table" style="width:100%">'
                + '<thead><tr><th>Ligne</th><th>Agent</th><th>Matricule</th>'
                + '<th>Salaire</th><th>Sursalaire</th></tr></thead><tbody>';
            data.details.forEach(d => {
                const sal = d.salaire !== null && d.salaire !== undefined
                    ? parseFloat(d.salaire).toLocaleString('fr-FR', { minimumFractionDigits: 2 }) : '-';
                const sur = d.sursalaire !== null && d.sursalaire !== undefined
                    ? parseFloat(d.sursalaire).toLocaleString('fr-FR', { minimumFractionDigits: 2 }) : '-';
                html += `<tr>
                    <td data-label="Ligne">${d.line}</td>
                    <td class="employee-name" data-label="Agent">${esc(d.nom)}</td>
                    <td data-label="Matricule">${esc(d.matricule)}</td>
                    <td data-label="Salaire" class="right">${sal}</td>
                    <td data-label="Sursalaire" class="right">${sur}</td>
                </tr>`;
            });
            html += '</tbody></table></div>';
        } else {
            html += '<div class="empty-state">Aucune ligne appariée</div>';
        }

        if (data.errors && data.errors.length) {
            html += '<div style="margin-top:.75rem; font-size:.85rem; color:var(--danger,#ef4444)">'
                + '<strong>Lignes ignorées :</strong><ul style="margin:.35rem 0 0 1.1rem">';
            data.errors.forEach(e => { html += '<li>' + esc(e) + '</li>'; });
            html += '</ul></div>';
        }

        box.innerHTML = html;

        if (status) {
            status.textContent = data.dry_run
                ? 'Analyse : ' + data.imported + ' agent(s) à importer, ' + data.skipped + ' ignoré(s).'
                : (data.message || 'Import terminé.');
            status.style.color = data.skipped > 0 ? 'var(--warning,#f59e0b)' : 'var(--success,#16a34a)';
        }
    }

    document.getElementById('recapForm').addEventListener('submit', async function (e) {
        e.preventDefault();

        const input = document.getElementById('recapFile');
        if (!input.files || !input.files.length) {
            alert('Choisissez un fichier Excel (.xlsx).');
            return;
        }

        const fd = new FormData();
        fd.append('file', input.files[0]);

        const btn = document.getElementById('recapPreviewBtn');
        const status = document.getElementById('recapStatus');
        btn.disabled = true;
        if (status) { status.textContent = 'Analyse en cours…'; }

        try {
            const res = await fetch('/api/payroll/recap/preview', { method: 'POST', body: fd });
            const data = await res.json();
            if (!data.success) {
                alert(data.error || 'Erreur');
                if (status) { status.textContent = ''; }
                return;
            }
            recapPreview = data;
            document.getElementById('recapImportBtn').disabled = data.imported === 0;
            renderRecapResult(data);
        } catch (err) {
            alert('Erreur réseau');
            if (status) { status.textContent = ''; }
        } finally {
            btn.disabled = false;
        }
    });

    document.getElementById('recapImportBtn').addEventListener('click', async function () {
        const input = document.getElementById('recapFile');
        if (!input.files || !input.files.length) return;

        if (!confirm('Importer le récap ?\n\n'
            + 'Les fiches Paie, contrats, primes et présences des agents appariés vont être '
            + 'mis à jour avec les données du fichier.')) return;

        const fd = new FormData();
        fd.append('file', input.files[0]);

        const btn = document.getElementById('recapImportBtn');
        const status = document.getElementById('recapStatus');
        btn.disabled = true;
        if (status) { status.textContent = 'Import en cours…'; }

        try {
            const res = await fetch('/api/payroll/recap/import', { method: 'POST', body: fd });
            const data = await res.json();
            if (!data.success) {
                alert(data.error || 'Erreur');
                return;
            }
            renderRecapResult(data);
            await loadAll();
        } catch (err) {
            alert('Erreur réseau');
        } finally {
            btn.disabled = false;
        }
    });

    loadAll();



</script>
