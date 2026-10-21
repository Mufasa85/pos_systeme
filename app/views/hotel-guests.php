<?php
/**
 * Page Hôtel > Guests (gestion des clients ayant séjourné).
 *
 *  - Liste paginée (25 par page) avec recherche par nom/tél/pièce
 *  - Stats : nb séjours + dernier séjour
 *  - Vue détaillée du guest : infos + liste de ses séjours
 *  - Permissions :
 *      manager : créer, éditer
 *      réceptionniste + gouvernante : lecture seule
 *
 * Pas de suppression (historique comptable) — RGPD : anonymisation prévue plus tard.
 */
$page = 'hotel-guests';
?>
<style>
  /* Conteneur large & spacieux (SaaS moderne), reste responsive. */
  .hotel-gn-wrap {
    max-width: 1600px;
    width: 100%;
    margin: 1.25rem auto;
    padding: 0 1.25rem;
  }
  @media (max-width: 640px) {
    .hotel-gn-wrap { padding: 0 .75rem; }
  }
  .hotel-gn-card { background: var(--card); border-radius: var(--radius); box-shadow: var(--shadow); padding: 1.25rem; }
  .hotel-gn-toolbar { display: flex; gap: .5rem; align-items: center; flex-wrap: wrap; margin-bottom: 1rem; }
  .hotel-gn-toolbar input[type=text], .hotel-gn-toolbar select {
    padding: 6px 10px; border-radius: var(--radius); border: 1px solid var(--border); background: #fff; font-size: .85rem;
  }
  .hotel-gn-table thead th { font-size: .7rem; text-transform: uppercase; color: var(--muted); }
  .hotel-gn-empty { padding: 3rem; text-align: center; color: var(--muted); }

  /* Pagination */
  .hotel-gn-pager { display: flex; justify-content: space-between; align-items: center; margin-top: 1rem; font-size: .8rem; color: var(--muted); }
  .hotel-gn-pager button { padding: 4px 10px; border-radius: var(--radius); border: 1px solid var(--border); background: #fff; cursor: pointer; }
  .hotel-gn-pager button:disabled { opacity: .5; cursor: not-allowed; }

  /* Drawer */
  .hotel-drawer-backdrop { position: fixed; inset: 0; background: rgba(0,0,0,.4); z-index: 9000; display: none; }
  .hotel-drawer-backdrop.open { display: block; }
  .hotel-drawer { position: fixed; top: 0; right: 0; height: 100vh; width: 560px; max-width: 100vw; background: var(--card); box-shadow: var(--shadow-lg); transform: translateX(100%); transition: transform .25s ease; z-index: 9001; display: flex; flex-direction: column; }
  .hotel-drawer.open { transform: translateX(0); }
  .hotel-drawer-head { padding: 1rem 1.25rem; border-bottom: 1px solid var(--border); display: flex; justify-content: space-between; align-items: center; }
  .hotel-drawer-head h3 { margin: 0; font-size: 1.05rem; color: var(--foreground); }
  .hotel-drawer-close { background: transparent; border: 0; cursor: pointer; color: var(--muted); padding: 4px; border-radius: var(--radius); }
  .hotel-drawer-close:hover { background: var(--background); color: var(--foreground); }
  .hotel-drawer-body { flex: 1; overflow-y: auto; padding: 1.25rem; }
  .hotel-drawer-foot { padding: .85rem 1.25rem; border-top: 1px solid var(--border); display: flex; gap: .5rem; justify-content: flex-end; background: var(--background); }

  .hotel-alert { padding: .65rem .85rem; border-radius: var(--radius); margin-bottom: 1rem; font-size: .85rem; }
  .hotel-alert.error { background: #fee2e2; color: #991b1b; }
  .hotel-alert.success { background: #dcfce7; color: #166534; }
  .hotel-alert.hidden { display: none; }

  .hotel-gn-stats { display: flex; gap: 1rem; margin-bottom: 1rem; font-size: .85rem; }
  .hotel-gn-stat { background: var(--background); padding: .5rem .85rem; border-radius: var(--radius); }
  .hotel-gn-stat strong { font-size: 1.1rem; color: var(--foreground); display: block; }
  .hotel-gn-stat span { color: var(--muted); }

  .hotel-gn-sejours { margin-top: 1rem; }
  .hotel-gn-sejour-row {
    display: flex; justify-content: space-between; align-items: center;
    padding: .55rem .75rem; border: 1px solid var(--border); border-radius: var(--radius);
    margin-bottom: .35rem; font-size: .82rem;
  }
  .hotel-gn-sejour-statut { font-size: .7rem; }
  .hotel-gn-statut-reserve   { background: #dbeafe; color: #1e40af; }
  .hotel-gn-statut-en_cours  { background: #dcfce7; color: #166534; }
  .hotel-gn-statut-termine   { background: #f1f5f9; color: #475569; }
  .hotel-gn-statut-annule    { background: #fee2e2; color: #991b1b; }
  .hotel-gn-statut-no_show   { background: #fef3c7; color: #92400e; }

  .hotel-readonly-banner {
    background: #fef3c7; color: #92400e;
    padding: .65rem .85rem; border-radius: var(--radius);
    margin-bottom: 1rem; font-size: .82rem;
    display: flex; gap: .5rem; align-items: center;
  }
</style>

<main class="hotel-gn-wrap">
  <div class="hotel-gn-card">
    <div class="page-header" style="display:flex;justify-content:space-between;align-items:center;margin-bottom:1rem;">
      <div>
        <h2 style="margin:0;color:var(--foreground);display:flex;align-items:center;gap:.5rem;">
        <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" style="color:var(--primary);">
          <path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"></path>
          <circle cx="9" cy="7" r="4"></circle>
          <path d="M23 21v-2a4 4 0 0 0-3-3.87"></path>
          <path d="M16 3.13a4 4 0 0 1 0 7.75"></path>
        </svg>
        Guests
      </h2>
        <div style="font-size:.8rem;color:var(--muted);margin-top:4px;">
          Clients ayant séjourné (ou réservé) à l'hôtel. Recherche par nom, téléphone ou pièce d'identité.
        </div>
      </div>
      <div style="display:flex; gap:.5rem; flex-wrap:wrap;">
        <button type="button" class="btn btn-primary js-new" id="btn-new-guest" style="display:none;">
          <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
            <line x1="12" y1="5" x2="12" y1="19"></line>
            <line x1="5" y1="12" x2="19" y2="12"></line>
          </svg>
          Nouveau guest
        </button>
        <button type="button" class="btn btn-ghost js-new-from-client" id="btn-new-guest-from-client" style="display:none;">
          <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
            <path d="M16 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"></path>
            <circle cx="8.5" cy="7" r="4"></circle>
            <line x1="20" y1="8" x2="20" y2="14"></line>
            <line x1="23" y1="11" x2="17" y2="11"></line>
          </svg>
          Depuis un client
        </button>
      </div>
    </div>

    <div id="hotel-alert-page" class="hotel-alert hidden"></div>
    <div id="hotel-gn-readonly" class="hotel-readonly-banner hidden">
      <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
        <circle cx="12" cy="12" r="10"></circle><line x1="12" y1="8" x2="12" y1="12"></line><line x1="12" y1="16" x2="12.01" y1="16"></line>
      </svg>
      <span>Lecture seule — la création et l'édition sont réservées au manager.</span>
    </div>

    <div class="hotel-gn-toolbar">
      <input type="text" id="filter-q" placeholder="Rechercher (nom, prénom, téléphone, pièce d'identité)…">
      <select id="filter-limit">
        <option value="25">25 / page</option>
        <option value="50">50 / page</option>
        <option value="100">100 / page</option>
      </select>
      <button type="button" class="btn btn-ghost btn-small" id="btn-clear">Réinitialiser</button>
    </div>

    <div class="table-container">
      <table class="data-table hotel-gn-table" style="width:100%;border-collapse:collapse;">
        <thead>
          <tr>
            <th>Nom</th>
            <th>Téléphone</th>
            <th>Pièce d'identité</th>
            <th>Nationalité</th>
            <th style="text-align:right;">Séjours</th>
            <th>Dernier séjour</th>
            <th style="text-align:right;">Actions</th>
          </tr>
        </thead>
        <tbody id="hotel-gn-tbody">
          <tr><td colspan="7" class="hotel-gn-empty">Chargement…</td></tr>
        </tbody>
      </table>
    </div>

    <div class="hotel-gn-pager">
      <span id="pager-info">—</span>
      <div>
        <button type="button" id="pager-prev">‹ Précédent</button>
        <button type="button" id="pager-next">Suivant ›</button>
      </div>
    </div>
  </div>
</main>

<!-- Drawer : création, édition, vue détaillée -->
<div class="hotel-drawer-backdrop" id="hotel-gn-backdrop"></div>
<aside class="hotel-drawer" id="hotel-gn-drawer" role="dialog" aria-modal="true" aria-labelledby="hotel-gn-drawer-title">
  <header class="hotel-drawer-head">
    <h3 id="hotel-gn-drawer-title">Guest</h3>
    <button type="button" class="hotel-drawer-close" id="hotel-gn-drawer-close" aria-label="Fermer">
      <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
        <line x1="18" y1="6" x2="6" y1="18"></line><line x1="6" y1="6" x2="18" y1="18"></line>
      </svg>
    </button>
  </header>
  <div class="hotel-drawer-body">
    <div id="hotel-gn-drawer-alert" class="hotel-alert hidden"></div>
    <div id="hotel-gn-drawer-content">Chargement…</div>
  </div>
  <footer class="hotel-gn-drawer-foot" id="hotel-gn-drawer-foot" style="display:none;">
    <button type="button" class="btn btn-ghost" id="hotel-gn-drawer-cancel">Annuler</button>
    <button type="button" class="btn btn-primary" id="hotel-gn-drawer-save">Enregistrer</button>
  </footer>
</aside>

<script src="/assets/js/hotel-guests.js?v=2"></script>
