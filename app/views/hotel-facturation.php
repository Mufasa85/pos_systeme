<?php
/**
 * Page Hôtel > Facturation.
 *
 * Affiche le récap complet d'un séjour (nuits + extras + acomptes) et permet
 * d'émettre la facture officielle (régime TVA type B hôtellerie).
 *
 * URL : /hotel/facturation?sejour_id=X
 *
 * Modes :
 *  - séjour sélectionné via query param : on charge preview() et on affiche
 *    les blocs + bouton "Émettre la facture"
 *  - séjour non sélectionné : sélecteur de séjour (chambres + nom guest + dates)
 *
 * Permissions : manager + réceptionniste pour émettre ; gouvernante en lecture seule.
 */
$page = 'hotel-facturation';
?>
<style>
  .hotel-ft-wrap { max-width: 1100px; margin: 1.5rem auto; padding: 0 1rem; }
  .hotel-ft-card { background: var(--card); border-radius: var(--radius); box-shadow: var(--shadow); padding: 1.25rem; margin-bottom: 1rem; }
  .hotel-ft-section-title { font-size: .7rem; text-transform: uppercase; color: var(--muted); margin: 0 0 .5rem; letter-spacing: .05em; }
  .hotel-ft-row { display: flex; justify-content: space-between; padding: .35rem 0; font-size: .85rem; }
  .hotel-ft-row strong { font-weight: 600; color: var(--foreground); }
  .hotel-ft-table { width: 100%; border-collapse: collapse; font-size: .85rem; }
  .hotel-ft-table th { text-align: left; padding: .35rem .5rem; font-size: .7rem; text-transform: uppercase; color: var(--muted); border-bottom: 1px solid var(--border); }
  .hotel-ft-table td { padding: .35rem .5rem; border-bottom: 1px solid var(--border); }
  .hotel-ft-table td.num { text-align: right; font-variant-numeric: tabular-nums; }
  .hotel-ft-totals { background: var(--background); padding: .85rem 1rem; border-radius: var(--radius); margin-top: .85rem; }
  .hotel-ft-totals .row { display: flex; justify-content: space-between; padding: .25rem 0; font-size: .9rem; }
  .hotel-ft-totals .row.total { font-size: 1.05rem; font-weight: 600; border-top: 1px solid var(--border); padding-top: .65rem; margin-top: .35rem; }
  .hotel-ft-banner { background: #ecfdf5; color: #065f46; padding: .65rem .85rem; border-radius: var(--radius); font-size: .85rem; margin-bottom: 1rem; }
  .hotel-ft-banner.warn { background: #fef3c7; color: #92400e; }
  .hotel-ft-empty { padding: 2rem; text-align: center; color: var(--muted); }
  .hotel-ft-actions { display: flex; gap: .5rem; justify-content: flex-end; margin-top: 1rem; flex-wrap: wrap; }
  .hotel-ft-payments { display: grid; grid-template-columns: 1fr 1fr auto; gap: .5rem; align-items: center; }
  .hotel-ft-payments select, .hotel-ft-payments input { padding: 6px 10px; border-radius: var(--radius); border: 1px solid var(--border); background: #fff; font-size: .85rem; }
  .hotel-ft-remise-input { width: 80px; }
  .hotel-ft-extras-add { display: flex; gap: .5rem; margin-top: .5rem; }
  .hotel-ft-extras-add input { padding: 6px 10px; border-radius: var(--radius); border: 1px solid var(--border); background: #fff; font-size: .85rem; flex: 1; }
</style>

<main class="hotel-ft-wrap">
  <div class="hotel-ft-card">
    <div class="page-header" style="display:flex;justify-content:space-between;align-items:center;margin-bottom:1rem;">
      <div>
        <h2 style="margin:0;color:var(--foreground);">Facturation hôtel</h2>
        <div style="font-size:.8rem;color:var(--muted);margin-top:4px;">
          Facture officielle du séjour — régime TVA type B (non collectée).
        </div>
      </div>
      <a href="/hotel/reception" class="btn btn-ghost btn-small">← Retour à la réception</a>
    </div>

    <div id="hotel-ft-alert-page" class="hotel-ft-banner hidden" style="display:none;"></div>

    <!-- Sélecteur de séjour -->
    <div id="hotel-ft-sejours-picker">
      <div style="font-size:.8rem;color:var(--muted);margin-bottom:.5rem;">Choisis le séjour à facturer :</div>
      <div class="form-group">
        <label for="hotel-ft-sejour-search">Rechercher</label>
        <input type="text" id="hotel-ft-sejour-search" class="form-input" placeholder="N° chambre, nom du guest ou dates…" autocomplete="off">
      </div>
      <div id="hotel-ft-sejour-results" style="max-height:400px;overflow-y:auto;border:1px solid var(--border);border-radius:var(--radius);"></div>
    </div>

    <!-- Bloc de facturation (chargé quand sejour_id présent) -->
    <div id="hotel-ft-bloc" style="display:none;"></div>
  </div>
</main>

<script src="/assets/js/hotel-facturation.js"></script>