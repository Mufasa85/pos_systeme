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

<!-- Loader overlay pendant la validation DGI -->
<div id="hotel-ft-overlay-loader" style="display:none;position:fixed;inset:0;z-index:9999;background:rgba(0,0,0,.5);align-items:center;justify-content:center;">
  <div style="background:#fff;padding:30px 50px;border-radius:12px;text-align:center;">
    <div style="width:40px;height:40px;border:4px solid #e2e8f0;border-top-color:#0B5E88;border-radius:50%;animation:spin 1s linear infinite;margin:0 auto 15px;"></div>
    <div style="color:#1a1a2e;font-weight:500;">Validation DGI…</div>
  </div>
</div>

<!-- ================================================================== -->
<!-- Zone d\'injection des modals preview & receipt — on RÉUTILISE les modals -->
<!-- globaux du footer.php (#preview-modal et #receipt-modal) exactement -->
<!-- comme le fait recharges.js (#preview-modal / #receipt-modal).        -->
<!-- Le contenu est injecté via JS dans #preview-content et #receipt-content. -->
<!-- ================================================================== -->

<!-- Bloc pour le multi-paiements (info facture) avant preview -->
<div id="hotel-ft-payments-panel" style="display:none; position:fixed; top:0; left:0; right:0; bottom:0; z-index:9999; background:rgba(0,0,0,.45); align-items:center; justify-content:center;">
  <div style="background:#fff; border-radius:12px; max-width:560px; width:100%; max-height:90vh; overflow-y:auto; box-shadow:0 8px 28px rgba(0,0,0,.3);">
    <div class="modal-header" style="padding:14px 18px; border-bottom:1px solid #e2e8f0; display:flex; justify-content:space-between; align-items:center;">
      <h3 style="margin:0; font-size:1.05rem;">
        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="vertical-align: middle; margin-right: 8px;">
          <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"></path>
          <polyline points="14 2 14 8 20 8"></polyline>
        </svg>
        Informations Facture — Hôtel
      </h3>
      <button class="close-modal" id="hotel-ft-payments-panel-close" type="button" style="background:none;border:none;font-size:1.5rem;cursor:pointer;line-height:1;">&times;</button>
    </div>
    <div style="padding: 1.25rem;">
      <h4 class="hotel-ft-section-title">Extras manuels ajoutés</h4>
      <div id="hotel-ft-preview-extras"></div>

      <h4 class="hotel-ft-section-title" style="margin-top: 1rem;">Modes de paiement (encaissement final)</h4>
      <div class="hotel-ft-payments" id="hotel-ft-payments-list"></div>
      <div class="hotel-ft-extras-add" style="margin-top: .5rem;">
        <select id="hotel-ft-pmt-mode">
          <option value="cash">Espèces</option>
          <option value="mobile_money">Mobile Money</option>
          <option value="carte">Carte</option>
          <option value="autre">Autre</option>
        </select>
        <input type="number" id="hotel-ft-pmt-montant" placeholder="Montant FC" min="0" step="0.01">
        <button type="button" class="btn btn-ghost btn-small" id="hotel-ft-pmt-add">+ Ajouter</button>
      </div>
      <div id="hotel-ft-preview-summary" style="margin-top: .65rem; padding: .5rem; background: #f8fafc; border-radius: 8px; text-align: center; font-weight: 600; display: none;">
        <span id="hotel-ft-preview-summary-label"></span>
        <span id="hotel-ft-preview-summary-amount" style="font-size: 1.05rem;"></span>
      </div>

      <div id="hotel-ft-preview-error" class="hotel-ft-banner warn" style="margin-top: .85rem; display: none;"></div>
    </div>
    <div style="padding: 14px 18px; border-top: 1px solid #e2e8f0; display: flex; justify-content: flex-end; gap: 8px;">
      <button type="button" class="btn btn-secondary" id="hotel-ft-payments-panel-cancel">Annuler</button>
      <button type="button" id="hotel-ft-payments-panel-confirm" class="btn btn-primary">
        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
          <polyline points="20 6 9 17 4 12"></polyline>
        </svg>
        Prévisualiser la facture
      </button>
    </div>
  </div>
</div>

<!-- Bloc "Nouvelle facture (autre séjour)" injecté dans #receipt-modal-actions -->
<div id="hotel-ft-receipt-extra-actions" style="display:none;">
  <button id="hotel-ft-reset-btn" class="btn btn-ghost" type="button" style="margin-right:8px;">
    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="vertical-align:middle;">
      <polyline points="1 4 1 10 7 10"></polyline>
      <path d="M3.51 15a9 9 0 1 0 2.13-9.36L1 10"></path>
    </svg>
    Nouvelle facture (autre séjour)
  </button>
</div>

<!-- app.js fournit STORE_INFO, _printReceiptContent, posCart.generateDGIQRCode, posCart.numberToFrenchWords, getPaymentTypeLabel -->
<script src="/assets/js/app.js"></script>
<script src="/assets/js/hotel-facturation.js"></script>