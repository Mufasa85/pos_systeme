<style>
  .pa-layout { display:grid; grid-template-columns:1fr; gap:1.5rem; margin-top:1.25rem; width:100% }
  .pa-section { background:var(--card,#fff); border:1px solid var(--border,#e2e8f0); border-radius:12px; padding:1.5rem }
  .pa-section h3 { margin:0 0 1rem; font-size:1.05rem }
  .pa-tabs { display:flex; gap:.5rem; border-bottom:1px solid var(--border,#e2e8f0); margin-bottom:1.25rem }
  .pa-tab { padding:.65rem 1rem; background:none; border:none; border-bottom:2px solid transparent; cursor:pointer; font-weight:500; color:var(--muted,#64748b) }
  .pa-tab.active { border-bottom-color:var(--primary,#0B5E88); color:var(--primary,#0B5E88) }
  .pa-tab-content { display:none }
  .pa-tab-content.active { display:block }
  .pa-form { display:grid; grid-template-columns:repeat(auto-fit,minmax(140px,1fr)); gap:.75rem; margin-bottom:1.25rem; align-items:end }
  .pa-form input, .pa-form select { padding:.55rem .65rem; border:1px solid #ddd; border-radius:6px; font-size:.9rem; width:100% }
  .pa-table { width:100%; border-collapse:collapse; font-size:.9rem }
  .pa-table th, .pa-table td { padding:.55rem .65rem; text-align:left; border-bottom:1px solid #f1f5f9; vertical-align:middle }
  .pa-table th { font-weight:600; color:var(--muted,#475569) }
  .pa-table tr:last-child td { border-bottom:none }
  .pa-empty { color:var(--muted,#94a3b8); font-size:.9rem; padding:.5rem 0 }
  .pa-actions { display:flex; gap:.5rem }
  .pa-shop-select { max-width:260px; margin-bottom:1rem }
</style>

<div id="page-pressing-admin" class="page <?= $page == 'pressing-admin' ? 'active' : '' ?>">
  <div class="page-header">
    <h2>
      <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="margin-right:8px;vertical-align:middle">
        <circle cx="12" cy="12" r="3"></circle>
        <path d="M19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 0 1 0 2.83 2 2 0 0 1-2.83 0l-.06-.06a1.65 1.65 0 0 0-1.82-.33 1.65 1.65 0 0 0-1 1.51V21a2 2 0 0 1-2 2 2 2 0 0 1-2-2v-.09A1.65 1.65 0 0 0 9 19.4a1.65 1.65 0 0 0-1.82.33l-.06.06a2 2 0 0 1-2.83 0 2 2 0 0 1 0-2.83l.06-.06a1.65 1.65 0 0 0 .33-1.82 1.65 1.65 0 0 0-1.51-1H3a2 2 0 0 1-2-2 2 2 0 0 1 2-2h.09A1.65 1.65 0 0 0 4.6 9a1.65 1.65 0 0 0-.33-1.82l-.06-.06a2 2 0 0 1 0-2.83 2 2 0 0 1 2.83 0l.06.06a1.65 1.65 0 0 0 1.82.33H9a1.65 1.65 0 0 0 1-1.51V3a2 2 0 0 1 2-2 2 2 0 0 1 2 2v.09a1.65 1.65 0 0 0 1 1.51 1.65 1.65 0 0 0 1.82-.33l.06-.06a2 2 0 0 1 2.83 0 2 2 0 0 1 0 2.83l-.06.06a1.65 1.65 0 0 0-.33 1.82V9a1.65 1.65 0 0 0 1.51 1H21a2 2 0 0 1 2 2 2 2 0 0 1-2 2h-.09a1.65 1.65 0 0 0-1.51 1z"></path>
      </svg>
      Pressing — Paramètres
    </h2>
  </div>

  <?php if (!empty($shops)): ?>
    <div class="pa-shop-select">
      <label style="display:block;font-size:.85rem;margin-bottom:.35rem">Boutique</label>
      <select id="pa-shop-id" onchange="changePaShop()">
        <?php foreach ($shops as $s): ?>
          <option value="<?= $s['id'] ?>" <?= ($currentShopId == $s['id']) ? 'selected' : '' ?>><?= htmlspecialchars($s['nom']) ?></option>
        <?php endforeach; ?>
      </select>
    </div>
  <?php endif; ?>

  <div class="pa-section">
    <div class="pa-tabs">
      <button class="pa-tab active" onclick="switchPaTab('services')">Services</button>
      <button class="pa-tab" onclick="switchPaTab('tarifs')">Tarifs</button>
      <button class="pa-tab" onclick="switchPaTab('consumables')">Consommables</button>
    </div>

    <!-- Services -->
    <div id="pa-tab-services" class="pa-tab-content active">
      <h3>Catalogue de services</h3>
      <form class="pa-form" onsubmit="return createPaService(event)">
        <input type="text" name="nom" placeholder="Nom du service" required>
        <input type="text" name="description" placeholder="Description">
        <input type="number" name="duree_estimee" placeholder="Durée estimée (min)">
        <button type="submit" class="btn btn-primary">Ajouter</button>
      </form>

      <?php if (empty($services)): ?>
        <p class="pa-empty">Aucun service enregistré.</p>
      <?php else: ?>
        <table class="pa-table">
          <thead><tr><th>Nom</th><th>Description</th><th>Durée (min)</th><th></th></tr></thead>
          <tbody>
            <?php foreach ($services as $s): ?>
              <tr>
                <td><?= htmlspecialchars($s['nom']) ?></td>
                <td><?= htmlspecialchars($s['description'] ?? '') ?></td>
                <td><?= (int)$s['duree_estimee'] ?></td>
                <td class="pa-actions">
                  <button class="btn btn-danger btn-small" onclick="deletePaItem('/api/pressing/services/<?= $s['id'] ?>/delete', 'service')">Supprimer</button>
                </td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      <?php endif; ?>
    </div>

    <!-- Tarifs -->
    <div id="pa-tab-tarifs" class="pa-tab-content">
      <h3>Grille tarifaire</h3>
      <form class="pa-form" onsubmit="return createPaTarif(event)">
        <select name="service_id" required>
          <option value="">Choisir un service</option>
          <?php foreach ($services as $s): ?>
            <option value="<?= $s['id'] ?>"><?= htmlspecialchars($s['nom']) ?></option>
          <?php endforeach; ?>
        </select>
        <input type="text" name="article_type" placeholder="Type d'article" required>
        <input type="number" step="0.01" name="prix_unitaire" placeholder="Prix unitaire" required>
        <button type="submit" class="btn btn-primary">Ajouter</button>
      </form>

      <?php if (empty($tarifs)): ?>
        <p class="pa-empty">Aucun tarif enregistré.</p>
      <?php else: ?>
        <table class="pa-table">
          <thead><tr><th>Service</th><th>Type d'article</th><th>Prix</th><th></th></tr></thead>
          <tbody>
            <?php foreach ($tarifs as $t): ?>
              <tr>
                <td><?= htmlspecialchars($t['service_nom'] ?? '—') ?></td>
                <td><?= htmlspecialchars($t['article_type']) ?></td>
                <td><?= number_format((float)$t['prix_unitaire'], 2) ?></td>
                <td class="pa-actions">
                  <button class="btn btn-danger btn-small" onclick="deletePaItem('/api/pressing/tarifs/<?= $t['id'] ?>/delete', 'tarif')">Supprimer</button>
                </td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      <?php endif; ?>
    </div>

    <!-- Consommables -->
    <div id="pa-tab-consumables" class="pa-tab-content">
      <h3>Consommables de nettoyage</h3>
      <form class="pa-form" onsubmit="return createPaConsumable(event)">
        <input type="text" name="nom" placeholder="Nom" required>
        <input type="number" step="0.001" name="quantite" placeholder="Quantité" required>
        <input type="text" name="unite" placeholder="Unité" value="unité">
        <input type="number" step="0.001" name="stock_minimum" placeholder="Stock minimum" required>
        <button type="submit" class="btn btn-primary">Ajouter</button>
      </form>

      <?php if (empty($consumables)): ?>
        <p class="pa-empty">Aucun consommable enregistré.</p>
      <?php else: ?>
        <table class="pa-table">
          <thead><tr><th>Nom</th><th>Quantité</th><th>Unité</th><th>Stock min.</th><th></th></tr></thead>
          <tbody>
            <?php foreach ($consumables as $c): ?>
              <tr>
                <td><?= htmlspecialchars($c['nom']) ?></td>
                <td><?= number_format((float)$c['quantite'], 3) ?></td>
                <td><?= htmlspecialchars($c['unite']) ?></td>
                <td><?= number_format((float)$c['stock_minimum'], 3) ?></td>
                <td class="pa-actions">
                  <button class="btn btn-danger btn-small" onclick="deletePaItem('/api/pressing/consumables/<?= $c['id'] ?>/delete', 'consommable')">Supprimer</button>
                </td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      <?php endif; ?>
    </div>
  </div>
</div>

<script>
  function switchPaTab(tab) {
    document.querySelectorAll('.pa-tab').forEach(t => t.classList.remove('active'));
    document.querySelectorAll('.pa-tab-content').forEach(c => c.classList.remove('active'));
    document.querySelector(`.pa-tab[onclick*="'${tab}'"]`).classList.add('active');
    document.getElementById('pa-tab-' + tab).classList.add('active');
  }

  function changePaShop() {
    const shopId = document.getElementById('pa-shop-id')?.value;
    if (shopId) {
      window.location.href = '/pressing/admin?shop_id=' + encodeURIComponent(shopId);
    }
  }

  async function paPost(url, body) {
    const res = await fetch(url, {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify(body)
    });
    return res.json();
  }

  async function createPaService(e) {
    e.preventDefault();
    const f = e.target;
    const data = {
      nom: f.nom.value,
      description: f.description.value,
      duree_estimee: f.duree_estimee.value ? parseInt(f.duree_estimee.value) : 0
    };
    const r = await paPost('/api/pressing/services', data);
    if (r.success) location.reload();
    else alert(r.error || 'Erreur');
    return false;
  }

  async function createPaTarif(e) {
    e.preventDefault();
    const f = e.target;
    const data = {
      service_id: parseInt(f.service_id.value),
      article_type: f.article_type.value,
      prix_unitaire: parseFloat(f.prix_unitaire.value)
    };
    const r = await paPost('/api/pressing/tarifs', data);
    if (r.success) location.reload();
    else alert(r.error || 'Erreur');
    return false;
  }

  async function createPaConsumable(e) {
    e.preventDefault();
    const f = e.target;
    const data = {
      nom: f.nom.value,
      quantite: parseFloat(f.quantite.value),
      unite: f.unite.value,
      stock_minimum: parseFloat(f.stock_minimum.value)
    };
    const r = await paPost('/api/pressing/consumables', data);
    if (r.success) location.reload();
    else alert(r.error || 'Erreur');
    return false;
  }

  async function deletePaItem(url, label) {
    if (!confirm('Supprimer ce ' + label + ' ?')) return;
    const r = await paPost(url, {});
    if (r.success) location.reload();
    else alert(r.error || 'Erreur');
  }
</script>
