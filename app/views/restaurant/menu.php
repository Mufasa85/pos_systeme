<style>
  .rm-tabs { display:flex; gap:.5rem; margin-top:1rem; border-bottom:1px solid var(--border,#e2e8f0) }
  .rm-tab { padding:.6rem 1rem; cursor:pointer; font-size:.85rem; font-weight:500; color:var(--muted,#64748b); border-bottom:2px solid transparent }
  .rm-tab.active { color:var(--primary,#0B5E88); border-bottom-color:var(--primary,#0B5E88) }
  .rm-cat-list { display:flex; flex-wrap:wrap; gap:.6rem; margin-top:1rem }
  .rm-cat-chip { display:flex; align-items:center; gap:.5rem; background:var(--card,#fff); border:1px solid var(--border,#e2e8f0); border-radius:20px; padding:.4rem .5rem .4rem 1rem; font-size:.85rem }
  .rm-cat-chip button { background:none; border:none; cursor:pointer; color:var(--muted,#94a3b8); padding:2px; display:flex }
  .rm-cat-chip button:hover { color:#dc2626 }
  .rm-items-grid { display:grid; grid-template-columns:repeat(auto-fill,minmax(220px,1fr)); gap:1.25rem; margin-top:1rem }
  .rm-item-card { background:var(--card,#fff); border:1px solid var(--border,#e2e8f0); border-radius:var(--radius,10px); overflow:hidden; transition:box-shadow .2s,transform .15s }
  .rm-item-card:hover { box-shadow:0 4px 20px rgba(0,0,0,.08); transform:translateY(-2px) }
  .rm-item-image { width:100%; height:140px; object-fit:cover; background:#f1f5f9 }
  .rm-item-body { padding:.9rem }
  .rm-item-cat { font-size:.7rem; color:var(--primary,#0B5E88); font-weight:600; text-transform:uppercase; letter-spacing:.03em }
  .rm-item-nom { font-weight:700; margin-top:.2rem }
  .rm-item-desc { font-size:.78rem; color:var(--muted,#64748b); margin-top:.2rem; display:-webkit-box; -webkit-line-clamp:2; -webkit-box-orient:vertical; overflow:hidden }
  .rm-item-meta { display:flex; justify-content:space-between; align-items:center; margin-top:.6rem; font-size:.8rem }
  .rm-item-prix { font-weight:700; color:var(--primary,#0B5E88) }
  .rm-item-temps { display:inline-flex; align-items:center; gap:4px; color:var(--muted,#64748b) }
  .rm-item-dispo { display:inline-flex; align-items:center; gap:4px; font-size:.7rem; font-weight:600; padding:2px 8px; border-radius:20px; margin-top:.6rem }
  .rm-item-dispo.oui { background:#dcfce7; color:#16a34a }
  .rm-item-dispo.non { background:#fee2e2; color:#dc2626 }
  .rm-item-actions { display:flex; gap:.4rem; margin-top:.75rem }
  .rm-item-actions .btn { flex:1; justify-content:center; font-size:.72rem; padding:.35rem .5rem }
</style>

<div id="page-restaurant-menu" class="page <?= $page == 'restaurant-menu' ? 'active' : '' ?>">
  <div class="page-header">
    <div>
      <h2>
        <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="margin-right:8px;vertical-align:middle">
          <path d="M4 6h16M4 10h16M4 14h7M4 18h10"></path>
        </svg>
        Restaurant — Catégories & Menu
      </h2>
      <p style="font-size:.85rem;color:var(--muted,#64748b);margin-top:.25rem">
        <?= count($restaurantCategories) ?> catégorie<?= count($restaurantCategories) > 1 ? 's' : '' ?> · <?= count($restaurantMenuItems) ?> plat<?= count($restaurantMenuItems) > 1 ? 's' : '' ?>
      </p>
    </div>
    <?php if (isset($_SESSION['role']) && in_array($_SESSION['role'], ['admin', 'super_admin'])): ?>
      <div style="display:flex; gap:.5rem">
        <button class="btn btn-secondary" onclick="openRmCategoryModal()">
          <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
          Nouvelle catégorie
        </button>
        <button class="btn btn-primary" onclick="openRmItemModal()">
          <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
          Nouveau plat
        </button>
      </div>
    <?php endif; ?>
  </div>

  <?php if (empty($restaurantCategories)): ?>
    <div class="empty-state" style="text-align:center;padding:2rem">
      <p style="color:var(--muted,#64748b)">Aucune catégorie. Créez d'abord une catégorie (Entrées, Plats, Desserts, Boissons...) avant d'ajouter des plats.</p>
    </div>
  <?php else: ?>
    <div class="rm-cat-list">
      <?php foreach ($restaurantCategories as $cat): ?>
        <div class="rm-cat-chip">
          <span><?= htmlspecialchars($cat['nom']) ?></span>
          <?php if (isset($_SESSION['role']) && in_array($_SESSION['role'], ['admin', 'super_admin'])): ?>
            <button onclick='editRmCategory(<?= htmlspecialchars(json_encode($cat), ENT_QUOTES) ?>)' title="Modifier">
              <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polygon points="16 3 21 8 8 21 3 21 3 16 16 3"/></svg>
            </button>
            <button onclick="deleteRmCategory(<?= $cat['id'] ?>)" title="Supprimer">
              <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
            </button>
          <?php endif; ?>
        </div>
      <?php endforeach; ?>
    </div>
  <?php endif; ?>

  <?php if (empty($restaurantMenuItems)): ?>
    <div class="empty-state" style="text-align:center;padding:3rem">
      <p style="color:var(--muted,#64748b)">Aucun plat enregistré</p>
      <?php if (!empty($restaurantCategories) && isset($_SESSION['role']) && in_array($_SESSION['role'], ['admin', 'super_admin'])): ?>
        <button class="btn btn-primary" onclick="openRmItemModal()" style="margin-top:1rem">Ajouter le premier plat</button>
      <?php endif; ?>
    </div>
  <?php else: ?>
    <div class="rm-items-grid">
      <?php foreach ($restaurantMenuItems as $item): ?>
        <div class="rm-item-card" data-id="<?= $item['id'] ?>">
          <?php if (!empty($item['image'])): ?>
            <img class="rm-item-image" src="/<?= htmlspecialchars($item['image']) ?>" alt="<?= htmlspecialchars($item['nom']) ?>" onerror="this.style.display='none'">
          <?php else: ?>
            <div class="rm-item-image" style="display:flex;align-items:center;justify-content:center;color:#cbd5e1">
              <svg width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><rect x="3" y="3" width="18" height="18" rx="2"/><circle cx="9" cy="9" r="2"/><path d="M21 15l-5-5L5 21"/></svg>
            </div>
          <?php endif; ?>
          <div class="rm-item-body">
            <div class="rm-item-cat"><?= htmlspecialchars($item['categorie_nom'] ?? '') ?></div>
            <div class="rm-item-nom"><?= htmlspecialchars($item['nom']) ?></div>
            <?php if (!empty($item['description'])): ?>
              <div class="rm-item-desc"><?= htmlspecialchars($item['description']) ?></div>
            <?php endif; ?>
            <div class="rm-item-meta">
              <span class="rm-item-prix"><?= number_format($item['prix'], 2) ?> Fc</span>
              <span class="rm-item-temps">
                <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>
                <?= (int)$item['temps_preparation'] ?> min
              </span>
            </div>
            <div class="rm-item-dispo <?= $item['disponible'] ? 'oui' : 'non' ?>" style="cursor:pointer" onclick="toggleRmDispo(<?= $item['id'] ?>, <?= $item['disponible'] ? 0 : 1 ?>)">
              <?= $item['disponible'] ? '✓ Disponible' : '✕ Indisponible' ?>
            </div>
            <?php if (isset($_SESSION['role']) && in_array($_SESSION['role'], ['admin', 'super_admin'])): ?>
              <div class="rm-item-actions">
                <button class="btn btn-secondary" onclick='editRmItem(<?= htmlspecialchars(json_encode($item), ENT_QUOTES) ?>)'>Modifier</button>
                <button class="btn btn-secondary" style="color:#dc2626" onclick="deleteRmItem(<?= $item['id'] ?>)">Supprimer</button>
              </div>
            <?php endif; ?>
          </div>
        </div>
      <?php endforeach; ?>
    </div>
  <?php endif; ?>
</div>

<!-- Modal Catégorie -->
<div id="rm-category-modal" class="modal" style="display:none; position:fixed; top:0; left:0; width:100%; height:100%; background:rgba(0,0,0,0.5); z-index:1000; align-items:center; justify-content:center;">
  <div class="modal-content" style="background:white; padding:2rem; border-radius:8px; width:400px; max-width:90%;">
    <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:1.5rem;">
      <h3 id="rm-category-modal-title" style="margin:0;">Nouvelle catégorie</h3>
      <button type="button" onclick="closeRmCategoryModal()" style="background:none; border:none; font-size:1.5rem; cursor:pointer;">&times;</button>
    </div>
    <form id="rm-category-form" onsubmit="return saveRmCategory(event)">
      <input type="hidden" id="rm-category-id" value="">
      <?php if (($_SESSION['role'] ?? '') === 'super_admin'): ?>
        <div class="form-group" style="margin-bottom:1rem;">
          <label style="display:block; margin-bottom:0.5rem; font-weight:500;">Boutique</label>
          <select id="rm-category-shop-id" required style="width:100%; padding:0.5rem; border:1px solid #ddd; border-radius:4px;">
            <option value="">-- Sélectionner --</option>
            <?php foreach ($shops as $shop): ?>
              <option value="<?= $shop['id'] ?>"><?= htmlspecialchars($shop['nom']) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
      <?php endif; ?>
      <div style="margin-bottom:1rem;">
        <label style="display:block; margin-bottom:0.5rem; font-weight:500;">Nom *</label>
        <input type="text" id="rm-category-nom" required style="width:100%; padding:0.5rem; border:1px solid #ddd; border-radius:4px;">
      </div>
      <div style="margin-bottom:1rem;">
        <label style="display:block; margin-bottom:0.5rem; font-weight:500;">Description</label>
        <input type="text" id="rm-category-description" style="width:100%; padding:0.5rem; border:1px solid #ddd; border-radius:4px;">
      </div>
      <div id="rm-category-error" style="color:#e53e3e;font-size:.85rem;margin-bottom:.5rem;display:none"></div>
      <div style="display:flex; gap:0.5rem; justify-content:flex-end; margin-top:1.5rem;">
        <button type="button" onclick="closeRmCategoryModal()" class="btn btn-secondary">Annuler</button>
        <button type="submit" class="btn btn-primary">Enregistrer</button>
      </div>
    </form>
  </div>
</div>

<!-- Modal Plat -->
<div id="rm-item-modal" class="modal" style="display:none; position:fixed; top:0; left:0; width:100%; height:100%; background:rgba(0,0,0,0.5); z-index:1000; align-items:center; justify-content:center;">
  <div class="modal-content" style="background:white; padding:2rem; border-radius:8px; width:460px; max-width:90%; max-height:90vh; overflow-y:auto;">
    <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:1.5rem;">
      <h3 id="rm-item-modal-title" style="margin:0;">Nouveau plat</h3>
      <button type="button" onclick="closeRmItemModal()" style="background:none; border:none; font-size:1.5rem; cursor:pointer;">&times;</button>
    </div>
    <form id="rm-item-form" onsubmit="return saveRmItem(event)" enctype="multipart/form-data">
      <input type="hidden" id="rm-item-id" value="">
      <?php if (($_SESSION['role'] ?? '') === 'super_admin'): ?>
        <div class="form-group" style="margin-bottom:1rem;">
          <label style="display:block; margin-bottom:0.5rem; font-weight:500;">Boutique</label>
          <select id="rm-item-shop-id" required style="width:100%; padding:0.5rem; border:1px solid #ddd; border-radius:4px;">
            <option value="">-- Sélectionner --</option>
            <?php foreach ($shops as $shop): ?>
              <option value="<?= $shop['id'] ?>"><?= htmlspecialchars($shop['nom']) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
      <?php endif; ?>
      <div style="margin-bottom:1rem;">
        <label style="display:block; margin-bottom:0.5rem; font-weight:500;">Catégorie *</label>
        <select id="rm-item-categorie-id" required style="width:100%; padding:0.5rem; border:1px solid #ddd; border-radius:4px;">
          <option value="">-- Sélectionner --</option>
          <?php foreach ($restaurantCategories as $cat): ?>
            <option value="<?= $cat['id'] ?>" data-shop="<?= $cat['shop_id'] ?>"><?= htmlspecialchars($cat['nom']) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div style="margin-bottom:1rem;">
        <label style="display:block; margin-bottom:0.5rem; font-weight:500;">Nom *</label>
        <input type="text" id="rm-item-nom" required style="width:100%; padding:0.5rem; border:1px solid #ddd; border-radius:4px;">
      </div>
      <div style="margin-bottom:1rem;">
        <label style="display:block; margin-bottom:0.5rem; font-weight:500;">Description</label>
        <textarea id="rm-item-description" rows="2" style="width:100%; padding:0.5rem; border:1px solid #ddd; border-radius:4px;"></textarea>
      </div>
      <div style="display:flex; gap:.75rem; margin-bottom:1rem;">
        <div style="flex:1">
          <label style="display:block; margin-bottom:0.5rem; font-weight:500;">Prix (Fc) *</label>
          <input type="number" id="rm-item-prix" min="0" step="0.01" required style="width:100%; padding:0.5rem; border:1px solid #ddd; border-radius:4px;">
        </div>
        <div style="flex:1">
          <label style="display:block; margin-bottom:0.5rem; font-weight:500;">Temps préparation (min)</label>
          <input type="number" id="rm-item-temps" min="0" value="15" style="width:100%; padding:0.5rem; border:1px solid #ddd; border-radius:4px;">
        </div>
      </div>
      <div style="margin-bottom:1rem;">
        <label style="display:block; margin-bottom:0.5rem; font-weight:500;">Image</label>
        <input type="file" id="rm-item-image" accept="image/*" style="width:100%;">
      </div>
      <div style="margin-bottom:1rem;">
        <label style="display:flex; align-items:center; gap:.5rem; font-weight:500;">
          <input type="checkbox" id="rm-item-disponible" checked> Disponible
        </label>
      </div>
      <div id="rm-item-error" style="color:#e53e3e;font-size:.85rem;margin-bottom:.5rem;display:none"></div>
      <div style="display:flex; gap:0.5rem; justify-content:flex-end; margin-top:1.5rem;">
        <button type="button" onclick="closeRmItemModal()" class="btn btn-secondary">Annuler</button>
        <button type="submit" class="btn btn-primary">Enregistrer</button>
      </div>
    </form>
  </div>
</div>

<script>
const RM_IS_SUPER_ADMIN = <?= json_encode(($_SESSION['role'] ?? '') === 'super_admin') ?>;

// ── Catégories ─────────────────────────────────────────────────

function openRmCategoryModal() {
  document.getElementById('rm-category-modal-title').textContent = 'Nouvelle catégorie';
  document.getElementById('rm-category-form').reset();
  document.getElementById('rm-category-id').value = '';
  document.getElementById('rm-category-error').style.display = 'none';
  document.getElementById('rm-category-modal').style.display = 'flex';
}
function closeRmCategoryModal() {
  document.getElementById('rm-category-modal').style.display = 'none';
}
function editRmCategory(cat) {
  document.getElementById('rm-category-modal-title').textContent = 'Modifier la catégorie';
  document.getElementById('rm-category-id').value = cat.id;
  document.getElementById('rm-category-nom').value = cat.nom;
  document.getElementById('rm-category-description').value = cat.description || '';
  if (RM_IS_SUPER_ADMIN && document.getElementById('rm-category-shop-id')) {
    document.getElementById('rm-category-shop-id').value = cat.shop_id;
  }
  document.getElementById('rm-category-error').style.display = 'none';
  document.getElementById('rm-category-modal').style.display = 'flex';
}
async function saveRmCategory(event) {
  event.preventDefault();
  const id = document.getElementById('rm-category-id').value;
  const errorEl = document.getElementById('rm-category-error');
  errorEl.style.display = 'none';

  const formData = new FormData();
  formData.append('nom', document.getElementById('rm-category-nom').value.trim());
  formData.append('description', document.getElementById('rm-category-description').value.trim());
  if (RM_IS_SUPER_ADMIN && document.getElementById('rm-category-shop-id')) {
    formData.append('shop_id', document.getElementById('rm-category-shop-id').value);
  }

  try {
    const url = id ? `/api/restaurant/categories/update/${id}` : '/api/restaurant/categories';
    const res = await fetch(url, { method: 'POST', body: formData });
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
async function deleteRmCategory(id) {
  if (!confirm('Supprimer cette catégorie ? Les plats associés seront aussi supprimés.')) return;
  try {
    const res = await fetch(`/api/restaurant/categories/delete/${id}`, { method: 'POST' });
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

// ── Plats ──────────────────────────────────────────────────────

function openRmItemModal() {
  document.getElementById('rm-item-modal-title').textContent = 'Nouveau plat';
  document.getElementById('rm-item-form').reset();
  document.getElementById('rm-item-id').value = '';
  document.getElementById('rm-item-disponible').checked = true;
  document.getElementById('rm-item-error').style.display = 'none';
  document.getElementById('rm-item-modal').style.display = 'flex';
}
function closeRmItemModal() {
  document.getElementById('rm-item-modal').style.display = 'none';
}
function editRmItem(item) {
  document.getElementById('rm-item-modal-title').textContent = 'Modifier le plat';
  document.getElementById('rm-item-id').value = item.id;
  document.getElementById('rm-item-categorie-id').value = item.categorie_id;
  document.getElementById('rm-item-nom').value = item.nom;
  document.getElementById('rm-item-description').value = item.description || '';
  document.getElementById('rm-item-prix').value = item.prix;
  document.getElementById('rm-item-temps').value = item.temps_preparation;
  document.getElementById('rm-item-disponible').checked = !!parseInt(item.disponible);
  if (RM_IS_SUPER_ADMIN && document.getElementById('rm-item-shop-id')) {
    document.getElementById('rm-item-shop-id').value = item.shop_id;
  }
  document.getElementById('rm-item-error').style.display = 'none';
  document.getElementById('rm-item-modal').style.display = 'flex';
}
async function saveRmItem(event) {
  event.preventDefault();
  const id = document.getElementById('rm-item-id').value;
  const errorEl = document.getElementById('rm-item-error');
  errorEl.style.display = 'none';

  const formData = new FormData();
  formData.append('categorie_id', document.getElementById('rm-item-categorie-id').value);
  formData.append('nom', document.getElementById('rm-item-nom').value.trim());
  formData.append('description', document.getElementById('rm-item-description').value.trim());
  formData.append('prix', document.getElementById('rm-item-prix').value);
  formData.append('temps_preparation', document.getElementById('rm-item-temps').value);
  formData.append('disponible', document.getElementById('rm-item-disponible').checked ? '1' : '0');
  if (RM_IS_SUPER_ADMIN && document.getElementById('rm-item-shop-id')) {
    formData.append('shop_id', document.getElementById('rm-item-shop-id').value);
  }
  const imageInput = document.getElementById('rm-item-image');
  if (imageInput.files.length > 0) {
    formData.append('image', imageInput.files[0]);
  }

  try {
    const url = id ? `/api/restaurant/plats/update/${id}` : '/api/restaurant/plats';
    const res = await fetch(url, { method: 'POST', body: formData });
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
async function toggleRmDispo(id, newValue) {
  try {
    const res = await fetch(`/api/restaurant/plats/toggle/${id}`, {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ disponible: newValue })
    });
    const data = await res.json();
    if (!res.ok || data.error) {
      alert(data.error || 'Erreur');
      return;
    }
    window.location.reload();
  } catch (e) {
    alert('Erreur réseau');
  }
}
async function deleteRmItem(id) {
  if (!confirm('Supprimer ce plat ?')) return;
  try {
    const res = await fetch(`/api/restaurant/plats/delete/${id}`, { method: 'POST' });
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
