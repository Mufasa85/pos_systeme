/**
 * Page Facturation hôtel (/hotel/facturation)
 *
 * Pattern aligné sur /caisse (app.js : posCart.showPreview / confirmSale)
 * et /recharges (recharges.js : billPayment.showPreviewFinal / generateTicket) :
 *
 *  1. À l'ouverture, on lit ?sejour_id=X dans l'URL.
 *     - Si présent → on masque le picker et on appelle loadSejour()
 *     - Si absent  → on affiche le picker pour choisir un séjour
 *  2. loadSejour() remplit les blocs (séjour, nuits, extras, acomptes, totaux)
 *     et autorise l'ajout d'extras manuels.
 *  3. Bouton "Émettre la facture" → modal Preview (#hotel-ft-preview-modal)
 *     avec récap + multi-paiements, comme dans /caisse et /recharges.
 *  4. Click sur "Valider" → POST /api/dgi (validation DGI)
 *     puis POST /api/hotel/factures (persist + dgi_data)
 *     → modal Receipt final (#hotel-ft-receipt-modal) avec QR code DGI.
 *
 *  Les helpers `generateDGIQRCode`, `numberToFrenchWords`, `getPaymentTypeLabel`
 *  et `_printReceiptContent` sont factorisés dans /assets/js/app.js et utilisés
 *  tels quels (déjà chargés via le footer global : <script src="/assets/js/app.js">).
 */
(function() {
  'use strict';

  const APP_URL = window.location.origin;
  const $ = (id) => document.getElementById(id);
  const pageAlert = $('hotel-ft-alert-page');
  const picker    = $('hotel-ft-sejours-picker');
  const bloc      = $('hotel-ft-bloc');
  const search    = $('hotel-ft-sejour-search');
  const results   = $('hotel-ft-sejour-results');

  let currentSejourId = null;
  let previewData = null;
  let extrasManuels = []; // [{ description, montant }]
  let paiements = [];     // [{ mode, montant }]

  // ── Helpers ───────────────────────────────────────────────────────────
  function showAlert(msg, type) {
    if (!pageAlert) return;
    if (!msg) { pageAlert.style.display = 'none'; return; }
    pageAlert.className = 'hotel-ft-banner ' + (type === 'error' ? 'warn' : '');
    pageAlert.textContent = msg;
    pageAlert.style.display = '';
  }
  function escapeHtml(s) {
    return String(s == null ? '' : s)
      .replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;')
      .replace(/"/g, '&quot;').replace(/'/g, '&#39;');
  }
  function fmtFc(n) {
    return parseFloat(n || 0).toLocaleString('fr-FR', { minimumFractionDigits: 2, maximumFractionDigits: 2 }) + ' Fc';
  }
  function fmtDate(s) {
    if (!s) return '—';
    const d = new Date(String(s).replace(' ', 'T'));
    if (isNaN(d)) return s;
    return ('0' + d.getDate()).slice(-2) + '/' + ('0' + (d.getMonth()+1)).slice(-2) + '/' + d.getFullYear();
  }
  async function jsonFetch(url, opts) {
    const res = await fetch(APP_URL + url, Object.assign(
      { credentials: 'same-origin', headers: { 'Accept': 'application/json' } },
      opts || {}
    ));
    let data = null; try { data = await res.json(); } catch (e) {}
    return { ok: res.ok, status: res.status, data };
  }

  // Mapping modes de paiement UI hôtel → nomenclature DGI
  const PAY_MODE_DGI = {
    cash:         'ESPECES',
    mobile_money: 'MOBILEMONEY',
    carte:        'CARTEBANCAIRE',
    autre:        'AUTRE',
  };
  // Numéro de facture envoyé à la DGI : on garde la nomenclature existante
  // (FV = Facture de Vente). Le DGI ne connaît pas 'HB' ; le backend transcrit
  // 'HB' dans ventes.invoice_type (cf. HotelFactureController::store()).
  const DGI_INVOICE_TYPE = 'FV';

  function paymentLabel(mode) {
    if (typeof getPaymentTypeLabel === 'function') {
      return getPaymentTypeLabel(PAY_MODE_DGI[mode] || 'AUTRE');
    }
    const m = { cash: 'Espèces', mobile_money: 'Mobile Money', carte: 'Carte', autre: 'Autre' };
    return m[mode] || mode;
  }

  // ── Picker de séjour ─────────────────────────────────────────────────
  let sTimer = null;
  if (search) {
    search.addEventListener('input', () => {
      clearTimeout(sTimer);
      const q = search.value.trim();
      if (q.length < 1) { results.innerHTML = ''; return; }
      sTimer = setTimeout(() => doSejourSearch(q), 220);
    });
  }
  async function doSejourSearch(q) {
    results.innerHTML = '<div style="padding:.65rem .85rem;font-size:.82rem;color:var(--muted);">Recherche…</div>';
    const r = await jsonFetch('/api/hotel/reservations?q=' + encodeURIComponent(q) + '&limit=20');
    if (!r.ok || !r.data) {
      results.innerHTML = '<div style="padding:.65rem .85rem;font-size:.82rem;color:#991b1b;">Erreur.</div>';
      return;
    }
    const list = (r.data.data || []).filter(s => s.statut === 'en_cours' || s.statut === 'reserve');
    if (list.length === 0) {
      results.innerHTML = '<div style="padding:.65rem .85rem;font-size:.82rem;color:var(--muted);">Aucun séjour actif.</div>';
      return;
    }
    results.innerHTML = list.map(s => `
      <div class="hotel-ft-sejour-row" data-id="${s.id}" style="padding:.55rem .85rem;border-bottom:1px solid var(--border);cursor:pointer;display:flex;justify-content:space-between;align-items:center;">
        <div>
          <div style="font-weight:500;">${escapeHtml(s.guest_nom || '')} ${escapeHtml(s.guest_prenom || '')}</div>
          <div style="font-size:.75rem;color:var(--muted);margin-top:2px;">Chambre ${escapeHtml(s.chambre_numero)} · ${fmtDate(s.date_arrivee)} → ${fmtDate(s.date_depart_prevue)}</div>
        </div>
        <span class="badge">${escapeHtml(s.statut)}</span>
      </div>`).join('');
    results.querySelectorAll('.hotel-ft-sejour-row').forEach(row => {
      row.addEventListener('click', () => {
        const id = parseInt(row.dataset.id, 10);
        if (id > 0) {
          const u = new URL(window.location.href);
          u.searchParams.set('sejour_id', String(id));
          window.history.replaceState({}, '', u.toString());
          loadSejour(id);
        }
      });
    });
  }

  // ── Chargement du séjour + preview ───────────────────────────────────
  async function loadSejour(sejourId) {
    currentSejourId = sejourId;
    if (picker) picker.style.display = 'none';
    if (bloc) bloc.style.display = '';
    if (bloc) bloc.innerHTML = '<div class="hotel-ft-empty">Chargement…</div>';
    extrasManuels = [];
    paiements = [];
    showAlert(null);

    const r = await jsonFetch('/api/hotel/factures/preview?sejour_id=' + sejourId);
    if (!r.ok || !r.data || !r.data.success) {
      const msg = (r.data && r.data.message) || ('Erreur ' + r.status);
      if (bloc) bloc.innerHTML = '<div class="hotel-ft-banner warn">' + escapeHtml(msg) + '</div>';
      return;
    }
    previewData = r.data.data;
    if (!previewData) {
      if (bloc) bloc.innerHTML = '<div class="hotel-ft-banner warn">Réponse serveur : donnée de prévisualisation vide.</div>';
      return;
    }
    try {
      renderBloc();
    } catch (err) {
      console.error('[hotel-ft] renderBloc crashed', err);
      if (bloc) bloc.innerHTML = '<div class="hotel-ft-banner warn">Erreur de rendu : ' + escapeHtml(err && err.message || String(err)) + '</div>';
    }
  }

  // ── renderBloc : récap + formulaire ──────────────────────────────────
  function renderBloc() {
    if (!previewData) return;
    const s = previewData.sejour;
    const n = previewData.nuits;
    const e = previewData.extras;
    const ex = previewData.existing_facture;
    const alreadyFactured = ex && ex.statut !== 'annulee';

    const nightsRows = (n.lignes || []).map(l => '<tr><td>' + fmtDate(l.date) + '</td><td class="num">' + fmtFc(l.prix) + '</td></tr>').join('');
    const remiseRow = n.remise_montant > 0
      ? '<tr><td>Remise séjour (' + n.remise_pct + '%)</td><td class="num" style="color:#991b1b;">− ' + fmtFc(n.remise_montant) + '</td></tr>'
      : '';
    const extrasRows = (e.lignes || []).map(x => '<tr><td>' + fmtDate(x.date) + '</td><td>' + escapeHtml(x.description) + '</td><td class="num">' + fmtFc(x.montant) + '</td></tr>').join('');
    const extrasTable = (e.lignes || []).length === 0
      ? '<div style="font-size:.82rem;color:var(--muted);font-style:italic;">Aucun extra saisi depuis la caisse pour ce séjour.</div>'
      : '<table class="hotel-ft-table"><thead><tr><th>Date</th><th>Description</th><th class="num">Montant</th></tr></thead><tbody>' + extrasRows + '</tbody></table>';
    const acomptesRows = (previewData.acomptes || []).map(a => '<tr><td>' + fmtDate(a.date) + '</td><td>' + escapeHtml(a.mode) + '</td><td class="num">' + fmtFc(a.montant) + '</td></tr>').join('');
    const acomptesTable = (previewData.acomptes || []).length === 0
      ? '<div style="font-size:.82rem;color:var(--muted);font-style:italic;">Aucun acompte.</div>'
      : '<table class="hotel-ft-table"><thead><tr><th>Date</th><th>Mode</th><th class="num">Montant</th></tr></thead><tbody>' + acomptesRows + '</tbody></table>';

    const totalsAndEmit = alreadyFactured ? '' : `
      <div class="hotel-ft-totals">
        <div class="row"><span>Total nuits (après remise)</span><span>${fmtFc(n.total_ht)}</span></div>
        <div class="row"><span>Total extras</span><span>${fmtFc(e.total_ht)}</span></div>
        <div class="row"><span>Total brut</span><span>${fmtFc(previewData.total_brut)}</span></div>
        <div class="row"><span>TVA (${previewData.tva.type})</span><span>${fmtFc(previewData.tva.montant)}</span></div>
        <div class="row total"><span>Total final</span><span>${fmtFc(previewData.total_final)}</span></div>
        <div class="row"><span>Acomptes déjà versés</span><span>− ${fmtFc(previewData.acompte_total)}</span></div>
        <div class="row total"><span>Solde dû</span><span>${fmtFc(previewData.solde_du)}</span></div>
      </div>
      <div style="margin-top:1rem;">
        <label style="display:flex;align-items:center;gap:.5rem;font-size:.85rem;">
          <input type="checkbox" id="hotel-ft-checkout">
          Clôturer le séjour (check-out) en même temps
        </label>
      </div>
      <div class="hotel-ft-actions">
        <button type="button" class="btn btn-ghost" id="hotel-ft-back">← Choisir un autre séjour</button>
        <button type="button" class="btn btn-primary" id="hotel-ft-emit">Émettre la facture</button>
      </div>`;

    const existingBanner = alreadyFactured ? `
      <div class="hotel-ft-banner warn" style="margin-top:1rem;">
        ⚠ Facture existante : <strong>${escapeHtml(ex.numero_facture)}</strong> (${fmtFc(ex.total_final)}) — ${escapeHtml(ex.statut)}.
        <div style="margin-top:.5rem;display:flex;gap:.5rem;justify-content:flex-end;">
          <a href="/facture/${ex.vente_id}" target="_blank" class="btn btn-primary btn-small">Voir / Imprimer</a>
          <button type="button" class="btn btn-ghost btn-small" id="hotel-ft-cancel-existing">Annuler</button>
        </div>
      </div>` : '';

    bloc.innerHTML = `
      <div style="display:flex;justify-content:space-between;align-items:start;flex-wrap:wrap;gap:.5rem;">
        <div>
          <h3 style="margin:0;">${escapeHtml(s.guest.prenom || '')} ${escapeHtml(s.guest.nom || '')}</h3>
          <div style="font-size:.82rem;color:var(--muted);margin-top:2px;">Chambre ${escapeHtml(s.chambre.numero)} · ${escapeHtml(s.chambre.type)} · ${fmtDate(s.date_arrivee)} → ${fmtDate(s.date_depart_prevue)}</div>
          ${s.client ? '<div style="font-size:.82rem;color:var(--muted);margin-top:2px;">Client lié : <strong>' + escapeHtml(s.client.code_client) + '</strong></div>' : ''}
        </div>
        <div style="text-align:right;">
          <div style="font-size:.7rem;text-transform:uppercase;color:var(--muted);">Séjour #${s.id}</div>
          <div style="font-size:.7rem;text-transform:uppercase;color:var(--muted);margin-top:4px;">Statut : <strong>${escapeHtml(s.statut)}</strong></div>
        </div>
      </div>
      ${existingBanner}
      <div style="margin-top:1.25rem;">
        <h4 class="hotel-ft-section-title">Nuits (${n.nb_nuits})</h4>
        <table class="hotel-ft-table"><thead><tr><th>Date</th><th class="num">Prix</th></tr></thead><tbody>${nightsRows}${remiseRow}</tbody></table>
      </div>
      <div style="margin-top:1.25rem;">
        <h4 class="hotel-ft-section-title">Extras (${(e.lignes||[]).length} saisie${(e.lignes||[]).length > 1 ? 's' : ''})</h4>
        ${extrasTable}
        <div style="margin-top:.85rem;font-size:.82rem;color:var(--muted);">Ajout manuel (minibar, casse, pourboire…) :</div>
        <div class="hotel-ft-extras-add">
          <input type="text" id="hotel-ft-extra-desc" placeholder="Description">
          <input type="number" id="hotel-ft-extra-montant" placeholder="Montant FC" min="0" step="0.01">
          <button type="button" class="btn btn-ghost btn-small" id="hotel-ft-extra-add">+ Ajouter</button>
        </div>
        <div id="hotel-ft-extras-manuels-list"></div>
      </div>
      <div style="margin-top:1.25rem;">
        <h4 class="hotel-ft-section-title">Acomptes déjà versés</h4>
        ${acomptesTable}
      </div>
      ${totalsAndEmit}
    `;

    const $extraAdd = $('hotel-ft-extra-add');
    if ($extraAdd) {
      $extraAdd.addEventListener('click', () => {
        const desc = $('hotel-ft-extra-desc').value.trim();
        const montant = parseFloat($('hotel-ft-extra-montant').value);
        if (!desc || !(montant > 0)) return;
        extrasManuels.push({ description: desc, montant });
        $('hotel-ft-extra-desc').value = '';
        $('hotel-ft-extra-montant').value = '';
        renderExtrasManuels();
      });
    }
    const $emit = $('hotel-ft-emit');
    if ($emit) $emit.addEventListener('click', openHotelInvoiceInfoModal);
    const $back = $('hotel-ft-back');
    if ($back) $back.addEventListener('click', () => {
      currentSejourId = null;
      previewData = null;
      extrasManuels = [];
      paiements = [];
      bloc.innerHTML = '';
      bloc.style.display = 'none';
      picker.style.display = '';
      const u = new URL(window.location.href);
      u.searchParams.delete('sejour_id');
      window.history.replaceState({}, '', u.toString());
    });
    const $cancelExisting = $('hotel-ft-cancel-existing');
    if ($cancelExisting) $cancelExisting.addEventListener('click', cancelExistingFacture);

    renderExtrasManuels();
  }

  function renderExtrasManuels() {
    const $list = $('hotel-ft-extras-manuels-list');
    if (!$list) return;
    if (extrasManuels.length === 0) { $list.innerHTML = ''; return; }
    const rows = extrasManuels.map((x, i) => '<tr><td>' + escapeHtml(x.description) + '</td><td class="num">' + fmtFc(x.montant) + '</td><td><button type="button" class="btn btn-ghost btn-small" data-rm="' + i + '">×</button></td></tr>').join('');
    $list.innerHTML =
      '<table class="hotel-ft-table" style="margin-top:.5rem;"><thead><tr><th>Description</th><th class="num">Montant</th><th></th></tr></thead><tbody>' +
      rows + '</tbody></table>' +
      '<div style="text-align:right;font-size:.85rem;color:var(--muted);margin-top:.25rem;">Sous-total : <strong>' +
      fmtFc(extrasManuels.reduce((s,x) => s + x.montant, 0)) + '</strong></div>';
    $list.querySelectorAll('[data-rm]').forEach(b => {
      b.addEventListener('click', () => {
        const i = parseInt(b.dataset.rm, 10);
        extrasManuels.splice(i, 1);
        renderExtrasManuels();
      });
    });
  }

  // ── CALCUL SOLDE COURANT ──────────────────────────────────────────────
  function computeSoldeCourant() {
    if (!previewData) return 0;
    return previewData.solde_du + extrasManuels.reduce((s, x) => s + x.montant, 0);
  }
  function getTotalPaiements() {
    return paiements.reduce((s, p) => s + (parseFloat(p.montant) || 0), 0);
  }


  // Helpers de rendu ticket (réutilisés par preview et receipt)
  function buildHotelReceiptItems(n, e, manuels) {
    var s = previewData.sejour;
    var html = '<table class="receipt-table"><thead><tr><th>Article</th><th style="text-align:right">Total HT</th></tr></thead><tbody>';
    (n.lignes || []).forEach(function(l) {
      html += '<tr class="item-name-row"><td colspan="2"><span class="item-name">Nuit ' + fmtDate(l.date) + ' — Chambre ' + escapeHtml(s.chambre.numero) + '<span class="item-tax-badge">B</span>[SER]</span></td></tr><tr class="item-detail-row"><td class="item-qty">1 × ' + fmtFc(l.prix) + '</td><td class="item-total" style="text-align:right;">' + fmtFc(l.prix) + '</td></tr>';
    });
    (e.lignes || []).forEach(function(x) {
      html += '<tr class="item-name-row"><td colspan="2"><span class="item-name">' + escapeHtml(x.description) + '<span class="item-tax-badge">B</span>[SER]</span></td></tr><tr class="item-detail-row"><td class="item-qty">1 × ' + fmtFc(x.montant) + '</td><td class="item-total" style="text-align:right;">' + fmtFc(x.montant) + '</td></tr>';
    });
    (manuels || []).forEach(function(x) {
      html += '<tr class="item-name-row"><td colspan="2"><span class="item-name">' + escapeHtml(x.description) + ' <small style="color:#b45309">(extra manuel)</small><span class="item-tax-badge">B</span>[SER]</span></td></tr><tr class="item-detail-row"><td class="item-qty">1 × ' + fmtFc(x.montant) + '</td><td class="item-total" style="text-align:right;">' + fmtFc(x.montant) + '</td></tr>';
    });
    return html + '</tbody></table>';
  }

  function buildHotelReceiptInfoSection() {
    var s = previewData.sejour;
    var sellerName = (typeof CURRENT_USER !== 'undefined' && CURRENT_USER.fullName) ? CURRENT_USER.fullName : (STORE_INFO.name || '');
    var agentCode  = (typeof CURRENT_USER !== 'undefined' && CURRENT_USER.agentCode) ? CURRENT_USER.agentCode : '';
    return '<div class="client-vendor-info" style="border-top:1px dashed #ccc; margin-top:6px; padding-top:6px; text-align:left; font-size:11px; line-height:1.5;">'
      + '<div style="display:flex;justify-content:space-between;gap:10px;"><span><strong>VENDEUR:</strong></span><span>' + escapeHtml(sellerName) + '</span></div>'
      + (agentCode ? '<div style="display:flex;justify-content:space-between;gap:10px;"><span><strong>NUM AGENT:</strong></span><span>' + escapeHtml(agentCode) + '</span></div>' : '')
      + '<div style="display:flex;justify-content:space-between;gap:10px;"><span><strong>CLIENT:</strong></span><span>' + escapeHtml(((s.guest.prenom||`)+' '+(s.guest.nom||`)).trim()) + '</span></div>'
      + (s.guest.telephone ? '<div style="display:flex;justify-content:space-between;gap:10px;"><span><strong>TEL:</strong></span><span>' + escapeHtml(s.guest.telephone) + '</span></div>' : '')
      + (s.client ? '<div style="display:flex;justify-content:space-between;gap:10px;"><span><strong>CODE CLIENT:</strong></span><span>' + escapeHtml(s.client.code_client) + '</span></div>' : '')
      + '<div style="display:flex;justify-content:space-between;gap:10px;"><span><strong>SEJOUR:</strong></span><span>#' + s.id + ' — Chambre ' + escapeHtml(s.chambre.numero) + '</span></div>'
      + '<div style="display:flex;justify-content:space-between;gap:10px;"><span><strong>SERVICE:</strong></span><span>Hôtellerie</span></div>'
      + '</div>';
  }

  function buildHotelReceiptTotals(total) {
    var n = previewData.nuits;
    var e = previewData.extras;
    var manualTotal = extrasManuels.reduce(function(a, x) { return a + x.montant; }, 0);
    var usdRate = (typeof USD_RATE !== 'undefined' && USD_RATE > 0) ? USD_RATE : 0;
    var amountInWords = (typeof posCart !== 'undefined' && posCart.numberToFrenchWords)
      ? posCart.numberToFrenchWords(total)
      : '';
    var html = '';
    html += '<div class="receipt-total-row"><span>Total nuits</span><span>' + fmtFc(n.total_ht) + '</span></div>';
    html += '<div class="receipt-total-row"><span>Total extras caisse</span><span>' + fmtFc(e.total_ht) + '</span></div>';
    if (manualTotal > 0) {
      html += '<div class="receipt-total-row"><span>Total extras manuels</span><span>' + fmtFc(manualTotal) + '</span></div>';
    }
    html += '<div class="receipt-total-row"><span>TVA (B — non collectée)</span><span>0,00 Fc</span></div>';
    html += '<div class="receipt-total-row grand-total"><span>TOTAL TTC :</span><span>' + fmtFc(total) + '</span></div>';
    if (usdRate) {
      html += '<div class="receipt-total-row" style="font-size:11px; color:#555;"><span>TAUX DU JOUR :</span><span>' + usdRate + ' Fc/USD</span></div>';
      html += '<div class="receipt-total-row" style="font-size:11px; color:#555;"><span>Equivalent en USD :</span><span>' + (total / usdRate).toFixed(2) + ' $</span></div>';
    }
    if (amountInWords) {
      html += '<div style="text-align:center; font-size:12px; color:#888; font-style:italic; margin-top:2px;">Arrêtée la présente facture à la somme de ' + escapeHtml(amountInWords) + ' congolais toutes taxes comprises</div>';
    }
    return html;
  }
  function buildHotelReceiptPayments() {
    if (!paiements || paiements.length === 0) return '';
    var html = '<div style="border-top:1px dashed #ccc; margin-top:6px; padding-top:6px;">';
    paiements.forEach(function(p) {
      html += '<div class="receipt-total-row" style="font-size:11px; color:#555;"><span>' + escapeHtml(paymentLabel(p.mode)) + ' :</span><span>' + fmtFc(p.montant) + '</span></div>';
    });
    return html + '</div>';
  }


  // ===============================================================
  // PANEL PAIEMENT/REFS — équivalent invoice-info-modal de /caisse
  //   Mêmes ids de DOM que /caisse, mêmes handlers, mêmes règles de
  //   validation (PHONE_NUMBER_REGEX, type client PP/PM/PL/PC/AO,
  //   ref obligatoire pour AO, NIF obligatoire sauf PP, etc.).
  // ===============================================================

  // Mapping mode hôtel (UI) → nomenclature DGI (cf. PAY_MODE_DGI).
  // Paiements UI: 'cash' | 'mobile_money' | 'carte' | 'autre'
  // Paiements DGI: 'ESPECES' | 'MOBILEMONEY' | 'CARTEBANCAIRE' | 'AUTRE'

  function addHotelModalPaymentLine(type, amount) {
    if (typeof type === 'undefined') type = 'ESPECES';
    if (typeof amount === 'undefined') amount = 0;
    var list = $('modal-payments-list');
    if (!list) return;
    var maxLines = 6;
    if (list.children.length >= maxLines) return;
    var line = document.createElement('div');
    line.className = 'modal-payment-line';
    line.style.cssText = 'display: grid; grid-template-columns: 1fr 100px 28px; gap: 8px; align-items: end;';
    line.innerHTML =
        '<div>' +
        '  <label style="font-size: 0.7rem; color: #166534; display: block; margin-bottom: 4px;">Type</label>' +
        '  <select class="modal-payment-type client-number-input" style="width: 100%; background: #fff;" onchange="hotelCalculateModalPayments()">' +
        '    <option value="ESPECES"' + (type === 'ESPECES' ? ' selected' : '') + '>Espèces</option>' +
        '    <option value="MOBILEMONEY"' + (type === 'MOBILEMONEY' ? ' selected' : '') + '>Mobile Money</option>' +
        '    <option value="CARTEBANCAIRE"' + (type === 'CARTEBANCAIRE' ? ' selected' : '') + '>Carte Bancaire</option>' +
        '    <option value="VIREMENT"' + (type === 'VIREMENT' ? ' selected' : '') + '>Virement</option>' +
        '    <option value="CREDIT"' + (type === 'CREDIT' ? ' selected' : '') + '>Crédit</option>' +
        '    <option value="CHEQUES"' + (type === 'CHEQUES' ? ' selected' : '') + '>Chèques</option>' +
        '    <option value="AUTRE"' + (type === 'AUTRE' ? ' selected' : '') + '>Autres</option>' +
        '  </select>' +
        '</div>' +
        '<div>' +
        '  <label style="font-size: 0.7rem; color: #166534; display: block; margin-bottom: 4px;">Montant</label>' +
        '  <input type="number" class="modal-payment-amount client-number-input" step="0.01" min="0" placeholder="0.00" value="' + (amount > 0 ? amount.toFixed(2) : '') + '" style="width: 100%;" oninput="hotelCalculateModalPayments()">' +
        '</div>' +
        '<button type="button" onclick="removeHotelModalPaymentLine(this)" style="background: #fee2e2; color: #dc2626; border: none; border-radius: 6px; width: 28px; height: 28px; display: flex; align-items: center; justify-content: center; cursor: pointer; font-size: 1rem;">×</button>';
    list.appendChild(line);
    hotelUpdateModalPaymentRemoveButtons();
    var addBtn = $('add-payment-line-btn');
    if (addBtn) addBtn.style.display = list.children.length >= maxLines ? 'none' : 'flex';
    hotelCalculateModalPayments();
  }

  function removeHotelModalPaymentLine(btn) {
    var line = btn.closest('.modal-payment-line');
    if (line) line.remove();
    hotelUpdateModalPaymentRemoveButtons();
    var list = $('modal-payments-list');
    var addBtn = $('add-payment-line-btn');
    if (addBtn && list) addBtn.style.display = list.children.length >= 6 ? 'none' : 'flex';
    hotelCalculateModalPayments();
  }

  function hotelUpdateModalPaymentRemoveButtons() {
    var lines = document.querySelectorAll('.modal-payment-line');
    lines.forEach(function(line) {
      var btn = line.querySelector('button');
      if (btn) btn.style.display = lines.length > 1 ? 'flex' : 'none';
    });
  }

  function hotelCalculateModalPayments() {
    var lines = document.querySelectorAll('.modal-payment-line');
    var total = 0;
    var payments = [];
    lines.forEach(function(line) {
      var type = line.querySelector('.modal-payment-type') ? line.querySelector('.modal-payment-type').value : 'ESPECES';
      var amount = parseFloat(line.querySelector('.modal-payment-amount') ? line.querySelector('.modal-payment-amount').value : 0) || 0;
      total += amount;
      payments.push({ type: type, amount: amount });
    });
    var cartTotal = computeSoldeCourant();
    var diff = total - cartTotal;
    var summary = $('modal-payment-summary');
    var summaryLabel = $('modal-payment-summary-label');
    var summaryAmount = $('modal-payment-summary-amount');
    if (summary && summaryLabel && summaryAmount) {
      if (Math.abs(diff) < 0.001) {
        summary.style.display = 'none';
      } else if (diff < 0) {
        summary.style.display = 'block';
        summaryLabel.textContent = 'Reste à payer :';
        summaryLabel.style.color = '#c62828';
        summaryAmount.textContent = Math.abs(diff).toFixed(2) + ' Fc';
        summaryAmount.style.color = '#c62828';
      } else {
        summary.style.display = 'block';
        summaryLabel.textContent = 'Monnaie à rendre :';
        summaryLabel.style.color = '#166534';
        summaryAmount.textContent = diff.toFixed(2) + ' Fc';
        summaryAmount.style.color = '#0B5E88';
      }
    }
    var hiddenType = $('modal-payment-type');
    if (hiddenType && payments.length > 0) hiddenType.value = payments[0].type;
    return payments;
  }

  function getHotelModalPayments() {
    var lines = document.querySelectorAll('.modal-payment-line');
    var payments = [];
    lines.forEach(function(line) {
      var type = line.querySelector('.modal-payment-type') ? line.querySelector('.modal-payment-type').value : 'ESPECES';
      var amount = parseFloat(line.querySelector('.modal-payment-amount') ? line.querySelector('.modal-payment-amount').value : 0) || 0;
      if (amount > 0) payments.push({ type: type, amount: amount });
    });
    return payments;
  }

  // ── REF DOCS (jusqu'à 8) — pattern /caisse
  function addHotelRefDocLine(value) {
    if (typeof value === 'undefined') value = '';
    var list = $('modal-ref-docs-list');
    if (!list) return;
    var maxLines = 8;
    var mainInput = $('modal-invoice-ref');
    var totalLines = (mainInput ? 1 : 0) + list.children.length;
    if (totalLines >= maxLines) return;
    var line = document.createElement('div');
    line.className = 'modal-ref-doc-line';
    line.style.cssText = 'display: flex; gap: 8px; align-items: center;';
    line.innerHTML =
        '<input type="text" class="modal-ref-doc-input client-number-input" placeholder="Réf..." value="' + value + '" style="width: 100%;" oninput="syncHotelRefDocsCount()">' +
        '<button type="button" onclick="removeHotelRefDocLine(this)" style="background: #fee2e2; color: #dc2626; border: none; border-radius: 6px; width: 28px; height: 28px; display: flex; align-items: center; justify-content: center; cursor: pointer; font-size: 1rem;">×</button>';
    list.appendChild(line);
    hotelUpdateAddRefDocButton();
    syncHotelRefDocsCount();
  }

  function removeHotelRefDocLine(btn) {
    var line = btn.closest('.modal-ref-doc-line');
    if (line) line.remove();
    hotelUpdateAddRefDocButton();
    syncHotelRefDocsCount();
  }

  function hotelUpdateAddRefDocButton() {
    var list = $('modal-ref-docs-list');
    var btn = $('add-ref-doc-btn');
    var mainInput = $('modal-invoice-ref');
    if (!list || !btn) return;
    var maxLines = 8;
    var totalLines = (mainInput ? 1 : 0) + list.children.length;
    btn.style.display = totalLines >= maxLines ? 'none' : 'flex';
  }

  function syncHotelRefDocsCount() {
    var list = $('modal-ref-docs-list');
    var countInput = $('modal-ref-docs-count');
    var mainInput = $('modal-invoice-ref');
    if (countInput) {
      var totalLines = (mainInput ? 1 : 0) + (list ? list.children.length : 0);
      countInput.value = totalLines;
    }
  }

  function getHotelModalRefDocs() {
    var refs = [];
    var mainInput = $('modal-invoice-ref');
    if (mainInput && mainInput.value && mainInput.value.trim()) refs.push(mainInput.value.trim());
    var list = $('modal-ref-docs-list');
    if (list) {
      list.querySelectorAll('.modal-ref-doc-input').forEach(function(input) {
        if (input.value && input.value.trim()) refs.push(input.value.trim());
      });
    }
    return refs;
  }

  // Highlight un champ en rouge (style /caisse)
  function hotelHighlightModalField(fieldId) {
    var el = $('hotel-' + fieldId) || $(fieldId);
    if (!el) return;
    var originalBoxShadow = el.style.boxShadow;
    var originalBorderColor = el.style.borderColor;
    el.style.boxShadow = '0 0 0 3px rgba(220, 38, 38, 0.5)';
    el.style.borderColor = '#dc2626';
    var clear = function() {
      el.style.boxShadow = originalBoxShadow;
      el.style.borderColor = originalBorderColor;
      el.removeEventListener('input', clear);
      el.removeEventListener('change', clear);
      el.removeEventListener('focus', clear);
    };
    el.addEventListener('input', clear, { once: true });
    el.addEventListener('change', clear, { once: true });
    el.addEventListener('focus', clear, { once: true });
    el.focus();
  }

  // Charge les types de clients dans #modal-client-type (appels API)
  async function hotelLoadClientTypes() {
    var typeSelect = $('modal-client-type');
    if (!typeSelect) return;
    try {
      var res = await fetch(APP_URL + '/api/client/types');
      var types = await res.json();
      if (Array.isArray(types) && types.length > 0) {
        typeSelect.innerHTML = '';
        var defOpt = document.createElement('option');
        defOpt.value = '';
        defOpt.textContent = 'Type client';
        typeSelect.appendChild(defOpt);
        types.forEach(function(t) {
          var o = document.createElement('option');
          o.value = t.id;
          o.textContent = (t.code || '') + ' - ' + (t.description || t.name || '');
          o.dataset.code = t.code || '';
          typeSelect.appendChild(o);
        });
      }
    } catch (e) {
      console.warn('[hotel-ft] loadClientTypes:', e);
    }
  }

  // ===============================================================
  // OUVERTURE + VALIDATION DU PANEL INFO FACTURE (ÉTAPE 1/3)
  //   Le seul point de divergence avec /caisse : le client est pré-rempli
  //   avec les infos du GUEST (previewData.sejour.guest) au lieu d'être
  //   saisi par l'utilisateur.
  // ===============================================================

  async function openHotelInvoiceInfoModal() {
    var modal = $('invoice-info-modal');
    if (!modal) { console.error('[hotel-ft] invoice-info-modal absent'); return; }
    if (!previewData) { console.error('[hotel-ft] previewData manquant'); return; }

    var s = previewData.sejour;

    // Pré-remplir la liste des extras manuels (lecture seule — déjà saisis dans le bloc principal)
    var exWrap = $('hotel-ft-preview-extras');
    if (exWrap) {
      if (extrasManuels.length === 0) {
        exWrap.innerHTML = '<div style="font-size:.85rem;color:var(--muted);font-style:italic;">Aucun extra manuel ajouté.</div>';
      } else {
        var rows = extrasManuels.map(function(x) {
          return '<tr><td>' + escapeHtml(x.description) + '</td><td class="num">' + fmtFc(x.montant) + '</td></tr>';
        }).join('');
        exWrap.innerHTML = '<table class="hotel-ft-table"><thead><tr><th>Description</th><th class="num">Montant</th></tr></thead><tbody>' + rows + '</tbody></table>';
      }
    }

    // Type de facture par défaut : HB (Hotel Bill)
    var invoiceTypeSelect = $('modal-invoice-type');
    if (invoiceTypeSelect && (!invoiceTypeSelect.value || invoiceTypeSelect.value === 'FV')) {
      // défaut : HB si boutique assujetie, FV sinon (cohérence avec /caisse)
      var isAssujeti = !!(typeof STORE_INFO !== 'undefined' && STORE_INFO && STORE_INFO.homologation);
      invoiceTypeSelect.value = isAssujeti ? 'HB' : 'FV';
    }

    // Références documents (vide au départ — max 8 cf. /caisse)
    var refList = $('modal-ref-docs-list');
    if (refList) refList.innerHTML = '';
    syncHotelRefDocsCount();
    hotelUpdateAddRefDocButton();

    // Modes de paiement : 1 ligne pré-remplie avec le solde dû en Espèces
    var solde = computeSoldeCourant();
    paiements = [{ mode: 'cash', montant: solde }];
    // On travaille avec les paiements DGI (la conversion cash→ESPECES est faite plus tard)
    $('modal-payments-list').innerHTML = '';
    addHotelModalPaymentLine('ESPECES', solde);
    // Stocke le mapping pour la conversion finale (UI → DGI)
    paiements = [{ mode: 'cash', dgiType: 'ESPECES', montant: solde }];

    // ─── Pré-remplissage info client (guest) ──────────────────────────
    $('modal-client-name').value = ((s.guest.prenom || '') + ' ' + (s.guest.nom || '')).trim();
    $('modal-client-number').value = (s.guest.telephone || '').replace(/[^0-9]/g, '');
    $('modal-client-nif').value = '';
    $('modal-client-address').value = '';
    $('modal-checkout').value = (typeof $('hotel-ft-checkout') !== 'undefined' && $('hotel-ft-checkout') && $('hotel-ft-checkout').checked) ? '1' : '0';

    // Type de client par défaut : PP (personne physique)
    // Si on a un code client POS lié, on tente de mapper : PM/PC/PL/AO (cf. /caisse)
    var clientTypeCode = 'PP';
    if (s.client && s.client.code_client) {
      var head = (s.client.code_client || '').toUpperCase().slice(0, 2);
      if (['PM', 'PC', 'PL', 'AO'].indexOf(head) >= 0) clientTypeCode = head;
    }

    // Charge les types de clients et sélectionne celui du guest
    await hotelLoadClientTypes();
    var typeSelect = $('modal-client-type');
    if (typeSelect && clientTypeCode) {
      var matchedOpt = null;
      for (var i = 0; i < typeSelect.options.length; i++) {
        var o = typeSelect.options[i];
        if ((o.dataset && o.dataset.code || '').toUpperCase() === clientTypeCode) { matchedOpt = o; break; }
      }
      if (matchedOpt) {
        typeSelect.value = matchedOpt.value;
      } else {
        // fallback : on tente un match par texte
        for (var j = 0; j < typeSelect.options.length; j++) {
          if (typeSelect.options[j].textContent.toUpperCase().indexOf(clientTypeCode) === 0) {
            typeSelect.value = typeSelect.options[j].value;
            break;
          }
        }
      }
    }

    // Affichage
    modal.classList.add('active');
    document.body.style.overflow = 'hidden';
  }

  function closeHotelInvoiceInfoModal() {
    var modal = $('invoice-info-modal');
    if (modal) modal.classList.remove('active');
    document.body.style.overflow = '';
  }

  // Validation des champs + constitution de paiements[] (mode UI 'cash' etc.)
  // puis bascule vers le modal preview global. Cette fonction est le
  // miroir de confirmInvoiceInfo() de app.js, adaptée à l'hôtel.
  function confirmHotelInvoiceInfo() {
    var errBox = $('modal-invoice-error');
    if (errBox) { errBox.style.display = 'none'; errBox.textContent = ''; }

    // 1. Type de facture — on remplit la variable globale invoiceType
    var isAdmin = typeof CURRENT_USER !== 'undefined' && CURRENT_USER !== null && ['admin', 'super_admin'].indexOf(CURRENT_USER.role) >= 0;
    var isAssujeti = !!(typeof STORE_INFO !== 'undefined' && STORE_INFO && STORE_INFO.homologation);
    var invoiceType = $('modal-invoice-type') && isAdmin ? ($('modal-invoice-type').value || 'HB') : (isAssujeti ? 'HB' : 'FV');
    if (!isAssujeti) invoiceType = 'FV';
    window.hotelInvoiceType = invoiceType;

    // REF FACTURE + EXONÉRATION (RAM/RRR/RAN/COR) — pattern /caisse
    window.hotelRefFacture   = ($('modal-invoice-num') && $('modal-invoice-num').value) ? $('modal-invoice-num').value.trim() : '';
    window.hotelExoneration = ($('modal-exoneration') && $('modal-exoneration').value) ? $('modal-exoneration').value : '';

    var invoiceRefs = getHotelModalRefDocs();
    var invoiceRef = invoiceRefs[0] || '';

    var clientName = $('modal-client-name') ? $('modal-client-name').value : '';
    var clientNumber = $('modal-client-number') ? ($('modal-client-number').value || '').trim() : '';
    var clientTypeId = $('modal-client-type') ? ($('modal-client-type').value || '') : '';
    var clientNif = $('modal-client-nif') ? $('modal-client-nif').value : '';
    var clientAddress = $('modal-client-address') ? $('modal-client-address').value : '';

    // 2. Validation téléphone (PHONE_NUMBER_REGEX = /^0[89]\d{8}$/, déclaré
    //    const en haut de app.js → global au scope du script, PAS sur window).
    if (clientNumber && !PHONE_NUMBER_REGEX.test(clientNumber)) {
      alert('Le numéro de téléphone doit respecter le format 08xxxxxxxx ou 09xxxxxxxx.');
      hotelHighlightModalField('modal-client-number');
      return;
    }

    // 3. Code type client (extrait depuis le texte "PP - Particulier")
    var clientTypeText = '';
    var typeSel = $('modal-client-type');
    if (typeSel && typeSel.selectedIndex >= 0) {
      clientTypeText = (typeSel.options[typeSel.selectedIndex].textContent || '').trim();
    }
    var clientTypeCode = (clientTypeText.split(' - ')[0] || '').trim().toUpperCase();

    // 4. Adresse obligatoire si PM/PL/PC/AO
    if (['PM', 'PL', 'PC', 'AO'].indexOf(clientTypeCode) >= 0 && !clientAddress.trim()) {
      alert("L'adresse est obligatoire pour ce type de client.");
      hotelHighlightModalField('modal-client-address');
      return;
    }
    // 5. NIF obligatoire sauf PP
    if (clientTypeCode !== 'PP' && clientTypeCode !== '' && !clientNif.trim()) {
      alert("Le NIF est obligatoire pour ce type de client.");
      hotelHighlightModalField('modal-client-nif');
      return;
    }
    // 6. Pour AO : au moins 1 référence document non vide
    if (clientTypeCode === 'AO' && (invoiceRefs.length === 0 || invoiceRefs.every(function(r) { return !r.trim(); }))) {
      alert("La référence document est obligatoire pour les ambassades et organisations internationales.");
      hotelHighlightModalField('modal-invoice-ref');
      return;
    }

    // 7. Paiements : on récupère depuis #modal-payments-list (format DGI)
    var dgiPayments = getHotelModalPayments();
    var totalSolde = computeSoldeCourant();
    var dgiTotal = dgiPayments.reduce(function(s, p) { return s + p.amount; }, 0);
    if (dgiPayments.length === 0 || dgiTotal < totalSolde - 0.001) {
      alert('Le montant total des paiements est insuffisant par rapport au solde de la facture (' + fmtFc(totalSolde) + ').');
      var firstAmt = document.querySelector('#modal-payments-list .modal-payment-amount');
      if (firstAmt) firstAmt.focus();
      return;
    }

    // 8. Mémorise les paiements pour processEmission (mapping DGI->UI)
    paiements = dgiPayments.map(function(p) {
      var uiMode = 'cash';
      if (p.type === 'MOBILEMONEY') uiMode = 'mobile_money';
      else if (p.type === 'CARTEBANCAIRE') uiMode = 'carte';
      else if (p.type === 'AUTRE') uiMode = 'autre';
      return { mode: uiMode, dgiType: p.type, montant: p.amount };
    });

    // 9. Ferme le panel, ouvre le preview
    closeHotelInvoiceInfoModal();
    showHotelPreviewTicket();
  }

  // ===============================================================
  // ETAPE 2/3 : MODAL PREVIEW TICKET (global #preview-modal)
  // Réutilise les helpers buildHotelReceipt* posés plus haut dans le fichier.
  // Réécrit le footer du modal preview pour pointer sur nos handlers.
  // ===============================================================
  function showHotelPreviewTicket() {
    var s = previewData.sejour;
    var n = previewData.nuits;
    var e = previewData.extras;
    var total = computeSoldeCourant();

    var itemsHtml = buildHotelReceiptItems(n, e, extrasManuels);
    var infoSection = buildHotelReceiptInfoSection();
    var totalsHtml  = buildHotelReceiptTotals(total);
    var paymentsHtml = buildHotelReceiptPayments();
    var typeLabel = 'Facture Hôtel — Régime type B (HB)';
    var now = new Date().toLocaleString('fr-FR', { day:'2-digit', month:'2-digit', year:'numeric', hour:'2-digit', minute:'2-digit' });

    var previewHtml =
      '<div class="receipt"><div class="receipt-header">' +
        (STORE_INFO.logo ? '<img src="' + STORE_INFO.logo + '" class="receipt-logo" alt="Logo">' : '') +
        '<div class="store-name">' + escapeHtml(STORE_INFO.name || '') + '</div>' +
        '<div class="store-info">' +
          '<div><strong>Point de vente :</strong> ' + escapeHtml(STORE_INFO.pdv || '') + '</div>' +
          '<div>Adresse : ' + escapeHtml(STORE_INFO.address || '') + '</div>' +
          '<div>Tel: ' + escapeHtml(STORE_INFO.phone || '') + '</div>' +
          (STORE_INFO.email ? '<div>Email: ' + escapeHtml(STORE_INFO.email) + '</div>' : '') +
          '<div>ID Nat: ' + escapeHtml(STORE_INFO.ice || '') + '</div>' +
          (STORE_INFO.rccm ? '<div>RCCM: ' + escapeHtml(STORE_INFO.rccm) + '</div>' : '') +
          (STORE_INFO.isf ? '<div>Numero Impot: ' + escapeHtml(STORE_INFO.isf) + '</div>' : '') +
        '</div>' + infoSection +
      '</div>' +
      '<div class="receipt-meta" style="justify-content:center; font-size:14px; font-weight:600;">' + typeLabel + '</div>' +
      (window.hotelRefFacture ? '<div style="text-align:center; font-size:12px; color:#666;">Réf : ' + escapeHtml(window.hotelRefFacture) + '</div>' : '') +
      (window.hotelExoneration ? '<div style="text-align:center; font-size:11px; color:#888; font-style:italic;">Exonération : ' + escapeHtml(getExonerationLabel(window.hotelExoneration)) + '</div>' : '') +
      '<div class="receipt-items">' + itemsHtml + '</div>' +
      '<div class="receipt-totals">' + totalsHtml + paymentsHtml + '</div>' +
      '<div class="receipt-footer">' +
        '<div class="barcode">FACTURE n° (à valider)</div>' +
        '<div style="font-size:11px; color:#333; margin-top:4px;">Date : ' + now + '</div>' +
        '<div class="thank-you">Merci de votre séjour !</div>' +
        '<div style="margin-top:5px; font-size:9px; font-style:italic;">---Powered By Osat---</div>' +
      '</div></div>';

    var previewContent = $('preview-content');
    if (previewContent) previewContent.innerHTML = previewHtml;

    var previewFooter = document.querySelector('#preview-modal .modal-actions');
    if (previewFooter) {
      previewFooter.innerHTML =
        '<button type="button" class="btn btn-secondary" id="hotel-ft-preview-cancel">Annuler</button>' +
        '<button type="button" id="hotel-ft-preview-validate" class="btn btn-primary">' +
          '<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">' +
            '<polyline points="20 6 9 17 4 12"></polyline>' +
          '</svg>' +
          ' Valider la facture</button>';
      var c = $('hotel-ft-preview-cancel');
      if (c) c.onclick = function() {
        var m = $('preview-modal'); if (m) m.classList.remove('active');
        document.body.style.overflow = '';
      };
      var v = $('hotel-ft-preview-validate');
      if (v) v.onclick = function() {
        var m = $('preview-modal'); if (m) m.classList.remove('active');
        processEmission();
      };
    }
    var modal = $('preview-modal');
    if (modal) { modal.classList.add('active'); document.body.style.overflow = 'hidden'; }
  }

  // ===============================================================
  // ETAPE 3/3 : VALIDATION DGI -> POST BACKEND -> MODAL RECEIPT
  // ===============================================================
  async function processEmission() {
    if (typeof showLoading === 'function') showLoading('Validation DGI…');
    else if ($('hotel-ft-overlay-loader')) $('hotel-ft-overlay-loader').style.display = 'flex';

    var dgiInvoiceNum = 'FAC-' + Date.now();
    try {
      var invRes = await fetch(APP_URL + '/api/vente/next-invoice');
      var invData = await invRes.json();
      if (invData && invData.invoice_number) dgiInvoiceNum = invData.invoice_number;
    } catch (_) {}

    var dgiPayload = buildDgiPayload(dgiInvoiceNum, 'FV');
    var dgiResp = await postToDgi(dgiPayload);
    if (!dgiResp || !dgiResp.success) {
      var msg = (dgiResp && dgiResp.message) || 'Échec validation DGI';
      if (typeof hideLoading === 'function') hideLoading();
      alert('DGI : ' + msg);
      return;
    }
    try {
      await postHotelFacture(dgiResp);
    } catch (e) {
      console.error('[hotel-ft] emission error', e);
      alert('Erreur : ' + e.message);
    } finally {
      if (typeof hideLoading === 'function') hideLoading();
      else if ($('hotel-ft-overlay-loader')) $('hotel-ft-overlay-loader').style.display = 'none';
    }
  }

  async function postToDgi(payload) {
    if (typeof DGI_API_URL === 'undefined') return { success: false, message: 'DGI_API_URL non défini (app.js doit être chargé)' };
    try {
      var res = await fetch(DGI_API_URL, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(payload),
      });
      var text = await res.text();
      if (!text) return { success: false, message: 'Réponse vide du serveur DGI' };
      try { return JSON.parse(text); }
      catch (_) { return { success: false, message: 'Réponse DGI non-JSON' }; }
    } catch (e) {
      return { success: false, message: 'Connexion DGI impossible : ' + e.message };
    }
  }

  function buildDgiPayload(invoiceNum, invoiceType) {
    var s = previewData.sejour;
    var n = previewData.nuits;
    var e = previewData.extras;
    var articles = [];
    (n.lignes || []).forEach(function(l) {
      articles.push({
        name: 'Nuit ' + fmtDate(l.date) + ' — Chambre ' + (s.chambre.numero || ''),
        quantity: 1,
        price: parseFloat(l.prix) || 0,
        tax_rate: 0,
        tax_etiquette: 'B',
        prod_service: 'SER',
      });
    });
    (e.lignes || []).forEach(function(x) {
      articles.push({
        name: x.description || ('Extra #' + x.numero),
        quantity: 1,
        price: parseFloat(x.montant) || 0,
        tax_rate: 0,
        tax_etiquette: 'B',
        prod_service: 'SER',
      });
    });
    extrasManuels.forEach(function(x) {
      articles.push({
        name: x.description,
        quantity: 1,
        price: parseFloat(x.montant) || 0,
        tax_rate: 0,
        tax_etiquette: 'B',
        prod_service: 'SER',
      });
    });
    var total = articles.reduce(function(acc, a) { return acc + (parseFloat(a.price) || 0) * (parseFloat(a.quantity) || 1); }, 0);
    var sellerName = (typeof CURRENT_USER !== 'undefined' && CURRENT_USER.fullName) ? CURRENT_USER.fullName : (STORE_INFO.name || '');
    var agentCode  = (typeof CURRENT_USER !== 'undefined' && CURRENT_USER.agentCode) ? CURRENT_USER.agentCode : '';
    var clientTypeCode = 'PP';
    if (s.client && s.client.code_client) {
      var head = (s.client.code_client || '').toUpperCase().slice(0, 2);
      if (['PM', 'PC', 'PL', 'AO'].indexOf(head) >= 0) clientTypeCode = head;
    }
    var dgiPayments = (paiements || []).map(function(p) { return { type: p.dgiType || 'ESPECES', amount: parseFloat(p.montant) || 0 }; });
    return {
      store_phone: (STORE_INFO.phone || ''),
      store_address: (STORE_INFO.address || ''),
      store_email: (STORE_INFO.email || ''),
      store_ice: (STORE_INFO.ice || ''),
      store_isf: (STORE_INFO.isf || ''),
      store_rccm: (STORE_INFO.rccm || ''),
      store_homologation: !!(STORE_INFO.homologation),
      store_pdv: (STORE_INFO.pdv || ''),
      store_name: (STORE_INFO.name || ''),
      seller_name: sellerName,
      seller_agent_code: agentCode,
      amount: total,
      client_number: (s.guest.telephone || ''),
      client_name: ((s.guest.prenom || '') + ' ' + (s.guest.nom || '')).trim() || 'Guest',
      client_type: clientTypeCode,
      client_type_val: '',
      client_nif: '',
      client_address: '',
      invoice_number: invoiceNum,
      invoice_type: invoiceType,
      invoice_refs: [],
      invoice_ref: '',
      ref_facture: (typeof window.hotelRefFacture === 'string') ? window.hotelRefFacture : '',
      exoneration: (typeof window.hotelExoneration === 'string') ? window.hotelExoneration : '',
      articles: articles,
      payments: dgiPayments,
      rate: (typeof USD_RATE !== 'undefined') ? USD_RATE : 0,
      providerService: '',
    };
  }

  async function postHotelFacture(dgiResp) {
    var dgiData = (dgiResp && dgiResp.data) ? dgiResp.data : {};
    var payload = {
      sejour_id: currentSejourId,
      extras_manuels: extrasManuels,
      paiements: (paiements || []).map(function(p) { return { mode: p.mode, montant: parseFloat(p.montant) || 0 }; }),
      check_out: ($('hotel-ft-checkout') && $('hotel-ft-checkout').checked) ? true : false,
      dgi_data: {
        dateDGI: dgiData.dateDGI || null,
        qrCode: dgiData.qrCode || '',
        codeDEFDGI: dgiData.codeDEFDGI || '',
        counters: dgiData.counters || null,
        nim: dgiData.nim || null,
        total: dgiData.total || null,
        vtotal: dgiData.vtotal || null,
        isf: dgiData.isf || null,
        comment: dgiResp.comment || dgiData.comment || null,
      },
    };
    var r = await jsonFetch('/api/hotel/factures', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify(payload),
    });
    if (!r.ok || !r.data || !r.data.success) {
      throw new Error((r.data && r.data.message) || ('Erreur ' + r.status));
    }
    openReceiptModal(r.data, dgiResp);
  }

  function openReceiptModal(saleData, dgiResp) {
    var s = previewData.sejour;
    var n = previewData.nuits;
    var e = previewData.extras;
    var dgiData = (dgiResp && dgiResp.data) ? dgiResp.data : {};
    var isHomologuee = !(STORE_INFO.homologation === 0 || STORE_INFO.homologation === '0' || STORE_INFO.homologation === false);
    var total = saleData.total_final || previewData.total_final || 0;

    var itemsHtml = buildHotelReceiptItems(n, e, extrasManuels);
    var infoSection = buildHotelReceiptInfoSection();
    var totalsHtml  = buildHotelReceiptTotals(total);
    var paymentsHtml = buildHotelReceiptPayments();
    var typeLabel = 'Facture Hôtel — Régime type B (HB)';
    var now = new Date().toLocaleString('fr-FR', { day:'2-digit', month:'2-digit', year:'numeric', hour:'2-digit', minute:'2-digit' });

    var dgiInfoHtml = '';
    if (isHomologuee && dgiData && (dgiData.codeDEFDGI || dgiData.counters || dgiData.nim)) {
      dgiInfoHtml =
        '<div style="background:#e8f5e9; border:1px solid #4caf50; border-radius:8px; padding:10px; margin:10px 0; text-align:center;">' +
        '<div style="color:#2e7d32; font-weight:bold; font-size:11px;">--- Elements de securite de la facture normalisee ---</div>' +
        '<div style="font-size:11px; color:#555; margin-top:4px;">' +
        (dgiData.codeDEFDGI ? 'CODE DEF/DGI: ' + escapeHtml(dgiData.codeDEFDGI) : '') +
        (dgiData.nim ? '<br>DEF NID : ' + escapeHtml(dgiData.nim) : '') +
        (dgiData.counters ? '<br>DEF Compteurs: ' + escapeHtml(dgiData.counters) : '') +
        (dgiData.dateDGI ? '<br>DEF Heure : ' + escapeHtml(dgiData.dateDGI) : '') +
        '</div></div>';
    }

    var receiptHtml =
      '<div class="receipt"><div class="receipt-header">' +
        (STORE_INFO.logo ? '<img src="' + STORE_INFO.logo + '" class="receipt-logo" alt="Logo">' : '') +
        '<div class="store-name">' + escapeHtml(STORE_INFO.name || '') + '</div>' +
        '<div class="store-info">' +
          '<div><strong>Point de vente :</strong> ' + escapeHtml(STORE_INFO.pdv || '') + '</div>' +
          '<div>Adresse : ' + escapeHtml(STORE_INFO.address || '') + '</div>' +
          '<div>Tel: ' + escapeHtml(STORE_INFO.phone || '') + '</div>' +
          (STORE_INFO.email ? '<div>Email: ' + escapeHtml(STORE_INFO.email) + '</div>' : '') +
          '<div>ID Nat: ' + escapeHtml(STORE_INFO.ice || '') + '</div>' +
          (STORE_INFO.rccm ? '<div>RCCM: ' + escapeHtml(STORE_INFO.rccm) + '</div>' : '') +
          (STORE_INFO.isf ? '<div>Numero Impot: ' + escapeHtml(STORE_INFO.isf) + '</div>' : '') +
        '</div>' + infoSection +
      '</div>' +
      '<div class="receipt-meta" style="justify-content:center; font-size:14px; font-weight:600;">' + typeLabel + '</div>' +
      (window.hotelRefFacture ? '<div style="text-align:center; font-size:12px; color:#666;">Réf : ' + escapeHtml(window.hotelRefFacture) + '</div>' : '') +
      (window.hotelExoneration ? '<div style="text-align:center; font-size:11px; color:#888; font-style:italic;">Exonération : ' + escapeHtml(getExonerationLabel(window.hotelExoneration)) + '</div>' : '') +
      '<div class="receipt-items">' + itemsHtml + '</div>' +
      '<div class="receipt-totals">' + totalsHtml + paymentsHtml +
        (isHomologuee && STORE_INFO.isf ? '<div style="margin:10px 0; font-size:11px; color:#333; border:1px dashed #ccc; padding:8px; border-radius:4px; text-align:center;">ISF : ' + escapeHtml(dgiData.isf || STORE_INFO.isf) + '</div>' : '') +
      '</div>' +
      dgiInfoHtml +
      '<div class="receipt-footer">' +
        '<div id="hotel-ft-qrcode-container" class="qrcode-container"></div>' +
        '<div class="barcode">FACTURE n°' + escapeHtml(saleData.numero_facture || '') + '</div>' +
        '<div style="font-size:11px; color:#333; margin-top:4px;">Date : ' + now + '</div>' +
        '<div class="thank-you">Merci de votre séjour !</div>' +
        '<div style="margin-top:5px; font-size:9px; font-style:italic;">---Powered By Osat---</div>' +
      '</div></div>';

    var receiptContent = $('receipt-content');
    if (receiptContent) receiptContent.innerHTML = receiptHtml;
    var modal = $('receipt-modal');
    if (modal) { modal.classList.add('active'); document.body.style.overflow = 'hidden'; }

    var qrContent = (dgiData && dgiData.qrCode) ? dgiData.qrCode : (saleData.numero_facture || '');
    if (typeof posCart !== 'undefined' && posCart.generateDGIQRCode && qrContent) {
      setTimeout(function() { posCart.generateDGIQRCode(qrContent, 'hotel-ft-qrcode-container'); }, 80);
    }
    var printBtn = $('print-receipt');
    if (printBtn) {
      printBtn.onclick = function() {
        var content = $('receipt-content') ? $('receipt-content').innerHTML : '';
        if (typeof _printReceiptContent === 'function') _printReceiptContent(content);
        else window.print();
      };
    }
  }
  function closeReceiptModal() {
    var modal = $('receipt-modal');
    if (modal) modal.classList.remove('active');
    document.body.style.overflow = '';
  }

  async function cancelExistingFacture() {
    if (!previewData || !previewData.existing_facture) return;
    var ex = previewData.existing_facture;
    if (!confirm('Annuler la facture ' + ex.numero_facture + ' ?\nCette opération est réservée aux managers.')) return;
    var r = await jsonFetch('/api/hotel/factures/' + ex.id + '/cancel', { method: 'POST' });
    if (r.ok && r.data && r.data.success) {
      loadSejour(currentSejourId);
    } else {
      alert((r.data && r.data.message) || 'Erreur');
    }
  }

  // Init
  var urlSejourId = parseInt(new URL(window.location.href).searchParams.get('sejour_id') || '0', 10);
  if (urlSejourId > 0) loadSejour(urlSejourId);

  // EXPOSITION GLOBALE (window.*) — requise car les onclick="..." du
  // markup PHP appellent ces fonctions. Doit rester DANS la closure
  // IIFE pour que les noms soient en scope.
  if (typeof window !== 'undefined') {
    window.openHotelInvoiceInfoModal = openHotelInvoiceInfoModal;
    window.closeHotelInvoiceInfoModal = closeHotelInvoiceInfoModal;
    window.confirmHotelInvoiceInfo    = confirmHotelInvoiceInfo;
    window.addHotelModalPaymentLine   = addHotelModalPaymentLine;
    window.removeHotelModalPaymentLine= removeHotelModalPaymentLine;
    window.addHotelRefDocLine         = addHotelRefDocLine;
    window.removeHotelRefDocLine      = removeHotelRefDocLine;
    window.syncHotelRefDocsCount      = syncHotelRefDocsCount;
    window.cancelExistingFacture      = cancelExistingFacture;
    window.closeReceiptModal          = closeReceiptModal;
  }
})();

