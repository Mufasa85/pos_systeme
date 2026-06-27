<!-- Taxes Page -->
<div id="page-taxes" class="page <?= $page == 'taxes' ? 'active' : '' ?>">
  <div class="page-header" style="margin: 20px;">
    <h2>
      <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="margin-right: 10px; vertical-align: middle;">
        <line x1="19" y1="5" x2="5" y2="19"></line>
        <circle cx="6.5" cy="6.5" r="2.5"></circle>
        <circle cx="17.5" cy="17.5" r="2.5"></circle>
      </svg>
      Gestion des taxes
    </h2>
    <?php if (isset($_SESSION['role']) && $_SESSION['role'] === 'admin'): ?>
      <button id="add-tax-btn" class="btn btn-primary" onclick="openTaxModal()">
        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
          <line x1="12" y1="5" x2="12" y2="19"></line>
          <line x1="5" y1="12" x2="19" y2="12"></line>
        </svg>
        Ajouter
      </button>
    <?php endif; ?>
  </div>

  <div class="filters-bar" style="margin: 20px;">
    <input type="text" id="taxes-filter" placeholder="Rechercher une taxe (code, étiquette...)" style="width: 100%; max-width: 400px; padding: 0.625rem 0.75rem; border: 1px solid var(--border); border-radius: var(--radius); background: var(--surface); color: var(--text);">
  </div>

  <div class="table-container" style="margin: 20px; background: var(--surface); border-radius: var(--radius); border: 1px solid var(--border); overflow: hidden;">
    <table class="data-table" style="width: 100%; border-collapse: collapse; text-align: left;">
      <thead>
        <tr style="border-bottom: 2px solid var(--border); background: var(--background);">
          <th style="padding: 1rem 0.75rem; font-weight: 600;">Code Groupe</th>
          <th style="padding: 1rem 0.75rem; font-weight: 600;">Étiquette</th>
          <th style="padding: 1rem 0.75rem; font-weight: 600;">Taux</th>
          <th style="padding: 1rem 0.75rem; font-weight: 600;">Description</th>
          <th style="padding: 1rem 0.75rem; font-weight: 600;">Type</th>
          <th style="padding: 1rem 0.75rem; font-weight: 600; text-align: right;" class="admin-only">Actions</th>
        </tr>
      </thead>
      <tbody id="taxes-table-body">
        <?php if (!empty($taxes)): ?>
          <?php foreach ($taxes as $t): ?>
            <?php $isSystem = ((int)$t['id'] <= 16); ?>
            <tr class="tax-row" data-groupe="<?= htmlspecialchars($t['groupe_taxe']) ?>" data-etiquette="<?= htmlspecialchars($t['etiquette']) ?>" style="border-bottom: 1px solid var(--border); transition: background-color 0.2s;">
              <td style="padding: 1rem 0.75rem;">
                <code style="font-family: 'JetBrains Mono', monospace; font-weight: 600; padding: 2px 6px; background: var(--background); border-radius: 4px; font-size: 0.85rem;">
                  <?= htmlspecialchars($t['groupe_taxe']) ?>
                </code>
              </td>
              <td style="padding: 1rem 0.75rem;"><strong><?= htmlspecialchars($t['etiquette']) ?></strong></td>
              <td style="padding: 1rem 0.75rem;">
                <span style="font-weight: 600; color: var(--primary); font-size: 1rem;"><?= number_format($t['taux'], 2) ?> %</span>
              </td>
              <td style="padding: 1rem 0.75rem; color: var(--muted); font-size: 0.9rem;">
                <?= htmlspecialchars($t['description'] ?? '') ?>
              </td>
              <td style="padding: 1rem 0.75rem;">
                <?php if ($isSystem): ?>
                  <span class="badge" style="background-color: var(--border); color: var(--text); padding: 4px 8px; border-radius: 9999px; font-size: 0.75rem; font-weight: 500;">Système</span>
                <?php else: ?>
                  <span class="badge" style="background-color: rgba(16, 185, 129, 0.15); color: #10B981; padding: 4px 8px; border-radius: 9999px; font-size: 0.75rem; font-weight: 500;">Personnalisée</span>
                <?php endif; ?>
              </td>
              <?php if (isset($_SESSION['role']) && $_SESSION['role'] === 'admin'): ?>
                <td style="padding: 1rem 0.75rem; text-align: right;">
                  <?php if (!$isSystem): ?>
                    <button class="btn btn-ghost btn-small" onclick="editTax(<?= htmlspecialchars(json_encode($t)) ?>)" title="Modifier" style="padding: 4px; color: var(--text); background: transparent; border: none; cursor: pointer; border-radius: 4px; transition: background-color 0.2s;">
                      <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <polygon points="16 3 21 8 8 21 3 21 3 16 16 3"></polygon>
                      </svg>
                    </button>
                    <button class="btn btn-ghost btn-small" onclick="deleteTax(<?= $t['id'] ?>)" title="Supprimer" style="padding: 4px; color: #DC2626; background: transparent; border: none; cursor: pointer; border-radius: 4px; transition: background-color 0.2s; margin-left: 4px;">
                      <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <polyline points="3 6 5 6 21 6"></polyline>
                        <path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"></path>
                      </svg>
                    </button>
                  <?php else: ?>
                    <span style="font-size: 0.8rem; color: var(--muted); font-style: italic; padding-right: 8px;">Non modifiable</span>
                  <?php endif; ?>
                </td>
              <?php endif; ?>
            </tr>
          <?php endforeach; ?>
        <?php else: ?>
          <tr>
            <td colspan="6" style="text-align: center; padding: 2rem; color: var(--muted);">Aucune taxe trouvée</td>
          </tr>
        <?php endif; ?>
      </tbody>
    </table>
  </div>
</div>

<!-- Modal Ajouter/Modifier Taxe -->
<div id="tax-modal" class="modal" style="display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5); z-index: 1000; align-items: center; justify-content: center;">
  <div class="modal-content" style="background: white; color: #333; padding: 2rem; border-radius: var(--radius); width: 450px; max-width: 90%; border: 1px solid var(--border); box-shadow: 0 10px 25px rgba(0,0,0,0.2);">
    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.5rem;">
      <h3 id="tax-modal-title" style="margin: 0; display: inline-flex; align-items: center; gap: 8px;">
        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
          <line x1="19" y1="5" x2="5" y2="19"></line>
          <circle cx="6.5" cy="6.5" r="2.5"></circle>
          <circle cx="17.5" cy="17.5" r="2.5"></circle>
        </svg>
        Ajouter une taxe
      </h3>
      <button onclick="closeTaxModal()" style="background: none; border: none; font-size: 1.5rem; cursor: pointer; color: #333; opacity: 0.7;">&times;</button>
    </div>
    <form id="tax-form" onsubmit="saveTax(event)">
      <input type="hidden" id="tax-id" name="id" value="">
      
      <div style="margin-bottom: 1.25rem;">
        <label style="display: block; margin-bottom: 0.5rem; font-weight: 500; font-size: 0.9rem;">Code Groupe <span style="color: red;">*</span></label>
        <input type="text" id="tax-groupe" name="groupe_taxe" required placeholder="Ex: TVA16, TVARED" style="width: 100%; padding: 0.625rem; border: 1px solid var(--border); border-radius: var(--radius); background: var(--background); color: var(--text); text-transform: uppercase; font-family: 'JetBrains Mono', monospace;">
        <span style="font-size: 0.75rem; color: var(--muted); margin-top: 0.25rem; display: block;">Identifiant court unique pour la taxe (lettres/chiffres uniquement)</span>
      </div>

      <div style="margin-bottom: 1.25rem;">
        <label style="display: block; margin-bottom: 0.5rem; font-weight: 500; font-size: 0.9rem;">Étiquette / Libellé <span style="color: red;">*</span></label>
        <input type="text" id="tax-etiquette" name="etiquette" required placeholder="Ex: TVA Standard, TVA Marchés Publics" style="width: 100%; padding: 0.625rem; border: 1px solid var(--border); border-radius: var(--radius); background: var(--background); color: var(--text);">
      </div>

      <div style="margin-bottom: 1.25rem;">
        <label style="display: block; margin-bottom: 0.5rem; font-weight: 500; font-size: 0.9rem;">Taux (%) <span style="color: red;">*</span></label>
        <input type="number" id="tax-taux" name="taux" required step="0.01" min="0" max="100" placeholder="Ex: 16.00, 5.00" style="width: 100%; padding: 0.625rem; border: 1px solid var(--border); border-radius: var(--radius); background: var(--background); color: var(--text);">
      </div>

      <div style="margin-bottom: 1.5rem;">
        <label style="display: block; margin-bottom: 0.5rem; font-weight: 500; font-size: 0.9rem;">Description</label>
        <textarea id="tax-description" name="description" placeholder="Ex: Appliqué aux prestations de services..." rows="3" style="width: 100%; padding: 0.625rem; border: 1px solid var(--border); border-radius: var(--radius); background: var(--background); color: var(--text); resize: vertical; font-family: inherit; font-size: 0.9rem;"></textarea>
      </div>

      <div style="display: flex; gap: 0.75rem; justify-content: flex-end; margin-top: 1.5rem;">
        <button type="button" onclick="closeTaxModal()" class="btn btn-secondary" style="padding: 0.5rem 1rem; border-radius: var(--radius); cursor: pointer;">Annuler</button>
        <button type="submit" class="btn btn-primary" style="padding: 0.5rem 1rem; border-radius: var(--radius); cursor: pointer;">Enregistrer</button>
      </div>
    </form>
  </div>
</div>

<style>
  .tax-row:hover {
    background-color: var(--background);
  }
  .btn-ghost:hover {
    background-color: rgba(0, 0, 0, 0.05);
  }
</style>

<script>
  // Fonctions de filtrage en temps réel
  document.getElementById('taxes-filter')?.addEventListener('input', function(e) {
    const filterValue = e.target.value.toLowerCase().trim();
    const rows = document.querySelectorAll('.tax-row');
    
    rows.forEach(row => {
      const groupe = row.getAttribute('data-groupe')?.toLowerCase() || '';
      const etiquette = row.getAttribute('data-etiquette')?.toLowerCase() || '';
      if (groupe.includes(filterValue) || etiquette.includes(filterValue)) {
        row.style.display = '';
      } else {
        row.style.display = 'none';
      }
    });
  });

  // Gestion des Modals
  function openTaxModal(tax = null) {
    const modal = document.getElementById('tax-modal');
    const title = document.getElementById('tax-modal-title');
    const idField = document.getElementById('tax-id');
    const groupeField = document.getElementById('tax-groupe');
    const etiquetteField = document.getElementById('tax-etiquette');
    const tauxField = document.getElementById('tax-taux');
    const descriptionField = document.getElementById('tax-description');

    if (!modal) return;

    if (tax) {
      title.innerHTML = `
        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="margin-right: 8px; vertical-align: middle;">
          <polygon points="16 3 21 8 8 21 3 21 3 16 16 3"></polygon>
        </svg>
        Modifier la taxe
      `;
      idField.value = tax.id;
      groupeField.value = tax.groupe_taxe;
      
      // Empêcher l'édition du code groupe et du taux pour les taxes système (ID <= 16)
      if (parseInt(tax.id) <= 16) {
        groupeField.readOnly = true;
        tauxField.readOnly = true;
      } else {
        groupeField.readOnly = false;
        tauxField.readOnly = false;
      }
      etiquetteField.value = tax.etiquette;
      tauxField.value = tax.taux;
      descriptionField.value = tax.description || '';
    } else {
      title.innerHTML = `
        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="margin-right: 8px; vertical-align: middle;">
          <line x1="12" y1="5" x2="12" y2="19"></line>
          <line x1="5" y1="12" x2="19" y2="12"></line>
        </svg>
        Ajouter une taxe
      `;
      idField.value = '';
      groupeField.value = '';
      groupeField.readOnly = false;
      etiquetteField.value = '';
      tauxField.value = '';
      tauxField.readOnly = false;
      descriptionField.value = '';
    }

    modal.style.display = 'flex';
  }

  function closeTaxModal() {
    const modal = document.getElementById('tax-modal');
    if (modal) modal.style.display = 'none';
  }

  // Enregistrer (Création / Édition) via AJAX
  async function saveTax(event) {
    event.preventDefault();
    
    const id = document.getElementById('tax-id').value;
    const groupe = document.getElementById('tax-groupe').value.toUpperCase().trim();
    const etiquette = document.getElementById('tax-etiquette').value.trim();
    const taux = parseFloat(document.getElementById('tax-taux').value);
    const description = document.getElementById('tax-description').value.trim();

    if (!groupe || !etiquette || isNaN(taux)) {
      alert('Veuillez remplir tous les champs obligatoires');
      return;
    }

    const payload = {
      groupe_taxe: groupe,
      etiquette: etiquette,
      taux: taux,
      description: description
    };

    let url = APP_URL + '/api/taxes';
    if (id) {
      payload.id = parseInt(id);
      url = APP_URL + '/api/taxes/update';
    }

    try {
      const res = await fetch(url, {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json'
        },
        body: JSON.stringify(payload)
      });
      const data = await res.json();

      if (data.success) {
        alert(id ? 'Taxe modifiée avec succès !' : 'Taxe créée avec succès !');
        closeTaxModal();
        window.location.reload();
      } else {
        alert(data.error || 'Une erreur est survenue lors de l\'enregistrement');
      }
    } catch (e) {
      console.error(e);
      alert('Erreur de connexion au serveur');
    }
  }

  // Supprimer via AJAX
  async function deleteTax(id) {
    if (!id) return;

    if (!confirm('Êtes-vous sûr de vouloir supprimer définitivement cette taxe ? Cette action est irréversible.')) {
      return;
    }

    try {
      const res = await fetch(APP_URL + '/api/taxes/delete', {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json'
        },
        body: JSON.stringify({ id: parseInt(id) })
      });
      const data = await res.json();

      if (data.success) {
        alert('Taxe supprimée avec succès !');
        window.location.reload();
      } else {
        alert(data.error || 'Erreur lors de la suppression');
      }
    } catch (e) {
      console.error(e);
      alert('Erreur réseau ou serveur inaccessible');
    }
  }

  function editTax(tax) {
    openTaxModal(tax);
  }
</script>
