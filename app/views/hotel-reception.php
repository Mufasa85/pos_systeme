<?php
/**
 * Page Hôtel > Réception.
 *
 * Affichage live de :
 *   - 3 cartes timeline : arrivées du jour, séjours en cours, départs du jour
 *   - Grille 5×4 des chambres avec leur état + guest si occupé
 *
 * Actions (manager + réceptionniste) :
 *   - Check-in / check-out depuis la timeline ou un tap sur une chambre
 *   - Marquer chambre "nettoyée" → revient à 'disponible'
 *   - Manager : marquer hors service
 *
 * Réceptionniste uniquement : aucune action de mutation lourde,
 * lecture seule sur les séjours d'autres boutiques (vu qu'il n'y en a
 * qu'une dans ce sprint).
 *
 * Auto-refresh toutes les 30 s.
 */
$page = 'hotel-reception';
?>
<style>
  .hotel-rec-wrap { max-width: 1280px; margin: 1rem auto; padding: 0 1rem; }
  .hotel-rec-card { background: var(--card); border-radius: var(--radius); box-shadow: var(--shadow); padding: 1.25rem; margin-bottom: 1rem; }

  /* Timeline 3 colonnes */
  .hotel-rec-timeline {
    display: grid;
    grid-template-columns: repeat(3, 1fr);
    gap: .75rem;
  }
  @media (max-width: 900px) {
    .hotel-rec-timeline { grid-template-columns: 1fr; }
  }
  .hotel-rec-tile {
    background: var(--background);
    border-radius: var(--radius);
    padding: .85rem 1rem;
    border: 1px solid var(--border);
  }
  .hotel-rec-tile h3 {
    margin: 0 0 .5rem 0;
    font-size: .85rem;
    text-transform: uppercase;
    color: var(--muted);
    display: flex; justify-content: space-between; align-items: center;
  }
  .hotel-rec-tile h3 .badge-count {
    background: var(--primary);
    color: #fff;
    padding: 1px 8px;
    border-radius: 999px;
    font-size: .7rem;
    font-weight: 700;
  }
  .hotel-rec-item {
    background: var(--card);
    border: 1px solid var(--border);
    border-radius: var(--radius);
    padding: .5rem .65rem;
    margin-bottom: .4rem;
    font-size: .82rem;
    display: flex; justify-content: space-between; align-items: center; gap: .5rem;
  }
  .hotel-rec-item .meta { font-size: .7rem; color: var(--muted); }
  .hotel-rec-item .actions { display: flex; gap: .25rem; flex-shrink: 0; }
  .hotel-rec-item-empty {
    font-size: .78rem; color: var(--muted); font-style: italic;
    padding: .35rem 0;
  }
  .btn-mini {
    padding: 3px 7px; font-size: .7rem;
    border-radius: var(--radius); border: 1px solid var(--border);
    background: var(--card); color: var(--foreground); cursor: pointer;
  }
  .btn-mini:hover { background: var(--background); }
  .btn-mini.primary { background: var(--primary); color: #fff; border-color: var(--primary); }
  .btn-mini.danger  { background: var(--danger); color: #fff; border-color: var(--danger); }
  .btn-mini.success { background: var(--success); color: #fff; border-color: var(--success); }

  /* Grille des chambres */
  .hotel-rec-grid {
    display: grid;
    grid-template-columns: repeat(5, 1fr);
    gap: .6rem;
  }
  @media (max-width: 900px) {
    .hotel-rec-grid { grid-template-columns: repeat(2, 1fr); }
  }
  .hotel-room-pad {
    background: var(--card);
    border: 2px solid var(--border);
    border-radius: var(--radius);
    padding: .7rem;
    cursor: pointer;
    transition: transform .12s, box-shadow .12s;
    display: flex; flex-direction: column; gap: .3rem;
    min-height: 92px;
  }
  .hotel-room-pad:hover { transform: translateY(-1px); box-shadow: var(--shadow); }
  .hotel-room-pad .room-num {
    font-size: 1.05rem;
    font-weight: 700;
    color: var(--foreground);
  }
  .hotel-room-pad .room-type { font-size: .7rem; color: var(--muted); text-transform: uppercase; }
  .hotel-room-pad .room-guest { font-size: .78rem; font-weight: 600; }
  .hotel-room-pad .room-when  { font-size: .7rem; color: var(--muted); }

  /* 5 états visuels (le 4e = occupée, inféré) */
  .hotel-room-pad.s-disponible   { border-color: #16a34a; background: #f0fdf4; }
  .hotel-room-pad.s-occupe       { border-color: #ea580c; background: #fff7ed; }
  .hotel-room-pad.s-a_nettoyer   { border-color: #d97706; background: #fffbeb; }
  .hotel-room-pad.s-hors_service { border-color: #dc2626; background: #fef2f2; opacity: .85; }

  .hotel-rec-empty { padding: 2rem; text-align: center; color: var(--muted); font-size: .85rem; }

  /* Drawer */
  .hotel-drawer-backdrop { position: fixed; inset: 0; background: rgba(0,0,0,.4); z-index: 9000; display: none; }
  .hotel-drawer-backdrop.open { display: block; }
  .hotel-drawer { position: fixed; top: 0; right: 0; height: 100vh; width: 480px; max-width: 100vw; background: var(--card); box-shadow: var(--shadow-lg); transform: translateX(100%); transition: transform .25s ease; z-index: 9001; display: flex; flex-direction: column; }
  .hotel-drawer.open { transform: translateX(0); }
  .hotel-drawer-head { padding: 1rem 1.25rem; border-bottom: 1px solid var(--border); display: flex; justify-content: space-between; align-items: center; }
  .hotel-drawer-head h3 { margin: 0; font-size: 1.05rem; color: var(--foreground); }
  .hotel-drawer-close { background: transparent; border: 0; cursor: pointer; color: var(--muted); padding: 4px; border-radius: var(--radius); }
  .hotel-drawer-close:hover { background: var(--background); color: var(--foreground); }
  .hotel-drawer-body { flex: 1; overflow-y: auto; padding: 1.25rem; }
  .hotel-alert { padding: .65rem .85rem; border-radius: var(--radius); margin-bottom: 1rem; font-size: .85rem; }
  .hotel-alert.error { background: #fee2e2; color: #991b1b; }
  .hotel-alert.success { background: #dcfce7; color: #166534; }
  .hotel-alert.hidden { display: none; }

  .hotel-rec-pulse {
    display: inline-block; width: 8px; height: 8px; border-radius: 50%;
    background: var(--success); margin-right: .35rem;
    animation: hotel-rec-blink 1.5s infinite;
  }
  @keyframes hotel-rec-blink {
    0%, 100% { opacity: 1; }
    50% { opacity: .35; }
  }
</style>

<main class="hotel-rec-wrap">

  <!-- En-tête avec horloge live -->
  <div class="page-header" style="display:flex;justify-content:space-between;align-items:flex-end;margin-bottom:1rem;">
    <div>
      <h2 style="margin:0;color:var(--foreground);display:flex;align-items:center;gap:.5rem;">
        <span class="hotel-rec-pulse"></span>
        <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" style="color:var(--primary);">
          <path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"></path>
          <polyline points="9 22 9 12 15 12 15 22"></polyline>
        </svg>
        Réception
      </h2>
      <div style="font-size:.8rem;color:var(--muted);margin-top:4px;">
        Vue live des chambres — actualisée toutes les 30 s.
      </div>
    </div>
    <div id="hotel-rec-clock" style="font-family:monospace;font-size:1.05rem;color:var(--muted);"></div>
  </div>

  <div id="hotel-alert-page" class="hotel-alert hidden"></div>

  <!-- Timeline -->
  <div class="hotel-rec-card">
    <div class="hotel-rec-timeline">
      <div class="hotel-rec-tile" id="tile-arrivals">
        <h3>
          <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" style="color:#16a34a;"><path d="M15 3l6 6"/><path d="M11 21l-6-6"/><path d="M21 3l-9 9"/><path d="M15 9l6-6"/><path d="M9 15l-6 6"/></svg>
          Arrivées aujourd'hui
          <span class="badge-count" id="count-arrivals">0</span>
        </h3>
        <div id="list-arrivals"><div class="hotel-rec-item-empty">Aucune arrivée prévue.</div></div>
      </div>
      <div class="hotel-rec-tile" id="tile-inProgress">
        <h3>
          <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" style="color:#2563eb;"><circle cx="12" cy="12" r="9"/><polyline points="12 7 12 12 15 14"/></svg>
          En cours
          <span class="badge-count" id="count-inProgress">0</span>
        </h3>
        <div id="list-inProgress"><div class="hotel-rec-item-empty">Aucun séjour en cours.</div></div>
      </div>
      <div class="hotel-rec-tile" id="tile-departures">
        <h3>
          <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" style="color:#dc2626;"><path d="M9 21l-6-6"/><path d="M3 21l9-9"/><path d="M9 15l-6-6"/><path d="M15 9l6 6"/></svg>
          Départs aujourd'hui
          <span class="badge-count" id="count-departures">0</span>
        </h3>
        <div id="list-departures"><div class="hotel-rec-item-empty">Aucun départ prévu.</div></div>
      </div>
    </div>
  </div>

  <!-- Grille des chambres -->
  <div class="hotel-rec-card">
    <h3 style="margin:0 0 .85rem 0;font-size:1rem;color:var(--foreground);">État des chambres</h3>
    <div class="hotel-rec-grid" id="hotel-rec-grid">
      <div class="hotel-rec-empty" style="grid-column:1/-1;">Chargement…</div>
    </div>
    <div style="margin-top:1rem;display:flex;flex-wrap:wrap;gap:.5rem;font-size:.7rem;color:var(--muted);">
      <span><span style="display:inline-block;width:10px;height:10px;background:#dcfce7;border:2px solid #16a34a;border-radius:3px;vertical-align:middle;"></span> Disponible</span>
      <span><span style="display:inline-block;width:10px;height:10px;background:#fff7ed;border:2px solid #ea580c;border-radius:3px;vertical-align:middle;"></span> Occupée</span>
      <span><span style="display:inline-block;width:10px;height:10px;background:#fffbeb;border:2px solid #d97706;border-radius:3px;vertical-align:middle;"></span> À nettoyer</span>
      <span><span style="display:inline-block;width:10px;height:10px;background:#fef2f2;border:2px solid #dc2626;border-radius:3px;vertical-align:middle;"></span> Hors service</span>
    </div>
  </div>
</main>

<!-- Drawer actions chambre -->
<div class="hotel-drawer-backdrop" id="hotel-rec-backdrop"></div>
<aside class="hotel-drawer" id="hotel-rec-drawer" role="dialog" aria-modal="true" aria-labelledby="hotel-rec-drawer-title">
  <header class="hotel-drawer-head">
    <h3 id="hotel-rec-drawer-title">Chambre</h3>
    <button type="button" class="hotel-drawer-close" id="hotel-rec-drawer-close" aria-label="Fermer">
      <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
        <line x1="18" y1="6" x2="6" y1="18"></line><line x1="6" y1="6" x2="18" y1="18"></line>
      </svg>
    </button>
  </header>
  <div class="hotel-drawer-body" id="hotel-rec-drawer-body">
    <div id="hotel-rec-drawer-alert" class="hotel-alert hidden"></div>
    <div id="hotel-rec-drawer-content">Chargement…</div>
  </div>
</aside>

<script src="/assets/js/hotel-reception.js"></script>
