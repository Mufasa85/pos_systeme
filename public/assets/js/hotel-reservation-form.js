/**
 * Drawer de création / édition de réservation hôtel.
 * Expose window.HotelReservationForm avec openForCreate() / openForEdit(id).
 *
 * Dépend de : aucun module externe. Fetch direct sur /api/hotel/*.
 */
(function() {
  'use strict';

  const APP_URL = window.location.origin;

  const drawer       = document.getElementById('hotel-drawer');
  const backdrop     = document.getElementById('hotel-drawer-backdrop');
  const titleEl      = document.getElementById('hotel-drawer-title');
  const closeBtn     = document.getElementById('hotel-drawer-close');
  const cancelBtn    = document.getElementById('hotel-drawer-cancel');
  const saveBtn      = document.getElementById('hotel-drawer-save');
  const form         = document.getElementById('hotel-res-form');
  const drawerAlert  = document.getElementById('hotel-drawer-alert');

  const guestSearch  = document.getElementById('res-guest-search');
  const guestIdInput = document.getElementById('res-guest-id');
  const guestResults = document.getElementById('hotel-guest-results');
  const guestSelectedBox = document.getElementById('res-guest-selected');
  const guestLabel       = document.getElementById('res-guest-label');
  const guestClear       = document.getElementById('res-guest-clear');
  const guestNewFields   = document.getElementById('res-guest-new-fields');

  const chambreSelect = document.getElementById('res-chambre');
  const arriveeInput  = document.getElementById('res-arrivee');
  const departInput   = document.getElementById('res-depart');
  const prixInput     = document.getElementById('res-prix');

  if (!drawer || !form) return; // pas sur la bonne page

  let currentId = null;
  let searchTimer = null;

  function open() {
    drawer.classList.add('open');
    backdrop.classList.add('open');
    document.body.style.overflow = 'hidden';
    setTimeout(() => guestSearch.focus(), 200);
  }
  function close() {
    drawer.classList.remove('open');
    backdrop.classList.remove('open');
    document.body.style.overflow = '';
  }
  function drawerMsg(msg, type) {
    if (!msg) { drawerAlert.classList.add('hidden'); return; }
    drawerAlert.textContent = msg;
    drawerAlert.classList.remove('hidden');
    drawerAlert.classList.toggle('error', type === 'error');
    drawerAlert.classList.toggle('success', type === 'success');
  }

  function resetForm() {
    form.reset();
    form.querySelector('input[name="id"]').value = '';
    currentId = null;
    guestIdInput.value = '';
    guestSelectedBox.classList.add('hidden');
    guestNewFields.style.display = 'none';
    chambreSelect.innerHTML = '<option value="">— Sélectionner une période d\'abord —</option>';
    drawerMsg(null);
    titleEl.textContent = 'Nouvelle réservation';
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

  /**
   * Sélection période : on attend que les 2 dates soient remplies.
   * On va alors lister les chambres dispo via /api/hotel/reservations/available-rooms
   */
  async function refreshAvailableRooms() {
    const ar = arriveeInput.value;
    const dp = departInput.value;
    if (!ar || !dp) return;
    if (new Date(dp) <= new Date(ar.substring(0, 10))) {
      chambreSelect.innerHTML = '<option value="">— Date de départ avant l\'arrivée —</option>';
      return;
    }
    const params = new URLSearchParams();
    params.set('date_arrivee', ar);
    params.set('date_depart_prevue', dp);
    if (currentId) params.set('exclude_reservation_id', currentId);

    const r = await jsonFetch('/api/hotel/reservations/available-rooms?' + params.toString());
    if (!r.ok || !r.data || !r.data.success) {
      chambreSelect.innerHTML = '<option value="">— Aucune chambre dispo —</option>';
      return;
    }
    const rooms = r.data.data || [];
    if (rooms.length === 0) {
      chambreSelect.innerHTML = '<option value="">— Aucune chambre disponible sur cette période —</option>';
      return;
    }
    chambreSelect.innerHTML = '<option value="">— Choisir —</option>' +
      rooms.map(c => `<option value="${c.id}" data-prix="${c.prix_base}">N° ${escapeHtml(String(c.numero))} — ${escapeHtml(c.type)} (${escapeHtml(String(c.prix_base))})</option>`).join('');
  }

  function escapeHtml(s) {
    return String(s == null ? '' : s)
      .replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;')
      .replace(/"/g, '&quot;').replace(/'/g, '&#39;');
  }

  // Recherche guest avec debounce
  guestSearch.addEventListener('input', () => {
    clearTimeout(searchTimer);
    const q = guestSearch.value.trim();
    if (q === '') {
      guestResults.classList.remove('open');
      guestResults.innerHTML = '';
      return;
    }
    // Si on a tapé, on est en train de chercher (pas d'id retenu)
    guestIdInput.value = '';
    guestSelectedBox.classList.add('hidden');

    searchTimer = setTimeout(async () => {
      const r = await jsonFetch('/api/hotel/guests/search?q=' + encodeURIComponent(q));
      if (!r.ok || !r.data || !r.data.success) {
        guestResults.classList.remove('open');
        return;
      }
      const items = r.data.data || [];
      if (items.length === 0) {
        // Pas de résultat → on affiche les champs "nouveau guest"
        guestNewFields.style.display = 'block';
        guestResults.classList.remove('open');
        return;
      }
      guestResults.innerHTML = items.map(g => `
        <div class="hotel-guest-item" data-id="${g.id}" data-label="${escapeHtml((g.prenom||'') + ' ' + (g.nom||''))}${g.telephone ? ' · ' + escapeHtml(g.telephone) : ''}">
          <div>${escapeHtml((g.prenom||'') + ' ' + (g.nom||''))}</div>
          ${(g.telephone || g.piece_identite) ? `<div class="muted">${escapeHtml(g.telephone || '')}${g.telephone && g.piece_identite ? ' · ' : ''}${escapeHtml(g.piece_identite || '')}</div>` : ''}
        </div>
      `).join('') + `
        <div class="hotel-guest-item" data-create="1" style="background:var(--background);">
          <div>＋ Créer un nouveau guest "${escapeHtml(q)}"</div>
        </div>
      `;
      guestResults.classList.add('open');

      guestResults.querySelectorAll('.hotel-guest-item').forEach(it => {
        it.addEventListener('click', () => {
          if (it.dataset.create === '1') {
            // Mode création : on pré-remplit "nom" et on cache la liste
            document.getElementById('res-guest-nom').value = q;
            guestNewFields.style.display = 'block';
            guestIdInput.value = '';
            guestResults.classList.remove('open');
            guestSelectedBox.classList.add('hidden');
          } else {
            guestIdInput.value = it.dataset.id;
            guestLabel.textContent = it.dataset.label;
            guestSelectedBox.classList.remove('hidden');
            guestNewFields.style.display = 'none';
            guestResults.classList.remove('open');
            guestSearch.value = '';
          }
        });
      });
    }, 220);
  });

  guestClear.addEventListener('click', () => {
    guestIdInput.value = '';
    guestSelectedBox.classList.add('hidden');
    guestNewFields.style.display = 'none';
    guestSearch.value = '';
    guestSearch.focus();
  });

  // Quand la période change, on recalcule les chambres dispo
  [arriveeInput, departInput].forEach(el => {
    el.addEventListener('change', refreshAvailableRooms);
  });

  // Quand on choisit une chambre, on pré-remplit le prix
  chambreSelect.addEventListener('change', () => {
    const opt = chambreSelect.options[chambreSelect.selectedIndex];
    if (opt && opt.dataset.prix && !prixInput.value) {
      prixInput.value = opt.dataset.prix;
    }
  });

  closeBtn.addEventListener('click', close);
  cancelBtn.addEventListener('click', close);
  backdrop.addEventListener('click', close);
  document.addEventListener('keydown', e => { if (e.key === 'Escape') close(); });

  // Soumission : POST ou PUT selon currentId
  form.addEventListener('submit', async (e) => {
    e.preventDefault();
    drawerMsg(null);
    saveBtn.disabled = true;

    const fd = new FormData(form);
    const payload = {
      chambre_id: parseInt(fd.get('chambre_id') || 0, 10),
      date_arrivee: fd.get('date_arrivee'),
      date_depart_prevue: fd.get('date_depart_prevue'),
      prix_par_nuit: parseFloat(fd.get('prix_par_nuit') || 0),
      remise_pct: parseFloat(fd.get('remise_pct') || 0),
      notes: fd.get('notes') || null,
    };
    const guestId = fd.get('guest_id');
    if (guestId) {
      payload.guest_id = parseInt(guestId, 10);
    } else {
      payload.guest = {
        nom: fd.get('guest_nom'),
        prenom: fd.get('guest_prenom'),
        telephone: fd.get('guest_tel'),
        piece_identite: fd.get('guest_piece'),
      };
    }

    let url = '/api/hotel/reservations';
    let method = 'POST';
    if (currentId) {
      url += '/' + encodeURIComponent(currentId);
      method = 'PUT';
    }

    const r = await jsonFetch(url, {
      method,
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify(payload),
    });

    saveBtn.disabled = false;

    if (r.ok && r.data && r.data.success) {
      drawerMsg(currentId ? 'Réservation mise à jour' : 'Réservation créée', 'success');
      // Notifier la liste + fermer après un court délai
      window.dispatchEvent(new CustomEvent('hotel:reservation-saved'));
      setTimeout(close, 600);
    } else {
      drawerMsg((r.data && r.data.message) || 'Erreur', 'error');
    }
  });

  /**
   * API publique
   */
  window.HotelReservationForm = {
    openForCreate() {
      resetForm();
      // Pré-remplir : aujourd'hui → demain
      const today = new Date();
      const tomorrow = new Date(today.getTime() + 24 * 60 * 60 * 1000);
      const pad = n => String(n).padStart(2, '0');
      const dt = (d) => d.getFullYear() + '-' + pad(d.getMonth()+1) + '-' + pad(d.getDate()) + 'T14:00';
      const dk = (d) => d.getFullYear() + '-' + pad(d.getMonth()+1) + '-' + pad(d.getDate());
      arriveeInput.value = dt(today);
      departInput.value  = dk(tomorrow);
      refreshAvailableRooms();
      open();
    },
    async openForEdit(id) {
      resetForm();
      const r = await jsonFetch('/api/hotel/reservations/' + encodeURIComponent(id));
      if (!r.ok || !r.data || !r.data.success) {
        alert((r.data && r.data.message) || 'Erreur de chargement');
        return;
      }
      const row = r.data.data;
      currentId = parseInt(id, 10);
      form.querySelector('input[name="id"]').value = currentId;
      titleEl.textContent = 'Modifier la réservation #' + currentId;

      // Guest sélectionné
      guestIdInput.value = row.guest_id;
      guestLabel.textContent = (row.guest_prenom || '') + ' ' + row.guest_nom + (row.guest_tel ? ' · ' + row.guest_tel : '');
      guestSelectedBox.classList.remove('hidden');

      chambreSelect.value = row.chambre_id;
      // datetime-local attend "YYYY-MM-DDTHH:MM"
      const ar = row.date_arrivee ? row.date_arrivee.replace(' ', 'T').substring(0, 16) : '';
      arriveeInput.value = ar;
      departInput.value  = (row.date_depart_prevue || '').substring(0, 10);
      prixInput.value    = row.prix_par_nuit;
      document.getElementById('res-remise').value = row.remise_pct || 0;
      document.getElementById('res-notes').value  = row.notes || '';

      await refreshAvailableRooms();
      // Après le refresh, on re-sélectionne la chambre de la résa existante
      if (!chambreSelect.querySelector('option[value="' + row.chambre_id + '"]')) {
        // Si elle n'est pas dispo, on l'ajoute en option selected pour conserver
        // la valeur actuelle.
        const opt = document.createElement('option');
        opt.value = row.chambre_id;
        opt.textContent = 'N° ' + row.chambre_numero + ' (actuelle)';
        opt.dataset.prix = row.prix_par_nuit;
        chambreSelect.insertBefore(opt, chambreSelect.firstChild.nextSibling);
      }
      chambreSelect.value = row.chambre_id;
      open();
    },
  };
})();
