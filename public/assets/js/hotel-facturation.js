/**
 * Page Facturation hôtel (/hotel/facturation)
 *
 * Flux :
 *  1. À l'ouverture, on lit ?sejour_id=X dans l'URL.
 *     - Si présent → on masque le picker et on appelle preview()
 *     - Si absent  → on affiche le picker pour choisir un séjour
 *  2. Sur preview() : on remplit les blocs (séjour, nuits, extras, acomptes, totaux)
 *     et on autorise l'ajout d'extras manuels et la saisie des paiements.
 *  3. Bouton "Émettre la facture" → POST /api/hotel/factures
 *     → redirige vers /facture/<vente_id> (impression via InvoiceController).
 *  4. Si le séjour a DÉJÀ une facture non-annulée, on remplace le bouton par
 *     "Voir la facture émise" + "Annuler la facture".
 */
(function() {
  'use strict';

  const APP_URL = window.location.origin;
  const $ = (id) => document.getElementById(id);
  const pageAlert   = $('hotel-ft-alert-page');
  const picker      = $('hotel-ft-sejours-picker');
  const bloc        = $('hotel-ft-bloc');
  const search      = $('hotel-ft-sejour-search');
  const results     = $('hotel-ft-sejour-results');

  let currentSejourId = null;
  let previewData = null;
  let extrasManuels = []; // [{ description, montant }]
  let paiements = [];     // [{ mode, montant }]

  function showAlert(msg, type) {
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
  function fmtFc(n) { return parseFloat(n || 0).toLocaleString('fr-FR', { minimumFractionDigits: 2, maximumFractionDigits: 2 }) + ' FC'; }
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

  // ── Sélecteur de séjour ────────────────────────────────────────────────
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
    // On n'a pas d'endpoint dédié séjours actifs pour autocomplete, on tape sur
    // /api/hotel/reservations (déjà existant, renvoie séjours reserve+en_cours).
    const r = await jsonFetch('/api/hotel/reservations?q=' + encodeURIComponent(q) + '&limit=20');
    if (!r.ok || !r.data) {
      results.innerHTML = '<div style="padding:.65rem .85rem;font-size:.82rem;color:#991b1b;">Erreur.</div>';
      return;
    }
    const list = (r.data.data || []).filter(s => s.statut === 'en_cours' || s.statut === 'reserve');
    if (list.length === 0) {
      results.innerHTML = '<div style="padding:.65rem .85rem;font-size:.82rem;color:var(--muted);">Aucun séjour actif. Cherche par n° de chambre ou nom de guest.</div>';
      return;
    }
    results.innerHTML = list.map(s => `
      <div class="hotel-ft-sejour-row" data-id="${s.id}" style="padding:.55rem .85rem;border-bottom:1px solid var(--border);cursor:pointer;display:flex;justify-content:space-between;align-items:center;">
        <div>
          <div style="font-weight:500;">${escapeHtml(s.guest_nom || '')} ${escapeHtml(s.guest_prenom || '')}</div>
          <div style="font-size:.75rem;color:var(--muted);margin-top:2px;">Chambre ${escapeHtml(s.chambre_numero)} · ${fmtDate(s.date_arrivee)} → ${fmtDate(s.date_depart_prevue)}</div>
        </div>
        <span class="badge">${escapeHtml(s.statut)}</span>
      </div>
    `).join('');
    results.querySelectorAll('.hotel-ft-sejour-row').forEach(row => {
      row.addEventListener('click', () => {
        const id = parseInt(row.dataset.id, 10);
        loadSejour(id);
      });
    });
  }

  // ── Chargement du séjour + preview ─────────────────────────────────────
  async function loadSejour(sejourId) {
    currentSejourId = sejourId;
    if (picker) picker.style.display = 'none';
    if (bloc) bloc.style.display = '';
    if (bloc) bloc.innerHTML = '<div class="hotel-ft-empty">Chargement…</div>';
    showAlert(null);

    const r = await jsonFetch('/api/hotel/factures/preview?sejour_id=' + sejourId);
    if (!r.ok || !r.data || !r.data.success) {
      const msg = (r.data && r.data.message) || ('Erreur ' + r.status);
      if (bloc) bloc.innerHTML = '<div class="hotel-ft-banner warn">' + escapeHtml(msg) + '</div>';
      console.error('[hotel-ft] preview failed', r);
      return;
    }
    previewData = r.data.data;
    console.log('[hotel-ft] preview OK', previewData);
    if (!previewData) {
      if (bloc) bloc.innerHTML = '<div class="hotel-ft-banner warn">Réponse inattendue du serveur : la donnée de prévisualisation est vide.</div>';
      return;
    }
    try {
      renderBloc();
    } catch (err) {
      console.error('[hotel-ft] renderBloc crashed', err);
      if (bloc) bloc.innerHTML = '<div class="hotel-ft-banner warn">Erreur de rendu : ' + escapeHtml(err && err.message || String(err)) + '<br>Voir la console pour le détail.</div>';
    }
  }

  function renderBloc() {
    if (!previewData) return;
    const s = previewData.sejour;
    const n = previewData.nuits;
    const e = previewData.extras;
    const ex = previewData.existing_facture;

    // Si une facture existe déjà → afficher l'état "déjà facturé"
    const alreadyFactured = ex && ex.statut !== 'annulee';

    bloc.innerHTML = `
      <div style="display:flex;justify-content:space-between;align-items:start;flex-wrap:wrap;gap:.5rem;">
        <div>
          <h3 style="margin:0;">${escapeHtml(s.guest.prenom || '')} ${escapeHtml(s.guest.nom || '')}</h3>
          <div style="font-size:.82rem;color:var(--muted);margin-top:2px;">
            Chambre ${escapeHtml(s.chambre.numero)} · ${escapeHtml(s.chambre.type)} · ${fmtDate(s.date_arrivee)} → ${fmtDate(s.date_depart_prevue)}
          </div>
          ${s.client ? `<div style="font-size:.82rem;color:var(--muted);margin-top:2px;">Client lié : <strong>${escapeHtml(s.client.code_client)}</strong> — ${escapeHtml(s.client.nom_client)}</div>` : ''}
        </div>
        <div style="text-align:right;">
          <div style="font-size:.7rem;text-transform:uppercase;color:var(--muted);">Séjour #${s.id}</div>
          <div style="font-size:.7rem;text-transform:uppercase;color:var(--muted);margin-top:4px;">Statut : <strong>${escapeHtml(s.statut)}</strong></div>
        </div>
      </div>

      ${alreadyFactured ? `
        <div class="hotel-ft-banner warn" style="margin-top:1rem;">
          ⚠ Une facture existe déjà pour ce séjour : <strong>${escapeHtml(ex.numero_facture)}</strong> (${fmtFc(ex.total_final)}) — statut ${escapeHtml(ex.statut)}.
          <div style="margin-top:.5rem;display:flex;gap:.5rem;justify-content:flex-end;">
            <a href="/facture/${ex.vente_id}" target="_blank" class="btn btn-primary btn-small">Voir / Imprimer</a>
            <button type="button" class="btn btn-ghost btn-small" id="hotel-ft-cancel-existing">Annuler cette facture</button>
          </div>
        </div>
      ` : ''}

      <!-- Nuits -->
      <div style="margin-top:1.25rem;">
        <h4 class="hotel-ft-section-title">Nuits (${n.nb_nuits})</h4>
        <table class="hotel-ft-table">
          <thead><tr><th>Date</th><th class="num">Prix</th></tr></thead>
          <tbody>
            ${n.lignes.map(l => `
              <tr>
                <td>${fmtDate(l.date)}</td>
                <td class="num">${fmtFc(l.prix)}</td>
              </tr>
            `).join('')}
            ${n.remise_montant > 0 ? `
              <tr>
                <td>Remise séjour (${n.remise_pct}%)</td>
                <td class="num" style="color:#991b1b;">− ${fmtFc(n.remise_montant)}</td>
              </tr>
            ` : ''}
          </tbody>
        </table>
      </div>

      <!-- Extras -->
      <div style="margin-top:1.25rem;">
        <h4 class="hotel-ft-section-title">Extras (${e.lignes.length} vente${e.lignes.length > 1 ? 's' : ''} en caisse)</h4>
        ${e.lignes.length === 0
          ? '<div style="font-size:.82rem;color:var(--muted);font-style:italic;">Aucun extra saisi depuis la caisse pour ce séjour.</div>'
          : `<table class="hotel-ft-table">
              <thead><tr><th>Date</th><th>Description</th><th class="num">Montant</th></tr></thead>
              <tbody>
                ${e.lignes.map(x => `
                  <tr>
                    <td>${fmtDate(x.date)}</td>
                    <td>${escapeHtml(x.description)}</td>
                    <td class="num">${fmtFc(x.montant)}</td>
                  </tr>
                `).join('')}
              </tbody>
            </table>`}

        <div style="margin-top:.85rem;font-size:.82rem;color:var(--muted);">Ajout manuel d'un extra (minibar, casse, pourboire…) :</div>
        <div class="hotel-ft-extras-add">
          <input type="text"   id="hotel-ft-extra-desc" placeholder="Description">
          <input type="number" id="hotel-ft-extra-montant" placeholder="Montant FC" min="0" step="0.01">
          <button type="button" class="btn btn-ghost btn-small" id="hotel-ft-extra-add">+ Ajouter</button>
        </div>
        <div id="hotel-ft-extras-manuels-list"></div>
      </div>

      <!-- Acomptes -->
      <div style="margin-top:1.25rem;">
        <h4 class="hotel-ft-section-title">Acomptes déjà versés</h4>
        ${previewData.acomptes.length === 0
          ? '<div style="font-size:.82rem;color:var(--muted);font-style:italic;">Aucun acompte.</div>'
          : `<table class="hotel-ft-table">
              <thead><tr><th>Date</th><th>Mode</th><th class="num">Montant</th></tr></thead>
              <tbody>
                ${previewData.acomptes.map(a => `
                  <tr>
                    <td>${fmtDate(a.date)}</td>
                    <td>${escapeHtml(a.mode)}</td>
                    <td class="num">${fmtFc(a.montant)}</td>
                  </tr>
                `).join('')}
              </tbody>
            </table>`}
      </div>

      <!-- Totaux + paiements (côté création uniquement) -->
      ${alreadyFactured ? '' : `
        <div class="hotel-ft-totals">
          <div class="row"><span>Total nuits (après remise)</span><span>${fmtFc(n.total_ht)}</span></div>
          <div class="row"><span>Total extras</span><span>${fmtFc(e.total_ht)}</span></div>
          <div class="row"><span>Total brut</span><span>${fmtFc(previewData.total_brut)}</span></div>
          <div class="row"><span>TVA (${previewData.tva.type} — ${escapeHtml(previewData.tva.note)})</span><span>${fmtFc(previewData.tva.montant)}</span></div>
          <div class="row"><span>Acomptes versés</span><span style="color:#166534;">− ${fmtFc(previewData.acompte_total)}</span></div>
          <div class="row total"><span>Solde dû</span><span>${fmtFc(previewData.solde_du)}</span></div>
        </div>

        <div style="margin-top:1rem;">
          <h4 class="hotel-ft-section-title">Paiements encaissés à la facturation</h4>
          <div class="hotel-ft-payments" id="hotel-ft-payments-list"></div>
          <div class="hotel-ft-extras-add" style="margin-top:.5rem;">
            <select id="hotel-ft-pmt-mode">
              <option value="cash">Cash</option>
              <option value="mobile_money">Mobile Money</option>
              <option value="carte">Carte</option>
              <option value="autre">Autre</option>
            </select>
            <input type="number" id="hotel-ft-pmt-montant" placeholder="Montant FC" min="0" step="0.01">
            <button type="button" class="btn btn-ghost btn-small" id="hotel-ft-pmt-add">+ Ajouter</button>
          </div>
          <div style="font-size:.75rem;color:var(--muted);margin-top:.35rem;">
            La somme des paiements doit couvrir le solde dû pour émettre la facture.
          </div>
        </div>

        <div style="margin-top:1rem;">
          <label style="display:flex;align-items:center;gap:.5rem;font-size:.85rem;">
            <input type="checkbox" id="hotel-ft-checkout">
            Clôturer le séjour (check-out) en même temps
          </label>
        </div>

        <div id="hotel-ft-error" class="hotel-ft-banner warn" style="margin-top:.85rem;display:none;"></div>

        <div class="hotel-ft-actions">
          <button type="button" class="btn btn-ghost" id="hotel-ft-back">← Choisir un autre séjour</button>
          <button type="button" class="btn btn-primary" id="hotel-ft-emit">Émettre la facture</button>
        </div>
      `}
    `;

    // Render des extras manuels
    renderExtrasManuels();
    renderPaiements();

    // Handlers
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
    const $pmtAdd = $('hotel-ft-pmt-add');
    if ($pmtAdd) {
      $pmtAdd.addEventListener('click', () => {
        const mode = $('hotel-ft-pmt-mode').value;
        const montant = parseFloat($('hotel-ft-pmt-montant').value);
        if (!(montant > 0)) return;
        paiements.push({ mode, montant });
        $('hotel-ft-pmt-montant').value = '';
        renderPaiements();
      });
    }
    const $emit = $('hotel-ft-emit');
    if ($emit) $emit.addEventListener('click', emitFacture);
    const $back = $('hotel-ft-back');
    if ($back) $back.addEventListener('click', () => {
      currentSejourId = null;
      previewData = null;
      extrasManuels = [];
      paiements = [];
      bloc.style.display = 'none';
      picker.style.display = '';
      // update URL
      const u = new URL(window.location.href);
      u.searchParams.delete('sejour_id');
      window.history.replaceState({}, '', u.toString());
    });
    const $cancelExisting = $('hotel-ft-cancel-existing');
    if ($cancelExisting) $cancelExisting.addEventListener('click', cancelExistingFacture);
  }

  function renderExtrasManuels() {
    const $list = $('hotel-ft-extras-manuels-list');
    if (!$list) return;
    if (extrasManuels.length === 0) { $list.innerHTML = ''; return; }
    $list.innerHTML = `
      <table class="hotel-ft-table" style="margin-top:.5rem;">
        <thead><tr><th>Description</th><th class="num">Montant</th><th></th></tr></thead>
        <tbody>
          ${extrasManuels.map((x, i) => `
            <tr>
              <td>${escapeHtml(x.description)}</td>
              <td class="num">${fmtFc(x.montant)}</td>
              <td><button type="button" class="btn btn-ghost btn-small" data-rm="${i}">×</button></td>
            </tr>
          `).join('')}
        </tbody>
      </table>
    `;
    $list.querySelectorAll('[data-rm]').forEach(b => {
      b.addEventListener('click', () => {
        const i = parseInt(b.dataset.rm, 10);
        extrasManuels.splice(i, 1);
        renderExtrasManuels();
      });
    });
  }

  function renderPaiements() {
    const $list = $('hotel-ft-payments-list');
    if (!$list) return;
    if (paiements.length === 0) {
      $list.innerHTML = '<div style="grid-column:1/-1;font-size:.82rem;color:var(--muted);font-style:italic;">Aucun paiement saisi.</div>';
      return;
    }
    $list.innerHTML = paiements.map((p, i) => `
      <div>${escapeHtml(p.mode)}</div>
      <div>${fmtFc(p.montant)}</div>
      <div><button type="button" class="btn btn-ghost btn-small" data-rm-pmt="${i}">×</button></div>
    `).join('');
    $list.querySelectorAll('[data-rm-pmt]').forEach(b => {
      b.addEventListener('click', () => {
        const i = parseInt(b.dataset.rmPmt, 10);
        paiements.splice(i, 1);
        renderPaiements();
      });
    });
  }

  async function emitFacture() {
    const errBox = $('hotel-ft-error');
    errBox.style.display = 'none';

    const solde = previewData.solde_du
      + extrasManuels.reduce((s, x) => s + x.montant, 0);
    const totalPaiements = paiements.reduce((s, p) => s + p.montant, 0);

    if (Math.abs(totalPaiements - solde) > 0.01) {
      errBox.textContent = `La somme des paiements (${fmtFc(totalPaiements)}) doit couvrir le solde dû (${fmtFc(solde)}).`;
      errBox.style.display = '';
      return;
    }

    const payload = {
      sejour_id:      currentSejourId,
      extras_manuels: extrasManuels,
      paiements:      paiements,
      check_out:      $('hotel-ft-checkout') ? $('hotel-ft-checkout').checked : false,
    };
    const btn = $('hotel-ft-emit');
    btn.disabled = true; btn.textContent = 'Émission…';
    const r = await jsonFetch('/api/hotel/factures', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify(payload),
    });
    btn.disabled = false; btn.textContent = 'Émettre la facture';
    if (r.ok && r.data && r.data.success) {
      // Redirige vers l'impression (InvoiceController existant)
      window.location.href = r.data.print_url || ('/facture/' + r.data.vente_id);
    } else {
      errBox.textContent = (r.data && r.data.message) || 'Erreur';
      errBox.style.display = '';
    }
  }

  async function cancelExistingFacture() {
    if (!previewData || !previewData.existing_facture) return;
    const ex = previewData.existing_facture;
    if (!confirm('Annuler la facture ' + ex.numero_facture + ' ?\nCette opération est réservée aux managers — la vente originale n\'est pas supprimée.')) return;
    const r = await jsonFetch('/api/hotel/factures/' + ex.id + '/cancel', { method: 'POST' });
    if (r.ok && r.data && r.data.success) {
      loadSejour(currentSejourId);
    } else {
      alert((r.data && r.data.message) || 'Erreur');
    }
  }

  // ── Init ────────────────────────────────────────────────────────────────
  const urlSejourId = parseInt(new URL(window.location.href).searchParams.get('sejour_id') || '0', 10);
  if (urlSejourId > 0) {
    loadSejour(urlSejourId);
  }
})();