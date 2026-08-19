<style>
  .rr-kpis { display:grid; grid-template-columns:repeat(auto-fit,minmax(180px,1fr)); gap:1rem; margin-top:1rem }
  .rr-kpi { background:var(--card,#fff); border:1px solid var(--border,#e2e8f0); border-radius:10px; padding:1.1rem }
  .rr-kpi-label { font-size:.78rem; color:var(--muted,#64748b) }
  .rr-kpi-value { font-size:1.4rem; font-weight:700; margin-top:.3rem; color:var(--primary,#0B5E88) }
  .rr-section { margin-top:2rem }
  .rr-section h3 { font-size:1rem; margin-bottom:.75rem }
  .rr-table { width:100%; border-collapse:collapse; background:var(--card,#fff); border:1px solid var(--border,#e2e8f0); border-radius:10px; overflow:hidden }
  .rr-table th, .rr-table td { padding:.6rem .9rem; text-align:left; font-size:.85rem; border-bottom:1px solid var(--border,#f1f5f9) }
  .rr-table th { background:#f8fafc; font-weight:600; color:var(--muted,#64748b); font-size:.75rem; text-transform:uppercase }
</style>

<div id="page-restaurant-rapports" class="page <?= $page == 'restaurant-rapports' ? 'active' : '' ?>">
  <div class="page-header">
    <h2>
      <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="margin-right:8px;vertical-align:middle">
        <path d="M3 3v18h18"></path>
        <path d="M18.7 8l-5.1 5.2-2.8-2.7L7 14.3"></path>
      </svg>
      Rapports Restaurant
    </h2>
  </div>

  <div class="rr-kpis">
    <div class="rr-kpi"><div class="rr-kpi-label">Commandes payées</div><div class="rr-kpi-value" id="rr-nb-commandes">—</div></div>
    <div class="rr-kpi"><div class="rr-kpi-label">Chiffre d'affaires</div><div class="rr-kpi-value" id="rr-ca">—</div></div>
  </div>

  <div class="rr-section">
    <h3>Plats les plus vendus</h3>
    <table class="rr-table">
      <thead><tr><th>Plat</th><th>Quantité vendue</th><th>CA généré</th></tr></thead>
      <tbody id="rr-top-plats"><tr><td colspan="3">Chargement...</td></tr></tbody>
    </table>
  </div>

  <div class="rr-section">
    <h3>Ventes par serveur</h3>
    <table class="rr-table">
      <thead><tr><th>Serveur</th><th>Nb ventes</th><th>Total</th></tr></thead>
      <tbody id="rr-par-serveur"><tr><td colspan="3">Chargement...</td></tr></tbody>
    </table>
  </div>

  <div class="rr-section">
    <h3>Ventes par jour (30 derniers jours)</h3>
    <table class="rr-table">
      <thead><tr><th>Date</th><th>Nb ventes</th><th>Total</th></tr></thead>
      <tbody id="rr-par-jour"><tr><td colspan="3">Chargement...</td></tr></tbody>
    </table>
  </div>

  <div class="rr-section">
    <h3>Ventes par mois (12 derniers mois)</h3>
    <table class="rr-table">
      <thead><tr><th>Mois</th><th>Nb ventes</th><th>Total</th></tr></thead>
      <tbody id="rr-par-mois"><tr><td colspan="3">Chargement...</td></tr></tbody>
    </table>
  </div>
</div>

<script>
function rrFormatMoney(n) {
  return Number(n).toLocaleString('fr-FR', { minimumFractionDigits: 2, maximumFractionDigits: 2 }) + ' Fc';
}

async function loadRestaurantReports() {
  try {
    const res = await fetch('/api/restaurant/rapports');
    const data = await res.json();

    document.getElementById('rr-nb-commandes').textContent = data.nb_commandes ?? 0;
    document.getElementById('rr-ca').textContent = rrFormatMoney(data.ca ?? 0);

    const topPlatsBody = document.getElementById('rr-top-plats');
    topPlatsBody.innerHTML = (data.top_plats || []).length
      ? data.top_plats.map(p => `<tr><td>${p.nom}</td><td>${p.quantite_totale}</td><td>${rrFormatMoney(p.ca_genere)}</td></tr>`).join('')
      : '<tr><td colspan="3">Aucune donnée</td></tr>';

    const parServeurBody = document.getElementById('rr-par-serveur');
    parServeurBody.innerHTML = (data.par_serveur || []).length
      ? data.par_serveur.map(s => `<tr><td>${s.nom_complet || 'N/A'}</td><td>${s.nb}</td><td>${rrFormatMoney(s.total)}</td></tr>`).join('')
      : '<tr><td colspan="3">Aucune donnée</td></tr>';

    const parJourBody = document.getElementById('rr-par-jour');
    parJourBody.innerHTML = (data.par_jour || []).length
      ? data.par_jour.map(j => `<tr><td>${j.jour}</td><td>${j.nb}</td><td>${rrFormatMoney(j.total)}</td></tr>`).join('')
      : '<tr><td colspan="3">Aucune donnée</td></tr>';

    const parMoisBody = document.getElementById('rr-par-mois');
    parMoisBody.innerHTML = (data.par_mois || []).length
      ? data.par_mois.map(m => `<tr><td>${m.mois}</td><td>${m.nb}</td><td>${rrFormatMoney(m.total)}</td></tr>`).join('')
      : '<tr><td colspan="3">Aucune donnée</td></tr>';
  } catch (e) {
    console.error('Erreur chargement rapports restaurant', e);
  }
}

loadRestaurantReports();
</script>
