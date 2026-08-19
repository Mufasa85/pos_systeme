<style>
  .rt-grid { display:grid; grid-template-columns:repeat(auto-fill,minmax(220px,1fr)); gap:1.25rem; margin-top:1rem }
  .rt-card { border-radius:var(--radius,10px); padding:1.25rem; color:#fff; position:relative; transition:transform .15s, box-shadow .2s; cursor:default; box-shadow:0 2px 8px rgba(0,0,0,.08) }
  .rt-card:hover { transform:translateY(-2px); box-shadow:0 6px 20px rgba(0,0,0,.12) }
  .rt-card.etat-libre { background:linear-gradient(135deg,#16a34a,#15803d) }
  .rt-card.etat-occupee { background:linear-gradient(135deg,#dc2626,#b91c1c) }
  .rt-card.etat-reservee { background:linear-gradient(135deg,#d97706,#b45309) }
  .rt-card.etat-nettoyage { background:linear-gradient(135deg,#64748b,#475569) }
  .rt-card-numero { font-size:1.4rem; font-weight:700; line-height:1 }
  .rt-card-nom { font-size:.8rem; opacity:.9; margin-top:.15rem }
  .rt-card-meta { display:flex; justify-content:space-between; align-items:center; margin-top:1rem; font-size:.75rem }
  .rt-card-capacite { display:inline-flex; align-items:center; gap:4px; background:rgba(255,255,255,.2); padding:2px 8px; border-radius:12px }
  .rt-card-etat-label { font-weight:600; text-transform:uppercase; letter-spacing:.03em; font-size:.68rem }
  .rt-card-actions { display:flex; gap:.4rem; margin-top:1rem }
  .rt-card-actions .btn { flex:1; justify-content:center; font-size:.72rem; padding:.35rem .5rem; background:rgba(255,255,255,.15); border:1px solid rgba(255,255,255,.35); color:#fff }
  .rt-card-actions .btn:hover { background:rgba(255,255,255,.3) }
  .rt-state-select { width:100%; margin-top:.6rem; font-size:.75rem; padding:.35rem .5rem; border-radius:6px; border:1px solid rgba(255,255,255,.4); background:rgba(255,255,255,.15); color:#fff }
  .rt-state-select option { color:#1e293b }
  .rt-legend { display:flex; gap:1rem; flex-wrap:wrap; margin-top:.75rem; font-size:.78rem; color:var(--muted,#64748b) }
  .rt-legend-item { display:flex; align-items:center; gap:6px }
  .rt-legend-dot { width:10px; height:10px; border-radius:50% }
</style>

<div id="page-restaurant-tables" class="page <?= $page == 'restaurant-tables' ? 'active' : '' ?>">
  <div class="page-header">
    <div>
      <h2>
        <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="margin-right:8px;vertical-align:middle">
          <line x1="12" y1="1" x2="12" y2="23"></line>
          <path d="M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"></path>
        </svg>
        Restaurant — Gestion des tables
      </h2>
      <p style="font-size:.85rem;color:var(--muted,#64748b);margin-top:.25rem">
        <?= count($restaurantTables) ?> table<?= count($restaurantTables) > 1 ? 's' : '' ?> enregistrée<?= count($restaurantTables) > 1 ? 's' : '' ?>
      </p>
    </div>
    <?php if (isset($_SESSION['role']) && in_array($_SESSION['role'], ['admin', 'super_admin'])): ?>
      <button class="btn btn-primary" onclick="openRtModal()">
        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
        Nouvelle table
      </button>
    <?php endif; ?>
  </div>

  <div class="rt-legend">
    <span class="rt-legend-item"><span class="rt-legend-dot" style="background:#16a34a"></span>Libre</span>
    <span class="rt-legend-item"><span class="rt-legend-dot" style="background:#dc2626"></span>Occupée</span>
    <span class="rt-legend-item"><span class="rt-legend-dot" style="background:#d97706"></span>Réservée</span>
    <span class="rt-legend-item"><span class="rt-legend-dot" style="background:#64748b"></span>Nettoyage</span>
  </div>

  <?php if (empty($restaurantTables)): ?>
    <div class="empty-state" style="text-align:center;padding:3rem">
      <svg width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="var(--muted,#94a3b8)" stroke-width="1.5" style="margin-bottom:1rem">
        <line x1="12" y1="1" x2="12" y2="23"></line>
        <path d="M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"></path>
      </svg>
      <p style="color:var(--muted,#64748b)">Aucune table enregistrée</p>
      <?php if (isset($_SESSION['role']) && in_array($_SESSION['role'], ['admin', 'super_admin'])): ?>
        <button class="btn btn-primary" onclick="openRtModal()" style="margin-top:1rem">Ajouter la première table</button>
      <?php endif; ?>
    </div>
  <?php else: ?>
    <div class="rt-grid" id="rt-grid">
      <?php foreach ($restaurantTables as $t): ?>
        <div class="rt-card etat-<?= htmlspecialchars($t['etat']) ?>" data-id="<?= $t['id'] ?>">
          <div class="rt-card-numero">Table <?= htmlspecialchars($t['numero']) ?></div>
          <?php if (!empty($t['nom'])): ?>
            <div class="rt-card-nom"><?= htmlspecialchars($t['nom']) ?></div>
          <?php endif; ?>
          <?php if (($_SESSION['role'] ?? '') === 'super_admin' && !empty($t['shop_name'])): ?>
            <div class="rt-card-nom">🏬 <?= htmlspecialchars($t['shop_name']) ?></div>
          <?php endif; ?>
          <div class="rt-card-meta">
            <span class="rt-card-capacite">
              <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"></path><circle cx="9" cy="7" r="4"></circle></svg>
              <?= (int)$t['capacite'] ?>
            </span>
            <span class="rt-card-etat-label"><?= htmlspecialchars(ucfirst($t['etat'])) ?></span>
          </div>

          <select class="rt-state-select" onchange="changeRtState(<?= $t['id'] ?>, this.value)">
            <option value="libre" <?= $t['etat'] === 'libre' ? 'selected' : '' ?>>Libre</option>
            <option value="occupee" <?= $t['etat'] === 'occupee' ? 'selected' : '' ?>>Occupée</option>
            <option value="reservee" <?= $t['etat'] === 'reservee' ? 'selected' : '' ?>>Réservée</option>
            <option value="nettoyage" <?= $t['etat'] === 'nettoyage' ? 'selected' : '' ?>>Nettoyage</option>
          </select>

          <?php if (isset($_SESSION['role']) && in_array($_SESSION['role'], ['admin', 'super_admin'])): ?>
            <div class="rt-card-actions">
              <button class="btn" onclick='editRtTable(<?= htmlspecialchars(json_encode($t), ENT_QUOTES) ?>)'>Modifier</button>
              <button class="btn" onclick="deleteRtTable(<?= $t['id'] ?>)">Supprimer</button>
            </div>
          <?php endif; ?>
        </div>
      <?php endforeach; ?>
    </div>
  <?php endif; ?>
</div>

<!-- Modal Ajouter/Modifier Table -->
<div id="rt-modal" class="modal" style="display:none; position:fixed; top:0; left:0; width:100%; height:100%; background:rgba(0,0,0,0.5); z-index:1000; align-items:center; justify-content:center;">
  <div class="modal-content" style="background:white; padding:2rem; border-radius:8px; width:420px; max-width:90%;">
    <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:1.5rem;">
      <h3 id="rt-modal-title" style="margin:0;">Nouvelle table</h3>
      <button type="button" onclick="closeRtModal()" style="background:none; border:none; font-size:1.5rem; cursor:pointer;">&times;</button>
    </div>
    <form id="rt-form" onsubmit="return saveRtTable(event)">
      <input type="hidden" id="rt-id" value="">

      <?php if (($_SESSION['role'] ?? '') === 'super_admin'): ?>
        <div class="form-group" style="margin-bottom:1rem;">
          <label style="display:block; margin-bottom:0.5rem; font-weight:500;">Boutique</label>
          <select id="rt-shop-id" required style="width:100%; padding:0.5rem; border:1px solid #ddd; border-radius:4px;">
            <option value="">-- Sélectionner --</option>
            <?php foreach ($shops as $shop): ?>
              <option value="<?= $shop['id'] ?>"><?= htmlspecialchars($shop['nom']) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
      <?php endif; ?>

      <div style="margin-bottom:1rem;">
        <label style="display:block; margin-bottom:0.5rem; font-weight:500;">Numéro de table *</label>
        <input type="text" id="rt-numero" required style="width:100%; padding:0.5rem; border:1px solid #ddd; border-radius:4px;">
      </div>
      <div style="margin-bottom:1rem;">
        <label style="display:block; margin-bottom:0.5rem; font-weight:500;">Nom (optionnel)</label>
        <input type="text" id="rt-nom" style="width:100%; padding:0.5rem; border:1px solid #ddd; border-radius:4px;">
      </div>
      <div style="margin-bottom:1rem;">
        <label style="display:block; margin-bottom:0.5rem; font-weight:500;">Capacité *</label>
        <input type="number" id="rt-capacite" min="1" value="4" required style="width:100%; padding:0.5rem; border:1px solid #ddd; border-radius:4px;">
      </div>
      <div style="margin-bottom:1rem;">
        <label style="display:block; margin-bottom:0.5rem; font-weight:500;">État</label>
        <select id="rt-etat" style="width:100%; padding:0.5rem; border:1px solid #ddd; border-radius:4px;">
          <option value="libre">Libre</option>
          <option value="occupee">Occupée</option>
          <option value="reservee">Réservée</option>
          <option value="nettoyage">Nettoyage</option>
        </select>
      </div>

      <div id="rt-error" style="color:#e53e3e;font-size:.85rem;margin-bottom:.5rem;display:none"></div>

      <div style="display:flex; gap:0.5rem; justify-content:flex-end; margin-top:1.5rem;">
        <button type="button" onclick="closeRtModal()" class="btn btn-secondary">Annuler</button>
        <button type="submit" class="btn btn-primary">Enregistrer</button>
      </div>
    </form>
  </div>
</div>

<script>
const RT_API = window.location.origin + '/api/restaurant/tables';
const RT_IS_SUPER_ADMIN = <?= json_encode(($_SESSION['role'] ?? '') === 'super_admin') ?>;

function openRtModal() {
  document.getElementById('rt-modal-title').textContent = 'Nouvelle table';
  document.getElementById('rt-form').reset();
  document.getElementById('rt-id').value = '';
  document.getElementById('rt-error').style.display = 'none';
  document.getElementById('rt-modal').style.display = 'flex';
}

function closeRtModal() {
  document.getElementById('rt-modal').style.display = 'none';
}

function editRtTable(t) {
  document.getElementById('rt-modal-title').textContent = 'Modifier la table';
  document.getElementById('rt-id').value = t.id;
  document.getElementById('rt-numero').value = t.numero;
  document.getElementById('rt-nom').value = t.nom || '';
  document.getElementById('rt-capacite').value = t.capacite;
  document.getElementById('rt-etat').value = t.etat;
  if (RT_IS_SUPER_ADMIN && document.getElementById('rt-shop-id')) {
    document.getElementById('rt-shop-id').value = t.shop_id;
  }
  document.getElementById('rt-error').style.display = 'none';
  document.getElementById('rt-modal').style.display = 'flex';
}

async function saveRtTable(event) {
  event.preventDefault();
  const id = document.getElementById('rt-id').value;
  const errorEl = document.getElementById('rt-error');
  errorEl.style.display = 'none';

  const payload = {
    numero: document.getElementById('rt-numero').value.trim(),
    nom: document.getElementById('rt-nom').value.trim(),
    capacite: parseInt(document.getElementById('rt-capacite').value, 10),
    etat: document.getElementById('rt-etat').value,
  };
  if (RT_IS_SUPER_ADMIN && document.getElementById('rt-shop-id')) {
    payload.shop_id = document.getElementById('rt-shop-id').value;
  }

  try {
    const url = id ? `${RT_API}/update/${id}` : RT_API;
    const res = await fetch(url, {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify(payload)
    });
    const data = await res.json();
    if (!res.ok || data.error) {
      errorEl.textContent = data.error || 'Erreur lors de l\'enregistrement';
      errorEl.style.display = 'block';
      return false;
    }
    window.location.reload();
  } catch (e) {
    errorEl.textContent = 'Erreur réseau';
    errorEl.style.display = 'block';
  }
  return false;
}

async function changeRtState(id, etat) {
  try {
    const res = await fetch(`${RT_API}/state/${id}`, {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ etat })
    });
    const data = await res.json();
    if (!res.ok || data.error) {
      alert(data.error || 'Erreur lors du changement d\'état');
      return;
    }
    const card = document.querySelector(`.rt-card[data-id="${id}"]`);
    if (card) {
      card.className = card.className.replace(/etat-\w+/, 'etat-' + etat);
      const label = card.querySelector('.rt-card-etat-label');
      if (label) label.textContent = etat.charAt(0).toUpperCase() + etat.slice(1);
    }
  } catch (e) {
    alert('Erreur réseau');
  }
}

async function deleteRtTable(id) {
  if (!confirm('Supprimer cette table ?')) return;
  try {
    const res = await fetch(`${RT_API}/delete/${id}`, { method: 'POST' });
    const data = await res.json();
    if (!res.ok || data.error) {
      alert(data.error || 'Erreur lors de la suppression');
      return;
    }
    window.location.reload();
  } catch (e) {
    alert('Erreur réseau');
  }
}
</script>
