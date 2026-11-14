<!-- Payroll Settings -->
<div id="page-payroll" class="page <?= $page == 'payroll' ? 'active' : '' ?>">
    <div class="page-header">
        <h2>Paramètres de paie</h2>
    </div>

    <div class="card">
        <div class="card-header">
            <div style="display:flex; gap:0.5rem; flex-wrap:wrap">
                <button class="btn btn-sm btn-primary" onclick="showTab('allowances')">Avantages</button>
                <button class="btn btn-sm btn-secondary" onclick="showTab('deductions')">Retenues</button>
                <button class="btn btn-sm btn-secondary" onclick="showTab('contributions')">Cotisations</button>
                <button class="btn btn-sm btn-secondary" onclick="showTab('seniority')">Ancienneté</button>
                <button class="btn btn-sm btn-secondary" onclick="showTab('payment-methods')">Modes paiement</button>
                <button class="btn btn-sm btn-secondary" onclick="showTab('calculation')">Calcul (modèle)</button>
            </div>
        </div>
        <div class="card-body" id="settingsBody">
            <em>Choisir un onglet</em>
        </div>
    </div>
</div>

<script>
    const settingsConfig = {
        allowances: {
            title: 'Avantages',
            endpoint: '/api/payroll/allowances',
            fields: ['code','label','calculation_type','amount','is_active'],
            types: { code:'text', label:'text', calculation_type:['fixed','percent_base'], amount:'number', is_active:['0','1'] }
        },
        deductions: {
            title: 'Retenues',
            endpoint: '/api/payroll/deductions',
            fields: ['code','label','calculation_type','amount','is_active'],
            types: { code:'text', label:'text', calculation_type:['fixed','percent_gross'], amount:'number', is_active:['0','1'] }
        },
        contributions: {
            title: 'Cotisations',
            endpoint: '/api/payroll/contributions',
            fields: ['code','label','employee_rate','employer_rate','ceiling_amount','is_active'],
            types: { code:'text', label:'text', employee_rate:'number', employer_rate:'number', ceiling_amount:'number', is_active:['0','1'] }
        },
        seniority: {
            title: 'Barème ancienneté',
            endpoint: '/api/payroll/seniority',
            // ⚠️ La colonne en base est `percent` (et non `rate`) : le JS
            // envoyait `rate`, qui était silencieusement ignoré → le taux
            // d'ancienneté restait à 0 et ne s'appliquait jamais.
            fields: ['min_years','max_years','percent','label'],
            types: { min_years:'number', max_years:'number', percent:'number', label:'text' }
        },
        'payment-methods': {
            title: 'Modes de paiement',
            endpoint: '/api/payroll/payment-methods',
            fields: ['code','label','is_active'],
            types: { code:'text', label:'text', is_active:['0','1'] }
        }
    };

    let currentTab = '';

    // ── Onglet « Calcul (modèle) » ────────────────────────────────────────
    // Ce bloc n'utilise pas la mécanique `settingsConfig` (générique CRUD) :
    // les paramètres de calcul sont un jeu de clés fixes, groupées par thème.
    const CALC_FIELDS = [
        { group: 'Modèle de calcul', rows: [
            { key: 'calculation_mode', label: 'Mode de calcul', type: 'select', options: [
                ['standard', 'Standard (générique)'],
                ['excel_rdc', 'Excel / RDC (barème IRT, LOG, TRANS…)'],
            ]},
            { key: 'country', label: 'Pays / zone', type: 'text' },
            { key: 'currency', label: 'Devise de la paie', type: 'text' },
            { key: 'default_working_days', label: 'Jours ouvrables par défaut', type: 'number' },
            { key: 'prorate_base', label: 'Proratiser le salaire de base sur les jours payés', type: 'bool' },
        ]},
        { group: 'Ancienneté', rows: [
            { key: 'seniority_rate_per_year', label: '% du salaire de base PAR ANNÉE', type: 'number' },
            { key: 'seniority_max_years', label: 'Plafond d’ancienneté (années, 0 = illimité)', type: 'number' },
        ]},
        { group: 'Gains calculés', rows: [
            { key: 'housing_rate', label: 'LOG : % de la masse salariale', type: 'number' },
            { key: 'transport_rate', label: 'TRANS : % du LOG', type: 'number' },
        ]},
        { group: 'Cotisations et impôt', rows: [
            { key: 'cnss_employee_rate', label: 'CNSS salarié (%)', type: 'number' },
            { key: 'cnss_employer_rate', label: 'CNSS employeur (%) — compté 2 fois', type: 'number' },
            { key: 'inpp_employer_rate', label: 'INPP employeur (%)', type: 'number' },
            { key: 'onem_employer_rate', label: 'ONEM employeur (%)', type: 'number' },
            { key: 'ier_employer_rate', label: 'IER employeur (%) — taux de référence', type: 'number' },
            { key: 'irt_enabled', label: 'Appliquer le barème IRT', type: 'bool' },
            { key: 'tax_dependents_allowance', label: 'Réduction IRT : % par personne à charge', type: 'number' },
            { key: 'exchange_rate', label: 'Taux de change (devise forte → devise locale)', type: 'number' },
            { key: 'exchange_rate_source', label: 'Origine du taux de change', type: 'select', options: [
                ['api', 'API OSAT (taux du jour)'],
                ['fixed', 'Taux fixe paramétré ci-dessus'],
            ]},
        ]},
    ];

    async function loadCalculationTab() {
        const body = document.getElementById('settingsBody');
        const res = await fetch('/api/payroll/settings-params').then(r => r.json());
        const values = res.values || {};

        let html = '<h3>Paramètres du modèle de calcul</h3>';
        html += '<p style="font-size:.85rem;color:var(--muted);margin-bottom:1rem">'
            + 'Ces paramètres pilotent le calcul des bulletins. Le mode <strong>Excel / RDC</strong> '
            + 'reproduit le modèle du classeur de paie (barème IRT à 5 tranches, logement 30 %, '
            + 'transport 21,15 % du LOG, ancienneté 3 % par année). '
            + 'Le taux d’IER reste saisi <strong>agent par agent</strong> (fiche de l’employé).</p>';

        html += '<form id="calcForm" style="display:flex;flex-direction:column;gap:1.25rem">';
        CALC_FIELDS.forEach(group => {
            html += '<fieldset style="border:1px solid var(--border,#e2e8f0);border-radius:10px;padding:1rem">';
            html += '<legend style="font-size:.8rem;font-weight:700;padding:0 .4rem">' + group.group + '</legend>';
            html += '<div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(260px,1fr));gap:1rem">';
            group.rows.forEach(f => {
                const v = values[f.key] ?? '';
                html += '<div><label style="font-size:.8rem;font-weight:600">' + f.label + '</label>';
                if (f.type === 'select') {
                    html += '<select id="calc_' + f.key + '" class="form-control">';
                    f.options.forEach(o => {
                        const sel = (String(v) === String(o[0])) ? ' selected' : '';
                        html += '<option value="' + o[0] + '"' + sel + '>' + o[1] + '</option>';
                    });
                    html += '</select>';
                } else if (f.type === 'bool') {
                    const on = ['1','true','yes','on'].includes(String(v).toLowerCase());
                    html += '<label style="display:flex;align-items:center;gap:.5rem;font-weight:400">'
                        + '<input type="checkbox" id="calc_' + f.key + '"' + (on ? ' checked' : '') + '> '
                        + (on ? 'Activé' : 'Désactivé') + '</label>';
                } else {
                    html += '<input type="number" step="any" id="calc_' + f.key + '" class="form-control" value="' + v + '">';
                }
                html += '</div>';
            });
            html += '</div></fieldset>';
        });
        html += '<div><button type="submit" class="btn btn-primary">Enregistrer les paramètres</button></div>';
        html += '</form>';
        body.innerHTML = html;

        document.getElementById('calcForm').addEventListener('submit', async function (e) {
            e.preventDefault();
            const payload = {};
            CALC_FIELDS.forEach(group => group.rows.forEach(f => {
                const el = document.getElementById('calc_' + f.key);
                if (!el) return;
                payload[f.key] = (f.type === 'bool') ? (el.checked ? '1' : '0') : el.value;
            }));

            const r = await fetch('/api/payroll/settings-params', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ values: payload })
            });
            const d = await r.json();
            if (d.success) {
                alert('Paramètres de calcul enregistrés');
                loadCalculationTab();
            } else {
                alert(d.error || 'Erreur');
            }
        });
    }

    function showTab(tab) {
        currentTab = tab;
        loadTab();
    }

    async function loadTab() {
        // Onglet « Calcul (modèle) » : traitement spécifique (hors CRUD générique).
        if (currentTab === 'calculation') {
            await loadCalculationTab();
            return;
        }

        const config = settingsConfig[currentTab];
        const body = document.getElementById('settingsBody');
        const items = await fetch(config.endpoint).then(r => r.json());

        let html = '<h3>' + config.title + '</h3>';
        html += '<form id="addForm" onsubmit="saveItem(event)" style="display:grid; grid-template-columns: repeat(auto-fit, minmax(120px, 1fr)); gap:1rem; margin-bottom:1rem;">';
        html += '<input type="hidden" id="editId" />';
        config.fields.forEach(f => {
            const type = config.types[f];
            if (Array.isArray(type)) {
                html += '<select id="' + f + '" class="form-control" required>';
                type.forEach(v => html += '<option value="' + v + '">' + v + '</option>');
                html += '</select>';
            } else {
                html += '<input id="' + f + '" type="' + (type === 'number' ? 'number' : 'text') + '" step="any" class="form-control" placeholder="' + f + '" required />';
            }
        });
        html += '<button type="submit" class="btn btn-primary" id="saveBtn">Ajouter</button>';
        html += '<button type="button" class="btn btn-secondary" onclick="resetForm()" id="resetBtn" hidden>Annuler</button>';
        html += '</form>';

        if (!items.length) {
            html += '<div class="empty-state">Aucune ligne</div>';
            body.innerHTML = html;
            return;
        }

        html += '<div style="overflow-x:auto"><table class="data-table" style="width:100%"><thead><tr>';
        config.fields.forEach(f => html += '<th>' + f + '</th>');
        html += '<th>Actions</th></tr></thead><tbody>';
        items.forEach(i => {
            html += '<tr>';
            config.fields.forEach(f => { html += '<td>' + (i[f] ?? '') + '</td>'; });
            html += '<td>';
            html += '<button onclick="editItem(' + i.id + ')" class="btn btn-sm btn-secondary">Modifier</button> ';
            html += '<button onclick="deleteItem(' + i.id + ')" class="btn btn-sm btn-danger">Supprimer</button>';
            html += '</td></tr>';
        });
        html += '</tbody></table></div>';
        body.innerHTML = html;
    }

    async function saveItem(e) {
        e.preventDefault();
        const config = settingsConfig[currentTab];
        const id = document.getElementById('editId').value;
        const body = {};
        config.fields.forEach(f => { body[f] = document.getElementById(f).value; });

        const url = id ? config.endpoint + '/update/' + id : config.endpoint;
        const res = await fetch(url, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(body)
        });
        const data = await res.json();
        if (data.success) { resetForm(); loadTab(); }
        else { alert(data.error || 'Erreur'); }
    }

    async function deleteItem(id) {
        if (!confirm('Supprimer ?')) return;
        const config = settingsConfig[currentTab];
        const res = await fetch(config.endpoint + '/delete/' + id, { method: 'POST' });
        const data = await res.json();
        if (data.success) { loadTab(); }
        else { alert(data.error || 'Erreur'); }
    }

    async function editItem(id) {
        const config = settingsConfig[currentTab];
        const items = await fetch(config.endpoint).then(r => r.json());
        const item = items.find(i => i.id == id);
        if (!item) return;
        document.getElementById('editId').value = id;
        config.fields.forEach(f => { const el = document.getElementById(f); if (el) el.value = item[f] ?? ''; });
        document.getElementById('saveBtn').textContent = 'Mettre à jour';
        document.getElementById('resetBtn').hidden = false;
    }

    function resetForm() {
        document.getElementById('editId').value = '';
        const config = settingsConfig[currentTab];
        config.fields.forEach(f => { const el = document.getElementById(f); if (el) el.value = ''; });
        document.getElementById('saveBtn').textContent = 'Ajouter';
        document.getElementById('resetBtn').hidden = true;
    }

    showTab('allowances');
</script>
