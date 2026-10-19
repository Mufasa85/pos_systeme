/**
 * Page Réception live — polling + timeline + grille + drawer d'actions.
 *
 * Endpoints :
 *   GET    /api/hotel/reception/board
 *   POST   /api/hotel/reservations/{id}/check-in
 *   POST   /api/hotel/reservations/{id}/check-out
 *   POST   /api/hotel/reception/rooms/{id}/cleaning-done
 *   POST   /api/hotel/reception/rooms/{id}/out-of-service
 */
(function() {
  'use strict';

  const APP_URL = window.location.origin;

  const elAlerts = {
    page:   document.getElementById('hotel-alert-page'),
    drawer: document.getElementById('hotel-rec-drawer-alert'),
  };
  const clockEl = document.getElementById('hotel-rec-clock');
  const lists = {
    arrivals:   document.getElementById('list-arrivals'),
    inProgress: document.getElementById('list-inProgress'),
    departures: document.getElementById('list-departures'),
  };
  const counts = {
    arrivals:   document.getElementById('count-arrivals'),
    inProgress: document.getElementById('count-inProgress'),
    departures: document.getElementById('count-departures'),
  };
  const grid = document.getElementById('hotel-rec-grid');

  const drawer = document.getElementById('hotel-rec-drawer');
  const backdrop = document.getElementById('hotel-rec-backdrop');
  const closeBtn = document.getElementById('hotel-rec-drawer-close');
  const drawerTitle = document.getElementById('hotel-rec-drawer-title');
  const drawerContent = document.getElementById('hotel-rec-drawer-content');

  if (!grid) return;

  let capabilities = { check_in_out: false, cleaning_done: false, out_of_service: false, emit_facture: false };
  let currentChambre = null;      // chambre en cours dans le drawer
  let currentSejour = null;       // séjour en cours dans le drawer (sélectionné via timeline)

  function showAlert(msg, type, where) {
    const el = elAlerts[where || 'page'];
    if (!msg) { el.classList.add('hidden'); return; }
    el.textContent = msg;
    el.classList.remove('hidden');
    el.classList.toggle('error',   type === 'error');
    el.classList.toggle('success', type === 'success');
  }

  function escapeHtml(s) {
    return String(s == null ? '' : s)
      .replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;')
      .replace(/"/g, '&quot;').replace(/'/g, '&#39;');
  }
  function fmtTime(s) {
    if (!s) return '';
    const d = new Date(s.replace(' ', 'T'));
    if (isNaN(d)) return s;
    return String(d.getHours()).padStart(2, '0') + ':' + String(d.getMinutes()).padStart(2, '0');
  }
  function fmtDateShort(s) {
    if (!s) return '';
    const d = new Date(s.replace(' ', 'T'));
    if (isNaN(d)) return s;
    return String(d.getDate()).padStart(2, '0') + '/' + String(d.getMonth()+1).padStart(2, '0');
  }
  function nights(s) {
    if (!s || !s.date_arrivee || !s.date_depart_prevue) return '';
    const a = new Date(s.date_arrivee);
    const dep = new Date(s.date_depart_prevue);
    const n = Math.round((dep - a) / 86400000);
    return n > 0 ? `${n} nuit${n > 1 ? 's' : ''}` : '';
  }

  async function jsonFetch(url, opts) {
    const res = await fetch(APP_URL + url, Object.assign(
      { credentials: 'same-origin', headers: { 'Accept': 'application/json' } },
      opts || {}
    ));
    let data = null;
    try { data = await res.json(); } catch(e) { data = null; }
    return { ok: res.ok, status: res.status, data };
  }

  function renderTimeline(list, items, type) {
    counts[list].textContent = items.length;
    if (items.length === 0) {
      lists[list].innerHTML = `<div class="hotel-rec-item-empty">${listEmptyText(list)}</div>`;
      return;
    }
    lists[list].innerHTML = items.map(s => {
      const guest = `${escapeHtml(s.guest_prenom || '')} ${escapeHtml(s.guest_nom || '')}`.trim();
      const chLabel = `N° ${escapeHtml(String(s.chambre_numero))}`;
      // Sprint 7 — 2026-10-19 : `s.facture_id` est exposé par le backend
      // (LEFT JOIN hotel_factures dans HotelReservation::chambresAvecSejourActif
      // et sejoursAujourdhui). S'il est défini (≠ null), la facture existe
      // déjà (statut != 'annulee') et on n'affiche PAS le bouton "Facturer"
      // (mais on garde un badge "Déjà facturé n°XXXX" pour la lisibilité).
      const factureEmise = !!s.facture_id;
      const factureLabel = factureEmise
        ? `<span class="badge" style="background:#dcfce7;color:#166534;padding:2px 6px;border-radius:4px;font-size:.7rem;font-weight:500;" title="Facture ${escapeHtml(s.facture_numero || '')}">✓ Facturé ${escapeHtml(s.facture_numero || '')}</span>`
        : '';
      let actionsHtml = '';
      if (type === 'arrival' && capabilities.check_in_out) {
        actionsHtml = `<button type="button" class="btn-mini primary js-checkin" data-id="${s.id}">Check-in</button>`;
      } else if (type === 'inProgress') {
        // Boutons disponibles selon capabilities :
        //   Détail     : tous les rôles hôtel voient le détail
        //   Facturer   : si emit_facture (manager + réceptionniste) ET
        //                pas déjà facturé (s.facture_id null)
        //   Check-out  : si check_in_out
        actionsHtml = `<button type="button" class="btn-mini js-open-sejour" data-id="${s.id}">Détail</button>`;
        if (capabilities.emit_facture && !factureEmise) {
          actionsHtml += `<button type="button" class="btn-mini primary js-facturer" data-id="${s.id}">Facturer</button>`;
        }
        if (factureEmise) {
          actionsHtml += factureLabel;
        }
        if (capabilities.check_in_out) {
          actionsHtml += `<button type="button" class="btn-mini danger js-checkout" data-id="${s.id}">Check-out</button>`;
        }
      } else if (type === 'departure') {
        // Le bouton Facturer apparaît même si check_in_out=false : facturer est
        // une action indépendante, contrôlée par sa propre capability emit_facture.
        // Condition supplémentaire : pas déjà facturé.
        if (capabilities.emit_facture && !factureEmise) {
          actionsHtml = `<button type="button" class="btn-mini primary js-facturer" data-id="${s.id}">Facturer</button>`;
        } else if (factureEmise) {
          actionsHtml = factureLabel;
        }
        if (capabilities.check_in_out) {
          actionsHtml += `<button type="button" class="btn-mini danger js-checkout" data-id="${s.id}">Check-out</button>`;
        }
      }
      return `
        <div class="hotel-rec-item">
          <div>
            <div style="font-weight:600;">${escapeHtml(guest) || '—'}</div>
            <div class="meta">${chLabel} · ${type === 'arrival' ? 'arr. ' + fmtTime(s.date_arrivee) : ''}${type === 'inProgress' || type === 'departure' ? 'nuit ' + fmtDateShort(s.date_depart_prevue) : ''}${nights(s) ? ' · ' + nights(s) : ''}</div>
          </div>
          <div class="actions">${actionsHtml}</div>
        </div>`;
    }).join('');

    lists[list].querySelectorAll('.js-checkin').forEach(b => {
      b.addEventListener('click', async () => {
        b.disabled = true;
        const r = await jsonFetch('/api/hotel/reservations/' + encodeURIComponent(b.dataset.id) + '/check-in', { method: 'POST' });
        b.disabled = false;
        if (r.ok && r.data && r.data.success) {
          showAlert('Check-in effectué', 'success');
          loadBoard();
        } else {
          showAlert((r.data && r.data.message) || 'Erreur check-in', 'error');
        }
      });
    });
    lists[list].querySelectorAll('.js-checkout').forEach(b => {
      b.addEventListener('click', async () => {
        if (!confirm('Confirmer le check-out ?')) return;
        b.disabled = true;
        const r = await jsonFetch('/api/hotel/reservations/' + encodeURIComponent(b.dataset.id) + '/check-out', { method: 'POST' });
        b.disabled = false;
        if (r.ok && r.data && r.data.success) {
          showAlert('Check-out effectué. Chambre passée à "à nettoyer".', 'success');
          loadBoard();
        } else {
          showAlert((r.data && r.data.message) || 'Erreur check-out', 'error');
        }
      });
    });
    lists[list].querySelectorAll('.js-open-sejour').forEach(b => {
      b.addEventListener('click', async () => {
        await openSejourDrawer(parseInt(b.dataset.id, 10));
      });
    });
    lists[list].querySelectorAll('.js-facturer').forEach(b => {
      b.addEventListener('click', () => {
        window.location.href = '/hotel/facturation?sejour_id=' + encodeURIComponent(b.dataset.id);
      });
    });
  }

  function listEmptyText(list) {
    if (list === 'arrivals')   return 'Aucune arrivée prévue.';
    if (list === 'inProgress') return 'Aucun séjour en cours.';
    if (list === 'departures') return 'Aucun départ prévu aujourd\'hui.';
    return '';
  }

  /**
   * Détermine l'état visuel d'une chambre :
   * - hors_service : on respecte la base
   * - occupée     : inférée si sejour_id présent (séjour en_cours sur la chambre)
   * - a_nettoyer  : idem champ etat
   * - disponible  : sinon
   */
  function roomStateClass(ch) {
    if (ch.etat === 'hors_service') return 's-hors_service';
    if (ch.sejour_id) return 's-occupe';
    if (ch.etat === 'a_nettoyer') return 's-a_nettoyer';
    return 's-disponible';
  }

  function roomStateLabel(ch) {
    if (ch.etat === 'hors_service') return 'Hors service';
    if (ch.sejour_id) return 'Occupée';
    if (ch.etat === 'a_nettoyer') return 'À nettoyer';
    return 'Disponible';
  }

  function renderGrid(chambres) {
    if (!chambres || chambres.length === 0) {
      grid.innerHTML = '<div class="hotel-rec-empty" style="grid-column:1/-1;">Aucune chambre.</div>';
      return;
    }
    grid.innerHTML = chambres.map(c => {
      const stateCls = roomStateClass(c);
      const guest = c.guest_id ? `${escapeHtml(c.guest_prenom || '')} ${escapeHtml(c.guest_nom || '')}`.trim() : '';
      const when = c.sejour_id && c.date_depart_prevue ? `jusqu'au ${fmtDateShort(c.date_depart_prevue)}` : '';
      return `
        <div class="hotel-room-pad ${stateCls}" data-chambre-id="${c.chambre_id}" data-sejour-id="${c.sejour_id || ''}">
          <div style="display:flex;justify-content:space-between;align-items:flex-start;">
            <div>
              <div class="room-num">N° ${escapeHtml(String(c.numero))}</div>
              <div class="room-type">${escapeHtml(c.type)}</div>
            </div>
            <div style="font-size:.7rem;color:var(--muted);text-align:right;">${roomStateLabel(c)}</div>
          </div>
          ${guest ? `<div class="room-guest">${escapeHtml(guest)}</div>` : ''}
          ${when ? `<div class="room-when">${escapeHtml(when)}</div>` : ''}
        </div>`;
    }).join('');
    grid.querySelectorAll('.hotel-room-pad').forEach(el => {
      el.addEventListener('click', () => {
        const chId = parseInt(el.dataset.chambreId, 10);
        const sejId = parseInt(el.dataset.sejourId || '0', 10);
        if (sejId) openSejourDrawer(sejId);
        else openChambreDrawer(chId);
      });
    });
  }

  async function loadBoard() {
    showAlert(null);
    const r = await jsonFetch('/api/hotel/reception/board');
    if (!r.ok || !r.data || !r.data.success) {
      showAlert((r.data && r.data.message) || 'Erreur de chargement', 'error');
      return;
    }
    capabilities = Object.assign(capabilities, r.data.capabilities || {});
    const d = r.data.data || {};
    renderTimeline('arrivals',   d.timeline && d.timeline.arrivals   || [], 'arrival');
    renderTimeline('inProgress', d.timeline && d.timeline.inProgress || [], 'inProgress');
    renderTimeline('departures', d.timeline && d.timeline.departures || [], 'departure');
    renderGrid(d.chambres || []);
    if (r.data.server_time && clockEl) {
      clockEl.textContent = 'MAJ ' + (r.data.server_time || '').substring(11, 19);
    }
  }

  function openDrawer() {
    drawer.classList.add('open');
    backdrop.classList.add('open');
    document.body.style.overflow = 'hidden';
  }
  function closeDrawer() {
    drawer.classList.remove('open');
    backdrop.classList.remove('open');
    document.body.style.overflow = '';
    currentChambre = null;
    currentSejour = null;
  }
  closeBtn.addEventListener('click', closeDrawer);
  backdrop.addEventListener('click', closeDrawer);
  document.addEventListener('keydown', e => { if (e.key === 'Escape') closeDrawer(); });

  /**
   * Drawer chambre (pas de séjour actif).
   */
  function openChambreDrawer(chambreId) {
    showAlert(null, null, 'drawer');
    jsonFetch('/api/hotel/reception/board').then(r => {
      const ch = (r.data.data.chambres || []).find(c => c.chambre_id === chambreId);
      if (!ch) { showAlert('Chambre introuvable', 'error', 'drawer'); return; }
      currentChambre = ch;
      currentSejour = null;
      drawerTitle.textContent = `Chambre N° ${ch.numero} — ${ch.type}`;

      const guest = ch.guest_id ? `${ch.guest_prenom || ''} ${ch.guest_nom || ''}`.trim() : null;
      const state = roomStateLabel(ch);

      let html = `
        <div style="margin-bottom:1rem;">
          <div style="font-size:.8rem;color:var(--muted);text-transform:uppercase;">État actuel</div>
          <div style="font-size:1rem;font-weight:600;margin-top:.25rem;">${escapeHtml(state)}</div>
        </div>
        <div style="font-size:.85rem;color:var(--muted);">${guest ? 'Occupée par <strong>' + escapeHtml(guest) + '</strong>' : 'Chambre libre.'}</div>
      `;
      let actionsHtml = '';
      if (ch.etat === 'a_nettoyer' && capabilities.cleaning_done) {
        actionsHtml += `<button type="button" class="btn btn-primary js-cleaning-done" style="margin-right:.5rem;">Marquer nettoyée</button>`;
      }
      if (capabilities.out_of_service && ch.etat !== 'hors_service') {
        actionsHtml += `<button type="button" class="btn btn-ghost js-oos">Mettre hors service</button>`;
      }
      if (ch.etat === 'hors_service' && capabilities.out_of_service) {
        actionsHtml += `<button type="button" class="btn btn-ghost js-back-service">Remettre en service</button>`;
      }
      if (actionsHtml) {
        html += `<div style="margin-top:1rem;display:flex;gap:.5rem;flex-wrap:wrap;">${actionsHtml}</div>`;
      }
      drawerContent.innerHTML = html;

      drawerContent.querySelectorAll('.js-cleaning-done').forEach(b => {
        b.addEventListener('click', async () => {
          b.disabled = true;
          const r2 = await jsonFetch('/api/hotel/reception/rooms/' + chambreId + '/cleaning-done', { method: 'POST' });
          b.disabled = false;
          if (r2.ok && r2.data && r2.data.success) {
            showAlert('Marquée comme disponible', 'success', 'drawer');
            setTimeout(() => { closeDrawer(); loadBoard(); }, 500);
          } else {
            showAlert((r2.data && r2.data.message) || 'Erreur', 'error', 'drawer');
          }
        });
      });
      drawerContent.querySelectorAll('.js-oos').forEach(b => {
        b.addEventListener('click', async () => {
          if (!confirm('Mettre cette chambre hors service ?')) return;
          b.disabled = true;
          const r2 = await jsonFetch('/api/hotel/reception/rooms/' + chambreId + '/out-of-service', { method: 'POST' });
          b.disabled = false;
          if (r2.ok && r2.data && r2.data.success) {
            showAlert('Chambre hors service', 'success', 'drawer');
            setTimeout(() => { closeDrawer(); loadBoard(); }, 500);
          } else {
            showAlert((r2.data && r2.data.message) || 'Erreur', 'error', 'drawer');
          }
        });
      });
      drawerContent.querySelectorAll('.js-back-service').forEach(b => {
        b.addEventListener('click', async () => {
          b.disabled = true;
          const r2 = await jsonFetch('/api/hotel/chambres/' + chambreId, {
            method: 'PUT',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ etat: 'disponible' }),
          });
          b.disabled = false;
          if (r2.ok && r2.data && r2.data.success) {
            showAlert('Chambre remise en service', 'success', 'drawer');
            setTimeout(() => { closeDrawer(); loadBoard(); }, 500);
          } else {
            showAlert((r2.data && r2.data.message) || 'Erreur', 'error', 'drawer');
          }
        });
      });
      openDrawer();
    });
  }

  /**
   * Drawer séjour : détails + bouton check-in / check-out selon le statut.
   */
  async function openSejourDrawer(sejourId) {
    showAlert(null, null, 'drawer');
    const r = await jsonFetch('/api/hotel/reservations/' + sejourId);
    if (!r.ok || !r.data || !r.data.success) {
      showAlert('Séjour introuvable', 'error', 'drawer');
      return;
    }
    const s = r.data.data;
    currentSejour = s;
    const guest = `${s.guest_prenom || ''} ${s.guest_nom || ''}`.trim();
    const dtInfo = s.date_arrivee ? `Arrivée prévue : ${fmtDateShort(s.date_arrivee)} ${fmtTime(s.date_arrivee)}` : '';
    const depInfo = s.date_depart_prevue ? `Départ prévu : ${fmtDateShort(s.date_depart_prevue)}` : '';
    const realDep = s.date_depart_reelle ? `<div style="margin-top:.25rem;">Départ réel : ${fmtDateShort(s.date_depart_reelle)} ${fmtTime(s.date_depart_reelle)}</div>` : '';
    const tel = s.guest_tel ? `<a href="tel:${escapeHtml(s.guest_tel)}" style="color:var(--primary);text-decoration:none;">${escapeHtml(s.guest_tel)}</a>` : '';

    drawerTitle.textContent = `Séjour #${s.id} — Chambre N° ${s.chambre_numero}`;

    let actionsHtml = '';
    if (s.statut === 'reserve' && capabilities.check_in_out) {
      actionsHtml += `<button type="button" class="btn btn-primary js-drawer-checkin">Check-in</button>`;
    }
    if (s.statut === 'en_cours' && capabilities.check_in_out) {
      actionsHtml += `<button type="button" class="btn btn-danger js-drawer-checkout">Check-out</button>`;
    }

    drawerContent.innerHTML = `
      <div style="margin-bottom:.85rem;font-size:.8rem;color:var(--muted);text-transform:uppercase;">Statut</div>
      <div style="margin-bottom:1rem;font-weight:600;">${escapeHtml(s.statut)}</div>

      <div style="display:grid;grid-template-columns:auto 1fr;gap:.4rem .8rem;font-size:.85rem;">
        <div style="color:var(--muted);">Guest</div><div><strong>${escapeHtml(guest) || '—'}</strong></div>
        ${tel ? '<div style="color:var(--muted);">Téléphone</div><div>' + tel + '</div>' : ''}
        <div style="color:var(--muted);">Chambre</div><div>N° ${escapeHtml(String(s.chambre_numero))} (${escapeHtml(s.chambre_type)})</div>
        <div style="color:var(--muted);">Arrivée</div><div>${dtInfo || '—'}</div>
        <div style="color:var(--muted);">Départ</div><div>${depInfo || '—'}${realDep}</div>
        <div style="color:var(--muted);">Nuits</div><div>${nights(s) || '—'}</div>
        <div style="color:var(--muted);">Prix / nuit</div><div>${Number(s.prix_par_nuit).toLocaleString('fr-FR')}</div>
      </div>
      ${actionsHtml ? '<div style="margin-top:1rem;display:flex;gap:.5rem;flex-wrap:wrap;">' + actionsHtml + '</div>' : ''}
    `;

    drawerContent.querySelectorAll('.js-drawer-checkin').forEach(b => {
      b.addEventListener('click', async () => {
        b.disabled = true;
        const r2 = await jsonFetch('/api/hotel/reservations/' + sejourId + '/check-in', { method: 'POST' });
        b.disabled = false;
        if (r2.ok && r2.data && r2.data.success) {
          showAlert('Check-in effectué', 'success', 'drawer');
          setTimeout(() => { closeDrawer(); loadBoard(); }, 500);
        } else {
          showAlert((r2.data && r2.data.message) || 'Erreur', 'error', 'drawer');
        }
      });
    });
    drawerContent.querySelectorAll('.js-drawer-checkout').forEach(b => {
      b.addEventListener('click', async () => {
        if (!confirm('Confirmer le check-out ?')) return;
        b.disabled = true;
        const r2 = await jsonFetch('/api/hotel/reservations/' + sejourId + '/check-out', { method: 'POST' });
        b.disabled = false;
        if (r2.ok && r2.data && r2.data.success) {
          showAlert('Check-out effectué', 'success', 'drawer');
          setTimeout(() => { closeDrawer(); loadBoard(); }, 500);
        } else {
          showAlert((r2.data && r2.data.message) || 'Erreur', 'error', 'drawer');
        }
      });
    });

    openDrawer();
  }

  // Polling 30 s
  loadBoard();
  setInterval(loadBoard, 30000);

})();
