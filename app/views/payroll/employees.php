<!-- Payroll Employees -->
<div id="page-payroll" class="page <?= $page == 'payroll' ? 'active' : '' ?>">
    <div class="page-header">
        <h2>Employés Paie</h2>
        <a href="/payroll/contracts" class="btn btn-sm btn-secondary">Gérer les contrats & salaires</a>
    </div>

    <!--
      Bloc « Transformer un vendeur en employé ».

      ⚠️ Ce formulaire est neutralisé (2026-11-13) : `UserController::create()`
      crée AUTOMATIQUEMENT une fiche Paie pour chaque nouveau vendeur
      (`PayrollEmployee::createForVendor()`). La liste `/api/payroll/employees/vendors`
      ne renvoyait donc jamais rien — le select restait vide et le formulaire
      était mort.

      La saisie du salaire se fait désormais sur /payroll/contracts (nouvelle page),
      qui répond aux deux questions : « qui est payé ? » et « combien ? ».
    -->
    <div class="card" style="margin-bottom: 1.5rem;" id="vendorTransformCard">
        <div class="card-header"><h3>Transformer un vendeur en employé</h3></div>
        <div class="card-body">
            <p style="margin:0 0 .75rem; font-size:.9rem; color:var(--muted);">
                Chaque vendeur créé depuis <strong>Utilisateurs</strong> obtient automatiquement une fiche
                Paie. Pour définir <strong>son salaire</strong> et savoir <strong>qui est effectivement payé</strong>,
                rendez-vous sur la page des contrats.
            </p>
            <a href="/payroll/contracts" class="btn btn-primary">Ouvrir les contrats de paie</a>
            <div id="vendorTransformInfo" style="margin-top:.75rem; font-size:.85rem; color:var(--muted);"></div>
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
    async function deleteEmployee(id) {
        if (!confirm('Supprimer cette fiche employé ?\n\n'
            + 'La fiche sera supprimée. Les bulletins de paie déjà calculés sont conservés '
            + '(l\'historique de paie ne doit pas disparaître), mais la fiche ne pourra '
            + 'plus être modifiée ni recalculée.')) return;

        const res = await fetch('/api/payroll/employees/delete/' + id, { method: 'POST' });
        const data = await res.json();
        if (data.success) {
            location.reload();
        } else {
            alert(data.error || 'Erreur');
        }
    }

    (async function () {
        const [emps, contracts] = await Promise.all([
            fetch('/api/payroll/employees').then(r => r.json()),
            fetch('/api/payroll/contracts').then(r => r.json())
        ]);

        // Info contextuelle : combien de fiches n'ont pas encore de contrat ?
        const withContract = contracts.filter(c => c.is_active && c.is_paid).length;
        const missing = emps.length - withContract;
        const info = document.getElementById('vendorTransformInfo');
        if (info) {
            if (missing > 0) {
                info.innerHTML = '⚠️ <strong>' + missing + '</strong> fiche(s) sans contrat actif : '
                    + 'ces agents sont exclus du calcul des bulletins.';
            } else {
                info.textContent = '✓ Toutes les fiches ont un contrat actif.';
            }
        }

        const list = document.getElementById('employeeList');
        if (!emps.length) {
            list.innerHTML = '<div class="empty-state">Aucun employé enregistré</div>';
            return;
        }

        let html = '<div style="overflow-x:auto"><table class="data-table" style="width:100%">';
        html += '<thead><tr><th>Matricule</th><th>Nom</th><th>Email</th><th>Téléphone</th><th>Statut</th><th>Contrat</th><th>Actions</th></tr></thead><tbody>';
        emps.forEach(e => {
            const c = contracts.find(x => x.employee_id == e.id && x.is_active && x.is_paid);
            const salary = c ? new Intl.NumberFormat('fr-FR', { maximumFractionDigits: 2 }).format(parseFloat(c.base_salary)) : null;
            html += `<tr>
                <td>${e.matricule}</td>
                <td>${e.nom_complet}</td>
                <td>${e.email || '-'}</td>
                <td>${e.telephone || '-'}</td>
                <td>${e.status}</td>
                <td>${salary !== null ? salary + ' ' + (c.currency || 'Fc') : '<span style="color:var(--warning,#f59e0b)">Aucun</span>'}</td>
                <td>
                    <a href="/payroll/employee_form?id=${e.id}" class="btn btn-sm btn-secondary">Modifier</a>
                    <a href="/payroll/contracts" class="btn btn-sm btn-primary">Contrat</a>
                    <button class="btn btn-sm btn-danger" onclick="deleteEmployee(${e.id})">Supprimer</button>
                </td>
            </tr>`;
        });
        html += '</tbody></table></div>';
        list.innerHTML = html;
    })();
</script>
