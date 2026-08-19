<style>
  .rk-grid { display:grid; grid-template-columns:repeat(auto-fill,minmax(280px,1fr)); gap:1.25rem; margin-top:1rem }
  .rk-order-card { background:var(--card,#fff); border:1px solid var(--border,#e2e8f0); border-radius:10px; overflow:hidden }
  .rk-order-header { display:flex; justify-content:space-between; align-items:center; padding:.8rem 1rem; background:#0B5E88; color:#fff }
  .rk-order-header strong { font-size:1rem }
  .rk-order-time { font-size:.72rem; opacity:.85 }
  .rk-plat { padding:.75rem 1rem; border-bottom:1px solid var(--border,#f1f5f9) }
  .rk-plat:last-child { border-bottom:none }
  .rk-plat-top { display:flex; justify-content:space-between; align-items:center }
  .rk-plat-nom { font-weight:600; font-size:.9rem }
  .rk-plat-qty { color:var(--muted,#64748b); font-size:.8rem }
  .rk-plat-comment { font-size:.75rem; color:var(--muted,#94a3b8); margin-top:.2rem; font-style:italic }
  .rk-badge { display:inline-flex; align-items:center; gap:4px; font-size:.68rem; font-weight:700; text-transform:uppercase; letter-spacing:.03em; padding:2px 8px; border-radius:20px; margin-top:.4rem }
  .rk-badge.en_attente { background:#fef3c7; color:#b45309 }
  .rk-badge.en_preparation { background:#dbeafe; color:#1d4ed8 }
  .rk-badge.pret { background:#dcfce7; color:#16a34a }
  .rk-badge.servi { background:#e2e8f0; color:#475569 }
  .rk-progress { height:5px; background:#e2e8f0; border-radius:3px; margin-top:.5rem; overflow:hidden }
  .rk-progress-bar { height:100%; background:#1d4ed8; transition:width 1s linear }
  .rk-plat-actions { margin-top:.5rem }
  .rk-plat-actions .btn { font-size:.72rem; padding:.3rem .7rem }
  .rk-empty { text-align:center; padding:3rem; color:var(--muted,#64748b) }
</style>

<div id="page-restaurant-cuisine" class="page <?= $page == 'restaurant-cuisine' ? 'active' : '' ?>">
  <div class="page-header">
    <h2>
      <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="margin-right:8px;vertical-align:middle">
        <circle cx="12" cy="12" r="10"></circle>
        <polyline points="12 6 12 12 16 14"></polyline>
      </svg>
      Écran Cuisine
    </h2>
    <span style="font-size:.78rem;color:var(--muted,#64748b)">Actualisation automatique toutes les 6s</span>
  </div>

  <div id="rk-grid" class="rk-grid">
    <div class="rk-empty">Chargement...</div>
  </div>
</div>

<script>
const RK_STATE_LABELS = { en_attente: 'En attente', en_preparation: 'En préparation', pret: 'Prêt', servi: 'Servi' };

function rkElapsedPercent(startedAt, tempsPrep) {
  if (!startedAt || !tempsPrep) return 0;
  const start = new Date(startedAt.replace(' ', 'T')).getTime();
  const durationMs = tempsPrep * 60000;
  const elapsed = Date.now() - start;
  return Math.max(0, Math.min(100, (elapsed / durationMs) * 100));
}

function renderRkOrders(orders) {
  const grid = document.getElementById('rk-grid');
  if (!orders.length) {
    grid.innerHTML = '<div class="rk-empty">Aucune commande en cuisine actuellement</div>';
    return;
  }

  grid.innerHTML = orders.map(order => `
    <div class="rk-order-card">
      <div class="rk-order-header">
        <strong>Table ${order.table_numero ?? '-'}</strong>
        <span class="rk-order-time">${new Date(order.created_at.replace(' ', 'T')).toLocaleTimeString('fr-FR', {hour:'2-digit', minute:'2-digit'})}</span>
      </div>
      ${order.plats.map(p => `
        <div class="rk-plat" data-detail-id="${p.detail_id}" data-statut="${p.statut}">
          <div class="rk-plat-top">
            <span class="rk-plat-nom">${p.nom}</span>
            <span class="rk-plat-qty">x${p.quantite}</span>
          </div>
          ${p.commentaire ? `<div class="rk-plat-comment">${p.commentaire}</div>` : ''}
          <div class="rk-badge ${p.statut}">${RK_STATE_LABELS[p.statut] || p.statut}</div>
          ${p.statut === 'en_preparation' ? `
            <div class="rk-progress"><div class="rk-progress-bar" style="width:${rkElapsedPercent(p.started_at, p.temps_preparation)}%"></div></div>
          ` : ''}
          <div class="rk-plat-actions">
            ${p.statut === 'en_attente' ? `<button class="btn btn-primary" onclick="rkStart(${p.detail_id})">Commencer</button>` : ''}
            ${p.statut === 'pret' ? `<button class="btn btn-secondary" onclick="rkMarkServed(${p.detail_id})">Marquer servi</button>` : ''}
          </div>
        </div>
      `).join('')}
    </div>
  `).join('');
}

async function loadRkOrders() {
  try {
    const res = await fetch('/api/restaurant/cuisine');
    const data = await res.json();
    renderRkOrders(Array.isArray(data) ? data : []);
  } catch (e) {
    console.error('Erreur chargement cuisine', e);
  }
}

async function rkStart(detailId) {
  try {
    const res = await fetch(`/api/restaurant/cuisine/${detailId}/commencer`, { method: 'POST' });
    const data = await res.json();
    if (!res.ok || data.error) {
      alert(data.error || 'Erreur');
      return;
    }
    loadRkOrders();
  } catch (e) {
    alert('Erreur réseau');
  }
}

async function rkMarkServed(detailId) {
  try {
    const res = await fetch(`/api/restaurant/cuisine/${detailId}/servi`, { method: 'POST' });
    const data = await res.json();
    if (!res.ok || data.error) {
      alert(data.error || 'Erreur');
      return;
    }
    loadRkOrders();
  } catch (e) {
    alert('Erreur réseau');
  }
}

loadRkOrders();
setInterval(loadRkOrders, 6000);

// Rafraîchit les barres de progression en continu sans attendre le prochain fetch
setInterval(() => {
  document.querySelectorAll('.rk-plat[data-statut="en_preparation"] .rk-progress-bar').forEach(bar => {
    const current = parseFloat(bar.style.width) || 0;
    if (current < 100) bar.style.width = Math.min(100, current + 0.5) + '%';
  });
}, 1000);
</script>
