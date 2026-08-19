<style>
  .rc-tables-grid { display:grid; grid-template-columns:repeat(auto-fill,minmax(160px,1fr)); gap:1rem; margin-top:1rem }
  .rc-table-pick { border-radius:10px; padding:1rem; color:#fff; text-align:center; text-decoration:none; font-weight:700; transition:transform .15s }
  .rc-table-pick:hover { transform:translateY(-2px) }
  .rc-table-pick.etat-libre { background:linear-gradient(135deg,#16a34a,#15803d) }
  .rc-table-pick.etat-occupee { background:linear-gradient(135deg,#dc2626,#b91c1c) }
  .rc-table-pick.etat-reservee { background:linear-gradient(135deg,#d97706,#b45309) }
  .rc-table-pick.etat-nettoyage { background:linear-gradient(135deg,#64748b,#475569) }

  .rc-layout { display:grid; grid-template-columns:1.6fr 1fr; gap:1.5rem; margin-top:1rem; align-items:start }
  @media (max-width:900px) { .rc-layout { grid-template-columns:1fr } }

  .rc-cat-tabs { display:flex; gap:.5rem; flex-wrap:wrap; margin-bottom:1rem }
  .rc-cat-tab { padding:.4rem .9rem; border-radius:20px; background:var(--card,#fff); border:1px solid var(--border,#e2e8f0); font-size:.82rem; cursor:pointer }
  .rc-cat-tab.active { background:var(--primary,#0B5E88); color:#fff; border-color:var(--primary,#0B5E88) }

  .rc-menu-grid { display:grid; grid-template-columns:repeat(auto-fill,minmax(160px,1fr)); gap:1rem }
  .rc-menu-item { background:var(--card,#fff); border:1px solid var(--border,#e2e8f0); border-radius:10px; padding:.8rem; cursor:pointer; transition:box-shadow .15s }
  .rc-menu-item:hover { box-shadow:0 4px 14px rgba(0,0,0,.08) }
  .rc-menu-item.disabled { opacity:.45; cursor:not-allowed }
  .rc-menu-item-nom { font-weight:600; font-size:.9rem }
  .rc-menu-item-prix { color:var(--primary,#0B5E88); font-weight:700; font-size:.85rem; margin-top:.3rem }

  .rc-cart { background:var(--card,#fff); border:1px solid var(--border,#e2e8f0); border-radius:10px; padding:1rem; position:sticky; top:1rem }
  .rc-cart-line { display:flex; align-items:center; gap:.5rem; padding:.5rem 0; border-bottom:1px solid var(--border,#f1f5f9) }
  .rc-cart-line-nom { flex:1; font-size:.85rem }
  .rc-cart-qty { display:flex; align-items:center; gap:.4rem }
  .rc-cart-qty button { width:22px; height:22px; border-radius:4px; border:1px solid var(--border,#e2e8f0); background:#fff; cursor:pointer }
  .rc-cart-remove { color:#dc2626; background:none; border:none; cursor:pointer; padding:2px }
  .rc-cart-totals { margin-top:1rem; font-size:.88rem }
  .rc-cart-totals-row { display:flex; justify-content:space-between; padding:.25rem 0 }
  .rc-cart-totals-row.total { font-weight:700; font-size:1.05rem; border-top:1px solid var(--border,#e2e8f0); padding-top:.5rem; margin-top:.25rem }
  .rc-cart-actions { display:flex; flex-direction:column; gap:.5rem; margin-top:1rem }
</style>

<div id="page-restaurant-commandes" class="page <?= $page == 'restaurant-commandes' ? 'active' : '' ?>">
  <div class="page-header">
    <h2>
      <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="margin-right:8px;vertical-align:middle">
        <line x1="12" y1="1" x2="12" y2="23"></line>
        <path d="M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"></path>
      </svg>
      Restaurant — Commandes
      <?php if ($selectedTable): ?> — Table <?= htmlspecialchars($selectedTable['numero']) ?><?php endif; ?>
    </h2>
    <?php if ($selectedTable): ?>
      <a href="/restaurant/commandes" class="btn btn-secondary">← Changer de table</a>
    <?php endif; ?>
  </div>

  <?php if (!$selectedTable): ?>
    <p style="color:var(--muted,#64748b);font-size:.85rem">Sélectionnez une table pour démarrer ou continuer une commande.</p>
    <div class="rc-tables-grid">
      <?php foreach ($restaurantTables as $t): ?>
        <a href="/restaurant/commandes?table_id=<?= $t['id'] ?>" class="rc-table-pick etat-<?= htmlspecialchars($t['etat']) ?>">
          Table <?= htmlspecialchars($t['numero']) ?><br>
          <span style="font-size:.7rem;font-weight:400;opacity:.9"><?= htmlspecialchars(ucfirst($t['etat'])) ?></span>
        </a>
      <?php endforeach; ?>
    </div>
  <?php else: ?>
    <div class="rc-layout">
      <div>
        <div class="rc-cat-tabs" id="rc-cat-tabs">
          <div class="rc-cat-tab active" data-cat="all" onclick="filterRcMenu('all', this)">Tous</div>
          <?php foreach ($restaurantCategories as $cat): ?>
            <div class="rc-cat-tab" data-cat="<?= $cat['id'] ?>" onclick="filterRcMenu(<?= $cat['id'] ?>, this)"><?= htmlspecialchars($cat['nom']) ?></div>
          <?php endforeach; ?>
        </div>
        <div class="rc-menu-grid" id="rc-menu-grid">
          <?php foreach ($restaurantMenuItems as $item): ?>
            <div class="rc-menu-item <?= $item['disponible'] ? '' : 'disabled' ?>" data-cat="<?= $item['categorie_id'] ?>"
                 onclick="<?= $item['disponible'] ? "addRcItem({$item['id']}, '" . htmlspecialchars($item['nom'], ENT_QUOTES) . "', {$item['prix']})" : '' ?>">
              <div class="rc-menu-item-nom"><?= htmlspecialchars($item['nom']) ?></div>
              <div class="rc-menu-item-prix"><?= number_format($item['prix'], 2) ?> Fc</div>
              <?php if (!$item['disponible']): ?><div style="font-size:.7rem;color:#dc2626;margin-top:.2rem">Indisponible</div><?php endif; ?>
            </div>
          <?php endforeach; ?>
        </div>
      </div>

      <div class="rc-cart">
        <h3 style="margin:0 0 .75rem;font-size:1rem">Commande en cours</h3>
        <div id="rc-cart-lines">
          <?php if (empty($currentOrderDetails)): ?>
            <p style="color:var(--muted,#94a3b8);font-size:.85rem" id="rc-empty-msg">Aucun plat ajouté</p>
          <?php else: ?>
            <?php foreach ($currentOrderDetails as $d): ?>
              <div class="rc-cart-line" data-detail-id="<?= $d['id'] ?>">
                <span class="rc-cart-line-nom"><?= htmlspecialchars($d['plat_nom']) ?></span>
                <div class="rc-cart-qty">
                  <button onclick="changeRcQty(<?= $d['id'] ?>, -1)">-</button>
                  <span><?= (int)$d['quantite'] ?></span>
                  <button onclick="changeRcQty(<?= $d['id'] ?>, 1)">+</button>
                </div>
                <button class="rc-cart-remove" onclick="removeRcItem(<?= $d['id'] ?>)">✕</button>
              </div>
            <?php endforeach; ?>
          <?php endif; ?>
        </div>

        <div class="rc-cart-totals">
          <div class="rc-cart-totals-row"><span>Sous-total</span><span id="rc-sous-total"><?= number_format($currentOrder['sous_total'] ?? 0, 2) ?> Fc</span></div>
          <div class="rc-cart-totals-row"><span>Taxes</span><span id="rc-taxes"><?= number_format($currentOrder['taxes'] ?? 0, 2) ?> Fc</span></div>
          <div class="rc-cart-totals-row">
            <span>Remise</span>
            <input type="number" id="rc-remise-input" min="0" step="0.01" value="<?= number_format($currentOrder['remise'] ?? 0, 2, '.', '') ?>" style="width:90px;text-align:right;padding:2px 4px;border:1px solid #ddd;border-radius:4px" onchange="updateRcRemise(this.value)">
          </div>
          <div class="rc-cart-totals-row total"><span>Total</span><span id="rc-total"><?= number_format($currentOrder['total'] ?? 0, 2) ?> Fc</span></div>
        </div>

        <div class="rc-cart-actions">
          <button class="btn btn-primary" onclick="sendRcToKitchen()" <?= empty($currentOrderDetails) ? 'disabled' : '' ?> id="rc-btn-kitchen">Envoyer en cuisine</button>
          <button class="btn btn-secondary" onclick="openRcPaymentModal()" <?= empty($currentOrderDetails) ? 'disabled' : '' ?> id="rc-btn-pay">Paiement</button>
          <button class="btn" style="color:#dc2626" onclick="cancelRcOrder()">Annuler la commande</button>
        </div>
      </div>
    </div>
  <?php endif; ?>
</div>

<!-- Modal Paiement -->
<div id="rc-payment-modal" class="modal" style="display:none; position:fixed; top:0; left:0; width:100%; height:100%; background:rgba(0,0,0,0.5); z-index:1000; align-items:center; justify-content:center;">
  <div class="modal-content" style="background:white; padding:2rem; border-radius:8px; width:400px; max-width:90%;">
    <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:1.5rem;">
      <h3 style="margin:0;">Paiement</h3>
      <button type="button" onclick="closeRcPaymentModal()" style="background:none; border:none; font-size:1.5rem; cursor:pointer;">&times;</button>
    </div>
    <div style="margin-bottom:1rem;">
      <label style="display:block; margin-bottom:0.5rem; font-weight:500;">Mode de paiement</label>
      <select id="rc-payment-mode" style="width:100%; padding:0.5rem; border:1px solid #ddd; border-radius:4px;">
        <option value="especes">Espèces</option>
        <option value="carte">Carte</option>
        <option value="mobile_money">Mobile Money</option>
        <option value="mixte">Mixte</option>
      </select>
    </div>
    <div style="margin-bottom:1rem;">
      <label style="display:block; margin-bottom:0.5rem; font-weight:500;">Montant reçu</label>
      <input type="number" id="rc-payment-montant" min="0" step="0.01" style="width:100%; padding:0.5rem; border:1px solid #ddd; border-radius:4px;">
    </div>
    <div id="rc-payment-error" style="color:#e53e3e;font-size:.85rem;margin-bottom:.5rem;display:none"></div>
    <div style="display:flex; gap:0.5rem; justify-content:flex-end; margin-top:1.5rem;">
      <button type="button" onclick="closeRcPaymentModal()" class="btn btn-secondary">Annuler</button>
      <button type="button" class="btn btn-primary" onclick="confirmRcPayment()">Confirmer le paiement</button>
    </div>
  </div>
</div>

<script>
const RC_ORDER_ID = <?= json_encode($currentOrder['id'] ?? null) ?>;
const RC_TABLE_ID = <?= json_encode($selectedTable['id'] ?? null) ?>;

async function ensureRcOrder() {
  if (RC_ORDER_ID) return RC_ORDER_ID;
  const res = await fetch('/api/restaurant/commandes', {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify({ table_id: RC_TABLE_ID })
  });
  const data = await res.json();
  if (data.id) {
    window.location.href = `/restaurant/commandes?table_id=${RC_TABLE_ID}`;
  }
  return data.id;
}

function filterRcMenu(cat, el) {
  document.querySelectorAll('#rc-cat-tabs .rc-cat-tab').forEach(t => t.classList.remove('active'));
  el.classList.add('active');
  document.querySelectorAll('#rc-menu-grid .rc-menu-item').forEach(card => {
    card.style.display = (cat === 'all' || card.dataset.cat == cat) ? '' : 'none';
  });
}

async function addRcItem(menuItemId, nom, prix) {
  const orderId = await ensureRcOrder();
  if (!orderId) return;
  try {
    const res = await fetch(`/api/restaurant/commandes/${orderId}/items`, {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ menu_item_id: menuItemId, quantite: 1 })
    });
    const data = await res.json();
    if (!res.ok || data.error) {
      alert(data.error || 'Erreur lors de l\'ajout');
      return;
    }
    window.location.reload();
  } catch (e) {
    alert('Erreur réseau');
  }
}

async function changeRcQty(detailId, delta) {
  const line = document.querySelector(`.rc-cart-line[data-detail-id="${detailId}"]`);
  const qtySpan = line.querySelector('.rc-cart-qty span');
  let qty = parseInt(qtySpan.textContent, 10) + delta;
  if (qty < 1) {
    return removeRcItem(detailId);
  }
  try {
    const res = await fetch(`/api/restaurant/commandes/items/${detailId}/update`, {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ quantite: qty })
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

async function removeRcItem(detailId) {
  try {
    const res = await fetch(`/api/restaurant/commandes/items/${detailId}/delete`, { method: 'POST' });
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

async function updateRcRemise(value) {
  if (!RC_ORDER_ID) return;
  try {
    await fetch(`/api/restaurant/commandes/${RC_ORDER_ID}/remise`, {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ remise: parseFloat(value) || 0 })
    });
    window.location.reload();
  } catch (e) {
    alert('Erreur réseau');
  }
}

async function sendRcToKitchen() {
  if (!RC_ORDER_ID) return;
  try {
    const res = await fetch(`/api/restaurant/commandes/${RC_ORDER_ID}/envoyer-cuisine`, { method: 'POST' });
    const data = await res.json();
    if (!res.ok || data.error) {
      alert(data.error || 'Erreur');
      return;
    }
    alert('Commande envoyée en cuisine !');
    window.location.reload();
  } catch (e) {
    alert('Erreur réseau');
  }
}

async function cancelRcOrder() {
  if (!RC_ORDER_ID) { window.location.href = '/restaurant/commandes'; return; }
  if (!confirm('Annuler cette commande ?')) return;
  try {
    const res = await fetch(`/api/restaurant/commandes/${RC_ORDER_ID}/annuler`, { method: 'POST' });
    const data = await res.json();
    if (!res.ok || data.error) {
      alert(data.error || 'Erreur');
      return;
    }
    window.location.href = '/restaurant/commandes';
  } catch (e) {
    alert('Erreur réseau');
  }
}

function openRcPaymentModal() {
  document.getElementById('rc-payment-montant').value = document.getElementById('rc-total').textContent.replace(/[^\d.]/g, '');
  document.getElementById('rc-payment-error').style.display = 'none';
  document.getElementById('rc-payment-modal').style.display = 'flex';
}
function closeRcPaymentModal() {
  document.getElementById('rc-payment-modal').style.display = 'none';
}
async function confirmRcPayment() {
  if (!RC_ORDER_ID) return;
  const mode = document.getElementById('rc-payment-mode').value;
  const montant = parseFloat(document.getElementById('rc-payment-montant').value) || 0;
  const errorEl = document.getElementById('rc-payment-error');

  try {
    const res = await fetch(`/api/restaurant/commandes/${RC_ORDER_ID}/paiement`, {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ payments: [{ type: mode, montant }] })
    });
    const data = await res.json();
    if (!res.ok || data.error) {
      errorEl.textContent = data.error || 'Erreur lors du paiement';
      errorEl.style.display = 'block';
      return;
    }
    window.location.href = `/facture?ref=${encodeURIComponent(data.numero_facture)}`;
  } catch (e) {
    errorEl.textContent = 'Erreur réseau';
    errorEl.style.display = 'block';
  }
}
</script>
