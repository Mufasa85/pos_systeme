<div class="page-header">
  <h2>Gestion des Boutiques</h2>
  <button class="btn btn-primary" onclick="openAddShopModal()">
    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
    Nouvelle boutique
  </button>
</div>

<div class="table-container">
  <table class="data-table" id="shops-table">
    <thead>
      <tr>
        <th>Code</th>
        <th>Nom</th>
        <th>Adresse</th>
        <th>Téléphone</th>
        <th>Email</th>
        <th>Statut</th>
        <th>Actions</th>
      </tr>
    </thead>
    <tbody>
      <?php foreach ($shops as $shop): ?>
      <tr data-id="<?= $shop['id'] ?>">
        <td><strong><?= htmlspecialchars($shop['code']) ?></strong></td>
        <td><?= htmlspecialchars($shop['nom']) ?></td>
        <td><?= htmlspecialchars($shop['adresse'] ?? '-') ?></td>
        <td><?= htmlspecialchars($shop['telephone'] ?? '-') ?></td>
        <td><?= htmlspecialchars($shop['email'] ?? '-') ?></td>
        <td>
          <?php if ($shop['actif']): ?>
            <span class="badge badge-success">Active</span>
          <?php else: ?>
            <span class="badge badge-danger">Inactive</span>
          <?php endif; ?>
        </td>
        <td>
          <button class="btn btn-small" onclick="openEditShopModal(<?= htmlspecialchars(json_encode($shop)) ?>)">Modifier</button>
          <button class="btn btn-small btn-danger" onclick="deleteShop(<?= $shop['id'] ?>)">Supprimer</button>
        </td>
      </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
</div>

<!-- Modal Boutique -->
<div id="shop-modal" class="modal" style="display:none">
  <div class="modal-content">
    <div class="modal-header">
      <h3 id="shop-modal-title">Nouvelle boutique</h3>
      <button class="modal-close" onclick="closeShopModal()">&times;</button>
    </div>
    <form id="shop-form" onsubmit="saveShop(event)">
      <input type="hidden" id="shop-id" value="">
      <div class="form-group">
        <label>Nom *</label>
        <input type="text" id="shop-nom" required>
      </div>
      <div class="form-group">
        <label>Code *</label>
        <input type="text" id="shop-code" required placeholder="Ex: SHOP01" style="text-transform:uppercase">
      </div>
      <div class="form-group">
        <label>Adresse</label>
        <input type="text" id="shop-adresse">
      </div>
      <div class="form-row" style="display:flex;gap:1rem">
        <div class="form-group" style="flex:1">
          <label>Téléphone</label>
          <input type="text" id="shop-telephone">
        </div>
        <div class="form-group" style="flex:1">
          <label>Email</label>
          <input type="email" id="shop-email">
        </div>
      </div>
      <div class="form-row" style="display:flex;gap:1rem">
        <div class="form-group" style="flex:1">
          <label>ICE</label>
          <input type="text" id="shop-ice">
        </div>
        <div class="form-group" style="flex:1">
          <label>RCCM</label>
          <input type="text" id="shop-rccm">
        </div>
        <div class="form-group" style="flex:1">
          <label>ISF</label>
          <input type="text" id="shop-isf">
        </div>
      </div>
      <div class="form-group">
        <label>Statut</label>
        <select id="shop-actif">
          <option value="1">Active</option>
          <option value="0">Inactive</option>
        </select>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn" onclick="closeShopModal()">Annuler</button>
        <button type="submit" class="btn btn-primary">Enregistrer</button>
      </div>
    </form>
  </div>
</div>

<script>
const SHOPS_API = window.location.origin + '/api/shops';

function openAddShopModal() {
  document.getElementById('shop-modal-title').textContent = 'Nouvelle boutique';
  document.getElementById('shop-id').value = '';
  document.getElementById('shop-form').reset();
  document.getElementById('shop-modal').style.display = 'flex';
}

function openEditShopModal(shop) {
  document.getElementById('shop-modal-title').textContent = 'Modifier la boutique';
  document.getElementById('shop-id').value = shop.id;
  document.getElementById('shop-nom').value = shop.nom || '';
  document.getElementById('shop-code').value = shop.code || '';
  document.getElementById('shop-adresse').value = shop.adresse || '';
  document.getElementById('shop-telephone').value = shop.telephone || '';
  document.getElementById('shop-email').value = shop.email || '';
  document.getElementById('shop-ice').value = shop.ice || '';
  document.getElementById('shop-rccm').value = shop.rccm || '';
  document.getElementById('shop-isf').value = shop.isf || '';
  document.getElementById('shop-actif').value = shop.actif || '1';
  document.getElementById('shop-modal').style.display = 'flex';
}

function closeShopModal() {
  document.getElementById('shop-modal').style.display = 'none';
}

async function saveShop(e) {
  e.preventDefault();
  const id = document.getElementById('shop-id').value;
  const data = {
    nom: document.getElementById('shop-nom').value,
    code: document.getElementById('shop-code').value.toUpperCase(),
    adresse: document.getElementById('shop-adresse').value,
    telephone: document.getElementById('shop-telephone').value,
    email: document.getElementById('shop-email').value,
    ice: document.getElementById('shop-ice').value,
    rccm: document.getElementById('shop-rccm').value,
    isf: document.getElementById('shop-isf').value,
    actif: parseInt(document.getElementById('shop-actif').value)
  };

  const url = id ? `${SHOPS_API}/${id}` : SHOPS_API;
  const method = id ? 'PUT' : 'POST';

  try {
    const res = await fetch(url, {
      method, headers: {'Content-Type': 'application/json'}, body: JSON.stringify(data)
    });
    const result = await res.json();
    if (result.success) {
      closeShopModal();
      window.location.reload();
    } else {
      alert(result.error || 'Erreur lors de la sauvegarde');
    }
  } catch (err) {
    alert('Erreur réseau');
  }
}

async function deleteShop(id) {
  if (!confirm('Supprimer cette boutique ? Cette action est irréversible.')) return;
  try {
    const res = await fetch(`${SHOPS_API}/${id}`, { method: 'DELETE' });
    const result = await res.json();
    if (result.success) {
      window.location.reload();
    } else {
      alert(result.error || 'Erreur lors de la suppression');
    }
  } catch (err) {
    alert('Erreur réseau');
  }
}
</script>
