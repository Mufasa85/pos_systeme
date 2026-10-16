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
  const tbody = document.getElementById('hotel-res-tbody');
  const alertEl = document.getElementById('hotel-alert');

  const filterStatut = document.getElementById('filter-statut');
  const filterFrom   = document.getElementById('filter-from');
  const filterTo     = document.getElementById('filter-to');
  const btnClear     = document.getElementById('btn-clear-filters');
  const btnNew       = document.getElementById('btn-new-reservation');

  if (!tbody) return; // pas sur la bonne page

  function showAlert(msg, type) {
    if (!msg) { alertEl.classList.add('hidden'); return; }
    alertEl.textContent = msg;
    alertEl.classList.remove('hidden');
    alertEl.classList.toggle('error', type === 'error');
    alertEl.classList.toggle('success', type === 'success');
  }

  function statutBadge(s) {
    const labels = {
      reserve:  ['Réservée',  'hotel-res-statut-reserve'],
      en_cours: ['En cours',  'hotel-res-statut-en_cours'],
      termine:  ['Terminée',  'hotel-res-statut-termine'],
      annule:   ['Annulée',   'hotel-res-statut-annule'],
      no_show:  ['No-show',   'hotel-res-statut-no_show'],
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
      tbody.innerHTML = '<tr><td colspan="7" class="hotel-res-empty">Erreur de chargement.</td></tr>';
      showAlert((r.data && r.data.message) || 'Erreur de chargement', 'error');
      return;
    }
    const rows = r.data.data || [];
    if (rows.length === 0) {
      tbody.innerHTML = '<tr><td colspan="7" class="hotel-res-empty">Aucune réservation.</td></tr>';
      return;
    }
    tbody.innerHTML = rows.map(row => `
      <tr data-id="${row.id}">
        <td>
          <div style="font-weight:500;">${escapeHtml(row.guest_prenom || '')} ${escapeHtml(row.guest_nom || '')}</div>
          ${row.guest_tel ? `<div style="font-size:.75rem;color:var(--muted);">${escapeHtml(row.guest_tel)}</div>` : ''}
        </td>
        <td>
          <span class="badge">${escapeHtml(String(row.chambre_numero))}</span>
          <span style="font-size:.75rem;color:var(--muted);">${escapeHtml(row.chambre_type)}</span>
        </td>
        <td style="font-size:.85rem;">${fmtDate(row.date_arrivee)}</td>
        <td style="font-size:.85rem;">${fmtDate(row.date_depart_prevue)}</td>
        <td style="font-size:.85rem;">${fmtMoney(row.prix_par_nuit)}</td>
        <td>${statutBadge(row.statut)}</td>
        <td style="text-align:right;">
          <button type="button" class="btn btn-ghost btn-small js-edit" title="Modifier">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
              <polygon points="16 3 21 8 8 21 3 21 3 16 16 3"></polygon>
            </svg>
          </button>
          ${(row.statut === 'reserve' || row.statut === 'en_cours') ? `
            <button type="button" class="btn btn-ghost btn-small js-cancel" title="Annuler" style="color:var(--danger);">
              <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <polyline points="3 6 5 6 21 6"></polyline>
                <path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"></path>
              </svg>
            </button>
          ` : ''}
        </td>
      </tr>
    `).join('');

    tbody.querySelectorAll('.js-edit').forEach(btn => {
      btn.addEventListener('click', () => {
        const id = btn.closest('tr').dataset.id;
        if (window.HotelReservationForm && typeof window.HotelReservationForm.openForEdit === 'function') {
          window.HotelReservationForm.openForEdit(id);
        }
      });
    });
    tbody.querySelectorAll('.js-cancel').forEach(btn => {
      btn.addEventListener('click', async () => {
        const id = btn.closest('tr').dataset.id;
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

  loadList();
})();
