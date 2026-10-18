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

<!-- Modal Informations Facture — pattern identique à /caisse et /recharges.
     Pré-rempli avec les infos du guest. Voir openHotelInvoiceInfoModal() -->
<div id="invoice-info-modal" class="modal">
  <div class="modal-content" style="max-width: 500px; padding: 0; overflow: auto;">
    <div class="modal-header">
      <h3>
        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="vertical-align: middle; margin-right: 8px;">
          <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"></path>
          <polyline points="14 2 14 8 20 8"></polyline>
        </svg>
        Informations Facture
      </h3>
      <button class="close-modal" onclick="closeHotelInvoiceInfoModal()">&times;</button>
    </div>
    <div style="padding: 1.5rem;">
      <!-- Type + Réf Document -->
      <div style="display: grid; grid-template-columns: 80px 1fr; gap: 12px; margin-bottom: 0.75rem; align-items: flex-end;">
        <div>
          <label for="modal-invoice-type" style="font-size: 0.7rem; font-weight: 600; color: #64748b; display: block; margin-bottom: 4px;">TYPE</label>
          <select id="modal-invoice-type" class="client-number-input" style="width: 100%; padding: 6px 8px; font-size: 0.8rem;">
            <option value="HB">HB</option>
            <option value="FV">FV</option>
            <option value="EV">EV</option>
            <option value="FT">FT</option>
            <option value="FA">FA</option>
            <option value="EA">EA</option>
            <option value="ET">ET</option>
          </select>
        </div>
        <div>
          <label style="font-size: 0.7rem; font-weight: 600; color: #64748b; display: block; margin-bottom: 4px;">RÉF DOCUMENT</label>
          <div style="display: flex; align-items: center; justify-content: space-between; gap: 6px;">
            <input type="text" id="modal-invoice-ref" class="client-number-input" placeholder="Réf..." style="width: 100%;" oninput="syncHotelRefDocsCount()">
            <button id="add-ref-doc-btn" type="button" onclick="addHotelRefDocLine()" style="background: #0B5E88; color: #fff; border: none; border-radius: 6px; width: 24px; height: 24px; display: flex; align-items: center; justify-content: center; cursor: pointer; font-size: 1rem; line-height: 1;">+</button>
          </div>
          <div id="modal-ref-docs-list" style="display: flex; flex-direction: column; gap: 0.5rem; margin-top: 0.5rem;"></div>
          <input type="hidden" id="modal-ref-docs-count" value="1">
        </div>
      </div>

      <!-- REF FACTURE + EXONÉRATION (pattern /caisse, intégrés à la grille type/réf) -->
      <div style="display: grid; grid-template-columns: 1fr 110px; gap: 12px; margin-bottom: 0.75rem; margin-top: 0.5rem; align-items: flex-end;">
        <div>
          <label for="modal-invoice-num" style="font-size: 0.7rem; font-weight: 600; color: #64748b; display: block; margin-bottom: 4px;">REF FACTURE</label>
          <input type="text" id="modal-invoice-num" class="client-number-input" placeholder="Réf facture (optionnel)" style="width: 100%; padding: 6px 8px; font-size: 0.8rem;">
        </div>
        <div>
          <label for="modal-exoneration" style="font-size: 0.7rem; font-weight: 600; color: #64748b; display: block; margin-bottom: 4px;">EXONÉRATION</label>
          <select id="modal-exoneration" class="client-number-input" style="width: 100%; padding: 6px 8px; font-size: 0.8rem;">
            <option value="">—</option>
            <option value="RAM">RAM — Avoir suite reprise</option>
            <option value="RRR">RRR — Remise, Ristourne, Rabais</option>
            <option value="RAN">RAN — Annulation</option>
            <option value="COR">COR — Correction</option>
          </select>
        </div>
      </div>
      <h4 class="hotel-ft-section-title">Extras manuels ajoutés au séjour</h4>
      <div id="hotel-ft-preview-extras"></div>

      <!-- Mode de Paiement (multi-lignes — pattern /caisse) -->
      <div style="background: #f0fdf4; border: 1px solid #bbf7d0; border-radius: 12px; padding: 1rem; margin-bottom: 1rem; margin-top: 1rem;">
        <div style="font-size: 0.75rem; font-weight: 600; color: #166534; margin-bottom: 0.75rem; text-transform: uppercase; display: flex; align-items: center; justify-content: space-between; gap: 6px;">
          <span style="display: flex; align-items: center; gap: 6px;">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
              <rect x="1" y="4" width="22" height="16" rx="2" ry="2"></rect>
              <line x1="1" y1="10" x2="23" y2="10"></line>
            </svg>
            Mode de Paiement (encaissement final)
          </span>
          <button id="add-payment-line-btn" type="button" onclick="addHotelModalPaymentLine()" style="background: #0B5E88; color: #fff; border: none; border-radius: 6px; width: 24px; height: 24px; display: flex; align-items: center; justify-content: center; cursor: pointer; font-size: 1rem; line-height: 1;">+</button>
        </div>
        <div id="modal-payments-list" style="display: flex; flex-direction: column; gap: 0.5rem;"></div>
        <div id="modal-payment-summary" style="margin-top: 0.75rem; padding: 0.5rem; background: #fff; border-radius: 8px; text-align: center; font-weight: 600; display: none;">
          <span id="modal-payment-summary-label" style="color: #166534;"></span>
          <span id="modal-payment-summary-amount" style="color: #0B5E88; font-size: 1.1rem;"></span>
        </div>
        <input type="hidden" id="modal-payment-type" value="ESPECES">
      </div>

      <!-- Info Client (pré-rempli par JS depuis previewData.sejour.guest) -->
      <div style="background: #fff; border: 1px solid #e2e8f0; border-radius: 10px; padding: 1rem; margin-bottom: 1rem;">
        <div style="font-size: 0.75rem; font-weight: 600; color: #64748b; margin-bottom: 0.75rem; text-transform: uppercase; display: flex; align-items: center; gap: 6px;">
          <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
            <path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"></path>
            <circle cx="12" cy="7" r="4"></circle>
          </svg>
          Informations Client (Guest)
        </div>
        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 8px;">
          <div>
            <label for="modal-client-name" style="font-size: 0.7rem; color: #94a3b8; display: block; margin-bottom: 2px;">Nom complet</label>
            <input type="text" id="modal-client-name" class="client-number-input" placeholder="Nom du guest" style="width: 100%;">
          </div>
          <div>
            <label for="modal-client-number" style="font-size: 0.7rem; color: #94a3b8; display: block; margin-bottom: 2px;">Téléphone</label>
            <input type="text" id="modal-client-number" class="client-number-input" placeholder="08xxxxxxxx" maxlength="10" style="width: 100%;">
          </div>
          <div>
            <label for="modal-client-type" style="font-size: 0.7rem; color: #94a3b8; display: block; margin-bottom: 2px;">Type client</label>
            <select id="modal-client-type" class="client-number-input" style="width: 100%; padding: 6px 8px;">
              <option value="">Type client</option>
            </select>
          </div>
          <div>
            <label for="modal-client-nif" style="font-size: 0.7rem; color: #94a3b8; display: block; margin-bottom: 2px;">NIF</label>
            <input type="text" id="modal-client-nif" class="client-number-input" placeholder="NIF (optionnel si PP)" style="width: 100%;">
          </div>
          <div style="grid-column: 1 / -1;">
            <label for="modal-client-address" style="font-size: 0.7rem; color: #94a3b8; display: block; margin-bottom: 2px;">Adresse</label>
            <input type="text" id="modal-client-address" class="client-number-input" placeholder="Adresse (obligatoire si PM/PL/PC/AO)" style="width: 100%;">
          </div>
        </div>
        <input type="hidden" id="modal-checkout" value="0">
      </div>

      <!-- Message d'erreur -->
      <div id="modal-invoice-error" class="hotel-ft-banner warn" style="margin-top: 0.85rem; display: none;"></div>
    </div>

    <div class="modal-actions">
      <button type="button" class="btn btn-secondary" onclick="closeHotelInvoiceInfoModal()">Annuler</button>
      <button type="button" class="btn btn-primary" onclick="confirmHotelInvoiceInfo()">
        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="vertical-align: middle; margin-right: 4px;">
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
<script src="/assets/js/hotel-facturation.js?v=2"></script>