/**
 * Liste + filtres + annulation de réservations hôtel.
 * Dépend de : window.HotelReservationForm (pour ouvrir le drawer en édition)
 *
 * Endpoints utilisés :
 *   GET    /api/hotel/reservations
 *   POST   /api/hotel/reservations/{id}/cancel
 */
(function() {
  'use strict';

  const APP_URL = window.location.origin;
  // Rendu en CARDS : on cible le conteneur grille (et on conserve `tbody` comme fallback
  // si quelqu'un remet le tableau — pour ne pas casser d'anciens appels).
  const grid  = document.getElementById('hotel-res-grid');
  const tbody = document.getElementById('hotel-res-tbody'); // legacy fallback
  const alertEl = document.getElementById('hotel-alert');

  const filterStatut = document.getElementById('filter-statut');
  const filterFrom   = document.getElementById('filter-from');
  const filterTo     = document.getElementById('filter-to');
  const btnClear     = document.getElementById('btn-clear-filters');
  const btnNew       = document.getElementById('btn-new-reservation');

  if (!grid && !tbody) return; // pas sur la bonne page
  const root = grid || tbody; // conteneur de rendu (cards ou fallback tableau)

  function showAlert(msg, type) {
    if (!msg) { alertEl.classList.add('hidden'); return; }
    alertEl.textContent = msg;
    alertEl.classList.remove('hidden');
    alertEl.classList.toggle('error', type === 'error');
    alertEl.classList.toggle('success', type === 'success');
  }

  function statutBadge(s) {
    const labels = {
      reserve:  ['En attente', 'hotel-res-statut-reserve'],   // orange
      en_cours: ['Confirmée',  'hotel-res-statut-en_cours'],  // vert
      termine:  ['Terminée',   'hotel-res-statut-termine'],   // bleu
      annule:   ['Annulée',    'hotel-res-statut-annule'],    // rouge
      no_show:  ['No-show',    'hotel-res-statut-no_show'],   // orange foncé
    };
    const it = labels[s] || [s, 'hotel-res-statut-termine'];
    return `<span class="badge ${it[1]}">${it[0]}</span>`;
  }

  function fmtDate(s) {
    if (!s) return '';
    const d = new Date(s.replace(' ', 'T'));
    if (isNaN(d)) return s;
    const pad = n => String(n).padStart(2, '0');
    return pad(d.getDate()) + '/' + pad(d.getMonth()+1) + '/' + d.getFullYear()
      + (s.length > 10 ? ' ' + pad(d.getHours()) + ':' + pad(d.getMinutes()) : '');
  }

  function fmtMoney(v) {
    const n = Number(v || 0);
    return n.toLocaleString('fr-FR', { minimumFractionDigits: 0, maximumFractionDigits: 2 });
  }

  async function fetchJson(url, opts) {
    const res = await fetch(APP_URL + url, Object.assign(
      { credentials: 'same-origin', headers: { 'Accept': 'application/json' } },
      opts || {}
    ));
    let data = null;
    try { data = await res.json(); } catch(e) { data = null; }
    return { ok: res.ok, status: res.status, data };
  }

  async function loadList() {
    showAlert(null);
    const params = new URLSearchParams();
    if (filterStatut.value) params.set('statut', filterStatut.value);
    if (filterFrom.value)   params.set('date_from', filterFrom.value);
    if (filterTo.value)     params.set('date_to', filterTo.value);
    const qs = params.toString();
    const r = await fetchJson('/api/hotel/reservations' + (qs ? '?' + qs : ''));
    if (!r.ok || !r.data || !r.data.success) {
      root.innerHTML = '<div class="res-card-empty">Erreur de chargement.</div>';
      showAlert((r.data && r.data.message) || 'Erreur de chargement', 'error');
      return;
    }
    const rows = r.data.data || [];
    if (rows.length === 0) {
      root.innerHTML = '<div class="res-card-empty">Aucune réservation.</div>';
      return;
    }
    root.innerHTML = rows.map(row => {
      // Sprint 7 — 2026-10-19 : `row.facture_id` est exposé par le backend
      // (LEFT JOIN hotel_factures dans HotelReservation::list). On l'utilise
      // pour afficher un badge "Facturé" dans la colonne Statut et pour
      // conditionner l'action "Facturer" dans le modal d'actions.
      const factureEmise = !!row.facture_id;
      const factureBadge = factureEmise
        ? `<span class="res-card-facture-badge" title="Facture ${escapeHtml(row.facture_numero || '')}">✓ ${escapeHtml(row.facture_numero || '')}</span>`
        : '';
      const remise = (row.remise_pct && Number(row.remise_pct) > 0)
        ? ` <small style="font-weight:400;color:var(--muted);">(-${Number(row.remise_pct).toLocaleString('fr-FR')}%)</small>`
        : '';
      const statut = row.statut || 'reserve';
      const statutSafe = ['reserve','en_cours','termine','annule','no_show'].includes(statut) ? statut : 'reserve';
      const guestName = `${escapeHtml(row.guest_prenom || '')} ${escapeHtml(row.guest_nom || '')}`.trim() || '—';
      const chambreNumero = escapeHtml(String(row.chambre_numero || '?'));
      const chambreType = escapeHtml(row.chambre_type || '');
      return `
      <article class="res-card s-${statutSafe}" data-id="${row.id}">
        <header class="res-card-head">
          <div class="res-card-guest">
            ${guestName}
            ${row.guest_tel ? `<div class="meta">${escapeHtml(row.guest_tel)}</div>` : ''}
          </div>
          <div style="display:flex;flex-direction:column;align-items:flex-end;gap:4px;">
            ${statutBadge(statut)}
            ${factureBadge}
          </div>
        </header>
        <div class="res-card-room">
          <span class="chambre-badge">${chambreNumero}</span>
          <span>${chambreType}</span>
        </div>
        <div class="res-card-dates">
          <div>
            <div class="lbl">Arrivée</div>
            <div class="val">${fmtDate(row.date_arrivee)}</div>
          </div>
          <div>
            <div class="lbl">Départ</div>
            <div class="val">${fmtDate(row.date_depart_prevue)}</div>
          </div>
        </div>
        <footer class="res-card-foot">
          <div class="res-card-prix">
            ${fmtMoney(row.prix_par_nuit)} Fc <small>par nuit</small>${remise}
          </div>
          <div class="res-card-actions">
            <button type="button" class="btn btn-primary btn-small js-actions" data-id="${row.id}" title="Actions">
              <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="vertical-align:-2px;margin-right:4px;">
                <circle cx="12" cy="6" r="2"></circle>
                <circle cx="12" cy="12" r="2"></circle>
                <circle cx="12" cy="18" r="2"></circle>
              </svg>
              Actions
            </button>
            <button type="button" class="btn btn-ghost btn-small js-edit" title="Modifier" style="margin-left:2px;">
              <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <polygon points="16 3 21 8 8 21 3 21 3 16 16 3"></polygon>
              </svg>
            </button>
            ${(statut === 'reserve' || statut === 'en_cours') ? `
              <button type="button" class="btn btn-ghost btn-small js-cancel" title="Annuler" style="color:var(--danger);margin-left:2px;">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                  <polyline points="3 6 5 6 21 6"></polyline>
                  <path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"></path>
                </svg>
              </button>
            ` : ''}
          </div>
        </footer>
      </article>
    `;}).join('');

    root.querySelectorAll('.js-edit').forEach(btn => {
      btn.addEventListener('click', () => {
        const id = btn.closest('[data-id]').dataset.id;
        if (window.HotelReservationForm && typeof window.HotelReservationForm.openForEdit === 'function') {
          window.HotelReservationForm.openForEdit(id);
        }
      });
    });
    root.querySelectorAll('.js-cancel').forEach(btn => {
      btn.addEventListener('click', async () => {
        const id = btn.closest('[data-id]').dataset.id;
        if (!confirm('Annuler cette réservation ?')) return;
        const r = await fetchJson('/api/hotel/reservations/' + encodeURIComponent(id) + '/cancel', { method: 'POST' });
        if (r.ok && r.data && r.data.success) {
          showAlert('Réservation annulée', 'success');
          loadList();
        } else {
          showAlert((r.data && r.data.message) || 'Échec', 'error');
        }
      });
    });
    // Sprint 7 — 2026-10-19 : bouton "Actions" → ouvre le modal d'actions
    root.querySelectorAll('.js-actions').forEach(btn => {
      btn.addEventListener('click', () => {
        openActionsModal(parseInt(btn.dataset.id, 10));
      });
    });
  }

  function escapeHtml(s) {
    return String(s == null ? '' : s)
      .replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;')
      .replace(/"/g, '&quot;').replace(/'/g, '&#39;');
  }

  // Filtres : on applique à la saisie (debounce léger)
  let filterTimer = null;
  [filterStatut, filterFrom, filterTo].forEach(el => {
    el.addEventListener('change', () => {
      clearTimeout(filterTimer);
      filterTimer = setTimeout(loadList, 150);
    });
  });
  btnClear.addEventListener('click', () => {
    filterStatut.value = '';
    filterFrom.value = '';
    filterTo.value = '';
    loadList();
  });

  btnNew.addEventListener('click', () => {
    if (window.HotelReservationForm && typeof window.HotelReservationForm.openForCreate === 'function') {
      window.HotelReservationForm.openForCreate();
    }
  });

  // Expose un événement pour que le drawer demande un refresh après save
  window.addEventListener('hotel:reservation-saved', () => loadList());

  // ======================================================================
  // MODAL D'ACTIONS RÉSERVATION (Sprint 7 — 2026-10-19)
  // Ouvre un modal qui récapitule la réservation et propose les actions
  // possibles (Check-in / Facturer / Check-out / Annuler / Modifier) selon
  // le statut courant et l'état de la facture.
  // ======================================================================

  // Cache du détail de la réservation courante
  let currentActionSejour = null;

  const actionsModal  = document.getElementById('hotel-res-actions-modal');
  const actionsBody   = document.getElementById('hotel-res-actions-body');
  const actionsSub    = document.getElementById('hotel-res-actions-subtitle');
  const actionsClose  = document.getElementById('hotel-res-actions-close');
  const actionsCancel = document.getElementById('hotel-res-actions-cancel');

  function openActionsModal(sejourId) {
    if (!actionsModal) return;
    currentActionSejour = sejourId;
    actionsSub.textContent = 'Chargement…';
    actionsBody.innerHTML = '<div style="padding:2rem;text-align:center;color:var(--muted);">Chargement des détails…</div>';
    actionsModal.classList.add('open');
    actionsModal.style.display = 'flex';
    loadActionsModalContent(sejourId);
  }

  function closeActionsModal() {
    if (!actionsModal) return;
    actionsModal.classList.remove('open');
    actionsModal.style.display = '';
    currentActionSejour = null;
  }

  if (actionsClose)  actionsClose.addEventListener('click', closeActionsModal);
  if (actionsCancel) actionsCancel.addEventListener('click', closeActionsModal);
  if (actionsModal) {
    actionsModal.addEventListener('click', (e) => {
      if (e.target === actionsModal) closeActionsModal();
    });
  }

  async function loadActionsModalContent(sejourId) {
    const r = await fetchJson('/api/hotel/reservations/' + encodeURIComponent(sejourId));
    if (!r.ok || !r.data || !r.data.success) {
      actionsBody.innerHTML = '<div style="padding:2rem;text-align:center;color:#991b1b;">Erreur de chargement.</div>';
      return;
    }
    renderActionsModal(r.data.data);
  }

  // Render le récap + les boutons d'action. Suite dans le bloc suivant.
  function renderActionsModal(s) {
    renderActionsModalSetVars(s, function (vars) {
      actionsBody.innerHTML = vars.summary + vars.factureBadge + vars.actions;
      bindActionsHandlers(s);
    });
  }

  // Helper : construit le HTML (récap + facture badge + boutons) et le passe
  // au callback. Le HTML utilise les valeurs de l'objet `s` exposé par
  // HotelReservation::findById (LEFT JOIN hotel_factures, cf. Sprint 7).
  function renderActionsModalSetVars(s, cb) {
    actionsSub.textContent = '#' + s.id + ' · Ch. ' + escapeHtml(String(s.chambre_numero)) + ' (' + escapeHtml(s.chambre_type) + ')';
    const guest = (escapeHtml(s.guest_prenom || '') + ' ' + escapeHtml(s.guest_nom || '')).trim() || '—';
    const arrivee = fmtDate(s.date_arrivee);
    const depart  = fmtDate(s.date_depart_prevue);
    const prixNuit = fmtMoney(s.prix_par_nuit);
    const remise = s.remise_pct > 0 ? ' − ' + Number(s.remise_pct).toFixed(0) + '% remise' : '';
    const factureEmise = !!s.facture_id;
    const factureBadge = factureEmise
      ? '<div style="background:#dcfce7;color:#166534;padding:.5rem .65rem;border-radius:6px;font-size:.85rem;margin-top:.5rem;border:1px solid #bbf7d0;">'
        + '<strong>✓ Déjà facturé</strong> · n°<code>' + escapeHtml(s.facture_numero || '') + '</code>'
        + '<br><span style="font-size:.78rem;">Total : <strong>' + fmtMoney(s.facture_total || 0) + ' Fc</strong> · Statut : ' + escapeHtml(s.facture_statut || '') + '</span></div>'
      : '';
    const canCheckIn    = s.statut === 'reserve';
    const canFacturer   = s.statut === 'en_cours' && !factureEmise;
    const canViewFacture = s.statut === 'en_cours' && factureEmise;
    const canCheckOut   = s.statut === 'en_cours';
    const canCancel     = (s.statut === 'reserve' || s.statut === 'en_cours') && !factureEmise;
    const canEdit       = s.statut === 'reserve' || s.statut === 'en_cours';
    const ic = '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="margin-right:6px;flex-shrink:0;vertical-align:-2px;">';
    const icCheckin  = ic + '<polyline points="20 6 9 17 4 12"></polyline></svg>';
    const icFile     = ic + '<path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"></path><polyline points="14 2 14 8 20 8"></polyline></svg>';
    const icCheckout = ic + '<polyline points="9 11 12 14 22 4"></polyline><path d="M21 12v7a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11"></path></svg>';
    const icEdit     = ic + '<polygon points="16 3 21 8 8 21 3 21 3 16 16 3"></polygon></svg>';
    const icCancel   = ic + '<polyline points="3 6 5 6 21 6"></polyline><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"></path></svg>';
    const btn = 'justify-content:flex-start;padding:.55rem .8rem;font-size:.85rem;';
    var actionsHtml = '<div style="display:flex;flex-direction:column;gap:.5rem;margin-top:1rem;">';
    if (canCheckIn)   actionsHtml += '<button type="button" class="btn btn-primary js-act-checkin" style="' + btn + '">' + icCheckin + 'Check-in (marquer comme en cours)</button>';
    if (canFacturer)  actionsHtml += '<button type="button" class="btn btn-primary js-act-facturer" style="' + btn + 'background:#0B5E88;">' + icFile + 'Émettre la facture</button>';
    else if (canViewFacture) actionsHtml += '<button type="button" class="btn btn-secondary js-act-view-facture" style="' + btn + '">' + icFile + 'Voir la facture n°' + escapeHtml(s.facture_numero || '') + '</button>';
    if (canCheckOut)  actionsHtml += '<button type="button" class="btn js-act-checkout" style="' + btn + 'background:#f59e0b;color:#fff;">' + icCheckout + escapeHtml(factureEmise ? 'Check-out (chambre → à nettoyer)' : 'Check-out (sans facture)') + '</button>';
    if (canEdit)      actionsHtml += '<button type="button" class="btn btn-secondary js-act-edit" style="' + btn + '">' + icEdit + 'Modifier</button>';
    if (canCancel)    actionsHtml += '<button type="button" class="btn js-act-cancel" style="' + btn + 'background:#fee2e2;color:#991b1b;">' + icCancel + 'Annuler</button>';
    actionsHtml += '</div>';
    var summary = '<div style="background:var(--background);padding:1rem;border-radius:8px;margin-bottom:1rem;">'
      + '<div style="font-size:.7rem;text-transform:uppercase;color:var(--muted);margin-bottom:.35rem;">Guest</div>'
      + '<div style="font-size:1.05rem;font-weight:600;">' + guest + '</div>'
      + (s.guest_tel ? '<div style="font-size:.8rem;color:var(--muted);">' + escapeHtml(s.guest_tel) + '</div>' : '')
      + '<div style="display:grid;grid-template-columns:1fr 1fr;gap:.65rem;margin-top:.75rem;font-size:.85rem;">'
        + '<div><div style="font-size:.7rem;text-transform:uppercase;color:var(--muted);">Arrivée</div><div>' + arrivee + '</div></div>'
        + '<div><div style="font-size:.7rem;text-transform:uppercase;color:var(--muted);">Départ</div><div>' + depart + '</div></div>'
        + '<div><div style="font-size:.7rem;text-transform:uppercase;color:var(--muted);">Prix / nuit</div><div>' + prixNuit + ' Fc' + remise + '</div></div>'
        + '<div><div style="font-size:.7rem;text-transform:uppercase;color:var(--muted);">Statut</div><div>' + statutBadge(s.statut) + '</div></div>'
      + '</div>'
      + (s.notes ? '<div style="margin-top:.65rem;font-size:.78rem;color:var(--muted);padding-top:.65rem;border-top:1px solid var(--border);"><strong>Notes :</strong> ' + escapeHtml(s.notes) + '</div>' : '')
    + '</div>';
    cb({ summary: summary, factureBadge: factureBadge, actions: actionsHtml });
  }

  function bindActionsHandlers(s) {
    const btnCheckin = actionsBody.querySelector('.js-act-checkin');
    if (btnCheckin) btnCheckin.addEventListener('click', () => handleAction(s, 'checkin'));
    const btnFacturer = actionsBody.querySelector('.js-act-facturer');
    if (btnFacturer) btnFacturer.addEventListener('click', () => handleAction(s, 'facturer'));
    const btnViewFacture = actionsBody.querySelector('.js-act-view-facture');
    if (btnViewFacture) btnViewFacture.addEventListener('click', () => {
      window.location.href = '/hotel/facturation?sejour_id=' + encodeURIComponent(s.id);
    });
    const btnCheckout = actionsBody.querySelector('.js-act-checkout');
    if (btnCheckout) btnCheckout.addEventListener('click', () => handleAction(s, 'checkout'));
    const btnEdit = actionsBody.querySelector('.js-act-edit');
    if (btnEdit) btnEdit.addEventListener('click', () => {
      closeActionsModal();
      if (window.HotelReservationForm && typeof window.HotelReservationForm.openForEdit === 'function') {
        window.HotelReservationForm.openForEdit(s.id);
      }
    });
    const btnCancel = actionsBody.querySelector('.js-act-cancel');
    if (btnCancel) btnCancel.addEventListener('click', () => handleAction(s, 'cancel'));
  }

  async function handleAction(s, action) {
    actionsBody.querySelectorAll('button').forEach(b => b.disabled = true);
    if (action === 'facturer') {
      window.location.href = '/hotel/facturation?sejour_id=' + encodeURIComponent(s.id);
      return;
    }
    let label, url, confirmMsg;
    if (action === 'checkin') {
      label = 'Check-in';
      url = '/api/hotel/reservations/' + encodeURIComponent(s.id) + '/check-in';
      confirmMsg = 'Confirmer le check-in de cette réservation ?';
    } else if (action === 'checkout') {
      label = 'Check-out';
      url = '/api/hotel/reservations/' + encodeURIComponent(s.id) + '/check-out';
      const factureEmise = !!s.facture_id;
      confirmMsg = factureEmise
        ? 'Confirmer le check-out ? La chambre passera à "à nettoyer".'
        : '⚠ Aucune facture n\'a été émise. Confirmer le check-out quand même ?';
    } else if (action === 'cancel') {
      label = 'Annulation';
      url = '/api/hotel/reservations/' + encodeURIComponent(s.id) + '/cancel';
      confirmMsg = 'Annuler définitivement cette réservation ?';
    } else {
      return;
    }
    if (!confirm(confirmMsg)) {
      actionsBody.querySelectorAll('button').forEach(b => b.disabled = false);
      return;
    }
    const r = await fetchJson(url, { method: 'POST' });
    if (r.ok && r.data && r.data.success) {
      showAlert(label + ' effectué avec succès', 'success');
      closeActionsModal();
      loadList();
    } else {
      showAlert((r.data && r.data.message) || 'Échec ' + label.toLowerCase(), 'error');
      actionsBody.querySelectorAll('button').forEach(b => b.disabled = false);
    }
  }

  loadList();
})();
