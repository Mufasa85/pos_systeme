<style>
  .pr-kpis { display:grid; grid-template-columns:repeat(auto-fit,minmax(180px,1fr)); gap:1rem; margin-top:1rem }
  .pr-kpi { background:var(--card,#fff); border:1px solid var(--border,#e2e8f0); border-radius:10px; padding:1.1rem }
  .pr-kpi-label { font-size:.78rem; color:var(--muted,#64748b) }
  .pr-kpi-value { font-size:1.4rem; font-weight:700; margin-top:.3rem; color:var(--primary,#0B5E88) }
  .pr-section { margin-top:2rem }
  .pr-section h3 { font-size:1rem; margin-bottom:.75rem }
  .pr-table { width:100%; border-collapse:collapse; background:var(--card,#fff); border:1px solid var(--border,#e2e8f0); border-radius:10px; overflow:hidden }
  .pr-table th, .pr-table td { padding:.6rem .9rem; text-align:left; font-size:.85rem; border-bottom:1px solid #f1f5f9 }
  .pr-table th { background:#f8fafc; font-weight:600; color:var(--muted,#64748b); font-size:.75rem; text-transform:uppercase }
</style>

<div id="page-pressing-rapports" class="page <?= $page == 'pressing-rapports' ? 'active' : '' ?>">
  <div class="page-header">
    <h2>
      <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="margin-right:8px;vertical-align:middle">
        <path d="M3 3v18h18"></path><path d="M18.7 8l-5.1 5.2-2.8-2.7L7 14.3"></path>
      </svg>
      Rapports Pressing
    </h2>
  </div>

  <div class="pr-kpis">
    <div class="pr-kpi"><div class="pr-kpi-label">Nombre de dépôts</div><div class="pr-kpi-value" id="pr-nb-depots">—</div></div>
    <div class="pr-kpi"><div class="pr-kpi-label">Revenus</div><div class="pr-kpi-value" id="pr-revenus">—</div></div>
  </div>

  <div class="pr-section">
    <h3>Services les plus utilisés</h3>
    <table class="pr-table">
      <thead><tr><th>Service</th><th>Nb utilisations</th><th>Total</th></tr></thead>
      <tbody id="pr-top-services"><tr><td colspan="3">Chargement...</td></tr></tbody>
    </table>
  </div>

  <div class="pr-section">
    <h3>Clients fidèles</h3>
    <table class="pr-table">
      <thead><tr><th>Client</th><th>Nb dépôts</th><th>Total dépensé</th></tr></thead>
      <tbody id="pr-top-clients"><tr><td colspan="3">Chargement...</td></tr></tbody>
    </table>
  </div>

  <div class="pr-section">
    <h3>Revenus journaliers (30 derniers jours)</h3>
    <table class="pr-table">
      <thead><tr><th>Date</th><th>Nb dépôts</th><th>Total</th></tr></thead>
      <tbody id="pr-par-jour"><tr><td colspan="3">Chargement...</td></tr></tbody>
    </table>
  </div>

  <div class="pr-section">
    <h3>Revenus mensuels (12 derniers mois)</h3>
    <table class="pr-table">
      <thead><tr><th>Mois</th><th>Nb dépôts</th><th>Total</th></tr></thead>
      <tbody id="pr-par-mois"><tr><td colspan="3">Chargement...</td></tr></tbody>
    </table>
  </div>
</div>

<script>
function prFormatMoney(n) {
  return Number(n).toLocaleString('fr-FR', { minimumFractionDigits: 2, maximumFractionDigits: 2 }) + ' Fc';
}

const PR_SERVICE_LABELS = {
  lavage: 'Lavage', repassage: 'Repassage', lavage_repassage: 'Lavage + Repassage',
  nettoyage_sec: 'Nettoyage à sec', detachage: 'Détachage', desinfection: 'Désinfection',
  blanchiment: 'Blanchiment', anti_odeur: 'Traitement anti-odeur', express: 'Express (24h)',
  pliage: 'Pliage', emballage_cintre: 'Emballage sur cintre'
};

async function loadPressingReports() {
  try {
    const res = await fetch('/api/pressing/rapports');
    const data = await res.json();

    document.getElementById('pr-nb-depots').textContent = data.nb_depots ?? 0;
    document.getElementById('pr-revenus').textContent = prFormatMoney(data.revenus ?? 0);

    document.getElementById('pr-top-services').innerHTML = (data.top_services || []).length
      ? data.top_services.map(s => `<tr><td>${PR_SERVICE_LABELS[s.service] || s.service}</td><td>${s.nb}</td><td>${prFormatMoney(s.total)}</td></tr>`).join('')
      : '<tr><td colspan="3">Aucune donnée</td></tr>';

    document.getElementById('pr-top-clients').innerHTML = (data.top_clients || []).length
      ? data.top_clients.map(c => `<tr><td>${c.nom_client || 'N/A'}</td><td>${c.nb_depots}</td><td>${prFormatMoney(c.total_depense)}</td></tr>`).join('')
      : '<tr><td colspan="3">Aucune donnée</td></tr>';

    document.getElementById('pr-par-jour').innerHTML = (data.par_jour || []).length
      ? data.par_jour.map(j => `<tr><td>${j.jour}</td><td>${j.nb}</td><td>${prFormatMoney(j.total)}</td></tr>`).join('')
      : '<tr><td colspan="3">Aucune donnée</td></tr>';

    document.getElementById('pr-par-mois').innerHTML = (data.par_mois || []).length
      ? data.par_mois.map(m => `<tr><td>${m.mois}</td><td>${m.nb}</td><td>${prFormatMoney(m.total)}</td></tr>`).join('')
      : '<tr><td colspan="3">Aucune donnée</td></tr>';
  } catch (e) {
    console.error('Erreur chargement rapports pressing', e);
  }
}

loadPressingReports();
</script>
