<?php
/**
 * Page Pressing — suivi des commandes de pressing.
 *
 * La page ne charge que la coquille HTML : le contenu vient de fetch()
 * vers /api/pressing/* (PressingController).
 *
 * Tous les noms de classes sont préfixés « pr- » : l'appli autonome
 * `pressing/` utilisait des noms génériques (.btn, .card, .modal…) qui
 * entreraient en collision avec les feuilles du POS (styles.css,
 * recharges.css, mobile-caisse.css) et casseraient toute l'interface.
 *
 * Le panneau de détail réutilise le drawer standard du projet
 * (.hotel-drawer / .hotel-drawer-backdrop), pas un <dialog> : l'utilisateur
 * n'a ainsi qu'un seul geste à mémoriser pour ouvrir un panneau latéral.
 */
$page = 'pressing';
?>
<style>
  /* ---------- Wrapper ---------- */
  .pr-wrap { max-width: 1200px; margin: 1.5rem auto; padding: 0 1rem; }

  /* ---------- Tuiles KPI (mêmes classes que le tableau de bord POS) ---------- */
  .pr-stats { display: grid; grid-template-columns: repeat(5, 1fr); gap: .75rem; margin-bottom: 1.25rem; }
  .pr-stat { background: var(--card, #fff); border: 1px solid var(--border, #e2e8f0); border-radius: var(--radius, 8px); padding: .9rem 1rem; }
  .pr-stat .n { font-size: 1.4rem; font-weight: 800; color: var(--foreground, #1e293b); line-height: 1.2; }
  .pr-stat .n.blue { color: var(--primary, #0B5E88); }
  .pr-stat .n.green { color: #16a34a; }
  .pr-stat .n.gray { color: var(--muted, #64748b); }
  .pr-stat .n.gold { color: #b45309; }
  .pr-stat .l { font-size: .68rem; text-transform: uppercase; letter-spacing: .05em; color: var(--muted, #64748b); margin-top: .15rem; }

  /* ---------- Carte ---------- */
  .pr-card { background: var(--card, #fff); border: 1px solid var(--border, #e2e8f0); border-radius: var(--radius, 8px); padding: 1.25rem; margin-bottom: 1rem; }

  /* ---------- Onglets d'état ---------- */
  .pr-chips { display: flex; gap: .4rem; flex-wrap: wrap; margin-bottom: 1rem; }
  .pr-chip { display: inline-flex; align-items: center; gap: .35rem; padding: .35rem .8rem; border-radius: 999px; background: var(--background, #f8fafc); border: 1px solid var(--border, #e2e8f0); color: var(--muted, #64748b); font-size: .78rem; font-weight: 600; cursor: pointer; text-decoration: none; transition: .15s; }
  .pr-chip:hover { background: var(--hover-bg, #f1f5f9); color: var(--foreground, #1e293b); }
  .pr-chip.on { background: var(--primary, #0B5E88); border-color: var(--primary, #0B5E88); color: #fff; }
  .pr-chip .cnt { font-size: .68rem; opacity: .8; }

  /* ---------- Tableau ---------- */
  .pr-table { width: 100%; border-collapse: collapse; font-size: .85rem; }
  .pr-table th { text-align: left; padding: .5rem .6rem; font-size: .68rem; text-transform: uppercase; letter-spacing: .04em; color: var(--muted, #64748b); border-bottom: 2px solid var(--border, #e2e8f0); white-space: nowrap; }
  .pr-table td { padding: .55rem .6rem; border-bottom: 1px solid var(--border, #f1f5f9); vertical-align: middle; }
  .pr-table tbody tr:hover { background: var(--hover-bg, #f8fafc); }
  .pr-table .num { text-align: right; font-variant-numeric: tabular-nums; white-space: nowrap; }
  .pr-table .mono { font-family: 'JetBrains Mono', ui-monospace, monospace; font-size: .78rem; }
  .pr-table tr.pr-row-remise { background: #f0fdf4; }
  .pr-table tr.pr-row-prete { background: #fdf2f8; }
  .pr-table tr.pr-row-retard { background: #fef2f2; }
  .pr-empty { text-align: center; padding: 2.5rem 1rem; color: var(--muted, #64748b); font-size: .85rem; }

  /* ---------- Badges d'état ---------- */
  .pr-etat { display: inline-block; padding: .2rem .55rem; border-radius: 999px; font-size: .7rem; font-weight: 700; white-space: nowrap; }
  .pr-etat.encours { background: #dbeafe; color: #1d4ed8; }
  .pr-etat.prete { background: #fce7f3; color: #9d174d; }
  .pr-etat.remise { background: #f1f5f9; color: #475569; }
  .pr-etat.retard { background: #fee2e2; color: #b91c1c; }
  .pr-badge-late { background: #fee2e2; color: #b91c1c; font-size: .62rem; font-weight: 800; padding: .12rem .4rem; border-radius: 999px; letter-spacing: .04em; }
  .pr-solde { color: #dc2626; font-weight: 700; }
  .pr-zero { color: #16a34a; font-weight: 600; }

  /* ---------- Pastille de couleur ---------- */
  .pr-dot { display: inline-block; width: 12px; height: 12px; border-radius: 50%; border: 1px solid rgba(0,0,0,.15); vertical-align: -1px; margin-right: .3rem; }

  /* ---------- Progression (7 étapes) ---------- */
  .pr-prog { display: flex; align-items: center; gap: 3px; }
  .pr-prog .step { width: 13px; height: 13px; border-radius: 50%; background: #e5e7eb; border: 2px solid #d1d5db; }
  .pr-prog .step.done { background: #16a34a; border-color: #16a34a; }
  .pr-prog .step.now { background: var(--primary, #0B5E88); border-color: var(--primary, #0B5E88); }

  /* ---------- Drawer (panneau latéral) ---------- */
  .pr-drawer-backdrop { position: fixed; inset: 0; background: rgba(0,0,0,.4); z-index: 9000; display: none; }
  .pr-drawer-backdrop.open { display: block; }
  .pr-drawer { position: fixed; top: 0; right: 0; height: 100vh; width: 760px; max-width: 100vw; background: var(--card, #fff); box-shadow: var(--shadow-lg, 0 10px 40px rgba(0,0,0,.18)); transform: translateX(100%); transition: transform .25s ease; z-index: 9001; display: flex; flex-direction: column; }
  .pr-drawer.open { transform: translateX(0); }
  .pr-drawer-head { padding: 1rem 1.25rem; border-bottom: 1px solid var(--border, #e2e8f0); display: flex; justify-content: space-between; align-items: center; gap: .5rem; }
  .pr-drawer-head h3 { margin: 0; font-size: 1.05rem; color: var(--foreground, #1e293b); }
  .pr-drawer-head small { display: block; font-size: .65rem; text-transform: uppercase; letter-spacing: .08em; color: var(--muted, #64748b); }
  .pr-drawer-close { background: transparent; border: 0; cursor: pointer; color: var(--muted, #64748b); padding: 4px; border-radius: var(--radius, 8px); font-size: 1.2rem; line-height: 1; }
  .pr-drawer-close:hover { background: var(--background, #f8fafc); color: var(--foreground, #1e293b); }
  .pr-drawer-body { flex: 1; overflow-y: auto; padding: 1.25rem; }
  .pr-drawer-foot { padding: .85rem 1.25rem; border-top: 1px solid var(--border, #e2e8f0); display: flex; gap: .5rem; justify-content: flex-end; background: var(--background, #f8fafc); flex-wrap: wrap; }

  /* ---------- Clés/valeurs ---------- */
  .pr-kv { display: grid; grid-template-columns: repeat(auto-fit, minmax(140px, 1fr)); gap: .5rem; margin-bottom: 1.1rem; }
  .pr-kv-item { background: var(--background, #f8fafc); border: 1px solid var(--border, #e2e8f0); border-radius: var(--radius, 8px); padding: .55rem .7rem; }
  .pr-kv-item span { display: block; font-size: .62rem; font-weight: 700; text-transform: uppercase; letter-spacing: .06em; color: var(--muted, #64748b); }
  .pr-kv-item b { font-size: .85rem; color: var(--foreground, #1e293b); font-weight: 600; }
  .pr-kv-item.ok { background: #f0fdf4; border-color: #bbf7d0; }

  /* ---------- Sections du drawer ---------- */
  .pr-section { font-size: .68rem; font-weight: 700; text-transform: uppercase; letter-spacing: .05em; color: var(--muted, #64748b); margin: 1.1rem 0 .5rem; display: flex; align-items: center; justify-content: space-between; gap: .5rem; }
  .pr-serie { font-size: .7rem; color: var(--muted, #64748b); margin-top: .2rem; }
  .pr-totaux { background: var(--background, #f8fafc); border-radius: var(--radius, 8px); padding: .7rem .85rem; margin-top: .6rem; display: flex; justify-content: space-between; font-size: .85rem; }

  /* ---------- Formulaire de paiement ---------- */
  .pr-pay-row { display: flex; gap: .5rem; align-items: center; flex-wrap: wrap; margin-top: .6rem; }
  .pr-pay-row input, .pr-pay-row select { padding: .45rem .6rem; border: 1px solid var(--border, #e2e8f0); border-radius: var(--radius, 8px); background: #fff; font-size: .82rem; }
  .pr-pay-row input[name=montant] { width: 120px; }

  /* ---------- Alerte ---------- */
  .pr-alert { padding: .6rem .8rem; border-radius: var(--radius, 8px); margin-bottom: .9rem; font-size: .82rem; display: none; }
  .pr-alert.error { background: #fee2e2; color: #991b1b; }
  .pr-alert.success { background: #dcfce7; color: #166534; }
  .pr-alert.warn { background: #fef3c7; color: #92400e; }
  .pr-alert.show { display: block; }

  /* ---------- Formulaire nouvelle commande ---------- */
  .pr-form-grid { display: grid; grid-template-columns: repeat(3, 1fr); gap: .7rem; margin-bottom: .8rem; }
  .pr-form-grid label { display: flex; flex-direction: column; gap: .25rem; font-size: .74rem; font-weight: 600; color: var(--muted, #64748b); }
  .pr-form-grid input, .pr-form-grid select { padding: .45rem .6rem; border: 1px solid var(--border, #e2e8f0); border-radius: var(--radius, 8px); background: #fff; font-size: .84rem; color: var(--foreground, #1e293b); }
  .pr-form-grid input[readonly] { background: var(--background, #f8fafc); color: var(--muted, #64748b); cursor: not-allowed; }

  /* ---------- Lignes d'articles ---------- */
  .pr-art-row { display: grid; grid-template-columns: minmax(90px, 1.3fr) minmax(80px, 1fr) 56px minmax(80px, 1fr) minmax(90px, 1fr) 62px minmax(86px, auto) 30px; gap: .35rem; align-items: end; padding: .5rem; background: var(--background, #f8fafc); border: 1px solid var(--border, #e2e8f0); border-radius: var(--radius, 8px); margin-bottom: .4rem; }
  .pr-art-row label { display: flex; flex-direction: column; gap: .15rem; font-size: .6rem; font-weight: 700; text-transform: uppercase; letter-spacing: .04em; color: var(--muted, #64748b); }
  .pr-art-row input, .pr-art-row select { padding: .35rem .5rem; border: 1px solid var(--border, #e2e8f0); border-radius: var(--radius, 8px); background: #fff; font-size: .8rem; width: 100%; min-width: 0; }
  .pr-f-total { display: flex !important; align-items: center; justify-content: flex-end; padding: .35rem .5rem; margin-left: .15rem; border-radius: var(--radius, 8px); background: #eef2ff; color: #4338ca; font-size: .8rem; font-weight: 800; white-space: nowrap; }
  .pr-art-del { border: 0; background: #fee2e2; color: #dc2626; border-radius: var(--radius, 8px); width: 30px; height: 30px; cursor: pointer; font-size: 1rem; line-height: 1; }
  .pr-art-del:hover { background: #fecaca; }

  /* ---------- Toast ---------- */
  .pr-toast { position: fixed; top: 18px; left: 50%; transform: translateX(-50%); padding: .7rem 1.2rem; border-radius: var(--radius, 8px); font-size: .85rem; font-weight: 600; z-index: 9500; box-shadow: 0 8px 26px rgba(15,23,42,.16); max-width: 90vw; }
  .pr-toast.err { background: #fee2e2; color: #991b1b; border: 1px solid #fca5a5; }
  .pr-toast.ok { background: #dcfce7; color: #166534; border: 1px solid #86efac; }
  .pr-toast.warn { background: #fef3c7; color: #92400e; border: 1px solid #fcd34d; }

  /* ---------- Responsive ---------- */
  @media (max-width: 900px) {
    .pr-stats { grid-template-columns: repeat(2, 1fr); }
    .pr-form-grid { grid-template-columns: 1fr; }
    .pr-art-row { grid-template-columns: 1fr 1fr; }
    .pr-drawer { width: 100vw; }
  }
</style>

<main class="pr-wrap">
  <div class="pr-card">
    <div class="page-header" style="display:flex;justify-content:space-between;align-items:center;margin-bottom:1rem;gap:.75rem;flex-wrap:wrap;">
      <div>
        <h2 style="margin:0;color:var(--foreground);display:flex;align-items:center;gap:.5rem;">
          <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" style="color:var(--primary);">
            <circle cx="6" cy="6" r="3"></circle>
            <circle cx="6" cy="18" r="3"></circle>
            <line x1="20" y1="4" x2="8.12" y2="15.88"></line>
            <line x1="14.47" y1="14.48" x2="20" y2="20"></line>
            <line x1="8.12" y1="8.12" x2="12" y2="12"></line>
          </svg>
          Pressing
        </h2>
        <div style="font-size:.8rem;color:var(--muted);margin-top:4px;">
          Suivi des commandes de pressing — état d'avancement du traitement du linge.
        </div>
      </div>
      <div style="display:flex;gap:.5rem;flex-wrap:wrap;">
        <button type="button" class="btn btn-secondary" id="pr-btn-ventes">
          Factures sans pressing
        </button>
        <button type="button" class="btn btn-primary" id="pr-btn-nouvelle">
          <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" style="vertical-align:-2px;margin-right:4px;">
            <line x1="12" y1="5" x2="12" y2="19"></line>
            <line x1="5" y1="12" x2="19" y2="12"></line>
          </svg>
          Nouvelle commande
        </button>
      </div>
    </div>

    <div id="pr-alert" class="pr-alert"></div>

    <!-- Compteurs -->
    <div class="pr-stats" id="pr-stats">
      <div class="pr-stat"><div class="n" data-k="total">0</div><div class="l">Commandes</div></div>
      <div class="pr-stat"><div class="n blue" data-k="encours">0</div><div class="l">En cours</div></div>
      <div class="pr-stat"><div class="n" style="color:#9d174d" data-k="pretes">0</div><div class="l">Prêtes</div></div>
      <div class="pr-stat"><div class="n gray" data-k="remises">0</div><div class="l">Remises</div></div>
      <div class="pr-stat"><div class="n gold" data-k="encaisse">0,00 FC</div><div class="l">Encaissé</div></div>
    </div>

    <!-- Filtres + recherche -->
    <div class="pr-chips" id="pr-chips"></div>

    <div style="display:flex;gap:.5rem;margin-bottom:.85rem;flex-wrap:wrap;">
      <input type="search" id="pr-search" placeholder="N° dossier, facture, nom ou téléphone…"
             style="flex:1;min-width:220px;padding:.5rem .7rem;border:1px solid var(--border);border-radius:var(--radius);background:#fff;font-size:.85rem;">
      <button type="button" class="btn btn-secondary" id="pr-btn-search">Rechercher</button>
    </div>

    <!-- Liste des dossiers -->
    <div class="table-container" style="overflow-x:auto;">
      <table class="pr-table">
        <thead>
          <tr>
            <th>Dossier</th>
            <th>Client</th>
            <th>Téléphone</th>
            <th>Dépôt</th>
            <th class="num">Art.</th>
            <th class="num">Total</th>
            <th class="num">Payé</th>
            <th class="num">Solde</th>
            <th>État</th>
            <th></th>
          </tr>
        </thead>
        <tbody id="pr-tbody">
          <tr><td colspan="10" class="pr-empty">Chargement…</td></tr>
        </tbody>
      </table>
    </div>
  </div>
</main>

<!-- ══════════ DRAWER : détail d'un dossier ══════════ -->
<div class="pr-drawer-backdrop" id="pr-backdrop"></div>
<aside class="pr-drawer" id="pr-drawer" role="dialog" aria-modal="true" aria-labelledby="pr-drawer-title">
  <header class="pr-drawer-head">
    <div>
      <small>Dossier de pressing</small>
      <h3 id="pr-drawer-title">—</h3>
    </div>
    <button type="button" class="pr-drawer-close" id="pr-drawer-close" aria-label="Fermer">×</button>
  </header>
  <div class="pr-drawer-body" id="pr-drawer-body">
    <div class="pr-empty">Chargement…</div>
  </div>
  <footer class="pr-drawer-foot" id="pr-drawer-foot"></footer>
</aside>

<!-- ══════════ DRAWER : nouvelle commande ══════════ -->
<div class="pr-drawer-backdrop" id="pr-backdrop2"></div>
<aside class="pr-drawer" id="pr-drawer2" role="dialog" aria-modal="true" aria-labelledby="pr-drawer2-title">
  <header class="pr-drawer-head">
    <div>
      <small>Nouveau dossier</small>
      <h3 id="pr-drawer2-title">Nouvelle commande de pressing</h3>
    </div>
    <button type="button" class="pr-drawer-close" id="pr-drawer2-close" aria-label="Fermer">×</button>
  </header>
  <div class="pr-drawer-body">
    <div class="pr-form-grid">
      <label>Numéro de facture DGI
        <input type="text" id="pr-f-facture" maxlength="30" placeholder="Ex. 2026/000849">
      </label>
      <label>&nbsp;
        <button type="button" class="btn btn-secondary" id="pr-btn-lookup" style="width:100%;">Récupérer la facture</button>
      </label>
      <label>Date et heure de dépôt
        <input type="datetime-local" id="pr-f-depot">
      </label>
    </div>

    <div class="pr-form-grid">
      <label>Nom du client *
        <input type="text" id="pr-f-nom" maxlength="120" placeholder="Rempli via « Récupérer la facture »">
      </label>
      <label>Téléphone
        <input type="tel" id="pr-f-tel" maxlength="40" placeholder="Rempli via « Récupérer la facture »">
      </label>
      <label>&nbsp;</label>
    </div>

    <div class="pr-section">
      <span>Vêtements déposés</span>
      <button type="button" class="btn btn-secondary btn-small" id="pr-btn-ajouter">+ Ajouter</button>
    </div>

    <div id="pr-articles"></div>
  </div>
  <footer class="pr-drawer-foot">
    <button type="button" class="btn btn-secondary" id="pr-btn-annuler">Annuler</button>
    <button type="button" class="btn btn-primary" id="pr-btn-enregistrer">Enregistrer la commande</button>
  </footer>
</aside>

<!-- ══════════ DRAWER : factures POS sans pressing ══════════ -->
<div class="pr-drawer-backdrop" id="pr-backdrop3"></div>
<aside class="pr-drawer" id="pr-drawer3" role="dialog" aria-modal="true" aria-labelledby="pr-drawer3-title">
  <header class="pr-drawer-head">
    <div>
      <small>Assistance</small>
      <h3 id="pr-drawer3-title">Factures POS sans pressing</h3>
    </div>
    <button type="button" class="pr-drawer-close" id="pr-drawer3-close" aria-label="Fermer">×</button>
  </header>
  <div class="pr-drawer-body" id="pr-drawer3-body">
    <div class="pr-empty">Chargement…</div>
  </div>
  <footer class="pr-drawer-foot">
    <button type="button" class="btn btn-secondary" id="pr-btn-ventes-fermer">Fermer</button>
  </footer>
</aside>

<script src="/assets/js/pressing.js?v=1"></script>
