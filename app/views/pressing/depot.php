<style>
  .pd-layout { display:grid; grid-template-columns:1fr; gap:1.5rem; margin-top:1.25rem; width:100% }
  .pd-section { background:var(--card,#fff); border:1px solid var(--border,#e2e8f0); border-radius:12px; padding:1.75rem }
  .pd-section h3 { margin:0 0 1.25rem; font-size:1.05rem }
  .pd-client-found { display:flex; align-items:center; justify-content:space-between; background:#f0fdf4; border:1px solid #bbf7d0; border-radius:8px; padding:.85rem 1.1rem; margin-top:1rem }
  .pd-articles-table { width:100%; border-collapse:collapse; margin-top:1rem; table-layout:fixed }
  .pd-articles-table th, .pd-articles-table td { padding:.65rem .6rem; text-align:left; font-size:.85rem; border-bottom:1px solid #f1f5f9; vertical-align:middle }
  .pd-articles-table th:nth-child(1) { width:22%; } /* Article */
  .pd-articles-table th:nth-child(2) { width:8%; }  /* Qté */
  .pd-articles-table th:nth-child(3) { width:22%; } /* État initial */
  .pd-articles-table th:nth-child(4) { width:20%; } /* Service */
  .pd-articles-table th:nth-child(5) { width:12%; } /* Prix unit. */
  .pd-articles-table th:nth-child(6) { width:12%; } /* Total */
  .pd-articles-table th:nth-child(7) { width:4%; }
  .pd-articles-table input, .pd-articles-table select { width:100%; padding:.5rem .6rem; border:1px solid #ddd; border-radius:6px; font-size:.85rem; box-sizing:border-box }
  .pd-remove-article { color:#dc2626; background:none; border:none; cursor:pointer; font-size:1rem }
  .pd-totals { margin-top:1.25rem; font-size:.92rem; max-width:400px }
  .pd-totals-row { display:flex; justify-content:space-between; padding:.4rem 0 }
  .pd-totals-row.total { font-weight:700; font-size:1.15rem; border-top:1px solid var(--border,#e2e8f0); padding-top:.6rem; margin-top:.3rem }
  .pd-totals-row em { font-style:normal; color:var(--muted,#64748b); font-weight:400; font-size:.82em; margin-left:.4rem }
  .pd-taux-box { display:flex; align-items:center; gap:.5rem; font-size:.82rem; color:var(--muted,#64748b); background:#f8fafc; border:1px solid var(--border,#e2e8f0); border-radius:8px; padding:.5rem .75rem; margin-bottom:1rem; flex-wrap:wrap }
  .pd-taux-box #pd-taux-display { font-weight:700; color:var(--primary,#0B5E88) }
  .pd-taux-edit-btn { background:none; border:none; cursor:pointer; font-size:.9rem; color:var(--primary,#0B5E88) }
  #pd-taux-edit-box input { padding:.25rem .4rem; border:1px solid #ddd; border-radius:4px; font-size:.82rem }
  .pd-recap-articles { border:1px solid var(--border,#e2e8f0); border-radius:8px; padding:.75rem 1rem; margin-bottom:1.25rem; max-height:220px; overflow-y:auto }
  .pd-recap-empty { color:var(--muted,#94a3b8); font-size:.82rem; margin:0 }
  .pd-recap-item { display:flex; justify-content:space-between; align-items:flex-start; gap:.75rem; padding:.45rem 0; border-bottom:1px solid #f1f5f9; font-size:.85rem }
  .pd-recap-item:last-child { border-bottom:none }
  .pd-recap-item-name { font-weight:600 }
  .pd-recap-item-meta { display:block; font-size:.75rem; color:var(--muted,#64748b); margin-top:.15rem }
  .pd-recap-item-amount { text-align:right; white-space:nowrap; font-weight:600 }
  .pd-recap-item-amount small { display:block; font-weight:400; color:var(--muted,#94a3b8) }
  .pd-etat-cell { position:relative }
  .pd-etat-btn { width:100%; text-align:left; background:#fff; border:1px solid #ddd; border-radius:6px; padding:.5rem .6rem; font-size:.83rem; cursor:pointer; color:#334155; overflow:hidden; text-overflow:ellipsis; white-space:nowrap }
  .pd-etat-dropdown { display:none; position:absolute; top:100%; left:0; z-index:50; background:#fff; border:1px solid #ddd; border-radius:8px; box-shadow:0 8px 20px rgba(0,0,0,.14); padding:.65rem; width:230px; max-height:240px; overflow-y:auto }
  .pd-etat-dropdown.open { display:block }
  .pd-etat-option { display:flex; align-items:center; gap:.5rem; font-size:.83rem; padding:.3rem 0; cursor:pointer }
  .pd-etat-option input { width:auto !important }
  @media (max-width:768px) {
    .pd-articles-table { table-layout:auto }
    .pd-articles-table thead { display:none }
    .pd-articles-table tbody tr { display:block; border-bottom:2px solid var(--border,#e2e8f0); padding:.75rem 0 }
    .pd-articles-table tbody td { display:block; width:100% !important; border-bottom:none; padding:.35rem 0 }
  }
</style>

<div id="page-pressing-depot" class="page <?= $page == 'pressing-depot' ? 'active' : '' ?>">
  <div class="page-header">
    <h2>
      <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="margin-right:8px;vertical-align:middle">
        <path d="M6 2v6a6 6 0 0 0 12 0V2"></path>
        <line x1="4" y1="22" x2="20" y2="22"></line>
        <path d="M6 22v-6a6 6 0 0 1 12 0v6"></path>
      </svg>
      Pressing — Nouveau dépôt
    </h2>
  </div>

  <div class="pd-layout">
    <div class="pd-section">
      <h3>1. Client</h3>
      <div style="display:flex; gap:.5rem">
        <input type="text" id="pd-client-search" placeholder="Rechercher par numéro de téléphone..." style="flex:1;padding:.5rem;border:1px solid #ddd;border-radius:4px">
        <button class="btn btn-secondary" onclick="searchPdClient()">Rechercher</button>
        <button class="btn btn-primary" onclick="openPdNewClientModal()">+ Nouveau client</button>
      </div>
      <div id="pd-client-found" class="pd-client-found" style="display:none">
        <span id="pd-client-name"></span>
        <button class="btn btn-ghost btn-small" onclick="clearPdClient()">✕</button>
      </div>
      <input type="hidden" id="pd-client-id" value="">
    </div>

    <div class="pd-section">
      <h3>2. Articles</h3>
      <table class="pd-articles-table">
        <thead>
          <tr><th>Article</th><th>Qté</th><th>État initial</th><th>Service</th><th>Prix unit.</th><th>Total</th><th></th></tr>
        </thead>
        <tbody id="pd-articles-body"></tbody>
      </table>
      <button class="btn btn-secondary" onclick="addPdArticleRow()" style="margin-top:.75rem">+ Ajouter un article</button>
    </div>

    <div class="pd-section">
      <h3>3. Récapitulatif</h3>

      <div class="pd-taux-box">
        <span>Taux du jour : 1 $ =</span>
        <span id="pd-taux-display"><?= number_format($tauxUsd, 2, ',', ' ') ?> Fc</span>
        <?php if ($isAdmin): ?>
          <button type="button" class="pd-taux-edit-btn" onclick="togglePdTauxEdit()" title="Modifier le taux">✎</button>
          <span id="pd-taux-edit-box" style="display:none">
            <input type="number" id="pd-taux-input" min="1" step="0.01" value="<?= htmlspecialchars($tauxUsd) ?>" style="width:100px">
            <button type="button" class="btn btn-secondary btn-small" onclick="savePdTaux()">OK</button>
          </span>
        <?php endif; ?>
      </div>

      <div id="pd-recap-articles" class="pd-recap-articles">
        <p class="pd-recap-empty">Aucun article ajouté</p>
      </div>

      <div class="pd-totals">
        <div class="pd-totals-row"><span>Sous-total</span><span id="pd-sous-total">0.00 Fc <em id="pd-sous-total-usd">(0.00 $)</em></span></div>
        <div class="pd-totals-row">
          <span>Remise</span>
          <input type="number" id="pd-remise" min="0" step="0.01" value="0" style="width:90px;text-align:right;padding:2px 4px;border:1px solid #ddd;border-radius:4px" oninput="updatePdTotals()">
        </div>
        <div class="pd-totals-row total"><span>Total</span><span id="pd-total">0.00 Fc <em id="pd-total-usd">(0.00 $)</em></span></div>
      </div>
      <div style="margin-top:1rem">
        <label style="display:block; margin-bottom:0.5rem; font-weight:500;">Date de retrait prévue</label>
        <input type="date" id="pd-date-prevue" style="padding:.5rem;border:1px solid #ddd;border-radius:4px">
      </div>
      <div style="margin-top:1rem">
        <label style="display:block; margin-bottom:0.5rem; font-weight:500;">Adresse de livraison</label>
        <input type="text" id="pd-adresse-livraison" placeholder="Laissez vide si retrait au pressing" style="padding:.5rem;border:1px solid #ddd;border-radius:4px;width:100%;box-sizing:border-box">
      </div>
      <div style="margin-top:1rem">
        <label style="display:block; margin-bottom:0.5rem; font-weight:500;">Date de retour (livraison)</label>
        <input type="date" id="pd-date-retour" style="padding:.5rem;border:1px solid #ddd;border-radius:4px">
      </div>
      <div id="pd-error" style="color:#e53e3e;font-size:.85rem;margin-top:.75rem;display:none"></div>
      <button class="btn btn-primary" onclick="submitPdDepot()" style="margin-top:1rem">Enregistrer le dépôt</button>
    </div>
  </div>
</div>

<!-- Modal Nouveau client -->
<div id="pd-client-modal" class="modal" style="display:none; position:fixed; top:0; left:0; width:100%; height:100%; background:rgba(0,0,0,0.5); z-index:1000; align-items:center; justify-content:center;">
  <div class="modal-content" style="background:white; padding:2rem; border-radius:8px; width:400px; max-width:90%;">
    <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:1.5rem;">
      <h3 style="margin:0;">Nouveau client</h3>
      <button type="button" onclick="closePdNewClientModal()" style="background:none; border:none; font-size:1.5rem; cursor:pointer;">&times;</button>
    </div>
    <div style="margin-bottom:1rem;">
      <label style="display:block; margin-bottom:0.5rem; font-weight:500;">Nom *</label>
      <input type="text" id="pd-new-client-nom" style="width:100%; padding:0.5rem; border:1px solid #ddd; border-radius:4px;">
    </div>
    <div style="margin-bottom:1rem;">
      <label style="display:block; margin-bottom:0.5rem; font-weight:500;">Numéro de téléphone *</label>
      <input type="text" id="pd-new-client-numero" style="width:100%; padding:0.5rem; border:1px solid #ddd; border-radius:4px;">
    </div>
    <div id="pd-new-client-error" style="color:#e53e3e;font-size:.85rem;margin-bottom:.5rem;display:none"></div>
    <div style="display:flex; gap:0.5rem; justify-content:flex-end; margin-top:1.5rem;">
      <button type="button" onclick="closePdNewClientModal()" class="btn btn-secondary">Annuler</button>
      <button type="button" class="btn btn-primary" onclick="createPdClient()">Créer</button>
    </div>
  </div>
</div>

<!-- Modal Reçu (après création) -->
<div id="pd-receipt-modal" class="modal" style="display:none; position:fixed; top:0; left:0; width:100%; height:100%; background:rgba(0,0,0,0.5); z-index:1000; align-items:center; justify-content:center;">
  <div class="modal-content" style="background:white; padding:2rem; border-radius:8px; width:380px; max-width:90%; text-align:center;">
    <h3>Dépôt enregistré</h3>
    <p style="font-size:1.4rem;font-weight:700;margin:1rem 0" id="pd-receipt-numero"></p>
    <p style="font-size:.95rem;color:var(--muted,#64748b);margin:-.5rem 0 1rem" id="pd-receipt-total"></p>
    <canvas id="pd-receipt-qr" width="150" height="150" style="margin:0 auto"></canvas>
    <div style="display:flex; gap:.5rem; justify-content:center; margin-top:1.5rem;">
      <button class="btn btn-secondary" onclick="window.location.href='/pressing/depot'">Nouveau dépôt</button>
      <button class="btn btn-primary" onclick="openPdTicket()">Voir le ticket</button>
    </div>
  </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/qrcode@1.5.3/build/qrcode.min.js"></script>
<script>
let pdArticleCount = 0;
let pdLastNumero = null;
let PD_TAUX_USD = <?= (float)$tauxUsd ?>;

function pdToUsd(fc) {
  return PD_TAUX_USD > 0 ? fc / PD_TAUX_USD : 0;
}

function togglePdTauxEdit() {
  document.getElementById('pd-taux-edit-box').style.display = 'inline-flex';
}

async function savePdTaux() {
  const val = parseFloat(document.getElementById('pd-taux-input').value);
  if (!val || val <= 0) return;
  try {
    const res = await fetch('/api/settings', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ taux_usd: val })
    });
    const data = await res.json();
    if (!res.ok || data.error) {
      alert(data.error || 'Erreur lors de la mise à jour du taux');
      return;
    }
    PD_TAUX_USD = val;
    document.getElementById('pd-taux-display').textContent = val.toLocaleString('fr-FR', { minimumFractionDigits: 2 }) + ' Fc';
    document.getElementById('pd-taux-edit-box').style.display = 'none';
    document.querySelectorAll('#pd-articles-body tr').forEach(row => updatePdRowTotal(parseInt(row.dataset.rowId, 10)));
    updatePdTotals();
  } catch (e) {
    alert('Erreur réseau');
  }
}

const PD_ARTICLES_CATALOGUE = {
  ' Vêtements': ['Chemise', 'T-shirt', 'Polo', 'Débardeur', 'Pantalon', 'Jean', 'Short', 'Jupe', 'Robe', 'Costume', 'Veste', 'Blazer', 'Manteau', 'Doudoune', 'Pull', 'Sweat-shirt', 'Gilet', 'Cravate', 'Nœud papillon', 'Écharpe', 'Châle'],
  ' Chaussures et accessoires': ['Baskets', 'Chaussures en cuir', 'Bottes', 'Casquette', 'Chapeau', 'Sac à main', 'Sac à dos', 'Ceinture', 'Gants'],
  ' Linge de maison': ['Drap', 'Drap-housse', "Taie d'oreiller", 'Housse de couette', 'Couverture', 'Couette', 'Édredon', 'Oreiller', 'Serviette', 'Peignoir', 'Nappe', 'Rideau', 'Voilage', 'Tapis (petit)', 'Housse de canapé'],
  ' Articles pour bébé': ['Body', 'Pyjama', 'Couverture bébé', 'Gigoteuse'],
  ' Articles professionnels': ['Uniforme', 'Blouse médicale', 'Combinaison de travail', 'Toge', 'Tablier', 'Tenue de cuisine'],
  ' Articles spéciaux': ['Robe de mariée', 'Robe de soirée', 'Costume traditionnel', 'Toge universitaire', 'Rideau lourd', 'Couette XXL'],
};

const PD_ETATS = [
  'Bon état', 'Sale', 'Très sale', 'Taché', 'Déchiré', 'Couture décousue',
  'Bouton manquant', 'Fermeture éclair cassée', 'Décoloré', 'Troué', 'Humide', 'Fragile'
];

const PD_SERVICES = [
  ['lavage', 'Lavage'],
  ['repassage', 'Repassage'],
  ['lavage_repassage', 'Lavage + Repassage'],
  ['nettoyage_sec', 'Nettoyage à sec'],
  ['detachage', 'Détachage'],
  ['desinfection', 'Désinfection'],
  ['blanchiment', 'Blanchiment'],
  ['anti_odeur', 'Traitement anti-odeur'],
  ['express', 'Express (24h)'],
  ['pliage', 'Pliage'],
  ['emballage_cintre', 'Emballage sur cintre'],
];

function pdArticleOptionsHtml() {
  return '<option value="">Sélectionner...</option>' + Object.entries(PD_ARTICLES_CATALOGUE).map(([cat, items]) =>
    `<optgroup label="${cat}">${items.map(i => `<option value="${i}">${i}</option>`).join('')}</optgroup>`
  ).join('');
}

function pdServiceOptionsHtml() {
  return PD_SERVICES.map(([val, label]) => `<option value="${val}">${label}</option>`).join('');
}

function pdServiceLabel(val) {
  const found = PD_SERVICES.find(([v]) => v === val);
  return found ? found[1] : val;
}

function pdEtatDropdownHtml(rowId) {
  return PD_ETATS.map((etat, i) => `
    <label class="pd-etat-option">
      <input type="checkbox" class="pd-etat-checkbox" value="${etat}" onchange="updatePdEtatLabel(${rowId})">
      ${etat}
    </label>
  `).join('');
}

function addPdArticleRow() {
  pdArticleCount++;
  const tbody = document.getElementById('pd-articles-body');
  const row = document.createElement('tr');
  row.dataset.rowId = pdArticleCount;
  row.innerHTML = `
    <td><select class="pd-art-nom" onchange="updatePdTotals()">${pdArticleOptionsHtml()}</select></td>
    <td><input type="number" class="pd-art-qte" min="1" value="1" style="width:60px" oninput="updatePdRowTotal(${pdArticleCount})"></td>
    <td class="pd-etat-cell">
      <button type="button" class="pd-etat-btn" onclick="togglePdEtatDropdown(event, ${pdArticleCount})">Sélectionner...</button>
      <div class="pd-etat-dropdown" id="pd-etat-dropdown-${pdArticleCount}">${pdEtatDropdownHtml(pdArticleCount)}</div>
    </td>
    <td>
      <select class="pd-art-service" onchange="updatePdTotals()">${pdServiceOptionsHtml()}</select>
    </td>
    <td><input type="number" class="pd-art-prix" min="0" step="0.01" value="0" style="width:80px" oninput="updatePdRowTotal(${pdArticleCount})"></td>
    <td class="pd-art-total">0.00 Fc</td>
    <td><button class="pd-remove-article" onclick="removePdArticleRow(${pdArticleCount})">✕</button></td>
  `;
  tbody.appendChild(row);
}

function togglePdEtatDropdown(event, rowId) {
  event.stopPropagation();
  const dropdown = document.getElementById(`pd-etat-dropdown-${rowId}`);
  document.querySelectorAll('.pd-etat-dropdown.open').forEach(d => {
    if (d !== dropdown) d.classList.remove('open');
  });
  dropdown.classList.toggle('open');
}

function updatePdEtatLabel(rowId) {
  const row = document.querySelector(`tr[data-row-id="${rowId}"]`);
  const checked = Array.from(row.querySelectorAll('.pd-etat-checkbox:checked')).map(cb => cb.value);
  const btn = row.querySelector('.pd-etat-btn');
  btn.textContent = checked.length ? checked.join(', ') : 'Sélectionner...';
  updatePdTotals();
}

function getPdEtatValue(row) {
  return Array.from(row.querySelectorAll('.pd-etat-checkbox:checked')).map(cb => cb.value).join(', ');
}

document.addEventListener('click', () => {
  document.querySelectorAll('.pd-etat-dropdown.open').forEach(d => d.classList.remove('open'));
});

function removePdArticleRow(rowId) {
  const row = document.querySelector(`tr[data-row-id="${rowId}"]`);
  if (row) row.remove();
  updatePdTotals();
}

function updatePdRowTotal(rowId) {
  const row = document.querySelector(`tr[data-row-id="${rowId}"]`);
  const qte = parseFloat(row.querySelector('.pd-art-qte').value) || 0;
  const prix = parseFloat(row.querySelector('.pd-art-prix').value) || 0;
  const total = qte * prix;
  row.querySelector('.pd-art-total').innerHTML = `${total.toFixed(2)} Fc<br><small style="color:#94a3b8">${pdToUsd(total).toFixed(2)} $</small>`;
  updatePdTotals();
}

function updatePdTotals() {
  let sousTotal = 0;
  const recapItems = [];

  document.querySelectorAll('#pd-articles-body tr').forEach(row => {
    const nom = row.querySelector('.pd-art-nom').value;
    const qte = parseFloat(row.querySelector('.pd-art-qte').value) || 0;
    const prix = parseFloat(row.querySelector('.pd-art-prix').value) || 0;
    const service = row.querySelector('.pd-art-service').value;
    const etat = getPdEtatValue(row);
    const ligneTotal = qte * prix;
    sousTotal += ligneTotal;

    if (nom) {
      recapItems.push({ nom, qte, service, etat, total: ligneTotal });
    }
  });

  const recapEl = document.getElementById('pd-recap-articles');
  if (recapItems.length === 0) {
    recapEl.innerHTML = '<p class="pd-recap-empty">Aucun article ajouté</p>';
  } else {
    recapEl.innerHTML = recapItems.map(item => `
      <div class="pd-recap-item">
        <div>
          <span class="pd-recap-item-name">${item.qte}x ${item.nom}</span>
          <span class="pd-recap-item-meta">${pdServiceLabel(item.service)}${item.etat ? ' · ' + item.etat : ''}</span>
        </div>
        <div class="pd-recap-item-amount">
          ${item.total.toFixed(2)} Fc
          <small>${pdToUsd(item.total).toFixed(2)} $</small>
        </div>
      </div>
    `).join('');
  }

  const remise = parseFloat(document.getElementById('pd-remise').value) || 0;
  const total = Math.max(0, sousTotal - remise);
  document.getElementById('pd-sous-total').innerHTML = `${sousTotal.toFixed(2)} Fc <em>(${pdToUsd(sousTotal).toFixed(2)} $)</em>`;
  document.getElementById('pd-total').innerHTML = `${total.toFixed(2)} Fc <em>(${pdToUsd(total).toFixed(2)} $)</em>`;
}

async function searchPdClient() {
  const numero = document.getElementById('pd-client-search').value.trim();
  if (!numero) return;
  try {
    const res = await fetch(`/api/client/search?numero=${encodeURIComponent(numero)}`);
    const data = await res.json();
    const client = data.client || data;
    if (!client || !client.id) {
      alert('Aucun client trouvé avec ce numéro. Créez-le via "Nouveau client".');
      return;
    }
    setPdClient(client);
  } catch (e) {
    alert('Erreur réseau');
  }
}

function setPdClient(client) {
  document.getElementById('pd-client-id').value = client.id;
  document.getElementById('pd-client-name').textContent = `${client.nom_client} (${client.numero})`;
  document.getElementById('pd-client-found').style.display = 'flex';
}

function clearPdClient() {
  document.getElementById('pd-client-id').value = '';
  document.getElementById('pd-client-found').style.display = 'none';
}

function openPdNewClientModal() {
  document.getElementById('pd-new-client-error').style.display = 'none';
  document.getElementById('pd-client-modal').style.display = 'flex';
}
function closePdNewClientModal() {
  document.getElementById('pd-client-modal').style.display = 'none';
}
async function createPdClient() {
  const nom = document.getElementById('pd-new-client-nom').value.trim();
  const numero = document.getElementById('pd-new-client-numero').value.trim();
  const errorEl = document.getElementById('pd-new-client-error');
  if (!nom || !numero) {
    errorEl.textContent = 'Nom et numéro requis';
    errorEl.style.display = 'block';
    return;
  }
  try {
    const res = await fetch('/api/client', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ nom, numero })
    });
    const data = await res.json();
    if (!data.success) {
      errorEl.textContent = data.message || 'Erreur lors de la création';
      errorEl.style.display = 'block';
      return;
    }
    setPdClient(data.client);
    closePdNewClientModal();
  } catch (e) {
    errorEl.textContent = 'Erreur réseau';
    errorEl.style.display = 'block';
  }
}

async function submitPdDepot() {
  const errorEl = document.getElementById('pd-error');
  errorEl.style.display = 'none';

  const clientId = document.getElementById('pd-client-id').value;
  if (!clientId) {
    errorEl.textContent = 'Sélectionnez ou créez un client';
    errorEl.style.display = 'block';
    return;
  }

  const articles = [];
  document.querySelectorAll('#pd-articles-body tr').forEach(row => {
    articles.push({
      nom_article: row.querySelector('.pd-art-nom').value.trim(),
      quantite: parseInt(row.querySelector('.pd-art-qte').value, 10) || 1,
      etat_initial: getPdEtatValue(row),
      service: row.querySelector('.pd-art-service').value,
      prix_unitaire: parseFloat(row.querySelector('.pd-art-prix').value) || 0,
    });
  });

  if (articles.length === 0) {
    errorEl.textContent = 'Ajoutez au moins un article';
    errorEl.style.display = 'block';
    return;
  }

  const payload = {
    client_id: clientId,
    articles,
    remise: parseFloat(document.getElementById('pd-remise').value) || 0,
    date_prevue: document.getElementById('pd-date-prevue').value || null,
    adresse_livraison: document.getElementById('pd-adresse-livraison').value.trim() || null,
    date_retour_prevue: document.getElementById('pd-date-retour').value || null,
  };

  try {
    const res = await fetch('/api/pressing/depots', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify(payload)
    });
    const data = await res.json();
    if (!res.ok || data.error) {
      errorEl.textContent = data.error || 'Erreur lors de l\'enregistrement';
      errorEl.style.display = 'block';
      return;
    }
    pdLastNumero = data.numero;
    document.getElementById('pd-receipt-numero').textContent = data.numero;
    const totalFc = parseFloat(document.getElementById('pd-total').textContent) || 0;
    document.getElementById('pd-receipt-total').textContent = `${totalFc.toFixed(2)} Fc (${pdToUsd(totalFc).toFixed(2)} $)`;
    QRCode.toCanvas(document.getElementById('pd-receipt-qr'), data.numero, { width: 150 });
    document.getElementById('pd-receipt-modal').style.display = 'flex';
  } catch (e) {
    errorEl.textContent = 'Erreur réseau';
    errorEl.style.display = 'block';
  }
}

function openPdTicket() {
  if (pdLastNumero) {
    window.open(`/pressing/ticket?numero=${encodeURIComponent(pdLastNumero)}`, '_blank');
  } else {
    window.print();
  }
}

// Une ligne d'article par défaut au chargement
addPdArticleRow();
updatePdTotals();
</script>
