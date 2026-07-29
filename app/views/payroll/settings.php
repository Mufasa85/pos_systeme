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
            fields: ['min_years','max_years','rate'],
            types: { min_years:'number', max_years:'number', rate:'number' }
        },
        'payment-methods': {
            title: 'Modes de paiement',
            endpoint: '/api/payroll/payment-methods',
            fields: ['code','label','is_active'],
            types: { code:'text', label:'text', is_active:['0','1'] }
        }
    };

    let currentTab = '';

    function showTab(tab) {
        currentTab = tab;
        loadTab();
    }

    async function loadTab() {
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
